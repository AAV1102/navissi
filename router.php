<?php
/** Router seguro para `php -S`: este servidor no interpreta .htaccess. */
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$normalizada = str_replace('\\', '/', $uri);

if (str_contains($normalizada, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $normalizada)) {
    http_response_code(400);
    exit('Ruta inválida');
}

if (str_starts_with($normalizada, '/data/')) {
    $permitidos = ['/data/agente_navissi.ps1', '/data/desplegar_en_pc_empleado.ps1'];
    if (!in_array($normalizada, $permitidos, true)) {
        http_response_code(403);
        header('X-Content-Type-Options: nosniff');
        exit('Acceso denegado');
    }
}

$ruta = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $normalizada);
if (is_file($ruta)) return false;

require __DIR__ . '/index.php';
