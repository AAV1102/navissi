<?php
require_once __DIR__ . '/../config.php';
requiere_login('../');
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM vacaciones_permisos WHERE id = ?");
$stmt->execute([$id]);
$sol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sol || empty($sol['adjunto_ruta'])) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

// Solo puede descargarlo: quien tenga rol de gestión (RRHH/ADMIN/Director de su área), o el propio dueño de la solicitud.
$personal = alcance_personal();
$esDueño = $personal !== null && $sol['empleado_documento'] === $personal['documento'];
$esGestion = tiene_rol(['ADMIN', 'RRHH']) || (rol_efectivo() === 'DIRECTOR' && alcance_area() !== null);
if (!$esDueño && !$esGestion) {
    http_response_code(403);
    exit('No tienes permiso para ver este archivo.');
}

$ruta = __DIR__ . '/../data/desprendibles/' . $sol['adjunto_ruta'];
if (!file_exists($ruta)) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . ($sol['adjunto_nombre'] ?: 'adjunto') . '"');
readfile($ruta);
