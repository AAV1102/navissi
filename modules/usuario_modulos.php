<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN'])) {
    layout_inicio('Módulos individuales', 'Usuarios y roles', '../');
    echo '<div class="msg-error">Solo un administrador puede asignar módulos individuales.</div>';
    layout_fin();
    exit;
}

$usuarioId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE id = ?");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    layout_inicio('Usuario no encontrado', 'Usuarios y roles', '../');
    echo '<div class="msg-error">Ese usuario no existe.</div><a class="btn" href="usuarios.php">Volver</a>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("DELETE FROM usuario_modulos_extra WHERE usuario_id = ?")->execute([$usuarioId]);
    $stmtIns = $pdo->prepare("INSERT INTO usuario_modulos_extra (usuario_id, modulo_href) VALUES (?,?)");
    foreach ($_POST['modulos'] ?? [] as $href) {
        $stmtIns->execute([$usuarioId, $href]);
    }
    $msg = ['ok', 'Módulos individuales actualizados.'];
}

$stmt = $pdo->prepare("SELECT modulo_href FROM usuario_modulos_extra WHERE usuario_id = ?");
$stmt->execute([$usuarioId]);
$asignados = $stmt->fetchAll(PDO::FETCH_COLUMN);

layout_inicio('Módulos individuales', 'Usuarios y roles', '../');
?>
<p class="small"><a href="usuarios.php">← Volver a Usuarios</a></p>
<h1><?= icon('users','icon-lg') ?> Módulos individuales de <?= e($usuario['nombre']) ?></h1>
<p class="subtitle">Además de lo que su rol (<strong><?= e($usuario['rol']) ?></strong>) ya le permite ver, marca aquí los módulos puntuales que quieras darle a esta persona específicamente — por ejemplo, dar "Contratos" a alguien de RRHH sin volverlo TI completo.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form method="post">
    <?php foreach (nav_grupos() as $grupo => $def): ?>
    <div class="panel">
        <h3><?= icon($def['icon']) ?> <?= e($grupo) ?></h3>
        <div class="grid-form">
            <?php foreach ($def['items'] as $href => [$label, $ic]): ?>
            <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                <input type="checkbox" name="modulos[]" value="<?= e($href) ?>" <?= in_array($href, $asignados, true) ? 'checked' : '' ?> style="width:18px;height:18px;">
                <?= icon($ic) ?> <?= e($label) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <button type="submit" style="position:sticky;bottom:16px;"><?= icon('check') ?> Guardar módulos individuales</button>
</form>
<?php layout_fin(); ?>
