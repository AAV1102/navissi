<?php
/**
 * Endpoint para programar (Task Scheduler / cron) la revisión de los buzones de mesa
 * de ayuda cada N minutos, sin necesidad de que alguien tenga sesión iniciada.
 * Protegido con un token derivado del propio dominio (no hace falta guardar otra clave).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/correo_a_tickets.php';
header('Content-Type: application/json; charset=utf-8');

$tokenEsperado = hash('sha256', 'navissi-correo-' . ($_SERVER['HTTP_HOST'] ?? ''));
if (($_GET['token'] ?? '') !== $tokenEsperado) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido.']);
    exit;
}

$pdo = db();
$resultado = sincronizar_correo_a_tickets($pdo);
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
