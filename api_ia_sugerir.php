<?php
/**
 * IA aplicada a los formatos de movimientos: toma notas sueltas del técnico
 * (ej. "se cambio el disco, quedo mas rapido") y las redacta profesional,
 * usando la ficha técnica real del equipo y su historial de hoja de vida
 * como contexto - para que la IA no invente nada que no esté respaldado.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/ia_client.php';
header('Content-Type: application/json; charset=utf-8');
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error' => 'Sesión expirada.']); exit; }

$pdo = db();
$configPath = private_path('ia_config.json');
if (!file_exists($configPath)) { echo json_encode(['error' => 'IA no configurada.']); exit; }
$config = leer_config_json($configPath);
if (empty($config['api_key']) && ($config['proveedor'] ?? '') !== 'local') { echo json_encode(['error' => 'IA no configurada.']); exit; }

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$serial = limpio($data['serial'] ?? null);
$tipo = limpio($data['tipo'] ?? null) ?: 'MOVIMIENTO';
$borrador = trim($data['borrador'] ?? '');

if (!$borrador) { echo json_encode(['error' => 'Escribe unas notas rápidas primero (aunque sea desordenadas).']); exit; }

$contextoEquipo = 'Sin equipo seleccionado.';
if ($serial) {
    $stmt = $pdo->prepare("SELECT * FROM inventario WHERE serial = ?");
    $stmt->execute([$serial]);
    $eq = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($eq) {
        $contextoEquipo = "{$eq['tipo']} {$eq['marca']} {$eq['modelo']}, SO {$eq['sistema_operativo']}, CPU {$eq['procesador']}, RAM {$eq['memoria']}, disco {$eq['almacenamiento']}, estado {$eq['estado']}.";
    }
    $stmt = $pdo->prepare("SELECT evento, detalle, creado_en FROM hoja_vida WHERE entidad_tipo='EQUIPO' AND entidad_id=? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$serial]);
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($historial) {
        $contextoEquipo .= " Historial reciente: " . implode(' | ', array_map(fn($h) => "{$h['evento']}: {$h['detalle']}", $historial));
    }
}

$systemPrompt = "Eres el redactor técnico de TI de Navissi/Grupo 10Z. Tu trabajo es tomar notas informales de un técnico "
    . "y convertirlas en un texto profesional y claro para un formato oficial (préstamo, devolución, repotenciamiento, baja, etc). "
    . "Reglas: no inventes datos que no estén en las notas ni en el contexto del equipo. Sé conciso (máximo 4-5 líneas). "
    . "No agregues encabezados ni firmas, solo el texto de la observación. Contexto del equipo: {$contextoEquipo}";

try {
    $client = new IAClient($config['proveedor'] ?? 'gemini', $config['api_key'] ?? '');
    $respuesta = $client->preguntar($systemPrompt, "Tipo de movimiento: {$tipo}. Notas del técnico: {$borrador}");
    echo json_encode(['sugerencia' => trim($respuesta)]);
} catch (IAException $e) {
    // Sin conexión a la IA (local o remota): redacción mínima determinista para
    // no dejar al técnico sin nada que guardar.
    $texto = trim(preg_replace('/\s+/', ' ', $borrador));
    echo json_encode(['sugerencia' => "Movimiento {$tipo}: {$texto}. Equipo verificado contra el inventario NAVISSI.", 'aviso' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
