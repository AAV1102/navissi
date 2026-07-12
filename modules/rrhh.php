<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null);
        $datos = [
            'documento' => limpio($_POST['documento'] ?? null),
            'nombres' => limpio($_POST['nombres'] ?? null),
            'cargo' => limpio($_POST['cargo'] ?? null),
            'area' => limpio($_POST['area'] ?? null),
            'sede_id' => $sedeId,
            'email' => limpio($_POST['email'] ?? null),
            'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVO',
            'fecha_ingreso' => limpio($_POST['fecha_ingreso'] ?? null),
            'tipo_contrato' => limpio($_POST['tipo_contrato'] ?? null),
            'salario' => $_POST['salario'] !== '' ? (float) ($_POST['salario'] ?? 0) : null,
        ];
        if (!$datos['nombres']) {
            $msg = ['error', 'El nombre es obligatorio.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE empleados SET {$set} WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Empleado actualizado.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO empleados ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Empleado agregado.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM empleados WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Empleado eliminado.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT e.*, s.nombre AS sede_nombre FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE 1=1";
$params = [];
if ($busqueda !== '') {
    $sql .= " AND (e.nombres LIKE :b OR e.documento LIKE :b OR e.cargo LIKE :b OR e.area LIKE :b)";
    $params['b'] = "%{$busqueda}%";
}
if (alcance_area() !== null) {
    $sql .= " AND e.area = :area";
    $params['area'] = alcance_area();
}
$sql .= " ORDER BY e.nombres";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('RRHH', 'RRHH', '../');
?>
<h1><?= icon('users','icon-lg') ?> Recursos Humanos</h1>
<p class="subtitle"><?= count($empleados) ?> empleados.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar empleado' : 'Agregar empleado' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Documento</label><input type="text" name="documento" value="<?= e($editar['documento'] ?? '') ?>"></div>
            <div><label>Nombres *</label><input type="text" name="nombres" required value="<?= e($editar['nombres'] ?? '') ?>"></div>
            <div><label>Cargo</label><input type="text" name="cargo" value="<?= e($editar['cargo'] ?? '') ?>"></div>
            <div><label>Área</label><input type="text" name="area" value="<?= e($editar['area'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- seleccionar --</option>
                    <?php foreach ($sedes as $s): ?>
                    <option <?= (($editar['sede_id'] ?? null) == $s['id']) ? 'selected' : '' ?> value="<?= e($s['nombre']) ?>"><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Email</label><input type="text" name="email" value="<?= e($editar['email'] ?? '') ?>"></div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['ACTIVO','INACTIVO'] as $es): ?>
                    <option <?= ($editar['estado'] ?? 'ACTIVO') === $es ? 'selected' : '' ?>><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Fecha de ingreso</label><input type="date" name="fecha_ingreso" value="<?= e($editar['fecha_ingreso'] ?? '') ?>"></div>
            <div><label>Tipo de contrato</label>
                <select name="tipo_contrato">
                    <option value="">-- sin definir --</option>
                    <?php foreach (['TERMINO INDEFINIDO','TERMINO FIJO','OBRA O LABOR','PRESTACIÓN DE SERVICIOS','APRENDIZAJE'] as $tc): ?>
                    <option <?= ($editar['tipo_contrato'] ?? '') === $tc ? 'selected' : '' ?>><?= $tc ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Salario</label><input type="number" step="0.01" name="salario" value="<?= e($editar['salario'] ?? '') ?>"></div>
        </div>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar empleado' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="rrhh.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<form class="toolbar" method="get">
    <input type="search" name="q" placeholder="Buscar por nombre, documento, cargo, área..." value="<?= e($busqueda) ?>" style="min-width:320px">
    <button type="submit">Buscar</button>
    <?php if ($busqueda): ?><a class="btn btn-secondary" href="rrhh.php">Limpiar</a><?php endif; ?>
</form>

<table>
    <tr><th>Documento</th><th>Nombres</th><th>Cargo</th><th>Área</th><th>Sede</th><th>Email</th><th>Estado</th><th></th></tr>
    <?php foreach ($empleados as $emp): ?>
    <tr>
        <td><?= e($emp['documento']) ?></td>
        <td><a href="empleado_detalle.php?id=<?= (int)$emp['id'] ?>"><strong><?= e($emp['nombres']) ?></strong></a></td>
        <td><?= e($emp['cargo']) ?></td>
        <td><?= e($emp['area']) ?></td>
        <td><?= e($emp['sede_nombre']) ?></td>
        <td><?= e($emp['email']) ?></td>
        <td><span class="badge <?= $emp['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($emp['estado']) ?></span></td>
        <td>
            <a href="?editar=<?= (int)$emp['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php layout_fin(); ?>
