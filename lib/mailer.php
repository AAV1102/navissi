<?php
/**
 * Cliente SMTP mínimo (sin dependencias/Composer) con STARTTLS, para el envío
 * real de correos (bienvenida, contraseña temporal, recuperación de clave).
 * Configuración en data/smtp_config.json.
 */

function smtp_config(): ?array {
    $path = __DIR__ . '/../data/smtp_config.json';
    if (!file_exists($path)) return null;
    $cfg = json_decode(file_get_contents($path), true);
    return $cfg ?: null;
}

function smtp_log(string $linea): void {
    $ruta = __DIR__ . '/../data/smtp_correo.log';
    @file_put_contents($ruta, '[' . gmdate('Y-m-d H:i:s') . " UTC] {$linea}\n", FILE_APPEND);
}

/**
 * Envía un correo real vía SMTP+STARTTLS. Devuelve true/false; nunca lanza excepción
 * (un fallo de correo no debe tumbar el flujo de creación/activación de usuarios).
 */
function enviar_correo(string $para, string $asunto, string $cuerpoHtml, ?string $paraNombre = null): bool {
    $cfg = smtp_config();
    if (!$cfg || empty($cfg['host']) || empty($cfg['usuario']) || empty($cfg['password'])) {
        smtp_log("SIN CONFIGURAR - no se envió a {$para}: {$asunto}");
        return false;
    }

    $host = $cfg['host'];
    $port = (int) ($cfg['port'] ?? 587);
    $user = $cfg['usuario'];
    $pass = $cfg['password'];
    $deNombre = $cfg['remitente_nombre'] ?? 'NAVISSI';
    $deCorreo = $cfg['remitente_correo'] ?? $user;

    $leer = function ($fp) {
        $data = '';
        while ($linea = fgets($fp, 515)) {
            $data .= $linea;
            if (isset($linea[3]) && $linea[3] === ' ') break;
        }
        return $data;
    };

    $fp = @fsockopen($host, $port, $errno, $errstr, 12);
    if (!$fp) {
        smtp_log("ERROR conexión {$host}:{$port} - {$errstr} ({$errno})");
        return false;
    }
    stream_set_timeout($fp, 12);

    try {
        $leer($fp);
        fwrite($fp, "EHLO navissi.local\r\n");
        $leer($fp);
        fwrite($fp, "STARTTLS\r\n");
        $resp = $leer($fp);
        if (strpos($resp, '220') !== 0) throw new Exception("STARTTLS rechazado: {$resp}");

        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('No se pudo iniciar TLS');
        }

        fwrite($fp, "EHLO navissi.local\r\n");
        $leer($fp);
        fwrite($fp, "AUTH LOGIN\r\n");
        $leer($fp);
        fwrite($fp, base64_encode($user) . "\r\n");
        $leer($fp);
        fwrite($fp, base64_encode($pass) . "\r\n");
        $resp = $leer($fp);
        if (strpos($resp, '235') !== 0) throw new Exception("Autenticación SMTP falló: {$resp}");

        fwrite($fp, "MAIL FROM:<{$deCorreo}>\r\n");
        $leer($fp);
        fwrite($fp, "RCPT TO:<{$para}>\r\n");
        $resp = $leer($fp);
        if (strpos($resp, '250') !== 0) throw new Exception("Destinatario rechazado: {$resp}");

        fwrite($fp, "DATA\r\n");
        $leer($fp);

        $paraCabecera = $paraNombre ? "\"{$paraNombre}\" <{$para}>" : $para;
        $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
        $limite = 'navissi-' . bin2hex(random_bytes(8));

        $mensaje  = "From: \"{$deNombre}\" <{$deCorreo}>\r\n";
        $mensaje .= "To: {$paraCabecera}\r\n";
        $mensaje .= "Subject: {$asuntoCodificado}\r\n";
        $mensaje .= "MIME-Version: 1.0\r\n";
        $mensaje .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mensaje .= "Content-Transfer-Encoding: 8bit\r\n";
        $mensaje .= "\r\n";
        $mensaje .= $cuerpoHtml;
        $mensaje .= "\r\n.\r\n";

        fwrite($fp, $mensaje);
        $resp = $leer($fp);
        if (strpos($resp, '250') !== 0) throw new Exception("Servidor rechazó el mensaje: {$resp}");

        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        smtp_log("OK enviado a {$para}: {$asunto}");
        return true;
    } catch (Throwable $e) {
        @fclose($fp);
        smtp_log("ERROR enviando a {$para}: " . $e->getMessage());
        return false;
    }
}

/** Plantilla HTML simple y consistente para todos los correos del sistema. */
function plantilla_correo_html(string $titulo, string $cuerpoHtml, ?string $botonTexto = null, ?string $botonUrl = null): string {
    $boton = '';
    if ($botonTexto && $botonUrl) {
        $boton = "<p style=\"text-align:center;margin:28px 0;\">
            <a href=\"" . htmlspecialchars($botonUrl) . "\" style=\"background:#7a2331;color:#fff;padding:12px 26px;border-radius:4px;text-decoration:none;font-weight:600;display:inline-block;\">" . htmlspecialchars($botonTexto) . "</a>
        </p>";
    }
    return "<!DOCTYPE html><html><body style=\"margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;\">
    <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\"><tr><td align=\"center\">
    <table width=\"480\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e6ec;\">
        <tr><td style=\"background:#07211d;padding:22px 28px;\">
            <span style=\"color:#fff;font-size:18px;font-weight:700;\">NAVISSI</span>
            <span style=\"color:#9fb3ae;font-size:12px;margin-left:6px;\">Grupo 10Z</span>
        </td></tr>
        <tr><td style=\"padding:28px;\">
            <h2 style=\"margin:0 0 14px;color:#111827;font-size:19px;\">" . htmlspecialchars($titulo) . "</h2>
            <div style=\"color:#374151;font-size:14px;line-height:1.6;\">{$cuerpoHtml}</div>
            {$boton}
        </td></tr>
        <tr><td style=\"padding:16px 28px;background:#f9fafb;border-top:1px solid #eef1f5;\">
            <p style=\"margin:0;color:#9aa4b2;font-size:11.5px;\">NAVISSI Inventario · Grupo 10Z SAS · correo automático, no respondas a este mensaje.</p>
        </td></tr>
    </table>
    </td></tr></table>
    </body></html>";
}
