<?php
/**
 * Callback del login con Microsoft 365. Intercambia el "code" por un token,
 * consulta /me en Graph, y si el correo coincide con un usuario ya existente
 * y activo en usuarios_sistema, inicia sesión. No crea cuentas nuevas solas
 * -por seguridad, el acceso lo sigue autorizando un administrador desde
 * Usuarios y roles-, solo evita tener que escribir la contraseña si el
 * usuario ya existe.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/totp.php';
iniciar_sesion_segura();
$pdo = db();

$configPath = MS365_CONFIG_PATH;
if (!file_exists($configPath)) { die('Inicio de sesión con Microsoft no configurado.'); }
$cfg = leer_config_json($configPath);

if (empty($_GET['code']) || empty($_GET['state']) || $_GET['state'] !== ($_SESSION['sso_state'] ?? null)) {
    header('Location: login.php?error=sso');
    exit;
}
unset($_SESSION['sso_state']);

$redirectUri = navissi_url_publica('sso_microsoft_callback.php');

$ch = curl_init('https://login.microsoftonline.com/' . $cfg['tenant_id'] . '/oauth2/v2.0/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'scope' => 'openid profile email User.Read',
        'code' => $_GET['code'],
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]),
]);
$respuesta = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($respuesta['access_token'])) {
    $motivo = $respuesta['error_description'] ?? 'sin detalle';
    die('No se pudo completar el login con Microsoft: ' . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') . '. Verifica que el Redirect URI esté registrado en Azure AD.');
}

$ch = curl_init('https://graph.microsoft.com/v1.0/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $respuesta['access_token']],
]);
$perfil = json_decode(curl_exec($ch), true);
curl_close($ch);

$correo = $perfil['mail'] ?? $perfil['userPrincipalName'] ?? null;
if (!$correo) {
    die('No se pudo obtener el correo de la cuenta de Microsoft.');
}

$stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE email = ? COLLATE NOCASE AND activo = 1");
$stmt->execute([$correo]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    header('Location: login.php?error=sso_sin_cuenta');
    exit;
}

$pdo->prepare("UPDATE usuarios_sistema SET sso_microsoft_id = ? WHERE id = ?")->execute([$perfil['id'] ?? null, $u['id']]);

if (!empty($u['totp_habilitado'])) {
    $_SESSION['pendiente_2fa'] = ['id' => $u['id']];
    header('Location: login.php');
    exit;
}

$_SESSION['usuario'] = sesion_desde_usuario($u);
session_regenerate_id(true);
$destino = $u['rol'] === 'EMPLEADO' ? 'modules/portal_empleado.php' : 'index.php';
header("Location: {$destino}");
