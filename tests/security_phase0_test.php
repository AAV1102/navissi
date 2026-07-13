<?php
declare(strict_types=1);

$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'navissi-security-' . bin2hex(random_bytes(5));
putenv('NAVISSI_PRIVATE_DIR=' . $temp);
putenv('NAVISSI_SKIP_FILE_MIGRATION=1');
require dirname(__DIR__) . '/config.php';
$csrfInicial = csrf_token();

function comprobar(bool $condicion, string $mensaje): void {
    if (!$condicion) throw new RuntimeException($mensaje);
    echo "OK  {$mensaje}\n";
}

$pdo = db();
comprobar(str_starts_with(DB_PATH, $temp), 'SQLite se crea fuera del document root');

$admin = $pdo->query("SELECT * FROM usuarios_sistema WHERE email = 'admin@navissi.com'")->fetch(PDO::FETCH_ASSOC);
comprobar((bool) $admin, 'se crea el administrador inicial');
comprobar(!password_verify('navissi2026', $admin['password_hash']), 'la contraseña publicada ya no funciona');
comprobar((int) $admin['password_temporal'] === 1, 'el acceso inicial obliga a cambiar contraseña');
comprobar(password_verify(trim((string) file_get_contents(private_path('bootstrap-admin.txt'))), $admin['password_hash']), 'la clave inicial aleatoria coincide');

$pdo->prepare("INSERT INTO credenciales (sistema, usuario, contrasena) VALUES (?,?,?)")
    ->execute(['PRUEBA', 'usuario', 'secreto-prueba']);
aplicar_migraciones_seguridad($pdo);
$guardado = $pdo->query("SELECT contrasena FROM credenciales WHERE sistema = 'PRUEBA'")->fetchColumn();
comprobar(secreto_cifrado($guardado), 'las credenciales quedan cifradas en reposo');
comprobar(secreto_descifrar($guardado) === 'secreto-prueba', 'una credencial autorizada se puede descifrar');

$body = '{"titulo":"Prueba"}';
$firma = hash_hmac('sha256', $body, navissi_webhook_secret());
comprobar(firma_hmac_valida($body, $firma, navissi_webhook_secret()), 'una firma HMAC válida es aceptada');
comprobar(!firma_hmac_valida($body . 'x', $firma, navissi_webhook_secret()), 'un cuerpo alterado es rechazado');

comprobar(csrf_token_valido($csrfInicial), 'el token CSRF emitido es válido');
comprobar(!csrf_token_valido(str_repeat('0', 64)), 'un token CSRF falso es rechazado');

echo "PASS security_phase0_test\n";
