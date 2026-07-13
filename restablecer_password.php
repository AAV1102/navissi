<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/icons.php';
$pdo = db();
$error = null;
$listo = false;

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$stmt = $pdo->prepare("SELECT rt.*, u.email, u.nombre FROM password_reset_tokens rt
    JOIN usuarios_sistema u ON u.id = rt.usuario_id
    WHERE rt.token = ? AND rt.usado = 0 AND rt.expira_en > datetime('now')");
$stmt->execute([$token]);
$tokenFila = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token || !$tokenFila) {
    $error = 'Este enlace ya no es válido — puede haber expirado (dura 1 hora) o ya haberse usado. Pide uno nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_requerir();
    $clave = $_POST['password'] ?? '';
    $clave2 = $_POST['password2'] ?? '';
    if (strlen($clave) < 12) {
        $error = 'La contraseña debe tener al menos 12 caracteres.';
    } elseif ($clave !== $clave2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $pdo->prepare("UPDATE usuarios_sistema SET password_hash = ?, password_temporal = 0 WHERE id = ?")
            ->execute([password_hash($clave, PASSWORD_DEFAULT), $tokenFila['usuario_id']]);
        $pdo->prepare("UPDATE password_reset_tokens SET usado = 1 WHERE id = ?")->execute([$tokenFila['id']]);
        $listo = true;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Restablecer contraseña - NAVISSI Inventario</title>
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-brand">
        <div class="auth-brand-inner">
            <span class="auth-brand-mark"><?= icon('inventory', 'icon') ?></span>
            <h1>NAVISSI Inventario</h1>
        </div>
    </div>
    <div class="auth-panel">
        <div class="auth-card">
            <p class="auth-eyebrow">Grupo 10Z SAS</p>
            <h2>Crear nueva contraseña</h2>
            <?php if ($listo): ?>
                <div class="msg-ok"><?= icon('check') ?> Contraseña actualizada correctamente.</div>
                <p class="auth-foot"><a href="login.php">Ir a iniciar sesión</a></p>
            <?php elseif ($error && !$tokenFila): ?>
                <div class="msg-error"><?= icon('x') ?> <?= e($error) ?></div>
                <p class="auth-foot"><a href="recuperar_password.php">Pedir un nuevo enlace</a></p>
            <?php else: ?>
                <p class="auth-sub">Hola <?= e($tokenFila['nombre']) ?>, elige tu nueva contraseña.</p>
                <?php if ($error): ?><div class="msg-error"><?= icon('x') ?> <?= e($error) ?></div><?php endif; ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <label>Nueva contraseña</label>
                    <input type="password" name="password" required minlength="12" placeholder="Mínimo 12 caracteres">
                    <label>Repite la contraseña</label>
                    <input type="password" name="password2" required minlength="12">
                    <button type="submit" class="btn-primary-lg"><?= icon('check') ?> Guardar contraseña</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
