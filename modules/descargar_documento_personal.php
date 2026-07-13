<?php
require_once __DIR__ . '/../config.php';
requiere_login('../');
$pdo = db();
$u = usuario_actual();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM documentos WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

// Puede verlo: quien lo subió/gestiona (ADMIN/TI, o RRHH/Director si es de su gente), o el dueño del documento.
$esDueño = $doc['empleado_documento'] && $doc['empleado_documento'] === $u['documento'];
$esGestion = tiene_rol(['ADMIN', 'TI', 'RRHH']) || (rol_efectivo() === 'DIRECTOR' && alcance_area() !== null);
if (!$esDueño && !$esGestion) {
    http_response_code(403);
    exit('No tienes permiso para ver este documento.');
}

$ruta = __DIR__ . '/../data/documentos/' . $doc['ruta'];
if (!file_exists($ruta)) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: inline; filename="' . $doc['nombre_archivo'] . '"');
readfile($ruta);
