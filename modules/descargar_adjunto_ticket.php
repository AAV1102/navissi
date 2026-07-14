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

$dir=tickets_adjuntos_dir();$ruta=$dir.DIRECTORY_SEPARATOR.basename((string)$a['ruta']);$real=realpath($ruta);if(!$real||!str_starts_with($real,realpath($dir).DIRECTORY_SEPARATOR)||!is_file($real)){http_response_code(404);exit('Archivo no encontrado.');}
header('Content-Type: ' . ($a['tipo_mime'] ?: 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');$nombre=preg_replace('/[\r\n"\\]/','_',(string)$a['nombre_archivo']);header("Content-Disposition: attachment; filename=\"{$nombre}\"; filename*=UTF-8''".rawurlencode((string)$a['nombre_archivo']));header('Content-Length: '.filesize($real));readfile($real);
