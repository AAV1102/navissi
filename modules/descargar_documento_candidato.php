<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
requiere_login('../');
if (!tiene_rol(['GERENCIA', 'CEO', 'ADMIN', 'RRHH', 'DIRECTOR'])) {
    http_response_code(403);
    exit('No autorizado.');
}
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM candidatos_documentos WHERE id = ?");
$stmt->execute([$id]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$d || !$d['ruta']) { http_response_code(404); exit('No encontrado.'); }
$ruta = __DIR__ . '/../data/candidatos_documentos/' . $d['ruta'];
$real = realpath($ruta);
$base = realpath(__DIR__ . '/../data/candidatos_documentos');
if (!$real || !$base || !str_starts_with($real, $base) || !file_exists($real)) { http_response_code(404); exit('Archivo no encontrado.'); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . ($d['nombre_archivo'] ?: basename($real)) . '"');
readfile($real);
