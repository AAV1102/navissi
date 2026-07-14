<?php
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/automatizacion_operativa.php';
require_once __DIR__ . '/../lib/siesa_connector.php';
require_once __DIR__ . '/../lib/gobierno_secretos.php';

try {
    $pdo = db();
    $resultado = automatizacion_operativa_ejecutar($pdo, 'CLI', $argv[1] ?? ('cli-' . gmdate('YmdHi')));
    try { $resultado['siesa'] = siesa_sincronizar_programada($pdo); } catch (Throwable $siesaError) { $resultado['siesa'] = ['ok' => false, 'error' => 'La sincronización autorizada de Siesa no pudo completarse.']; }
    try { $resultado['secretos'] = secretos_escanear_programado($pdo); } catch (Throwable $secretosError) { $resultado['secretos'] = ['ok' => false, 'error' => 'El control programado de metadatos no pudo completarse.']; }
    $stmt = $pdo->prepare("INSERT INTO config_general(clave,valor) VALUES(?,?) ON CONFLICT(clave) DO UPDATE SET valor=excluded.valor");
    $stmt->execute(['AUTOMATIZACION_TAREA_ULTIMO_HEARTBEAT', gmdate('Y-m-d H:i:s')]);
    $stmt->execute(['AUTOMATIZACION_TAREA_ULTIMO_ESTADO', !empty($resultado['ocupada']) ? 'OCUPADA' : (!empty($resultado['ok']) ? 'OK' : 'ERROR')]);
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO config_general(clave,valor) VALUES(?,?) ON CONFLICT(clave) DO UPDATE SET valor=excluded.valor");
            $stmt->execute(['AUTOMATIZACION_TAREA_ULTIMO_HEARTBEAT', gmdate('Y-m-d H:i:s')]);
            $stmt->execute(['AUTOMATIZACION_TAREA_ULTIMO_ESTADO', 'ERROR']);
        } catch (Throwable $ignored) {}
    }
    fwrite(STDERR, "ERROR: la automatización no pudo completarse.\n");
    exit(1);
}
