<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_departamento') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if ($nombre) {
            try {
                $pdo->prepare("INSERT INTO departamentos (nombre, responsable) VALUES (?,?)")->execute([$nombre, limpio($_POST['responsable'] ?? null)]);
                $msg = ['ok', 'Departamento creado.'];
            } catch (PDOException $e) { $msg = ['error', 'Ya existe ese departamento.']; }
        }
    }
    if ($accion === 'crear_cargo') {
        $nombre = limpio($_POST['nombre_cargo'] ?? null);
        if ($nombre) {
            $pdo->prepare("INSERT INTO cargos (nombre, departamento_id) VALUES (?,?)")->execute([$nombre, (int) $_POST['departamento_id']]);
            $msg = ['ok', 'Cargo creado.'];
        }
    }
    if ($accion === 'eliminar_departamento') {
        $pdo->prepare("DELETE FROM departamentos WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
    if ($accion === 'eliminar_cargo') {
        $pdo->prepare("DELETE FROM cargos WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$departamentos = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM empleados e WHERE e.area = d.nombre) AS n_empleados FROM departamentos d ORDER BY d.nombre")->fetchAll(PDO::FETCH_ASSOC);
$cargos = $pdo->query("SELECT c.*, d.nombre AS departamento_nombre FROM cargos c LEFT JOIN departamentos d ON c.departamento_id = d.id ORDER BY d.nombre, c.nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Departamentos y Cargos', 'Departamentos y Cargos', '../');
?>
<h1><?= icon('briefcase','icon-lg') ?> Departamentos y Cargos</h1>
<p class="subtitle">Catálogo organizacional, con conteo real de empleados por área (cruzado con RRHH).</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Nuevo departamento</h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="crear_departamento">
        <input type="text" name="nombre" placeholder="Nombre" required>
        <input type="text" name="responsable" placeholder="Responsable">
        <button type="submit">Crear</button>
    </form>
</div>

<table>
    <tr><th>Departamento</th><th>Responsable</th><th>Empleados (RRHH)</th><th></th></tr>
    <?php foreach ($departamentos as $d): ?>
    <tr>
        <td><?= e($d['nombre']) ?></td><td><?= e($d['responsable']) ?></td><td><?= (int)$d['n_empleados'] ?></td>
        <td><form method="post" class="inline" onsubmit="return confirm('¿Eliminar?');"><input type="hidden" name="accion" value="eliminar_departamento"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button type="submit" class="btn-danger" style="padding:4px 8px;font-size:11px;">Eliminar</button></form></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$departamentos): ?><tr><td colspan="4" class="small">Sin departamentos todavía.</td></tr><?php endif; ?>
</table>

<div class="panel" style="margin-top:20px;">
    <h3>Nuevo cargo</h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="crear_cargo">
        <input type="text" name="nombre_cargo" placeholder="Nombre del cargo" required>
        <select name="departamento_id">
            <option value="">-- departamento --</option>
            <?php foreach ($departamentos as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['nombre']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit">Crear</button>
    </form>
</div>

<table>
    <tr><th>Cargo</th><th>Departamento</th><th></th></tr>
    <?php foreach ($cargos as $c): ?>
    <tr>
        <td><?= e($c['nombre']) ?></td><td><?= e($c['departamento_nombre']) ?></td>
        <td><form method="post" class="inline" onsubmit="return confirm('¿Eliminar?');"><input type="hidden" name="accion" value="eliminar_cargo"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button type="submit" class="btn-danger" style="padding:4px 8px;font-size:11px;">Eliminar</button></form></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$cargos): ?><tr><td colspan="3" class="small">Sin cargos todavía.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
