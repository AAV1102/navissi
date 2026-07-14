<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$sedes = $pdo->query("SELECT * FROM sedes ORDER BY zona, nombre")->fetchAll(PDO::FETCH_ASSOC);
$ticketsPorSede = $pdo->query("SELECT sede_id, COUNT(*) AS n FROM tickets WHERE estado != 'CERRADO' GROUP BY sede_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$equiposPorSede = $pdo->query("SELECT sede_id, COUNT(*) AS n FROM inventario GROUP BY sede_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$mermasPorSede = $pdo->query("SELECT sede_id, COUNT(*) AS n FROM mermas_inventario WHERE estado != 'RECHAZADA' GROUP BY sede_id")->fetchAll(PDO::FETCH_KEY_PAIR);

function estadoSalud(int $tickets, int $mermas): array {
    if ($tickets >= 3 || $mermas >= 2) return ['CRITICA', 'var(--accent-600)'];
    if ($tickets >= 1 || $mermas >= 1) return ['ATENCION', '#c98a2e'];
    return ['NORMAL', '#2e7d4f'];
}

layout_inicio('Mapa de Tiendas', 'Mapa de Tiendas', '../');
?>
<h1><?= icon('store','icon-lg') ?> Mapa de Tiendas en Tiempo Real</h1>
<p class="subtitle">Estado operativo por sede — tickets abiertos, equipos en inventario y mermas activas.</p>

<div class="toolbar" style="margin-bottom:16px;gap:14px;">
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#2e7d4f;"></span> Normal</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#c98a2e;"></span> Atención</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--accent-600);"></span> Crítica</span>
</div>

<div class="cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;">
    <?php foreach ($sedes as $s):
        $t = (int) ($ticketsPorSede[$s['id']] ?? 0);
        $eq = (int) ($equiposPorSede[$s['id']] ?? 0);
        $m = (int) ($mermasPorSede[$s['id']] ?? 0);
        [$salud, $color] = estadoSalud($t, $m);
    ?>
    <a href="sede_detalle.php?id=<?= (int)$s['id'] ?>" class="card" style="display:block;border-top:4px solid <?= $color ?>;text-decoration:none;color:inherit;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <strong><?= e($s['nombre']) ?></strong>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $color ?>;margin-top:4px;"></span>
        </div>
        <div class="small" style="color:var(--ink-500);margin:4px 0 10px;"><?= e($s['ciudad']) ?: '—' ?> <?= $s['zona'] ? '· Zona ' . e($s['zona']) : '' ?></div>
        <div style="display:flex;gap:14px;font-size:13px;">
            <div><strong><?= $t ?></strong><br><span class="small">tickets</span></div>
            <div><strong><?= $eq ?></strong><br><span class="small">equipos</span></div>
            <div><strong><?= $m ?></strong><br><span class="small">mermas</span></div>
        </div>
        <div class="small" style="margin-top:8px;"><span class="badge <?= $s['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($s['estado']) ?></span></div>
    </a>
    <?php endforeach; ?>
    <?php if (!$sedes): ?><p class="small">Sin sedes registradas.</p><?php endif; ?>
</div>
<?php layout_fin(); ?>
