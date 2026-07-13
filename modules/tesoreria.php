<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO'])) {
    layout_inicio('Tesorería', 'Tesorería', '../');
    echo '<div class="msg-error">No tienes permiso para ver Tesorería.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    $concepto = limpio($_POST['concepto'] ?? null);
    $monto = (float) ($_POST['monto'] ?? 0);
    if ($concepto && $monto > 0) {
        $pdo->prepare("INSERT INTO movimientos_tesoreria (tipo, concepto, monto, cuenta, fecha, responsable, observaciones) VALUES (?,?,?,?,?,?,?)")
            ->execute([limpio($_POST['tipo'] ?? null) ?: 'INGRESO', $concepto, $monto, limpio($_POST['cuenta'] ?? null),
                limpio($_POST['fecha'] ?? null) ?: date('Y-m-d'), $u['nombre'], limpio($_POST['observaciones'] ?? null)]);
        $msg = ['ok', 'Movimiento registrado.'];
    } else {
        $msg = ['error', 'Concepto y monto (mayor a 0) son obligatorios.'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $pdo->prepare("DELETE FROM movimientos_tesoreria WHERE id = ?")->execute([(int) $_POST['id']]);
    $msg = ['ok', 'Eliminado.'];
}

$desde = trim($_GET['desde'] ?? date('Y-m-01'));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
$stmt = $pdo->prepare("SELECT * FROM movimientos_tesoreria WHERE fecha BETWEEN ? AND ? ORDER BY fecha DESC, id DESC");
$stmt->execute([$desde, $hasta]);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalIngresos = array_sum(array_map(fn($m) => $m['tipo'] === 'INGRESO' ? $m['monto'] : 0, $movimientos));
$totalEgresos = array_sum(array_map(fn($m) => $m['tipo'] === 'EGRESO' ? $m['monto'] : 0, $movimientos));

layout_inicio('Tesorería', 'Tesorería', '../');
?>
<h1><?= icon('dollar','icon-lg') ?> Tesorería</h1>
<p class="subtitle">Ingresos y egresos registrados — flujo de caja básico para control gerencial.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="dash-hero">
    <div>
        <div class="dash-hero-eyebrow">Balance del periodo</div>
        <div class="dash-hero-num">$<?= number_format($totalIngresos - $totalEgresos, 0, ',', '.') ?></div>
        <div class="dash-hero-label"><?= e($desde) ?> a <?= e($hasta) ?></div>
    </div>
    <div class="dash-hero-side">
        <div class="dash-hero-mini"><span class="n">$<?= number_format($totalIngresos, 0, ',', '.') ?></span><span class="l">Ingresos</span></div>
        <div class="dash-hero-mini"><span class="n">$<?= number_format($totalEgresos, 0, ',', '.') ?></span><span class="l">Egresos</span></div>
    </div>
</div>

<form class="toolbar" method="get">
    <label class="small">Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
    <label class="small">Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
    <button type="submit"><?= icon('search') ?> Aplicar</button>
</form>

<div class="panel">
    <h3><?= icon('plus') ?> Nuevo movimiento</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Tipo</label>
                <select name="tipo"><option value="INGRESO">Ingreso</option><option value="EGRESO">Egreso</option></select>
            </div>
            <div><label>Concepto *</label><input type="text" name="concepto" required></div>
            <div><label>Monto *</label><input type="number" step="0.01" name="monto" required></div>
            <div><label>Cuenta</label><input type="text" name="cuenta" placeholder="Banco / caja"></div>
            <div><label>Fecha</label><input type="date" name="fecha" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;"></textarea>
        <button type="submit">Registrar</button>
    </form>
</div>

<table>
    <tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Cuenta</th><th>Monto</th><th>Responsable</th><th></th></tr>
    <?php foreach ($movimientos as $m): ?>
    <tr>
        <td class="small"><?= e($m['fecha']) ?></td>
        <td><span class="badge <?= $m['tipo']==='INGRESO'?'badge-activo':'badge-err' ?>"><?= e($m['tipo']) ?></span></td>
        <td><?= e($m['concepto']) ?></td>
        <td class="small"><?= e($m['cuenta']) ?: '—' ?></td>
        <td>$<?= number_format($m['monto'], 0, ',', '.') ?></td>
        <td class="small"><?= e($m['responsable']) ?></td>
        <td>
            <form method="post" class="inline" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$movimientos): ?><tr><td colspan="7" class="small">Sin movimientos en este periodo.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
