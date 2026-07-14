<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$u = usuario_actual();

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'COORDINADOR'])) {
    layout_inicio('Comisiones', 'Comisiones de Venta', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar comisiones.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'generar') {
        // Genera comisiones pendientes para oportunidades GANADO que aun no tengan una fila de comision.
        $pendientes = $pdo->query("SELECT o.* FROM oportunidades o
            WHERE o.etapa = 'GANADO' AND NOT EXISTS (SELECT 1 FROM comisiones_venta c WHERE c.oportunidad_id = o.id)")->fetchAll(PDO::FETCH_ASSOC);
        $porcentajeDefecto = (float) ($_POST['porcentaje_defecto'] ?? 3);
        $generadas = 0;
        foreach ($pendientes as $o) {
            $valor = (float) ($o['valor'] ?? 0);
            $comision = round($valor * $porcentajeDefecto / 100, 2);
            $pdo->prepare("INSERT INTO comisiones_venta (oportunidad_id, vendedor_documento, vendedor_nombre, valor_venta, porcentaje, valor_comision, periodo)
                VALUES (?,?,?,?,?,?,?)")
                ->execute([$o['id'], $o['responsable_documento'], $o['responsable_nombre'] ?: 'Sin asignar', $valor, $porcentajeDefecto, $comision, date('Y-m')]);
            $generadas++;
        }
        $msg = ['ok', "{$generadas} comisión(es) generada(s) a partir de negocios ganados."];
    } elseif ($accion === 'pagar') {
        $pdo->prepare("UPDATE comisiones_venta SET estado = 'PAGADA', pagado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Comisión marcada como pagada.'];
    } elseif ($accion === 'editar_porcentaje') {
        $id = (int) $_POST['id'];
        $porcentaje = (float) $_POST['porcentaje'];
        $stmt = $pdo->prepare("SELECT valor_venta FROM comisiones_venta WHERE id = ?");
        $stmt->execute([$id]);
        $valorVenta = (float) $stmt->fetchColumn();
        $pdo->prepare("UPDATE comisiones_venta SET porcentaje = ?, valor_comision = ? WHERE id = ?")
            ->execute([$porcentaje, round($valorVenta * $porcentaje / 100, 2), $id]);
        $msg = ['ok', 'Porcentaje actualizado.'];
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM comisiones_venta WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$comisiones = $pdo->query("SELECT c.*, o.titulo AS oportunidad_titulo FROM comisiones_venta c LEFT JOIN oportunidades o ON c.oportunidad_id = o.id ORDER BY c.creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalPendiente = 0; $totalPagado = 0;
foreach ($comisiones as $c) {
    if ($c['estado'] === 'PAGADA') $totalPagado += (float) $c['valor_comision'];
    else $totalPendiente += (float) $c['valor_comision'];
}
$pendientesPorGenerar = (int) $pdo->query("SELECT COUNT(*) FROM oportunidades o
    WHERE o.etapa = 'GANADO' AND NOT EXISTS (SELECT 1 FROM comisiones_venta c WHERE c.oportunidad_id = o.id)")->fetchColumn();

layout_inicio('Comisiones', 'Comisiones de Venta', '../');
?>
<h1><?= icon('dollar','icon-lg') ?> Comisiones de Venta</h1>
<p class="subtitle">Comisión por asesor calculada sobre negocios ganados en el Pipeline de Ventas.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="dash-hero"><div class="num"><?= number_format($totalPendiente,0,',','.') ?></div><div class="label">Pendiente de pago ($)</div></div>
    <div class="card"><div class="num"><?= number_format($totalPagado,0,',','.') ?></div><div class="label">Pagado este periodo ($)</div></div>
    <div class="card"><div class="num"><?= $pendientesPorGenerar ?></div><div class="label">Negocios ganados sin comisión generada</div></div>
</div>

<div class="panel">
    <h3><?= icon('zap') ?> Generar comisiones de negocios ganados</h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="generar">
        <label>% por defecto <input type="number" step="0.1" name="porcentaje_defecto" value="3" style="width:80px;"></label>
        <button type="submit">Generar (<?= $pendientesPorGenerar ?> pendientes)</button>
    </form>
</div>

<table>
    <tr><th>Vendedor</th><th>Negocio</th><th>Valor venta</th><th>%</th><th>Comisión</th><th>Periodo</th><th>Estado</th><th></th></tr>
    <?php foreach ($comisiones as $c): ?>
    <tr>
        <td><?= e($c['vendedor_nombre']) ?></td>
        <td class="small"><?= e($c['oportunidad_titulo']) ?: '—' ?></td>
        <td>$<?= number_format((float)$c['valor_venta'],0,',','.') ?></td>
        <td>
            <form class="inline" method="post" onsubmit="this.querySelector('[name=porcentaje]').disabled=false;">
                <input type="hidden" name="accion" value="editar_porcentaje"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input type="number" step="0.1" name="porcentaje" value="<?= e($c['porcentaje']) ?>" style="width:60px;" onchange="this.form.requestSubmit()" <?= $c['estado']==='PAGADA'?'disabled':'' ?>>
            </form>
        </td>
        <td>$<?= number_format((float)$c['valor_comision'],0,',','.') ?></td>
        <td class="small"><?= e($c['periodo']) ?></td>
        <td><span class="badge <?= $c['estado']==='PAGADA'?'badge-activo':'badge-otro' ?>"><?= e($c['estado']) ?></span></td>
        <td>
            <?php if ($c['estado'] !== 'PAGADA'): ?>
            <form class="inline" method="post"><input type="hidden" name="accion" value="pagar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button type="submit" style="padding:4px 10px;font-size:12px;">Marcar pagada</button></form>
            <?php endif; ?>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$comisiones): ?><tr><td colspan="8" class="small">Sin comisiones generadas todavía.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
