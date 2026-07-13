<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/icons.php';
iniciar_sesion_segura();
$pdo = db();
$error = null;

$uid = $_SESSION['pendiente_cambio_clave']['id'] ?? null;
if (!$uid) {
    header('Location: login.php');
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE id = ? AND activo = 1");
$stmt->execute([$uid]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    unset($_SESSION['pendiente_cambio_clave']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_requerir();
    $clave = $_POST['password'] ?? '';
    $clave2 = $_POST['password2'] ?? '';
    if (strlen($clave) < 12) {
        $error = 'La contraseña debe tener al menos 12 caracteres.';
    } elseif ($clave !== $clave2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $pdo->prepare("UPDATE usuarios_sistema SET password_hash = ?, password_temporal = 0 WHERE id = ?")
            ->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
        if (strcasecmp((string) ($u['email'] ?? ''), 'admin@navissi.com') === 0) {
            @unlink(private_path('bootstrap-admin.txt'));
        }
        unset($_SESSION['pendiente_cambio_clave']);
        $_SESSION['usuario'] = sesion_desde_usuario($u);
        session_regenerate_id(true);
        $destino = es_solo_empleado() ? 'modules/portal_empleado.php' : 'index.php';
        header("Location: {$destino}");
        exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cambiar contraseña - NAVISSI Inventario</title>
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
            <h2>Crea tu contraseña definitiva</h2>
            <p class="auth-sub">Hola <?= e($u['nombre']) ?>, tu cuenta tiene una contraseña temporal. Antes de continuar, crea una nueva que solo tú conozcas.</p>
            <?php if ($error): ?><div class="msg-error"><?= icon('x') ?> <?= e($error) ?></div><?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>Nueva contraseña</label>
                <input type="password" name="password" required minlength="12" placeholder="Mínimo 12 caracteres" autofocus>
                <label>Repite la contraseña</label>
                <input type="password" name="password2" required minlength="12">
                <button type="submit" class="btn-primary-lg"><?= icon('check') ?> Guardar y continuar</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
