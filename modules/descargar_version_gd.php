<?php
require_once __DIR__ . '/../config.php';
$pdo = db();
requiere_login('../');
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM gd_versiones WHERE id = ?");
$stmt->execute([$id]);
$v = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$v) { http_response_code(404); exit('No encontrado.'); }

$ruta = __DIR__ . '/../data/gestion_documental/' . $v['ruta'];
if (!file_exists($ruta)) { http_response_code(404); exit('Archivo no encontrado.'); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="v' . (int)$v['version'] . '_' . basename($v['ruta']) . '"');
readfile($ruta);
