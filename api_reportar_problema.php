<?php
/**
 * Endpoint para el script "reportar_problema.ps1" que corre en el equipo del
 * empleado: recibe el serial (detectado automáticamente por el equipo, sin que
 * el empleado tenga que saber nada técnico) + una descripción en lenguaje
 * simple, arma el ticket con la ficha técnica completa, dispara el triage de
 * IA, y devuelve si se resolvió solo o quedó escalado a un técnico - para que
 * el script se lo muestre al empleado en el momento, en su propio equipo.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/ia_triage.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = db();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$serial = limpio($data['serial'] ?? null);
$descripcion = limpio($data['descripcion'] ?? null);
$usuarioWindows = limpio($data['usuario_windows'] ?? null);

if (!$descripcion) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta la descripción del problema.']);
    exit;
}

$eq = null;
if ($serial) {
    $stmt = $pdo->prepare("SELECT * FROM inventario WHERE serial = ?");
    $stmt->execute([$serial]);
    $eq = $stmt->fetch(PDO::FETCH_ASSOC);
}

$descripcionCompleta = $descripcion;
$titulo = mb_substr($descripcion, 0, 80);
$sedeId = null;
$solicitante = $usuarioWindows ?: 'Empleado (agente local)';

if ($eq) {
    $sedeId = $eq['sede_id'];
    $solicitante = $eq['asignado_a'] ?: $solicitante;
    $descripcionCompleta .= "\n\n[Ficha técnica autodetectada por el agente local]\n"
        . "Equipo: {$eq['tipo']} {$eq['marca']} {$eq['modelo']} (serial {$eq['serial']}, placa {$eq['placa']})\n"
        . "Sistema operativo: {$eq['sistema_operativo']}\n"
        . "Procesador: {$eq['procesador']} · Memoria: {$eq['memoria']} · Almacenamiento: {$eq['almacenamiento']}\n"
        . "Estado actual en inventario: {$eq['estado']}";
}

$slaLimite = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));
$stmt = $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, sede_id, solicitante, solicitante_contacto, sla_limite, origen, equipo_serial)
    VALUES (?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([$titulo, $descripcionCompleta, 'SOPORTE', 'MEDIA', $sedeId, $solicitante, $usuarioWindows, $slaLimite, 'AGENTE_LOCAL', $serial]);
$ticketId = $pdo->lastInsertId();

hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'CREADO_DESDE_AGENTE_LOCAL', $titulo, $solicitante, $ticketId);
if ($serial) hoja_vida_registrar($pdo, 'EQUIPO', $serial, 'PROBLEMA_REPORTADO', $descripcion, $solicitante, $ticketId);

ia_triage_ticket($pdo, $ticketId);

$stmt = $pdo->prepare("SELECT estado FROM tickets WHERE id = ?");
$stmt->execute([$ticketId]);
$estado = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT comentario FROM tickets_comentarios WHERE ticket_id = ? AND tipo = 'IA' ORDER BY id DESC LIMIT 1");
$stmt->execute([$ticketId]);
$respuestaIa = $stmt->fetchColumn();

echo json_encode([
    'ok' => true,
    'ticket_id' => $ticketId,
    'resuelto' => $estado === 'RESUELTO POR IA',
    'mensaje' => $respuestaIa ?: 'Tu reporte quedó registrado como ticket #' . $ticketId . '. Un técnico de TI lo revisará pronto.',
], JSON_UNESCAPED_UNICODE);
