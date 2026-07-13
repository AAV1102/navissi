<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
requiere_login('../');
if (!tiene_rol(['GERENCIA', 'CEO', 'ADMIN', 'RRHH', 'DIRECTOR'])) {
    http_response_code(403);
    exit('No autorizado.');
}
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM candidatos WHERE id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$c || !$c['cv_ruta']) { http_response_code(404); exit('No encontrado.'); }
$ruta = __DIR__ . '/../data/candidatos_cv/' . $c['cv_ruta'];
if (!file_exists($ruta)) { http_response_code(404); exit('Archivo no encontrado.'); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . ($c['cv_nombre'] ?: basename($ruta)) . '"');
readfile($ruta);
