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

// Plantillas de perfil por rol, para poder "cargar" de un solo clic el set de
// módulos típico de un rol (ej. EMPLEADO) y aplicarlo a este usuario individual.
$perfilesPorRol = [];
foreach ($pdo->query("SELECT rol, modulo_href FROM perfiles_modulos")->fetchAll(PDO::FETCH_ASSOC) as $fila) {
    $perfilesPorRol[$fila['rol']][] = $fila['modulo_href'];
}

layout_inicio('Módulos individuales', 'Usuarios y roles', '../');
?>
<p class="small"><a href="usuarios.php">← Volver a Usuarios</a></p>
<h1><?= icon('users','icon-lg') ?> Módulos individuales de <?= e($usuario['nombre']) ?></h1>
<p class="subtitle">Además de lo que su rol (<strong><?= e($usuario['rol']) ?></strong>) ya le permite ver, marca aquí los módulos puntuales que quieras darle a esta persona específicamente — por ejemplo, dar "Contratos" a alguien de RRHH sin volverlo TI completo.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('zap') ?> Acciones rápidas</h3>
    <div class="toolbar" style="margin-bottom:0;">
        <button type="button" class="btn-secondary" id="btn-marcar-todo"><?= icon('check') ?> Marcar todos los módulos</button>
        <button type="button" class="btn-secondary" id="btn-desmarcar-todo"><?= icon('x') ?> Desmarcar todos</button>
        <?php if ($perfilesPorRol): ?>
        <select id="cargar-perfil">
            <option value="">-- cargar desde un perfil... --</option>
            <?php foreach (array_keys($perfilesPorRol) as $rolPerfil): ?>
            <option value="<?= e($rolPerfil) ?>">Perfil <?= e($rolPerfil) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn-secondary" id="btn-cargar-perfil"><?= icon('upload') ?> Aplicar perfil</button>
        <?php else: ?>
        <span class="small">No hay perfiles predefinidos todavía — configúralos en <a href="perfiles_modulos.php">Perfiles por rol</a>.</span>
        <?php endif; ?>
    </div>
</div>

<form method="post">
    <?php foreach (nav_grupos() as $grupo => $def): ?>
    <div class="panel">
        <h3>
            <?= icon($def['icon']) ?> <?= e($grupo) ?>
            <label class="small" style="float:right;font-weight:400;display:flex;align-items:center;gap:6px;">
                <input type="checkbox" class="chk-grupo-todo" data-grupo="<?= e($grupo) ?>" style="width:16px;height:16px;"> Seleccionar todo el grupo
            </label>
        </h3>
        <div class="grid-form">
            <?php foreach ($def['items'] as $href => [$label, $ic]): ?>
            <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                <input type="checkbox" name="modulos[]" class="chk-modulo" data-grupo="<?= e($grupo) ?>" value="<?= e($href) ?>" <?= in_array($href, $asignados, true) ? 'checked' : '' ?> style="width:18px;height:18px;">
                <?= icon($ic) ?> <?= e($label) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <button type="submit" style="position:sticky;bottom:16px;"><?= icon('check') ?> Guardar módulos individuales</button>
</form>

<script>
document.getElementById('btn-marcar-todo')?.addEventListener('click', function () {
    document.querySelectorAll('.chk-modulo').forEach(function (c) { c.checked = true; });
});
document.getElementById('btn-desmarcar-todo')?.addEventListener('click', function () {
    document.querySelectorAll('.chk-modulo').forEach(function (c) { c.checked = false; });
});
document.querySelectorAll('.chk-grupo-todo').forEach(function (chkGrupo) {
    chkGrupo.addEventListener('change', function () {
        document.querySelectorAll('.chk-modulo[data-grupo="' + this.dataset.grupo + '"]').forEach(function (c) {
            c.checked = chkGrupo.checked;
        });
    });
});
var perfiles = <?= json_encode($perfilesPorRol, JSON_UNESCAPED_UNICODE) ?>;
document.getElementById('btn-cargar-perfil')?.addEventListener('click', function () {
    var rol = document.getElementById('cargar-perfil').value;
    if (!rol || !perfiles[rol]) return;
    var permitidos = perfiles[rol];
    document.querySelectorAll('.chk-modulo').forEach(function (c) {
        c.checked = permitidos.indexOf(c.value) !== -1;
    });
});
</script>
<?php layout_fin(); ?>
