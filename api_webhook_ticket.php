<?php
/**
 * Endpoint público para que n8n (u otro flujo externo) cree tickets de Mesa de
 * Ayuda. Pensado para uso en red local/confiable - si se expone a internet,
 * agregar validación de una clave compartida en el header.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/ia_triage.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = db();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$titulo = limpio($data['titulo'] ?? null);
if (!$titulo) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta el título del ticket.']);
    exit;
}

$prioridad = limpio($data['prioridad'] ?? null) ?: 'MEDIA';
$horasSla = ['URGENTE' => 4, 'ALTA' => 8, 'MEDIA' => 24, 'BAJA' => 72][$prioridad] ?? 24;
$slaLimite = gmdate('Y-m-d H:i:s', strtotime("+{$horasSla} hours"));
$sedeId = !empty($data['sede']) ? sede_id_por_nombre($pdo, $data['sede'], false) : null;

$stmt = $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, sede_id, solicitante, solicitante_contacto, sla_limite, origen)
    VALUES (?,?,?,?,?,?,?,?,?)");
$stmt->execute([
    $titulo, limpio($data['descripcion'] ?? null), limpio($data['categoria'] ?? null) ?: 'SOPORTE',
    $prioridad, $sedeId, limpio($data['solicitante'] ?? null), limpio($data['solicitante_contacto'] ?? null),
    $slaLimite, limpio($data['origen'] ?? null) ?: 'AUTOMATIZACION',
]);
$id = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
    ->execute([$id, 'Sistema', 'Ticket creado automáticamente vía webhook/n8n.', 'SISTEMA']);
hoja_vida_registrar($pdo, 'TICKET', (string) $id, 'CREADO_VIA_N8N', $titulo, 'n8n', $id);
ia_triage_ticket($pdo, $id);

echo json_encode(['ok' => true, 'ticket_id' => $id]);
