<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $datos = [
            'nombre' => limpio($_POST['nombre'] ?? null),
            'ciudad' => limpio($_POST['ciudad'] ?? null),
            'direccion' => limpio($_POST['direccion'] ?? null),
            'proveedor_internet' => limpio($_POST['proveedor_internet'] ?? null),
            'ip_red' => limpio($_POST['ip_red'] ?? null),
            'ip_asignada' => limpio($_POST['ip_asignada'] ?? null),
            'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVO',
            'zona' => limpio($_POST['zona'] ?? null),
            'coordinadora' => limpio($_POST['coordinadora'] ?? null),
            'coordinadora_celular' => limpio($_POST['coordinadora_celular'] ?? null),
            'administradora' => limpio($_POST['administradora'] ?? null),
            'administradora_celular' => limpio($_POST['administradora_celular'] ?? null),
            'segunda_encargada' => limpio($_POST['segunda_encargada'] ?? null),
            'segunda_encargada_celular' => limpio($_POST['segunda_encargada_celular'] ?? null),
            'correo_corporativo' => limpio($_POST['correo_corporativo'] ?? null),
        ];
        if (!$datos['nombre']) {
            $msg = ['error', 'El nombre de la sede es obligatorio.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE sedes SET {$set} WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Sede actualizada.'];
            } else {
                try {
                    $cols = implode(', ', array_keys($datos));
                    $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                    $pdo->prepare("INSERT INTO sedes ({$cols}) VALUES ({$ph})")->execute($datos);
                    $msg = ['ok', 'Sede agregada.'];
                } catch (PDOException $e) {
                    $msg = ['error', 'Ya existe una sede con ese nombre.'];
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM sedes WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Sede eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM sedes WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$sedes = $pdo->query("
    SELECT s.*, (SELECT COUNT(*) FROM inventario i WHERE i.sede_id = s.id) AS equipos,
           (SELECT COUNT(*) FROM credenciales c WHERE c.sede_id = s.id) AS credenciales,
           (SELECT COUNT(*) FROM empleados e WHERE e.sede_id = s.id) AS empleados
    FROM sedes s ORDER BY s.nombre
")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Sedes', 'Sedes', '../');
?>
<h1><?= icon('building','icon-lg') ?> Sedes / Tiendas</h1>
<p class="subtitle"><?= count($sedes) ?> sedes registradas.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar sede' : 'Agregar sede' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Ciudad</label><input type="text" name="ciudad" value="<?= e($editar['ciudad'] ?? '') ?>"></div>
            <div><label>Zona</label><input type="text" name="zona" value="<?= e($editar['zona'] ?? '') ?>" placeholder="Medellín, Bogotá y Rionegro..."></div>
            <div><label>Dirección</label><input type="text" name="direccion" value="<?= e($editar['direccion'] ?? '') ?>"></div>
            <div><label>Correo corporativo</label><input type="text" name="correo_corporativo" value="<?= e($editar['correo_corporativo'] ?? '') ?>"></div>
            <div><label>Proveedor internet</label><input type="text" name="proveedor_internet" value="<?= e($editar['proveedor_internet'] ?? '') ?>"></div>
            <div><label>IP de red</label><input type="text" name="ip_red" value="<?= e($editar['ip_red'] ?? '') ?>"></div>
            <div><label>IP asignada</label><input type="text" name="ip_asignada" value="<?= e($editar['ip_asignada'] ?? '') ?>"></div>
            <div><label>Coordinadora</label><input type="text" name="coordinadora" value="<?= e($editar['coordinadora'] ?? '') ?>"></div>
            <div><label>Celular coordinadora</label><input type="text" name="coordinadora_celular" value="<?= e($editar['coordinadora_celular'] ?? '') ?>"></div>
            <div><label>Administradora / encargada</label><input type="text" name="administradora" value="<?= e($editar['administradora'] ?? '') ?>"></div>
            <div><label>Celular administradora</label><input type="text" name="administradora_celular" value="<?= e($editar['administradora_celular'] ?? '') ?>"></div>
            <div><label>Segunda encargada</label><input type="text" name="segunda_encargada" value="<?= e($editar['segunda_encargada'] ?? '') ?>"></div>
            <div><label>Celular segunda encargada</label><input type="text" name="segunda_encargada_celular" value="<?= e($editar['segunda_encargada_celular'] ?? '') ?>"></div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['ACTIVO','INACTIVO'] as $es): ?>
                    <option <?= ($editar['estado'] ?? 'ACTIVO') === $es ? 'selected' : '' ?>><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar sede' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="sedes.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<div class="tabla-toolbar">
    <label class="small chk-todos"><input type="checkbox" id="chk-todas-sedes"> Seleccionar todo</label>
    <span class="tabla-toolbar-acciones small">
        <button type="button" class="link-btn" disabled><?= icon('trash') ?> Eliminar</button>
    </span>
    <span class="small" style="margin-left:auto;">Mostrando <?= count($sedes) ?> de <?= count($sedes) ?> sedes</span>
</div>
<table class="tabla-tickets">
    <thead>
    <tr>
        <th style="width:30px;"></th>
        <th>Nombre</th><th>Ciudad</th><th>Administradora / celular</th><th>Equipos</th><th>Empleados</th><th>Credenciales</th><th>Estado</th><th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($sedes as $s):
        $inicialesSede = mb_strtoupper(mb_substr($s['nombre'], 0, 1));
    ?>
    <tr onclick="window.location='sede_detalle.php?id=<?= (int)$s['id'] ?>'" style="cursor:pointer;">
        <td onclick="event.stopPropagation()"><input type="checkbox" class="chk-sede"></td>
        <td>
            <div style="display:flex; gap:10px; align-items:center;">
                <span class="avatar-sq"><?= e($inicialesSede) ?></span>
                <strong><?= e($s['nombre']) ?></strong>
            </div>
        </td>
        <td><?= e($s['ciudad']) ?: '—' ?></td>
        <td><?= e($s['administradora']) ?: '—' ?><?php if ($s['administradora_celular']): ?><br><span class="small"><?= icon('chat') ?> <?= e($s['administradora_celular']) ?></span><?php endif; ?></td>
        <td><?= (int)$s['equipos'] ?></td>
        <td><?= (int)$s['empleados'] ?></td>
        <td><?= (int)$s['credenciales'] ?></td>
        <td><span class="badge <?= $s['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($s['estado']) ?></span></td>
        <td onclick="event.stopPropagation()">
            <a href="?editar=<?= (int)$s['id'] ?>" class="small">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar esta sede?');" style="display:inline;">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="link-btn" style="color:var(--err-fg);"><?= icon('trash') ?></button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$sedes): ?><tr><td colspan="9" style="text-align:center;padding:40px;" class="small">No hay sedes registradas.</td></tr><?php endif; ?>
    </tbody>
</table>
<script>
document.getElementById('chk-todas-sedes')?.addEventListener('change', function () {
    document.querySelectorAll('.chk-sede').forEach(c => c.checked = this.checked);
});
</script>
<?php layout_fin(); ?>
