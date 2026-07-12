<?php
/**
 * Sincroniza la "carpeta vigilada": revisa la ruta configurada en
 * data/importador_config.json y trae lo nuevo/modificado a la base de
 * datos. Se puede llamar desde el navegador (botón "Sincronizar ahora")
 * o por linea de comandos / Tarea Programada de Windows para que corra
 * solo cada tanto:
 *   php.exe api_importador_carpeta.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/importador_universal.php';

$esCli = php_sapi_name() === 'cli';
if (!$esCli) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    requiere_login('');
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = db();
$configPath = __DIR__ . '/data/importador_config.json';
$config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
$ruta = $config['ruta_carpeta'] ?? '';

$resumen = iu_sincronizar_carpeta($pdo, $ruta);

if ($esCli) {
    echo "Revisados: {$resumen['archivos_revisados']} | Procesados: {$resumen['archivos_procesados']} | Importados: {$resumen['importados']} | Actualizados: {$resumen['actualizados']} | Omitidos: {$resumen['omitidos']}\n";
    foreach ($resumen['errores'] as $e) echo "ERROR: {$e}\n";
} else {
    echo json_encode(['ok' => true] + $resumen);
}
