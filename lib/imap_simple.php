<?php
/**
 * Cliente IMAP mínimo por sockets (sin la extensión imap de PHP, que no está
 * disponible ni en local ni en la mayoría de hosting compartido). Solo lo
 * necesario para mesa de ayuda: conectar por SSL, buscar no leídos, traer
 * asunto/remitente/cuerpo, marcar como leído.
 */

class ImapSimpleException extends Exception {}

class ImapSimple {
    private $fp;
    private $tag = 0;

    public function __construct(string $host, int $port, string $usuario, string $password) {
        $this->fp = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15);
        if (!$this->fp) throw new ImapSimpleException("No se pudo conectar a {$host}:{$port} - {$errstr}");
        stream_set_timeout($this->fp, 15);
        $this->leerLinea(); // saludo del servidor
        $this->comando("LOGIN " . $this->literal($usuario) . ' ' . $this->literal($password));
        $this->comando('SELECT INBOX');
    }

    private function literal(string $s): string {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    private function leerLinea(): string {
        $linea = fgets($this->fp, 8192);
        if ($linea === false) throw new ImapSimpleException('Conexión IMAP cerrada inesperadamente.');
        return $linea;
    }

    private function comando(string $cmd): array {
        $tag = 'A' . (++$this->tag);
        fwrite($this->fp, "{$tag} {$cmd}\r\n");
        $lineas = [];
        while (true) {
            $linea = $this->leerLinea();
            $lineas[] = $linea;
            if (strpos($linea, "{$tag} OK") === 0) break;
            if (strpos($linea, "{$tag} NO") === 0 || strpos($linea, "{$tag} BAD") === 0) {
                throw new ImapSimpleException("Comando IMAP falló: {$cmd} -> {$linea}");
            }
        }
        return $lineas;
    }

    /** IDs de mensajes no leídos en INBOX. */
    public function buscarNoLeidos(): array {
        $lineas = $this->comando('SEARCH UNSEEN');
        foreach ($lineas as $linea) {
            if (strpos($linea, '* SEARCH') === 0) {
                $ids = array_filter(array_map('trim', explode(' ', trim(substr($linea, 8)))));
                return array_map('intval', $ids);
            }
        }
        return [];
    }

    /** Trae asunto, remitente y una porción del cuerpo de un mensaje por su número de secuencia. */
    public function leerMensaje(int $id): array {
        $lineas = $this->comando("FETCH {$id} (BODY.PEEK[HEADER.FIELDS (SUBJECT FROM)] BODY.PEEK[TEXT])");
        $crudo = implode('', $lineas);
        $asunto = '(sin asunto)';
        if (preg_match('/Subject:\s*(.+)/i', $crudo, $m)) {
            $asunto = trim(preg_replace('/=\?UTF-8\?B\?(.+?)\?=/i', '', $m[1])) ?: trim($m[1]);
            // Decodifica el asunto si viene en base64 MIME (=?UTF-8?B?...?=)
            if (preg_match('/=\?UTF-8\?B\?([A-Za-z0-9+\/=]+)\?=/i', $m[1], $m2)) {
                $asunto = base64_decode($m2[1]);
            }
        }
        $remitenteCorreo = 'desconocido';
        $remitenteNombre = $remitenteCorreo;
        if (preg_match('/From:\s*"?([^"<]*)"?\s*<?([^>\s]+@[^>\s]+)>?/i', $crudo, $m)) {
            $remitenteNombre = trim($m[1]) ?: $m[2];
            $remitenteCorreo = trim($m[2]);
        }
        $cuerpo = '';
        if (preg_match('/\r\n\r\n(.*)$/s', $crudo, $m)) {
            $cuerpo = trim(strip_tags($m[1]));
        }
        return ['asunto' => $asunto, 'remitente' => $remitenteCorreo, 'remitente_nombre' => $remitenteNombre, 'cuerpo' => mb_substr($cuerpo, 0, 500)];
    }

    public function marcarLeido(int $id): void {
        $this->comando("STORE {$id} +FLAGS (\\Seen)");
    }

    public function cerrar(): void {
        if ($this->fp) { @fwrite($this->fp, "A999 LOGOUT\r\n"); fclose($this->fp); }
    }
}
