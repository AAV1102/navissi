<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

if (!tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI', 'DIRECTOR', 'GERENCIA', 'CEO'])) {
    layout_inicio('Informes', 'Informes', '../');
    echo '<div class="msg-error">No tienes permiso para ver los informes.</div>';
    layout_fin();
    exit;
}

$desde = trim($_GET['desde'] ?? date('Y-m-01', strtotime('-2 months')));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));

// --- SLA: cumplimiento real por prioridad ---
$stmt = $pdo->prepare("SELECT prioridad,
        COUNT(*) AS total,
        SUM(CASE WHEN sla_limite IS NOT NULL AND
                ((estado NOT IN ('CERRADO','RESUELTO POR IA') AND sla_limite < datetime('now'))
                 OR (estado IN ('CERRADO','RESUELTO POR IA') AND cerrado_en IS NOT NULL AND cerrado_en > sla_limite))
            THEN 1 ELSE 0 END) AS vencidos
    FROM tickets WHERE date(creado_en) BETWEEN ? AND ? GROUP BY prioridad ORDER BY prioridad");
$stmt->execute([$desde, $hasta]);
$slaPorPrioridad = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Tickets por estado ---
$stmt = $pdo->prepare("SELECT estado, COUNT(*) c FROM tickets WHERE date(creado_en) BETWEEN ? AND ? GROUP BY estado ORDER BY c DESC");
$stmt->execute([$desde, $hasta]);
$porEstado = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Carga por técnico (asignado_a) ---
$stmt = $pdo->prepare("SELECT COALESCE(NULLIF(asignado_a,''),'Sin asignar') AS tecnico, COUNT(*) AS total,
        SUM(CASE WHEN estado IN ('CERRADO','RESUELTO POR IA') THEN 1 ELSE 0 END) AS resueltos
    FROM tickets WHERE date(creado_en) BETWEEN ? AND ? GROUP BY tecnico ORDER BY total DESC");
$stmt->execute([$desde, $hasta]);
$cargaTecnico = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Tickets por categoría ---
$stmt = $pdo->prepare("SELECT COALESCE(NULLIF(categoria,''),'Sin categoría') AS categoria, COUNT(*) c FROM tickets WHERE date(creado_en) BETWEEN ? AND ? GROUP BY categoria ORDER BY c DESC");
$stmt->execute([$desde, $hasta]);
$porCategoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Movimientos de inventario por tipo ---
$stmt = $pdo->prepare("SELECT tipo, COUNT(*) c FROM movimientos_equipos WHERE date(creado_en) BETWEEN ? AND ? GROUP BY tipo ORDER BY c DESC");
$stmt->execute([$desde, $hasta]);
$movimientosPorTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalTickets = array_sum(array_column($porEstado, 'c'));

layout_inicio('Informes', 'Informes', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Informes</h1>
<p class="subtitle">Cumplimiento de SLA, carga por técnico y actividad — calculado en vivo sobre los datos reales, sin gráficos de relleno.</p>

<div class="tabs-atera">
    <a href="#sla" class="tab-atera activo"><?= icon('shield') ?> General / SLA</a>
    <a href="#tecnicos" class="tab-atera"><?= icon('users') ?> Técnicos</a>
    <a href="#categorias" class="tab-atera"><?= icon('sliders') ?> Categorías</a>
    <a href="#inventario-mov" class="tab-atera"><?= icon('inventory') ?> Inventario</a>
</div>

<form class="toolbar" method="get">
    <label class="small">Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
    <label class="small">Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
    <button type="submit"><?= icon('search') ?> Aplicar</button>
</form>

<div class="cards">
    <div class="card"><div class="num"><?= $totalTickets ?></div><div class="label">Tickets en el periodo</div></div>
    <?php foreach ($porEstado as $e): ?>
    <div class="card"><div class="num"><?= (int)$e['c'] ?></div><div class="label"><?= e($e['estado']) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="panel" id="sla">
    <h3><?= icon('shield') ?> Acuerdos de Nivel de Servicio (SLA)</h3>
    <table>
        <tr><th>Prioridad</th><th>Tickets</th><th>Vencidos</th><th>Cumplimiento</th></tr>
        <?php foreach ($slaPorPrioridad as $s):
            $pct = $s['total'] > 0 ? round((($s['total'] - $s['vencidos']) / $s['total']) * 100, 1) : 100;
        ?>
        <tr>
            <td><?= e($s['prioridad']) ?></td>
            <td><?= (int)$s['total'] ?></td>
            <td><?= (int)$s['vencidos'] ?></td>
            <td><span class="badge <?= $pct >= 90 ? 'badge-activo' : ($pct >= 70 ? 'badge-warn' : 'badge-err') ?>"><?= $pct ?>%</span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$slaPorPrioridad): ?><tr><td colspan="4" class="small">Sin tickets en este periodo.</td></tr><?php endif; ?>
    </table>
</div>

<div class="panel" id="tecnicos">
    <h3><?= icon('users') ?> Carga por técnico</h3>
    <table>
        <tr><th>Técnico</th><th>Tickets asignados</th><th>Resueltos</th><th>% Resolución</th></tr>
        <?php foreach ($cargaTecnico as $t):
            $pct = $t['total'] > 0 ? round(($t['resueltos'] / $t['total']) * 100, 1) : 0;
        ?>
        <tr>
            <td><?= e($t['tecnico']) ?></td>
            <td><?= (int)$t['total'] ?></td>
            <td><?= (int)$t['resueltos'] ?></td>
            <td><?= $pct ?>%</td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$cargaTecnico): ?><tr><td colspan="4" class="small">Sin tickets en este periodo.</td></tr><?php endif; ?>
    </table>
</div>

<div class="panel" id="categorias">
    <h3>Tickets por categoría</h3>
    <table>
        <tr><th>Categoría</th><th>Cantidad</th></tr>
        <?php foreach ($porCategoria as $c): ?>
        <tr><td><?= e($c['categoria']) ?></td><td><?= (int)$c['c'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="panel" id="inventario-mov">
    <h3>Movimientos de inventario por tipo</h3>
    <table>
        <tr><th>Tipo</th><th>Cantidad</th></tr>
        <?php foreach ($movimientosPorTipo as $m): ?>
        <tr><td><?= e($m['tipo']) ?></td><td><?= (int)$m['c'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$movimientosPorTipo): ?><tr><td colspan="2" class="small">Sin movimientos en este periodo.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
