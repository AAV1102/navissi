<?php
/**
 * Endpoint para programar (Task Scheduler / cron) la revisión de los buzones de mesa
 * de ayuda cada N minutos, sin necesidad de que alguien tenga sesión iniciada.
 * Protegido con un secreto compartido en entorno o carpeta privada.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/correo_a_tickets.php';
require_once __DIR__ . '/lib/automatizacion_operativa.php';
require_once __DIR__ . '/lib/inteligencia_operativa.php';
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120); // este endpoint corre sin usuario esperando (cron/GitHub Actions), puede tomarse su tiempo con varios buzones
ob_start();

// El token debe ser un secreto compartido configurado fuera del repositorio
// (GitHub Actions lo envía como NAVISSI_CORREO_TOKEN y el hosting lo guarda en
// private/correo_sync_token.txt). El antiguo hash derivado del dominio era
// predecible y no protegía realmente el endpoint.
$tokenEsperado = getenv('NAVISSI_CORREO_TOKEN') ?: trim((string) @file_get_contents(private_path('correo_sync_token.txt')));
$auth = (string) ($_SERVER['HTTP_X_NAVISSI_CORREO_TOKEN'] ?? '');
if ($auth === '' && preg_match('/^Bearer\s+(.+)$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) $auth = trim($m[1]);
// Se mantiene el query param solo para compatibilidad con instalaciones
// antiguas; los nuevos workflows usan header para no dejar el secreto en URLs.
$tokenRecibido = $auth !== '' ? $auth : (string) ($_GET['token'] ?? '');
if ($tokenEsperado === '' || $tokenRecibido === '' || !hash_equals($tokenEsperado, $tokenRecibido)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido.']);
    exit;
}

$pdo = db();
// Registro simple de la última vez que se llamó este endpoint - así se puede verificar
// desde fuera si la automatización (Power Automate, cron, etc.) sigue corriendo.
@file_put_contents(private_path('ultima_sincronizacion_correo.txt'), gmdate('Y-m-d H:i:s') . " UTC\n", FILE_APPEND);

// Responder de inmediato y seguir revisando los buzones en segundo plano: los
// servicios gratuitos de cron-ping (cron-job.org, etc.) suelen limitar el
// timeout a 30s, y revisar varios buzones puede tardar más. Si el servidor
// soporta fastcgi_finish_request, el cliente HTTP recibe la respuesta ya
// mismo y no importa cuánto tarde lo de después.
ignore_user_abort(true);
echo json_encode(['ok' => true, 'estado' => 'en_proceso'], JSON_UNESCAPED_UNICODE);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    header('Content-Length: ' . ob_get_length());
    header('Connection: close');
    @ob_end_flush();
    @flush();
}

$resultado = sincronizar_correo_a_tickets($pdo);
$resultado['automatizacion'] = automatizacion_operativa_ejecutar($pdo, 'CLOUDFLARE', 'auto-' . (string)floor(time() / 120));
// Los agentes de inteligencia requieren menos frecuencia que el correo.
$resultado['inteligencia'] = inteligencia_ejecutar($pdo, 'CLOUDFLARE', 'intel-' . (string)floor(time() / 600));
@file_put_contents(private_path('ultimo_resultado_correo.json'), json_encode($resultado, JSON_UNESCAPED_UNICODE));
