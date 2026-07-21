<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
requiere_login('../');
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM cotizaciones_adjuntos WHERE id = ?");
$stmt->execute([$id]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$a) { http_response_code(404); exit('No encontrado.'); }

$stmtR = $pdo->prepare("SELECT r.*, s.area_responsable FROM cotizaciones_respuestas r JOIN cotizaciones_solicitudes s ON s.id = r.solicitud_id WHERE r.id = ?");
$stmtR->execute([$a['respuesta_id']]);
$respuesta = $stmtR->fetch(PDO::FETCH_ASSOC);
if (!$respuesta) { http_response_code(404); exit('No encontrado.'); }
if (alcance_area() !== null && $respuesta['area_responsable'] !== alcance_area()) {
    http_response_code(403);
    exit('No autorizado.');
}

$dir = __DIR__ . '/../data/cotizaciones_adjuntos';
$ruta = $dir . DIRECTORY_SEPARATOR . basename((string) $a['ruta']);
$real = realpath($ruta);
if (!$real || !str_starts_with($real, realpath($dir) . DIRECTORY_SEPARATOR) || !is_file($real)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}
header('Content-Type: ' . ($a['tipo_mime'] ?: 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
$nombre = preg_replace('/[\r\n"\\\\]/', '_', (string) $a['nombre_archivo']);
header("Content-Disposition: attachment; filename=\"{$nombre}\"; filename*=UTF-8''" . rawurlencode((string) $a['nombre_archivo']));
header('Content-Length: ' . filesize($real));
readfile($real);
