<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'COORDINADOR', 'TI'])) {
    layout_inicio('Devoluciones', 'Devoluciones y Garantías', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar devoluciones.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $producto = limpio($_POST['producto'] ?? null);
        $motivo = limpio($_POST['motivo'] ?? null);
        if (!$producto || !$motivo) {
            $msg = ['error', 'Producto y motivo son obligatorios.'];
        } else {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $datos = [
                'cliente_nombre' => limpio($_POST['cliente_nombre'] ?? null),
                'sede_id' => $sedeId,
                'producto' => $producto,
                'referencia' => limpio($_POST['referencia'] ?? null),
                'talla' => limpio($_POST['talla'] ?? null),
                'motivo' => $motivo,
                'tipo_solucion' => limpio($_POST['tipo_solucion'] ?? null) ?: 'CAMBIO',
                'valor' => (float) ($_POST['valor'] ?? 0) ?: null,
                'estado' => limpio($_POST['estado'] ?? null) ?: 'SOLICITADA',
                'observaciones' => limpio($_POST['observaciones'] ?? null),
            ];
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $datos['id'] = $id;
                if (($datos['estado'] ?? null) === 'RESUELTA') {
                    $pdo->prepare("UPDATE devoluciones_producto SET {$set}, resuelto_en = CURRENT_TIMESTAMP WHERE id = :id")->execute($datos);
                } else {
                    $pdo->prepare("UPDATE devoluciones_producto SET {$set} WHERE id = :id")->execute($datos);
                }
                $msg = ['ok', 'Actualizada.'];
            } else {
                $datos['creado_por'] = usuario_actual()['nombre'] ?? null;
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO devoluciones_producto ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Devolución registrada.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM devoluciones_producto WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM devoluciones_producto WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
$devoluciones = $pdo->query("SELECT d.*, s.nombre AS sede_nombre FROM devoluciones_producto d LEFT JOIN sedes s ON d.sede_id = s.id ORDER BY d.creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$totalPendientes = 0;
foreach ($devoluciones as $d) { if ($d['estado'] !== 'RESUELTA') $totalPendientes++; }

layout_inicio('Devoluciones', 'Devoluciones y Garantías', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Devoluciones y Garantías</h1>
<p class="subtitle">Cambios, reembolsos y garantías de producto vendido al cliente final — <?= $totalPendientes ?> pendientes de <?= count($devoluciones) ?> totales.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar' : 'Registrar' ?> devolución</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Cliente</label><input type="text" name="cliente_nombre" value="<?= e($editar['cliente_nombre'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option <?= (($editar['sede_id'] ?? null)==$s['id'])?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Producto *</label><input type="text" name="producto" required value="<?= e($editar['producto'] ?? '') ?>"></div>
            <div><label>Referencia</label><input type="text" name="referencia" value="<?= e($editar['referencia'] ?? '') ?>"></div>
            <div><label>Talla</label><input type="text" name="talla" value="<?= e($editar['talla'] ?? '') ?>"></div>
            <div><label>Valor</label><input type="number" step="0.01" name="valor" value="<?= e($editar['valor'] ?? '') ?>"></div>
            <div><label>Tipo de solución</label>
                <select name="tipo_solucion">
                    <?php foreach (['CAMBIO','REEMBOLSO','NOTA_CREDITO','REPARACION'] as $t): ?><option <?= ($editar['tipo_solucion'] ?? 'CAMBIO')===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['SOLICITADA','EN_REVISION','APROBADA','RECHAZADA','RESUELTA'] as $es): ?><option <?= ($editar['estado'] ?? 'SOLICITADA')===$es?'selected':'' ?>><?= $es ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:1/-1;"><label>Motivo *</label><input type="text" name="motivo" required value="<?= e($editar['motivo'] ?? '') ?>"></div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Observaciones"><?= e($editar['observaciones'] ?? '') ?></textarea>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Registrar' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="devoluciones.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Cliente</th><th>Producto</th><th>Motivo</th><th>Solución</th><th>Valor</th><th>Sede</th><th>Estado</th><th></th></tr>
    <?php foreach ($devoluciones as $d): ?>
    <tr>
        <td><?= e($d['cliente_nombre']) ?: '—' ?></td>
        <td><?= e($d['producto']) ?> <?= $d['referencia'] ? '· ' . e($d['referencia']) : '' ?> <?= $d['talla'] ? '· T.' . e($d['talla']) : '' ?></td>
        <td class="small"><?= e($d['motivo']) ?></td>
        <td><?= e($d['tipo_solucion']) ?></td>
        <td><?= $d['valor'] ? '$' . number_format((float)$d['valor'], 0, ',', '.') : '—' ?></td>
        <td><?= e($d['sede_nombre']) ?: '—' ?></td>
        <td><span class="badge <?= $d['estado']==='RESUELTA'?'badge-activo':'badge-otro' ?>"><?= e($d['estado']) ?></span></td>
        <td>
            <a href="?editar=<?= (int)$d['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$devoluciones): ?><tr><td colspan="8" class="small">Sin devoluciones registradas.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
