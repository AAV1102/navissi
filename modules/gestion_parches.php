<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Gestión de Parches', 'Gestión de Parches', '../');
    echo '<div class="msg-error">Solo TI puede ver la gestión de parches.</div>';
    layout_fin();
    exit;
}

$totalParches = (int) $pdo->query("SELECT COUNT(*) FROM parches_equipo")->fetchColumn();
$equiposConReporte = (int) $pdo->query("SELECT COUNT(DISTINCT inventario_id) FROM parches_equipo")->fetchColumn();
$totalEquipos = (int) $pdo->query("SELECT COUNT(*) FROM inventario WHERE estado='ACTIVO'")->fetchColumn();
$sinReporte = max(0, $totalEquipos - $equiposConReporte);

$equipoFiltro = trim($_GET['equipo'] ?? '');
$sql = "SELECT p.*, i.serial, i.marca, i.modelo, i.asignado_a FROM parches_equipo p JOIN inventario i ON p.inventario_id = i.id WHERE 1=1";
$params = [];
if ($equipoFiltro !== '') {
    $sql .= " AND (i.serial LIKE ? OR i.asignado_a LIKE ?)";
    $params[] = "%{$equipoFiltro}%"; $params[] = "%{$equipoFiltro}%";
}
$sql .= " ORDER BY p.fecha_instalado DESC LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Resumen por equipo: cuantos parches y cual fue el ultimo reportado
$porEquipo = $pdo->query("SELECT i.id, i.serial, i.marca, i.modelo, i.asignado_a, i.ultima_conexion_agente,
        COUNT(p.id) AS total_parches, MAX(p.fecha_instalado) AS ultimo_parche
    FROM inventario i LEFT JOIN parches_equipo p ON p.inventario_id = i.id
    WHERE i.estado = 'ACTIVO'
    GROUP BY i.id ORDER BY total_parches ASC, i.serial")->fetchAll(PDO::FETCH_ASSOC);

$pctParcheo = $totalEquipos > 0 ? round(($equiposConReporte / $totalEquipos) * 100) : 0;
$pendienteReinicio = (int) $pdo->query("SELECT COUNT(*) FROM inventario WHERE estado='ACTIVO' AND reinicio_pendiente = 1")->fetchColumn();
$fallidos = (int) $pdo->query("SELECT COUNT(*) FROM parches_equipo WHERE tipo = 'FALLIDO'")->fetchColumn();

layout_inicio('Gestión de Parches', 'Gestión de Parches', '../');
?>
<h1><?= icon('shield','icon-lg') ?> Gestión de Parches</h1>
<p class="subtitle">Actualizaciones de Windows realmente instaladas en cada equipo, reportadas por el agente local (<code>Get-HotFix</code>) — no es una simulación.</p>

<div class="tabs-atera">
    <a href="#resumen" class="tab-atera activo">Resumen</a>
    <a href="#dispositivos" class="tab-atera">Dispositivos</a>
    <a href="#parches" class="tab-atera">Parches del SO</a>
</div>

<div id="resumen" class="panel-grid-2">
    <div class="panel">
        <h3>Estado de parcheo del SO</h3>
        <div class="barra-progreso"><div class="barra-progreso-fill" style="width:<?= $pctParcheo ?>%"></div></div>
        <p class="small" style="margin-top:6px;"><?= $pctParcheo ?>% · <?= $equiposConReporte ?> de <?= $totalEquipos ?> dispositivos</p>
        <div class="donuts-row">
            <div class="donut" style="--pct:<?= $totalEquipos ? round(($equiposConReporte/$totalEquipos)*100) : 0 ?>;"><span>Windows PC</span></div>
            <div class="donut" style="--pct:0;"><span>Windows Server</span></div>
            <div class="donut" style="--pct:0;"><span>Mac</span></div>
            <div class="donut" style="--pct:0;"><span>Linux</span></div>
        </div>
    </div>
    <div>
        <div class="cards" style="grid-template-columns:1fr 1fr;">
            <div class="card"><div class="num"><?= $sinReporte ?></div><div class="label">Dispositivos que faltan parches críticos</div></div>
            <div class="card"><div class="num"><?= $sinReporte ?></div><div class="label">Dispositivos faltantes parches de SO</div></div>
            <div class="card"><div class="num"><?= $pendienteReinicio ?></div><div class="label">Dispositivos pendiente reinicio</div></div>
            <div class="card" style="border-left-color:<?= $fallidos ? '#b3392c' : '#0d9488' ?>"><div class="num"><?= $fallidos ?></div><div class="label">Parches de SO fallidos</div></div>
        </div>
    </div>
</div>

<div id="dispositivos" class="panel">
    <h3>Equipos: cobertura de parches</h3>
    <table>
        <tr><th>Equipo</th><th>Asignado a</th><th>Parches reportados</th><th>Último parche instalado</th><th>Último reporte del agente</th></tr>
        <?php foreach ($porEquipo as $e): ?>
        <tr>
            <td><a href="equipo_detalle.php?id=<?= (int)$e['id'] ?>"><?= e($e['marca']) ?> <?= e($e['modelo']) ?></a><br><span class="small"><?= e($e['serial']) ?></span></td>
            <td><?= e($e['asignado_a']) ?: '—' ?></td>
            <td><span class="badge <?= $e['total_parches'] > 0 ? 'badge-activo' : 'badge-otro' ?>"><?= (int)$e['total_parches'] ?></span></td>
            <td class="small"><?= e($e['ultimo_parche']) ?: '—' ?></td>
            <td class="small"><?= e($e['ultima_conexion_agente']) ?: 'Sin agente' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<form class="toolbar" method="get">
    <input type="search" name="equipo" placeholder="Buscar por serial o empleado..." value="<?= e($equipoFiltro) ?>" style="min-width:280px">
    <button type="submit"><?= icon('search') ?> Buscar</button>
</form>

<table id="parches">
    <tr><th>KB</th><th>Descripción</th><th>Tipo</th><th>Equipo</th><th>Fecha instalado</th></tr>
    <?php foreach ($parches as $p): ?>
    <tr>
        <td><code><?= e($p['kb']) ?></code></td>
        <td><?= e($p['descripcion']) ?: '—' ?></td>
        <td><span class="badge badge-otro"><?= e($p['tipo']) ?></span></td>
        <td><?= e($p['marca']) ?> <?= e($p['modelo']) ?> <span class="small">(<?= e($p['serial']) ?>)</span></td>
        <td class="small"><?= e($p['fecha_instalado']) ?: '—' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$parches): ?><tr><td colspan="5" class="small">Sin parches reportados todavía. Corre el agente (<code>agente_navissi.ps1</code>) en los equipos para poblar esta lista.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
