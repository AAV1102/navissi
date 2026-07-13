<?php
/**
 * Inicia el flujo de "Iniciar sesión con Microsoft 365" (OAuth2 Authorization
 * Code). Usa el mismo App Registration de Azure AD que ya está configurado
 * para Graph (data/ms365_config.json), pero con permisos delegados de login
 * (openid/profile/email/User.Read) en vez del flujo app-only.
 *
 * IMPORTANTE (paso manual, una sola vez, en Azure Portal -> App registrations
 * -> tu app -> Authentication -> Add a platform -> Web): agregar como
 * Redirect URI exactamente la URL de sso_microsoft_callback.php de este
 * servidor (ej. http://192.168.99.64:8099/sso_microsoft_callback.php, o el
 * dominio real cuando esto esté en el NAS/hosting). Sin ese paso, Microsoft
 * rechaza el login con "redirect_uri_mismatch".
 */
require_once __DIR__ . '/config.php';
iniciar_sesion_segura();

$configPath = MS365_CONFIG_PATH;
if (!file_exists($configPath)) {
    http_response_code(500);
    die('El inicio de sesión con Microsoft no está configurado (falta data/ms365_config.json).');
}
$cfg = leer_config_json($configPath);

$state = bin2hex(random_bytes(16));
$_SESSION['sso_state'] = $state;

$redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/sso_microsoft_callback.php';

$params = [
    'client_id' => $cfg['client_id'],
    'response_type' => 'code',
    'redirect_uri' => $redirectUri,
    'response_mode' => 'query',
    'scope' => 'openid profile email User.Read',
    'state' => $state,
];

header('Location: https://login.microsoftonline.com/' . $cfg['tenant_id'] . '/oauth2/v2.0/authorize?' . http_build_query($params));
exit;
