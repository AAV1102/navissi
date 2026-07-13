<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
requiere_login('../');
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM gd_archivos WHERE id = ?");
$stmt->execute([$id]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$a) { http_response_code(404); exit('No encontrado.'); }

$stmtC = $pdo->prepare("SELECT * FROM gd_carpetas WHERE id = ?");
$stmtC->execute([$a['carpeta_id']]);
$carpeta = $stmtC->fetch(PDO::FETCH_ASSOC);
if ($carpeta && $carpeta['area'] && !tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'SUPER_ADMIN']) && alcance_area() !== $carpeta['area']) {
    http_response_code(403);
    exit('No autorizado.');
}

$ruta = __DIR__ . '/../data/gestion_documental/' . $a['ruta'];
if (!file_exists($ruta)) { http_response_code(404); exit('Archivo no encontrado.'); }
header('Content-Type: ' . ($a['tipo_mime'] ?: 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . $a['nombre_archivo'] . '"');
readfile($ruta);
