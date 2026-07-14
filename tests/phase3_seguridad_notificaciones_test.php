<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/notificaciones.php';
require_once __DIR__ . '/../lib/agente_auth.php';

function fase3_ok(bool $condicion, string $mensaje): void {
    if (!$condicion) throw new RuntimeException("FAIL {$mensaje}");
    echo "OK  {$mensaje}\n";
}

$pdo = db();
$pdo->exec('DELETE FROM notificaciones_cola');
$pdo->exec('DELETE FROM agentes_tokens');
$pdo->exec('DELETE FROM sedes');
$pdo->exec("INSERT INTO sedes (nombre,estado) VALUES ('Tienda segura','ACTIVO')");
$sedeId = (int) $pdo->lastInsertId();

notificaciones_config_guardar(['correo_habilitado' => false, 'correos_operacion' => '', 'whatsapp_habilitado' => false,
    'whatsapp_destino' => '', 'teams_habilitado' => false, 'teams_webhook' => '']);
$configRaw = (string) file_get_contents(private_path('notificaciones_config.json'));
fase3_ok(secreto_cifrado($configRaw), 'cifra la configuración de canales');

fase3_ok(notificacion_encolar($pdo, 'evento-1', 'CORREO', 'ti@navissi.com', 'Prueba', 'Contenido'), 'encola una notificación');
fase3_ok(!notificacion_encolar($pdo, 'evento-1', 'CORREO', 'ti@navissi.com', 'Prueba', 'Contenido'), 'no duplica un mismo evento');
$procesado = notificaciones_procesar($pdo, 10);
fase3_ok($procesado['errores'] === 1, 'un canal desactivado queda para reintento sin tumbar el proceso');
$filaCola = $pdo->query("SELECT * FROM notificaciones_cola WHERE clave_unica='evento-1'")->fetch(PDO::FETCH_ASSOC);
fase3_ok($filaCola['estado'] === 'ERROR' && (int)$filaCola['intentos'] === 1 && !empty($filaCola['proximo_intento_en']), 'registra error, intento y próximo reintento');
for ($i = 0; $i < 4; $i++) {
    $pdo->exec("UPDATE notificaciones_cola SET proximo_intento_en=datetime('now','-1 minute') WHERE clave_unica='evento-1'");
    notificaciones_procesar($pdo, 10);
}
$filaCola = $pdo->query("SELECT * FROM notificaciones_cola WHERE clave_unica='evento-1'")->fetch(PDO::FETCH_ASSOC);
fase3_ok($filaCola['estado'] === 'FALLIDA' && (int)$filaCola['intentos'] === 5 && empty($filaCola['proximo_intento_en']), 'detiene reintentos al quinto fallo');
fase3_ok(count(notificaciones_config_validar(['correo_habilitado' => true, 'correos_operacion' => 'correo-invalido'])) >= 1, 'impide activar canales incompletos');

$token = agente_token_emitir($pdo, 'Prueba segura', $sedeId, 'QA');
$filaToken = $pdo->query('SELECT * FROM agentes_tokens LIMIT 1')->fetch(PDO::FETCH_ASSOC);
fase3_ok($filaToken['token_hash'] === hash('sha256', $token) && !str_contains(json_encode($filaToken), $token), 'guarda solo el hash de la credencial');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$agente = agente_autenticar($pdo, 'SERIAL-SEGURO-01', true);
fase3_ok($agente['serial_vinculado'] === 'SERIAL-SEGURO-01', 'vincula la credencial al primer serial');
$filaToken = $pdo->query('SELECT * FROM agentes_tokens LIMIT 1')->fetch(PDO::FETCH_ASSOC);
fase3_ok($filaToken['sede_id'] == $sedeId && !empty($filaToken['ultimo_uso_en']), 'restringe por sede y audita el último uso');

$adjuntos = tickets_adjuntos_dir();
fase3_ok(str_starts_with(realpath($adjuntos), realpath(navissi_private_dir())), 'almacena adjuntos fuera del sitio web');
echo "PASS phase3_seguridad_notificaciones_test\n";

