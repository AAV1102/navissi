<?php
/** Cliente mínimo para proveedores de IA (Anthropic o OpenAI), sin librerías externas. */
class IAException extends Exception {}

class IAClient {
    private $proveedor, $apiKey;

    public function __construct($proveedor, $apiKey) {
        $this->proveedor = $proveedor; // 'anthropic' | 'openai'
        $this->apiKey = $apiKey;
    }

    public function preguntar($systemPrompt, $mensajeUsuario) {
        return match ($this->proveedor) {
            'openai' => $this->preguntarOpenAI($systemPrompt, $mensajeUsuario),
            'gemini' => $this->preguntarGemini($systemPrompt, $mensajeUsuario),
            'ollama', 'local' => $this->preguntarOllama($systemPrompt, $mensajeUsuario),
            default => $this->preguntarAnthropic($systemPrompt, $mensajeUsuario),
        };
    }

    private function curlJson($url, array $headers, array $body) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_TIMEOUT => 45,
        ]);
        $respBody = curl_exec($ch);
        if ($respBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new IAException("Error de red: {$err}");
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($respBody, true);
        if ($code >= 300) {
            $err = $data['error']['message'] ?? $respBody;
            throw new IAException("API [{$code}]: {$err}");
        }
        return $data;
    }

    private function preguntarAnthropic($systemPrompt, $mensaje) {
        $data = $this->curlJson('https://api.anthropic.com/v1/messages',
            ["x-api-key: {$this->apiKey}", 'anthropic-version: 2023-06-01'],
            ['model' => 'claude-3-5-haiku-20241022', 'max_tokens' => 1024,
                'system' => $systemPrompt, 'messages' => [['role' => 'user', 'content' => $mensaje]]]);
        return $data['content'][0]['text'] ?? '';
    }

    private function preguntarOpenAI($systemPrompt, $mensaje) {
        $data = $this->curlJson('https://api.openai.com/v1/chat/completions',
            ["Authorization: Bearer {$this->apiKey}"],
            ['model' => 'gpt-4o-mini', 'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $mensaje],
            ]]);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /** Google Gemini vía Google AI Studio - tiene nivel gratuito real sin tarjeta de crédito. */
    private function preguntarGemini($systemPrompt, $mensaje) {
        $modelo = 'gemini-2.0-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key=" . urlencode($this->apiKey);
        $data = $this->curlJson($url, [], [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $mensaje]]]],
        ]);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * IA propia, local y gratuita: habla con Ollama (https://ollama.com) corriendo
     * en el mismo servidor/red interna, sin salir a internet y sin API key ni
     * costo por token. $this->apiKey aquí se reutiliza como "host:puerto"
     * (ej. "127.0.0.1:11434") para no tener que tocar el esquema de config; si
     * viene vacío se asume el valor por defecto de Ollama en localhost.
     */
    private function preguntarOllama($systemPrompt, $mensaje) {
        $modelo = OLLAMA_MODELO_DEFECTO;
        $host = trim((string) $this->apiKey) ?: '127.0.0.1:11434';
        if (preg_match('/^modelo=([^;]+);?(.*)$/', $host, $m)) { $modelo = $m[1]; $host = $m[2] ?: '127.0.0.1:11434'; }
        $url = "http://{$host}/api/chat";
        try {
            $data = $this->curlJson($url, [], [
                'model' => $modelo,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $mensaje],
                ],
                'stream' => false,
            ]);
        } catch (IAException $e) {
            throw new IAException("No se pudo conectar con la IA local (Ollama) en {$host}. ¿Está corriendo 'ollama serve' en el servidor? Detalle: " . $e->getMessage());
        }
        return $data['message']['content'] ?? '';
    }
}

if (!defined('OLLAMA_MODELO_DEFECTO')) define('OLLAMA_MODELO_DEFECTO', 'llama3.2');
