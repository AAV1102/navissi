<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
requiere_login('../');
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM tickets_adjuntos WHERE id = ?");
$stmt->execute([$id]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$a) { http_response_code(404); exit('No encontrado.'); }

$stmtT = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$stmtT->execute([$a['ticket_id']]);
$ticket = $stmtT->fetch(PDO::FETCH_ASSOC);
$personal = alcance_personal();
if ($personal !== null && $ticket['solicitante'] !== $personal['nombre'] && $ticket['solicitante_contacto'] !== $personal['email']) {
    http_response_code(403);
    exit('No autorizado.');
}
if ($personal === null && alcance_area() !== null && $ticket['solicitante_area'] !== alcance_area()) {
    http_response_code(403);
    exit('No autorizado.');
}

$ruta = __DIR__ . '/../data/tickets_adjuntos/' . $a['ruta'];
if (!file_exists($ruta)) { http_response_code(404); exit('Archivo no encontrado.'); }
header('Content-Type: ' . ($a['tipo_mime'] ?: 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . $a['nombre_archivo'] . '"');
readfile($ruta);
