<?php
/**
 * Endpoint público para que n8n (u otro flujo externo) cree tickets de Mesa de
 * Ayuda. Toda solicitud debe estar firmada con HMAC-SHA256 usando el secreto
 * privado de NAVISSI y el header X-Navissi-Signature.
 */
define('CSRF_EXEMPT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/ia_triage.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = db();

$rawBody = (string) file_get_contents('php://input');
if (!firma_hmac_valida($rawBody, $_SERVER['HTTP_X_NAVISSI_SIGNATURE'] ?? null, navissi_webhook_secret())) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Firma del webhook inválida.']);
    exit;
}
$data = json_decode($rawBody, true) ?: [];
$eventoId = trim((string)($_SERVER['HTTP_X_NAVISSI_EVENT_ID'] ?? ($data['evento_id'] ?? '')));
if ($eventoId !== '') {
    // n8n reintenta los webhooks cuando hay timeout; el identificador evita
    // crear tickets duplicados aunque el primer intento sí haya llegado.
    $mensajeId = 'webhook:' . hash('sha256', $eventoId);
    $dup = $pdo->prepare('SELECT ticket_id FROM correos_a_tickets WHERE mensaje_id = ? LIMIT 1');
    $dup->execute([$mensajeId]);
    $ticketExistente = $dup->fetchColumn();
    if ($ticketExistente) {
        echo json_encode(['ok' => true, 'duplicado' => true, 'ticket_id' => (int)$ticketExistente]);
        exit;
    }
} else {
    $mensajeId = null;
}
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
if ($mensajeId !== null) {
    $pdo->prepare("INSERT INTO correos_a_tickets (mensaje_id, buzon, remitente, asunto, ticket_id) VALUES (?,?,?,?,?)")
        ->execute([$mensajeId, 'webhook', limpio($data['solicitante_contacto'] ?? null), $titulo, $id]);
}
ia_triage_ticket($pdo, $id);

echo json_encode(['ok' => true, 'ticket_id' => $id]);
