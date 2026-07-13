<?php
/**
 * Endpoint para programar (Task Scheduler / cron) la revisión de los buzones de mesa
 * de ayuda cada N minutos, sin necesidad de que alguien tenga sesión iniciada.
 * Protegido con un token derivado del propio dominio (no hace falta guardar otra clave).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/correo_a_tickets.php';
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120); // este endpoint corre sin usuario esperando (cron/GitHub Actions), puede tomarse su tiempo con varios buzones

$tokenEsperado = hash('sha256', 'navissi-correo-' . ($_SERVER['HTTP_HOST'] ?? ''));
if (($_GET['token'] ?? '') !== $tokenEsperado) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido.']);
    exit;
}

$pdo = db();
// Registro simple de la última vez que se llamó este endpoint - así se puede verificar
// desde fuera si la automatización (Power Automate, cron, etc.) sigue corriendo.
@file_put_contents(__DIR__ . '/data/ultima_sincronizacion_correo.txt', gmdate('Y-m-d H:i:s') . " UTC\n", FILE_APPEND);
$resultado = sincronizar_correo_a_tickets($pdo);
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
