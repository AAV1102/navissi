<?php
define('CSRF_EXEMPT', true);
/**
 * Endpoint que recibe el reporte del agente de inventario (agente_navissi.ps1)
 * que corre en cada equipo. Cada instalación usa una credencial individual,
 * limitada a una sede y vinculada al primer serial que la presenta.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/agente_auth.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = db();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || empty($data['serial'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta el serial del equipo.']);
    exit;
}

$serialSeguro = limpio($data['serial']);
$agenteAutorizado = agente_autenticar($pdo, $serialSeguro, true);
$sedeId = !empty($agenteAutorizado['sede_id']) ? (int)$agenteAutorizado['sede_id'] : (!empty($data['sede']) ? sede_id_por_nombre($pdo, $data['sede'], false) : null);

$stmt = $pdo->prepare("SELECT id FROM inventario WHERE serial = ?");
$stmt->execute([$serialSeguro]);
$existente = $stmt->fetch(PDO::FETCH_ASSOC);

// IMPORTANTE: en una actualizacion solo se tocan los campos que de verdad
// llegaron en el payload. Antes se sobrescribian TODOS los campos siempre
// (incluso con vacio si el agente no los mandaba), lo que podia borrar datos
// reales de un equipo con un reporte parcial.
$camposOpcionales = [
    'usuario_windows' => 'asignado_a', 'tipo' => 'tipo', 'marca' => 'marca', 'modelo' => 'modelo',
    'sistema_operativo' => 'sistema_operativo', 'procesador' => 'procesador', 'memoria' => 'memoria',
    'almacenamiento' => 'almacenamiento', 'ip_local' => 'ip_local', 'hostname' => 'hostname',
];
$campos = ['ultima_conexion_agente' => gmdate('Y-m-d H:i:s')]; // UTC, igual que CURRENT_TIMESTAMP de SQLite
foreach ($camposOpcionales as $origen => $destino) {
    if (!empty($data[$origen])) $campos[$destino] = limpio($data[$origen]);
}
if (!empty($data['rustdesk_id'])) $campos['rustdesk_id'] = limpio($data['rustdesk_id']);
if (!empty($data['rustdesk_password'])) $campos['rustdesk_password'] = limpio($data['rustdesk_password']);
if ($sedeId) $campos['sede_id'] = $sedeId;

if ($existente) {
    $inventarioId = $existente['id'];
    $campos['estado'] = 'ACTIVO';
    $campos['fuente'] = 'Agente automático';
    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($campos)));
    $stmt = $pdo->prepare("UPDATE inventario SET {$set}, actualizado_en = CURRENT_TIMESTAMP WHERE id = :id");
    $campos['id'] = $inventarioId;
    $stmt->execute($campos);
    $accion = 'actualizado';
} else {
    // Un equipo nuevo si necesita todos los campos, aunque vengan vacios.
    $campos['serial'] = $serialSeguro;
    $campos['tipo'] = $campos['tipo'] ?? 'ESCRITORIO';
    $campos['estado'] = 'ACTIVO';
    $campos['fuente'] = 'Agente automático';
    $cols = implode(', ', array_keys($campos));
    $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($campos)));
    $pdo->prepare("INSERT INTO inventario ({$cols}) VALUES ({$ph})")->execute($campos);
    $inventarioId = $pdo->lastInsertId();
    $accion = 'creado';
}

// Parches reales de Windows Update reportados por el agente (Get-HotFix) —
// upsert por KB, sin duplicar en cada reporte.
$parchesRecibidos = 0;
if (!empty($data['parches']) && is_array($data['parches'])) {
    $stmtParche = $pdo->prepare("INSERT INTO parches_equipo (inventario_id, kb, descripcion, tipo, fecha_instalado)
        VALUES (?,?,?,?,?)
        ON CONFLICT(inventario_id, kb) DO UPDATE SET descripcion=excluded.descripcion, fecha_instalado=excluded.fecha_instalado, reportado_en=CURRENT_TIMESTAMP");
    foreach ($data['parches'] as $p) {
        if (empty($p['kb'])) continue;
        $stmtParche->execute([$inventarioId, limpio($p['kb']), limpio($p['descripcion'] ?? null), limpio($p['tipo'] ?? null) ?: 'ACTUALIZACION', limpio($p['fecha_instalado'] ?? null)]);
        $parchesRecibidos++;
    }
}

// Programas instalados (registro de Windows) reportados por el agente —
// upsert por nombre, sin duplicar en cada reporte cada 5 minutos.
$softwareRecibido = 0;
if (!empty($data['software']) && is_array($data['software'])) {
    $stmtSoftware = $pdo->prepare("INSERT INTO equipos_software (inventario_id, nombre, version, editor)
        VALUES (?,?,?,?)
        ON CONFLICT(inventario_id, nombre) DO UPDATE SET version=excluded.version, editor=excluded.editor, reportado_en=CURRENT_TIMESTAMP");
    foreach ($data['software'] as $s) {
        if (empty($s['nombre'])) continue;
        $stmtSoftware->execute([$inventarioId, limpio($s['nombre']), limpio($s['version'] ?? null), limpio($s['editor'] ?? null)]);
        $softwareRecibido++;
    }
}

echo json_encode(['ok' => true, 'accion' => $accion, 'id' => $inventarioId, 'parches_recibidos' => $parchesRecibidos, 'software_recibido' => $softwareRecibido]);
