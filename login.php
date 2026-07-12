<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/totp.php';
require_once __DIR__ . '/lib/icons.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();
$error = null;
$pedirCodigo = false;

if (!empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

function login_completar(array $u): void {
    $_SESSION['usuario'] = sesion_desde_usuario($u);
    unset($_SESSION['pendiente_2fa']);
    $destino = $u['rol'] === 'EMPLEADO' ? 'modules/portal_empleado.php' : 'index.php';
    header("Location: {$destino}");
    exit;
}

$ssoDisponible = file_exists(__DIR__ . '/data/ms365_config.json');
$passwordCambiada = isset($_GET['clave_cambiada']);
if (isset($_GET['error']) && !$error) {
    $error = match ($_GET['error']) {
        'sso' => 'No se pudo verificar el inicio de sesión con Microsoft. Intenta de nuevo.',
        'sso_sin_cuenta' => 'Tu cuenta de Microsoft es válida, pero no tienes un usuario creado en NAVISSI. Pide a tu administrador que te dé de alta en Seguridad → Usuarios y roles.',
        default => null,
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_2fa'])) {
    $uid = $_SESSION['pendiente_2fa']['id'] ?? null;
    $stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE id = ? AND activo = 1");
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && $u['totp_habilitado'] && totp_verificar($u['totp_secreto'], $_POST['codigo_2fa'] ?? '')) {
        login_completar($u);
    }
    $error = 'Código de verificación incorrecto.';
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
            <h1>NAVISSI Inventario</h1>
            <p>La plataforma de TI de Grupo 10Z: inventario, mesa de ayuda, RRHH, automatización e IA — todo en un solo lugar.</p>
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
                <form method="post" class="auth-form">
                    <label>Código de verificación</label>
                    <input type="text" name="codigo_2fa" required autofocus inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" class="auth-code-input">
                    <button type="submit" class="btn-primary-lg"><?= icon('check') ?> Verificar y entrar</button>
                </form>
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
                    <label>Correo</label>
                    <input type="email" name="email" required autofocus placeholder="tu.correo@grupo10z.com">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="••••••••">
                    <button type="submit" class="btn-primary-lg"><?= icon('check') ?> Ingresar</button>
                </form>
                <p class="auth-foot">¿Olvidaste tu contraseña? Pídele a tu administrador que te la restablezca desde <em>Seguridad → Usuarios y roles</em>.</p>
                <p class="auth-foot small">Usuario inicial: admin@navissi.com / navissi2026</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
