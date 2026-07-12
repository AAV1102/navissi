<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/whatsapp_client.php';
require_once __DIR__ . '/lib/ia_triage.php';
$pdo = db();

$configPath = BASE_DIR . '/data/whatsapp_config.json';
$c = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];

// Verificación inicial que pide Meta al registrar el webhook (GET).
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['hub_verify_token'] ?? '') === ($c['verify_token'] ?? null) && !empty($c['verify_token'])) {
        echo $_GET['hub_challenge'] ?? '';
        exit;
    }
    http_response_code(403);
    exit;
}

// Mensajes entrantes reales (POST).
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$mensajes = $data['entry'][0]['changes'][0]['value']['messages'] ?? [];

$dirMedia = BASE_DIR . '/data/whatsapp_media';
if (!is_dir($dirMedia)) mkdir($dirMedia, 0777, true);

foreach ($mensajes as $m) {
    $numero = $m['from'] ?? 'desconocido';
    $mensajeId = $m['id'] ?? uniqid();

    $stmt = $pdo->prepare("SELECT id FROM correos_a_tickets WHERE mensaje_id = ?");
    $stmt->execute([$mensajeId]);
    if ($stmt->fetchColumn()) continue; // ya procesado

    $texto = null;
    $mediaUrl = null;

    if (($m['type'] ?? '') === 'text') {
        $texto = $m['text']['body'] ?? '';
    } elseif (in_array($m['type'] ?? '', ['image', 'document', 'video', 'audio'], true)) {
        $tipoMedia = $m['type'];
        $mediaId = $m[$tipoMedia]['id'] ?? null;
        $texto = $m[$tipoMedia]['caption'] ?? "(Envió un archivo tipo {$tipoMedia} - se adjunta)";
        if ($mediaId && !empty($c['token'])) {
            try {
                $client = new WhatsAppClient($c['token'], $c['phone_number_id']);
                $ext = ['image' => 'jpg', 'document' => 'pdf', 'video' => 'mp4', 'audio' => 'ogg'][$tipoMedia] ?? 'bin';
                $destino = $dirMedia . '/' . uniqid() . '.' . $ext;
                $client->descargarMedia($mediaId, $destino);
                $mediaUrl = 'data/whatsapp_media/' . basename($destino);
            } catch (WhatsAppException $e) {
                $texto .= " (no se pudo descargar el archivo: {$e->getMessage()})";
            }
        }
    } else {
        $texto = '(mensaje de tipo no soportado)';
    }

    $slaLimite = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));
    $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, solicitante, solicitante_contacto, sla_limite, origen, canal_media_url)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute(["WhatsApp de {$numero}", $texto, 'WHATSAPP', 'MEDIA', $numero, $numero, $slaLimite, 'WHATSAPP', $mediaUrl]);
    $ticketId = $pdo->lastInsertId();

    $comentarioInicial = $texto . ($mediaUrl ? "\n[Archivo adjunto: {$mediaUrl}]" : '');
    $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
        ->execute([$ticketId, $numero, $comentarioInicial, 'COMENTARIO']);

    $pdo->prepare("INSERT INTO correos_a_tickets (mensaje_id, buzon, remitente, asunto, ticket_id) VALUES (?,?,?,?,?)")
        ->execute([$mensajeId, 'whatsapp', $numero, $texto, $ticketId]);

    hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'CREADO_DESDE_WHATSAPP', $texto, $numero, $ticketId);
    ia_triage_ticket($pdo, $ticketId);

    // Si la IA resolvió el ticket, se le responde al empleado por el mismo WhatsApp.
    if (!empty($c['token'])) {
        $stmt = $pdo->prepare("SELECT estado FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        if ($stmt->fetchColumn() === 'RESUELTO POR IA') {
            $stmtC = $pdo->prepare("SELECT comentario FROM tickets_comentarios WHERE ticket_id = ? AND tipo = 'IA' ORDER BY id DESC LIMIT 1");
            $stmtC->execute([$ticketId]);
            $respuestaIa = $stmtC->fetchColumn();
            if ($respuestaIa) {
                try {
                    (new WhatsAppClient($c['token'], $c['phone_number_id']))->enviarTexto($numero, $respuestaIa);
                } catch (WhatsAppException $e) { /* queda igual la respuesta en el ticket aunque falle el envío */ }
            }
        }
    }
}

http_response_code(200);
echo 'EVENT_RECEIVED';
