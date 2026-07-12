<?php
/** Cliente mínimo de WhatsApp Cloud API (Meta), igual de real que el de Graph. */
class WhatsAppException extends Exception {}

class WhatsAppClient {
    private $token, $phoneNumberId, $apiVersion;

    public function __construct($token, $phoneNumberId, $apiVersion = 'v21.0') {
        $this->token = $token;
        $this->phoneNumberId = $phoneNumberId;
        $this->apiVersion = $apiVersion;
    }

    private function post($path, array $body) {
        $ch = curl_init("https://graph.facebook.com/{$this->apiVersion}/{$path}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$this->token}", 'Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
        ]);
        $respBody = curl_exec($ch);
        if ($respBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new WhatsAppException("Error de red hacia WhatsApp: {$err}");
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($respBody, true);
        if ($code >= 300) {
            $err = $data['error']['message'] ?? $respBody;
            throw new WhatsAppException("WhatsApp API [{$code}]: {$err}");
        }
        return $data;
    }

    /** Prueba de conexión: intenta consultar el número de teléfono configurado. */
    public function probarConexion() {
        $ch = curl_init("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$this->token}"],
            CURLOPT_TIMEOUT => 10,
        ]);
        $respBody = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($respBody, true);
        if ($code >= 300) {
            $err = $data['error']['message'] ?? $respBody;
            throw new WhatsAppException("WhatsApp API [{$code}]: {$err}");
        }
        return $data;
    }

    /** Envía un mensaje de texto simple. */
    public function enviarTexto($numeroDestino, $texto) {
        return $this->post("{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $numeroDestino,
            'type' => 'text',
            'text' => ['body' => $texto],
        ]);
    }

    /** Descarga una imagen/archivo que el empleado envió por WhatsApp (2 pasos: obtener URL, luego descargar). */
    public function descargarMedia($mediaId, $destinoArchivo) {
        $ch = curl_init("https://graph.facebook.com/{$this->apiVersion}/{$mediaId}");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["Authorization: Bearer {$this->token}"], CURLOPT_TIMEOUT => 15]);
        $info = json_decode(curl_exec($ch), true);
        curl_close($ch);
        if (empty($info['url'])) throw new WhatsAppException('No se pudo obtener la URL del archivo.');

        $ch = curl_init($info['url']);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["Authorization: Bearer {$this->token}"], CURLOPT_TIMEOUT => 30]);
        $binario = curl_exec($ch);
        curl_close($ch);
        if ($binario === false) throw new WhatsAppException('No se pudo descargar el archivo.');

        file_put_contents($destinoArchivo, $binario);
        return $destinoArchivo;
    }
}
