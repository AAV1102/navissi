<?php
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/whatsapp_client.php';
require_once __DIR__ . '/correo_a_tickets.php';

function notificaciones_config(): array {
    $base = [
        'correo_habilitado' => false, 'correos_operacion' => '',
        'whatsapp_habilitado' => false, 'whatsapp_destino' => '',
        'teams_habilitado' => false, 'teams_webhook' => '',
    ];
    $datos = leer_config_json(private_path('notificaciones_config.json'));
    return array_merge($base, is_array($datos) ? $datos : []);
}

function notificaciones_config_guardar(array $datos): void {
    guardar_config_json(private_path('notificaciones_config.json'), array_merge(notificaciones_config(), $datos));
}

function notificaciones_config_validar(array $config): array {
    $errores = [];
    $smtp = smtp_config() ?: [];
    $wa = leer_config_json(WHATSAPP_CONFIG_PATH) ?: [];
    $correos = preg_split('/[,;\s]+/', (string)($config['correos_operacion'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if (!empty($config['correo_habilitado'])) {
        if (empty($smtp['host']) || empty($smtp['usuario']) || empty($smtp['password'])) $errores[] = 'Correo: falta completar SMTP.';
        if (!$correos || count(array_filter($correos, fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL))) !== count($correos)) $errores[] = 'Correo: agrega al menos un destinatario válido.';
    }
    if (!empty($config['whatsapp_habilitado'])) {
        if (empty($wa['token']) || empty($wa['phone_number_id'])) $errores[] = 'WhatsApp: faltan credenciales de Cloud API.';
        if (strlen(preg_replace('/\D+/', '', (string)($config['whatsapp_destino'] ?? ''))) < 10) $errores[] = 'WhatsApp: el número destino no es válido.';
    }
    if (!empty($config['teams_habilitado'])) {
        $url = trim((string)($config['teams_webhook'] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) $errores[] = 'Teams: el webhook HTTPS no es válido.';
    }
    return $errores;
}

function notificacion_encolar(PDO $pdo, string $clave, string $canal, string $destino, string $asunto, string $contenido, array $meta = []): bool {
    $canal = strtoupper($canal);
    if (!in_array($canal, ['CORREO', 'WHATSAPP', 'TEAMS'], true) || trim($destino) === '') return false;
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO notificaciones_cola(clave_unica,canal,destinatario,asunto,contenido,metadatos_json) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$clave, $canal, trim($destino), $asunto, $contenido, json_encode($meta, JSON_UNESCAPED_UNICODE)]);
    return $stmt->rowCount() > 0;
}

function notificaciones_encolar_operacion(PDO $pdo, string $evento, string $asunto, string $contenido, array $extras = [], array $meta = []): int {
    $config = notificaciones_config();
    $creadas = 0;
    if ($config['correo_habilitado']) {
        $correos = array_merge($extras, preg_split('/[,;\s]+/', (string)$config['correos_operacion'], -1, PREG_SPLIT_NO_EMPTY) ?: []);
        foreach (array_unique(array_map('strtolower', $correos)) as $correo) {
            if (filter_var($correo, FILTER_VALIDATE_EMAIL) && notificacion_encolar($pdo, $evento . ':correo:' . hash('sha256', $correo), 'CORREO', $correo, $asunto, $contenido, $meta)) $creadas++;
        }
    }
    if ($config['whatsapp_habilitado'] && ($telefono = preg_replace('/\D+/', '', (string)$config['whatsapp_destino']))) {
        if (notificacion_encolar($pdo, $evento . ':whatsapp', 'WHATSAPP', $telefono, $asunto, $contenido, $meta)) $creadas++;
    }
    if ($config['teams_habilitado'] && !empty($config['teams_webhook'])) {
        if (notificacion_encolar($pdo, $evento . ':teams', 'TEAMS', 'Operación NAVISSI', $asunto, $contenido, $meta)) $creadas++;
    }
    return $creadas;
}

function notificacion_teams_enviar(string $url, string $asunto, string $contenido): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) return false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['text' => "**{$asunto}**\n\n{$contenido}"], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $codigo >= 200 && $codigo < 300;
}

