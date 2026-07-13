<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/totp.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/mailer.php';
iniciar_sesion_segura();
$pdo = db();
$error = null;
$pedirCodigo = false;

if (!empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

function login_completar(array $u): void {
    unset($_SESSION['pendiente_2fa']);
    session_regenerate_id(true);
    if (!empty($u['password_temporal'])) {
        // Contraseña temporal asignada por un admin: hay que cambiarla antes de poder usar el sistema.
        $_SESSION['pendiente_cambio_clave'] = ['id' => $u['id']];
        header('Location: cambiar_password_temporal.php');
        exit;
    }
    $_SESSION['usuario'] = sesion_desde_usuario($u);
    $destino = es_solo_empleado() ? 'modules/portal_empleado.php' : 'index.php';
    header("Location: {$destino}");
    exit;
}

$ssoDisponible = file_exists(MS365_CONFIG_PATH);
$passwordCambiada = isset($_GET['clave_cambiada']);
if (isset($_GET['error']) && !$error) {
    $error = match ($_GET['error']) {
        'sso' => 'No se pudo verificar el inicio de sesión con Microsoft. Intenta de nuevo.',
        'sso_sin_cuenta' => 'Tu cuenta de Microsoft es válida, pero no tienes un usuario creado en NAVISSI. Pide a tu administrador que te dé de alta en Seguridad → Usuarios y roles.',
        default => null,
    };
}

$codigoCorreoEnviado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_requerir();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'enviar_codigo_correo') {
    $uid = $_SESSION['pendiente_2fa']['id'] ?? null;
    $stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE id = ? AND activo = 1");
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $codigo = (string) random_int(100000, 999999);
        $_SESSION['pendiente_2fa']['codigo_correo'] = $codigo;
        $_SESSION['pendiente_2fa']['codigo_expira'] = time() + 600; // 10 minutos
        $html = plantilla_correo_html('Tu código de verificación', "<p>Hola " . e($u['nombre']) . ",</p><p>Este es tu código de acceso porque tu Authenticator no está disponible:</p><p style=\"font-size:28px;letter-spacing:6px;font-weight:700;text-align:center;\">{$codigo}</p><p>Vence en 10 minutos. Si no fuiste tú, ignora este correo.</p>");
        enviar_correo($u['email'], 'NAVISSI - Código de verificación', $html, $u['nombre']);
        $codigoCorreoEnviado = true;
    }
    $pedirCodigo = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_2fa'])) {
    $uid = $_SESSION['pendiente_2fa']['id'] ?? null;
    $stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE id = ? AND activo = 1");
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    $codigoIngresado = $_POST['codigo_2fa'] ?? '';
    $codigoCorreoValido = !empty($_SESSION['pendiente_2fa']['codigo_correo'])
        && hash_equals($_SESSION['pendiente_2fa']['codigo_correo'], $codigoIngresado)
        && time() < ($_SESSION['pendiente_2fa']['codigo_expira'] ?? 0);
    if ($u && (($u['totp_habilitado'] && totp_verificar($u['totp_secreto'], $codigoIngresado)) || $codigoCorreoValido)) {
        login_completar($u);
    }
    $error = 'Código de verificación incorrecto o vencido.';
    $pedirCodigo = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $clave = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE email = ? COLLATE NOCASE AND activo = 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($clave, $u['password_hash'])) {
        if (!empty($u['totp_habilitado'])) {
            $_SESSION['pendiente_2fa'] = ['id' => $u['id']];
            $pedirCodigo = true;
        } else {
            login_completar($u);
        }
    } else {
        $error = 'Correo o contraseña incorrectos.';
    }
} elseif (!empty($_SESSION['pendiente_2fa'])) {
    $pedirCodigo = true;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingresar - NAVISSI Inventario</title>
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-brand">
        <div class="auth-brand-inner">
            <span class="auth-brand-mark"><?= icon('inventory', 'icon') ?></span>
            <p class="auth-brand-kicker">NAVISSI BACKSTAGE</p>
            <h1>La operación detrás de cada tienda.</h1>
            <p>Inventario, servicio, personas y automatización en un mismo pulso operativo para Grupo 10Z.</p>
            <ul class="auth-brand-list">
                <li><?= icon('shield') ?> Verificación en dos pasos con Authenticator</li>
                <li><?= icon('cloud') ?> Inicio de sesión con Microsoft 365</li>
                <li><?= icon('log') ?> Trazabilidad completa por empleado y equipo</li>
            </ul>
        </div>
    </div>
    <div class="auth-panel">
        <div class="auth-card">
            <p class="auth-eyebrow">Grupo 10Z SAS</p>
            <?php if ($pedirCodigo): ?>
                <h2>Verificación en dos pasos</h2>
                <p class="auth-sub">Abre Microsoft Authenticator (o tu app de códigos) e ingresa el código de 6 dígitos.</p>
                <?php if ($error): ?><div class="msg-error"><?= icon('x') ?> <?= e($error) ?></div><?php endif; ?>
                <?php if ($codigoCorreoEnviado): ?><div class="msg-ok"><?= icon('check') ?> Te enviamos un código por correo, vence en 10 minutos.</div><?php endif; ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <label>Código de verificación</label>
                    <input type="text" name="codigo_2fa" required autofocus inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" class="auth-code-input">
                    <button type="submit" class="btn-primary-lg"><?= icon('check') ?> Verificar y entrar</button>
                </form>
                <p class="auth-foot">
                    ¿No tienes acceso a tu Authenticator?
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="enviar_codigo_correo">
                        <button type="submit" class="btn-link-inline"><?= icon('send') ?> Enviar código a mi correo</button>
                    </form>
                </p>
            <?php else: ?>
                <h2>Iniciar sesión</h2>
                <p class="auth-sub">Ingresa con tu cuenta de NAVISSI o con tu cuenta corporativa de Microsoft.</p>
                <?php if ($error): ?><div class="msg-error"><?= icon('x') ?> <?= e($error) ?></div><?php endif; ?>
                <?php if ($passwordCambiada): ?><div class="msg-ok"><?= icon('check') ?> Contraseña actualizada. Ya puedes ingresar.</div><?php endif; ?>
                <?php if ($ssoDisponible): ?>
                <a href="sso_microsoft.php" class="btn-microsoft">
                    <svg width="18" height="18" viewBox="0 0 21 21" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
                    Continuar con Microsoft 365
                </a>
                <div class="auth-divider"><span>o con tu correo</span></div>
                <?php endif; ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <label>Correo</label>
                    <input type="email" name="email" required autofocus placeholder="tu.correo@grupo10z.com">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="••••••••">
                    <button type="submit" class="btn-primary-lg"><?= icon('check') ?> Ingresar</button>
                </form>
                <p class="auth-foot"><a href="recuperar_password.php"><?= icon('key') ?> ¿Olvidaste tu contraseña?</a></p>
                <p class="auth-foot small">El acceso inicial se genera de forma segura y queda disponible solo para TI.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
