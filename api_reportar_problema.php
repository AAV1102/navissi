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
require_once __DIR__ . '/lib/agente_auth.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = db();

// Solo usuarios autenticados o agentes de inventario con token HMAC/Bearer
// pueden crear tickets. Antes este endpoint era público y permitía spam.
iniciar_sesion_segura();
$sesionValida = !empty($_SESSION['usuario']);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$serial = limpio($data['serial'] ?? null);
$tokenAgente = agente_token_header();
if (!$sesionValida && !$tokenAgente) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sesión o credencial de agente requerida.']);
    exit;
}
if (!$sesionValida) {
    agente_autenticar($pdo, $serial, true);
}
// limpio_html() por seguridad: descripcion queda como texto plano normal pero
// bloquea cualquier <script>/onerror si alguien manda HTML malicioso via API,
// ya que el detalle del ticket renderiza descripcion sin volver a escapar.
$descripcion = limpio_html($data['descripcion'] ?? null);
$usuarioWindows = limpio($data['usuario_windows'] ?? null);
$correoSolicitante = filter_var($data['correo'] ?? $data['reporter_email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null;

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
$solicitante = $usuarioWindows ?: limpio($data['reporter_name'] ?? null) ?: 'Empleado (agente local)';

// El agente local normalmente no pide el correo al empleado; lo resolvemos
// contra la identidad NAVISSI para que la respuesta automática llegue a la
// persona y no se pierda en la cola.
if (!$correoSolicitante && $solicitante !== 'Empleado (agente local)') {
    $stmtCorreo = $pdo->prepare('SELECT email FROM usuarios_sistema WHERE lower(nombre) = lower(?) AND activo = 1 LIMIT 1');
    $stmtCorreo->execute([$solicitante]);
    $correoSolicitante = filter_var($stmtCorreo->fetchColumn(), FILTER_VALIDATE_EMAIL) ?: null;
}

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
$stmt->execute([$titulo, $descripcionCompleta, 'SOPORTE', 'MEDIA', $sedeId, $solicitante, $correoSolicitante, $slaLimite, 'AGENTE_LOCAL', $serial]);
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

$stmt = $pdo->prepare("SELECT categoria, asignado_a, estado FROM tickets WHERE id = ?");
$stmt->execute([$ticketId]);
$resultadoTicket = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

echo json_encode([
    'ok' => true,
    'ticket_id' => $ticketId,
    'resuelto' => $estado === 'RESUELTO POR IA',
    'categoria' => $resultadoTicket['categoria'] ?? null,
    'asignado_a' => $resultadoTicket['asignado_a'] ?? null,
    'estado' => $resultadoTicket['estado'] ?? $estado,
    'mensaje' => $respuestaIa ?: 'Tu reporte quedó registrado como ticket #' . $ticketId . '. Un técnico de TI lo revisará pronto.',
], JSON_UNESCAPED_UNICODE);
