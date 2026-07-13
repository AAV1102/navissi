<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/ia_client.php';
header('Content-Type: application/json; charset=utf-8');
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error' => 'Sesión expirada, recarga la página.']); exit; }

$pdo = db();
$configPath = private_path('ia_config.json');
if (!file_exists($configPath)) {
    echo json_encode(['error' => 'La IA no está configurada todavía. Ve a Automatización e IA → IA Multiagente y pon tu clave de API.']);
    exit;
}
$config = leer_config_json($configPath);
if (empty($config['api_key'])) {
    echo json_encode(['error' => 'La IA no está configurada todavía. Ve a Automatización e IA → IA Multiagente y pon tu clave de API.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$pregunta = trim($data['pregunta'] ?? '');
if (!$pregunta) { echo json_encode(['error' => 'Escribe una pregunta.']); exit; }

$u = usuario_actual();
$ticketsAbiertos = $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado != 'CERRADO'")->fetchColumn();
$equipos = $pdo->query("SELECT COUNT(*) FROM inventario")->fetchColumn();
$empleados = $pdo->query("SELECT COUNT(*) FROM empleados WHERE estado='ACTIVO'")->fetchColumn();
$sedes = $pdo->query("SELECT COUNT(*) FROM sedes WHERE estado='ACTIVO'")->fetchColumn();

$systemPrompt = "Eres el asistente general del software NAVISSI Inventario (Grupo 10Z / Navissi retail). "
    . "Ayudas a {$u['nombre']} (rol {$u['rol']}) a moverse por el sistema y resolver dudas sobre tickets, inventario, "
    . "sedes, RRHH, credenciales y licencias. Responde breve y concreto, en español. "
    . "Contexto real actual: {$ticketsAbiertos} tickets abiertos, {$equipos} equipos en inventario, "
    . "{$empleados} empleados activos, {$sedes} sedes activas. "
    . "Si te preguntan algo que requiere datos que no tienes aquí, sugiere en qué módulo del menú lo pueden ver.";

try {
    $client = new IAClient($config['proveedor'] ?? 'anthropic', $config['api_key']);
    $respuesta = $client->preguntar($systemPrompt, $pregunta);
    echo json_encode(['respuesta' => $respuesta]);
} catch (IAException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
