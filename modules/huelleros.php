<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI', 'RRHH'])) {
    layout_inicio('Huelleros / Biométricos', 'Huelleros', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar biométricos.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if (!$nombre) {
            $msg = ['error', 'El nombre es obligatorio.'];
        } else {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $datos = ['nombre' => $nombre, 'marca' => limpio($_POST['marca'] ?? null), 'modelo' => limpio($_POST['modelo'] ?? null),
                'ip_red' => limpio($_POST['ip_red'] ?? null), 'sede_id' => $sedeId,
                'capacidad_huellas' => (int) ($_POST['capacidad_huellas'] ?? 0) ?: null,
                'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVO', 'observaciones' => limpio($_POST['observaciones'] ?? null)];
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $datos['id'] = $id;
                $pdo->prepare("UPDATE biometricos SET {$set} WHERE id = :id")->execute($datos);
                $msg = ['ok', 'Actualizado.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO biometricos ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Biométrico agregado.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM biometricos WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM biometricos WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
$biometricos = $pdo->query("SELECT b.*, s.nombre AS sede_nombre FROM biometricos b LEFT JOIN sedes s ON b.sede_id = s.id ORDER BY b.nombre")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Huelleros / Biométricos', 'Huelleros', '../');
?>
<h1><?= icon('briefcase','icon-lg') ?> Huelleros / Biométricos</h1>
<p class="subtitle">Inventario de dispositivos biométricos de asistencia por sede — complementa Control de Asistencia.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar' : 'Agregar' ?> dispositivo</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Marca</label><input type="text" name="marca" value="<?= e($editar['marca'] ?? '') ?>"></div>
            <div><label>Modelo</label><input type="text" name="modelo" value="<?= e($editar['modelo'] ?? '') ?>"></div>
            <div><label>IP de red</label><input type="text" name="ip_red" value="<?= e($editar['ip_red'] ?? '') ?>"></div>
            <div><label>Capacidad de huellas</label><input type="number" name="capacidad_huellas" value="<?= e($editar['capacidad_huellas'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option <?= (($editar['sede_id'] ?? null)==$s['id'])?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['ACTIVO','INACTIVO','EN MANTENIMIENTO'] as $es): ?><option <?= ($editar['estado'] ?? 'ACTIVO')===$es?'selected':'' ?>><?= $es ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;"><?= e($editar['observaciones'] ?? '') ?></textarea>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="huelleros.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Nombre</th><th>Marca/Modelo</th><th>IP</th><th>Sede</th><th>Estado</th><th></th></tr>
    <?php foreach ($biometricos as $b): ?>
    <tr>
        <td><?= e($b['nombre']) ?></td>
        <td><?= e($b['marca']) ?> <?= e($b['modelo']) ?></td>
        <td class="small"><?= e($b['ip_red']) ?: '—' ?></td>
        <td><?= e($b['sede_nombre']) ?: '—' ?></td>
        <td><span class="badge <?= $b['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($b['estado']) ?></span></td>
        <td>
            <a href="?editar=<?= (int)$b['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$biometricos): ?><tr><td colspan="6" class="small">Sin dispositivos registrados.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
