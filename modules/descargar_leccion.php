<?php
require_once __DIR__ . '/../config.php';
requiere_login('../');
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM lecciones WHERE id = ?");
$stmt->execute([$id]);
$leccion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$leccion || empty($leccion['archivo_ruta'])) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$ruta = __DIR__ . '/../data/documentos/cursos/' . $leccion['archivo_ruta'];
if (!file_exists($ruta)) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}

$ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf',
    default => 'application/octet-stream',
};
header("Content-Type: {$mime}");
header('Content-Disposition: inline; filename="' . ($leccion['archivo_nombre'] ?: 'leccion') . '"');
readfile($ruta);