function notificaciones_procesar(PDO $pdo, int $limite = 20): array {
    $resultado = ['procesadas' => 0, 'enviadas' => 0, 'errores' => 0, 'agotadas' => 0, 'ocupada' => false];
    $lockDir = private_path('locks');
    if (!is_dir($lockDir)) @mkdir($lockDir, 0700, true);
    $lock = @fopen($lockDir . DIRECTORY_SEPARATOR . 'notificaciones.lock', 'c+');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) fclose($lock);
        $resultado['ocupada'] = true;
        return $resultado;
    }

    try {
        $config = notificaciones_config();
        $limite = min(100, max(1, $limite));
        $stmt = $pdo->prepare("SELECT * FROM notificaciones_cola WHERE estado IN ('PENDIENTE','ERROR') AND intentos<5 AND (proximo_intento_en IS NULL OR proximo_intento_en<=datetime('now')) ORDER BY id LIMIT ?");
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $resultado['procesadas']++;
            $ok = false;
            $error = 'Canal desactivado o sin configurar.';
            try {
                // Punto único de despacho de correo: bloquea aquí CUALQUIER intento de
                // notificar a una de nuestras propias direcciones (SLA, alertas,
                // aprobaciones, lo que sea) - así no hace falta blindar cada productor
                // de notificaciones por separado, y se corta de raíz el riesgo de que
                // una notificación llegue de vuelta al mismo buzón que se lee para
                // crear tickets y arranque un ciclo infinito.
                if ($item['canal'] === 'CORREO' && correo_es_direccion_propia($pdo, (string) $item['destinatario'])) {
                    $pdo->prepare("UPDATE notificaciones_cola SET estado='DESCARTADA',proximo_intento_en=NULL,ultimo_error='Descartada: el destinatario es una dirección propia de NAVISSI (evita ciclos de correo).' WHERE id=?")->execute([$item['id']]);
                    continue;
                } elseif ($item['canal'] === 'CORREO' && $config['correo_habilitado']) {
                    $ok = enviar_correo($item['destinatario'], $item['asunto'] ?: 'Notificación NAVISSI', plantilla_correo_html($item['asunto'] ?: 'Notificación NAVISSI', '<p>' . nl2br(e($item['contenido'])) . '</p>'));
                    if (!$ok) $error = 'SMTP no configurado o envío rechazado.';
                } elseif ($item['canal'] === 'WHATSAPP' && $config['whatsapp_habilitado']) {
                    $wa = leer_config_json(WHATSAPP_CONFIG_PATH) ?: [];
                    if (empty($wa['token']) || empty($wa['phone_number_id'])) throw new RuntimeException('WhatsApp no configurado.');
                    (new WhatsAppClient($wa['token'], $wa['phone_number_id']))->enviarTexto($item['destinatario'], $item['asunto'] . "\n\n" . $item['contenido']);
                    $ok = true;
                } elseif ($item['canal'] === 'TEAMS' && $config['teams_habilitado']) {
                    $ok = notificacion_teams_enviar($config['teams_webhook'], $item['asunto'], $item['contenido']);
                    if (!$ok) $error = 'Webhook de Teams rechazó el mensaje.';
                }
            } catch (Throwable $e) {
                $error = preg_replace('/(token|secret|password)\s*[:=]\s*\S+/i', '$1=[protegido]', $e->getMessage());
            }

            if ($ok) {
                $pdo->prepare("UPDATE notificaciones_cola SET estado='ENVIADA',intentos=intentos+1,enviado_en=CURRENT_TIMESTAMP,proximo_intento_en=NULL,ultimo_error=NULL WHERE id=?")->execute([$item['id']]);
                $resultado['enviadas']++;
                continue;
            }

            $intentos = (int)$item['intentos'] + 1;
            $agotada = $intentos >= 5;
            $proximo = $agotada ? null : gmdate('Y-m-d H:i:s', time() + min(240, 5 * (2 ** max(0, $intentos - 1))) * 60);
            $pdo->prepare('UPDATE notificaciones_cola SET estado=?,intentos=?,proximo_intento_en=?,ultimo_error=? WHERE id=?')
                ->execute([$agotada ? 'FALLIDA' : 'ERROR', $intentos, $proximo, substr($error, 0, 500), $item['id']]);
            $resultado[$agotada ? 'agotadas' : 'errores']++;
        }
        return $resultado;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
