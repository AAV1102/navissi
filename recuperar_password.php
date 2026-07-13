<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/mailer.php';
$pdo = db();
$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_requerir();
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE email = ? COLLATE NOCASE AND activo = 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    // Por seguridad, siempre se muestra el mismo mensaje exista o no la cuenta (no se revela si el correo está registrado).
    $enviado = true;
    if ($u) {
        $token = bin2hex(random_bytes(32));
        $expira = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));
        $pdo->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expira_en) VALUES (?,?,?)")
            ->execute([$u['id'], $token, $expira]);

        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $enlace = "{$base}/restablecer_password.php?token={$token}";
        $html = plantilla_correo_html(
            'Restablece tu contraseña',
            "<p>Hola " . e($u['nombre']) . ",</p><p>Pediste restablecer tu contraseña de NAVISSI. Este enlace es válido por 1 hora y solo se puede usar una vez.</p><p>Si no fuiste tú, ignora este correo — tu contraseña actual sigue siendo válida.</p>",
            'Restablecer mi contraseña',
            $enlace
        );
        enviar_correo($u['email'], 'NAVISSI - Restablecer tu contraseña', $html, $u['nombre']);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar contraseña - NAVISSI Inventario</title>
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-brand">
        <div class="auth-brand-inner">
            <span class="auth-brand-mark"><?= icon('inventory', 'icon') ?></span>
            <h1>NAVISSI Inventario</h1>
            <p>La plataforma de TI de Grupo 10Z: inventario, mesa de ayuda, RRHH, automatización e IA — todo en un solo lugar.</p>
        </div>
    </div>
    <div class="auth-panel">
        <div class="auth-card">
            <p class="auth-eyebrow">Grupo 10Z SAS</p>
            <h2>Recuperar contraseña</h2>
            <?php if ($enviado): ?>
                <div class="msg-ok"><?= icon('check') ?> Si ese correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada (y spam).</div>
                <p class="auth-foot"><a href="login.php">Volver a iniciar sesión</a></p>
            <?php else: ?>
                <p class="auth-sub">Ingresa tu correo y te enviamos un enlace para crear una nueva contraseña.</p>
                <form method="post" class="auth-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <label>Correo</label>
                    <input type="email" name="email" required autofocus placeholder="tu.correo@grupo10z.com">
                    <button type="submit" class="btn-primary-lg"><?= icon('key') ?> Enviar enlace</button>
                </form>
                <p class="auth-foot"><a href="login.php">Volver a iniciar sesión</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
