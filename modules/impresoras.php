<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Impresoras', 'Impresoras', '../');
    echo '<div class="msg-error">Solo TI puede gestionar impresoras.</div>';
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
            $datos = ['nombre' => $nombre, 'sede_id' => $sedeId, 'ubicacion' => limpio($_POST['ubicacion'] ?? null),
                'area' => limpio($_POST['area'] ?? null), 'marca' => limpio($_POST['marca'] ?? null),
                'modelo' => limpio($_POST['modelo'] ?? null), 'tipo' => limpio($_POST['tipo'] ?? null),
                'ip_red' => limpio($_POST['ip_red'] ?? null), 'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVA',
                'observaciones' => limpio($_POST['observaciones'] ?? null)];
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $datos['id'] = $id;
                $pdo->prepare("UPDATE impresoras SET {$set} WHERE id = :id")->execute($datos);
                $msg = ['ok', 'Actualizada.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO impresoras ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Impresora agregada.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM impresoras WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM impresoras WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
$impresoras = $pdo->query("SELECT i.*, s.nombre AS sede_nombre FROM impresoras i LEFT JOIN sedes s ON i.sede_id = s.id ORDER BY i.nombre")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Impresoras', 'Impresoras', '../');
?>
<h1><?= icon('file','icon-lg') ?> Impresoras</h1>
<p class="subtitle">Inventario de impresoras por sede, con toner/mantenimiento en observaciones.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar' : 'Agregar' ?> impresora</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option <?= (($editar['sede_id'] ?? null)==$s['id'])?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Ubicación</label><input type="text" name="ubicacion" value="<?= e($editar['ubicacion'] ?? '') ?>" placeholder="Ej. Recepción, Bodega"></div>
            <div><label>Área</label><input type="text" name="area" value="<?= e($editar['area'] ?? '') ?>"></div>
            <div><label>Marca</label><input type="text" name="marca" value="<?= e($editar['marca'] ?? '') ?>"></div>
            <div><label>Modelo</label><input type="text" name="modelo" value="<?= e($editar['modelo'] ?? '') ?>"></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach (['LASER','TINTA','MULTIFUNCIONAL','TERMICA','OTRO'] as $t): ?><option <?= ($editar['tipo'] ?? '')===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>IP de red</label><input type="text" name="ip_red" value="<?= e($editar['ip_red'] ?? '') ?>"></div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['ACTIVA','EN MANTENIMIENTO','DAÑADA','DADA DE BAJA'] as $es): ?><option <?= ($editar['estado'] ?? 'ACTIVA')===$es?'selected':'' ?>><?= $es ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Toner, mantenimiento, notas"><?= e($editar['observaciones'] ?? '') ?></textarea>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="impresoras.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Nombre</th><th>Sede</th><th>Ubicación</th><th>Marca/Modelo</th><th>IP</th><th>Estado</th><th></th></tr>
    <?php foreach ($impresoras as $i): ?>
    <tr>
        <td><?= e($i['nombre']) ?></td>
        <td><?= e($i['sede_nombre']) ?: '—' ?></td>
        <td><?= e($i['ubicacion']) ?: '—' ?></td>
        <td><?= e($i['marca']) ?> <?= e($i['modelo']) ?></td>
        <td class="small"><?= e($i['ip_red']) ?: '—' ?></td>
        <td><span class="badge <?= $i['estado']==='ACTIVA'?'badge-activo':'badge-otro' ?>"><?= e($i['estado']) ?></span></td>
        <td>
            <a href="?editar=<?= (int)$i['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$impresoras): ?><tr><td colspan="7" class="small">Sin impresoras registradas.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
