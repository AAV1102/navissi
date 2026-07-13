<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN'])) {
    layout_inicio('Perfiles por rol', 'Usuarios y roles', '../');
    echo '<div class="msg-error">Solo un administrador puede configurar perfiles por rol.</div>';
    layout_fin();
    exit;
}

$rolSeleccionado = trim($_GET['rol'] ?? 'EMPLEADO');
if (!in_array($rolSeleccionado, ROLES_DISPONIBLES, true)) $rolSeleccionado = 'EMPLEADO';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rolGuardar = trim($_POST['rol'] ?? '');
    if (in_array($rolGuardar, ROLES_DISPONIBLES, true)) {
        $pdo->prepare("DELETE FROM perfiles_modulos WHERE rol = ?")->execute([$rolGuardar]);
        $stmtIns = $pdo->prepare("INSERT INTO perfiles_modulos (rol, modulo_href) VALUES (?,?)");
        foreach ($_POST['modulos'] ?? [] as $href) {
            $stmtIns->execute([$rolGuardar, $href]);
        }
        $msg = ['ok', "Perfil {$rolGuardar} actualizado."];
        $rolSeleccionado = $rolGuardar;
    }
}

$stmt = $pdo->prepare("SELECT modulo_href FROM perfiles_modulos WHERE rol = ?");
$stmt->execute([$rolSeleccionado]);
$asignados = $stmt->fetchAll(PDO::FETCH_COLUMN);

$conteos = $pdo->query("SELECT rol, COUNT(*) c FROM perfiles_modulos GROUP BY rol")->fetchAll(PDO::FETCH_KEY_PAIR);

layout_inicio('Perfiles por rol', 'Usuarios y roles', '../');
?>
<p class="small"><a href="usuarios.php">← Volver a Usuarios</a></p>
<h1><?= icon('shield','icon-lg') ?> Perfiles de módulos por rol</h1>
<p class="subtitle">Define qué módulos trae <strong>por defecto</strong> cada rol al asignarlo desde "Módulos individuales". Es una plantilla reutilizable — no reemplaza el control fino por usuario, solo te ahorra marcarlos uno por uno.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form method="get" class="toolbar">
    <select name="rol" onchange="this.form.submit()">
        <?php foreach (ROLES_DISPONIBLES as $r): if ($r === 'SUPER_ADMIN') continue; ?>
        <option value="<?= e($r) ?>" <?= $rolSeleccionado === $r ? 'selected' : '' ?>>
            Perfil <?= e($r) ?> <?= isset($conteos[$r]) ? '(' . (int)$conteos[$r] . ' módulos)' : '(sin configurar)' ?>
        </option>
        <?php endforeach; ?>
    </select>
</form>

<form method="post">
    <input type="hidden" name="rol" value="<?= e($rolSeleccionado) ?>">
    <div class="toolbar" style="margin-bottom:0;">
        <button type="button" class="btn-secondary" id="btn-marcar-todo"><?= icon('check') ?> Marcar todos</button>
        <button type="button" class="btn-secondary" id="btn-desmarcar-todo"><?= icon('x') ?> Desmarcar todos</button>
    </div>
    <?php foreach (nav_grupos() as $grupo => $def): ?>
    <div class="panel">
        <h3><?= icon($def['icon']) ?> <?= e($grupo) ?></h3>
        <div class="grid-form">
            <?php foreach ($def['items'] as $href => [$label, $ic]): ?>
            <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                <input type="checkbox" name="modulos[]" class="chk-modulo" value="<?= e($href) ?>" <?= in_array($href, $asignados, true) ? 'checked' : '' ?> style="width:18px;height:18px;">
                <?= icon($ic) ?> <?= e($label) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <button type="submit" style="position:sticky;bottom:16px;"><?= icon('check') ?> Guardar perfil <?= e($rolSeleccionado) ?></button>
</form>
<script>
document.getElementById('btn-marcar-todo')?.addEventListener('click', function () {
    document.querySelectorAll('.chk-modulo').forEach(function (c) { c.checked = true; });
});
document.getElementById('btn-desmarcar-todo')?.addEventListener('click', function () {
    document.querySelectorAll('.chk-modulo').forEach(function (c) { c.checked = false; });
});
</script>
<?php layout_fin(); ?>
