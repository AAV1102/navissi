<?php
require_once __DIR__ . '/config.php';
requiere_login('');
if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'COORDINADOR', 'TI'])) { http_response_code(403); exit('Sin permiso.'); }
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM proveedores_actualizaciones WHERE id = ?");
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$reg || !$reg['archivo_ruta']) { http_response_code(404); exit('Archivo no encontrado.'); }
$ruta = __DIR__ . '/data/' . $reg['archivo_ruta'];
if (!file_exists($ruta)) { http_response_code(404); exit('Archivo no encontrado.'); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . ($reg['archivo_nombre'] ?: basename($ruta)) . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
