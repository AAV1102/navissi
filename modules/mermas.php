<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'COORDINADOR', 'TI'])) {
    layout_inicio('Mermas', 'Mermas e Inventario Perdido', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar mermas.</div>';
    layout_fin();
    exit;
}

$u = usuario_actual();
$puedeAprobar = tiene_rol(['ADMIN', 'GERENCIA', 'CEO']);

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
                'sede_id' => $sedeId,
                'producto' => $producto,
                'referencia' => limpio($_POST['referencia'] ?? null),
                'cantidad' => (float) ($_POST['cantidad'] ?? 1),
                'motivo' => $motivo,
                'valor_estimado' => (float) ($_POST['valor_estimado'] ?? 0) ?: null,
                'observaciones' => limpio($_POST['observaciones'] ?? null),
            ];
            if ($datos['cantidad'] <= 0) {
                $msg = ['error', 'La cantidad debe ser mayor que cero.'];
            } elseif ($datos['valor_estimado'] !== null && $datos['valor_estimado'] < 0) {
                $msg = ['error', 'El valor estimado no puede ser negativo.'];
            }
            $id = (int) ($_POST['id'] ?? 0);
            if ($msg) { /* validación ya reportada */ }
            elseif ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $datos['id'] = $id;
                $pdo->prepare("UPDATE mermas_inventario SET {$set} WHERE id = :id")->execute($datos);
                $msg = ['ok', 'Actualizada.'];
            } else {
                $datos['creado_por'] = $u['nombre'] ?? null;
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO mermas_inventario ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Merma reportada, queda pendiente de aprobación.'];
            }
        }
    } elseif ($accion === 'aprobar' && $puedeAprobar) {
        $pdo->prepare("UPDATE mermas_inventario SET estado = 'APROBADA', aprobado_por = ?, aprobado_en = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$u['nombre'] ?? null, (int) $_POST['id']]);
        $msg = ['ok', 'Merma aprobada.'];
    } elseif ($accion === 'rechazar' && $puedeAprobar) {
        $pdo->prepare("UPDATE mermas_inventario SET estado = 'RECHAZADA', aprobado_por = ?, aprobado_en = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$u['nombre'] ?? null, (int) $_POST['id']]);
        $msg = ['ok', 'Merma rechazada.'];
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM mermas_inventario WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM mermas_inventario WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
$mermas = $pdo->query("SELECT m.*, s.nombre AS sede_nombre FROM mermas_inventario m LEFT JOIN sedes s ON m.sede_id = s.id ORDER BY m.creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$valorTotal = 0;
foreach ($mermas as $m) { if ($m['estado'] === 'APROBADA') $valorTotal += (float) $m['valor_estimado']; }

layout_inicio('Mermas', 'Mermas e Inventario Perdido', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Mermas e Inventario Perdido</h1>
<p class="subtitle">Justificación de diferencias de stock por pérdida, daño o robo — $<?= number_format($valorTotal, 0, ',', '.') ?> en mermas aprobadas.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar' : 'Reportar' ?> merma</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Producto *</label><input type="text" name="producto" required value="<?= e($editar['producto'] ?? '') ?>"></div>
            <div><label>Referencia</label><input type="text" name="referencia" value="<?= e($editar['referencia'] ?? '') ?>"></div>
            <div><label>Cantidad</label><input type="number" step="0.01" name="cantidad" value="<?= e($editar['cantidad'] ?? '1') ?>"></div>
            <div><label>Valor estimado</label><input type="number" step="0.01" name="valor_estimado" value="<?= e($editar['valor_estimado'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option <?= (($editar['sede_id'] ?? null)==$s['id'])?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:1/-1;"><label>Motivo *</label><input type="text" name="motivo" required placeholder="Daño en bodega, robo, extravío..." value="<?= e($editar['motivo'] ?? '') ?>"></div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Observaciones"><?= e($editar['observaciones'] ?? '') ?></textarea>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Reportar' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="mermas.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Producto</th><th>Cantidad</th><th>Motivo</th><th>Valor est.</th><th>Sede</th><th>Estado</th><th></th></tr>
    <?php foreach ($mermas as $m): ?>
    <tr>
        <td><?= e($m['producto']) ?> <?= $m['referencia'] ? '· ' . e($m['referencia']) : '' ?></td>
        <td><?= e($m['cantidad']) ?></td>
        <td class="small"><?= e($m['motivo']) ?></td>
        <td><?= $m['valor_estimado'] ? '$' . number_format((float)$m['valor_estimado'], 0, ',', '.') : '—' ?></td>
        <td><?= e($m['sede_nombre']) ?: '—' ?></td>
        <td><span class="badge <?= $m['estado']==='APROBADA'?'badge-activo':'badge-otro' ?>"><?= e($m['estado']) ?></span>
            <?php if ($m['aprobado_por']): ?><div class="small"><?= e($m['aprobado_por']) ?></div><?php endif; ?>
        </td>
        <td>
            <?php if ($puedeAprobar && $m['estado'] === 'REPORTADA'): ?>
            <form class="inline" method="post"><input type="hidden" name="accion" value="aprobar"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button type="submit" style="padding:4px 10px;font-size:12px;">Aprobar</button></form>
            <form class="inline" method="post"><input type="hidden" name="accion" value="rechazar"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button type="submit" class="btn-secondary" style="padding:4px 10px;font-size:12px;">Rechazar</button></form>
            <?php endif; ?>
            <a href="?editar=<?= (int)$m['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$mermas): ?><tr><td colspan="7" class="small">Sin mermas reportadas.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
