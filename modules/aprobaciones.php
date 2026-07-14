<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/gobierno_operativo.php';
requiere_login('../');
$pdo = db();
$usuario = usuario_actual();
$where = ['1=1'];
$params = [];
$personal = alcance_personal();
if ($personal !== null) {
    $where[] = 's.solicitante_documento=?'; $params[] = $personal['documento'];
} elseif (in_array(rol_efectivo(), ['DIRECTOR','COORDINADOR','RRHH'], true) && alcance_area() !== null) {
    $where[] = 's.area_responsable=?'; $params[] = alcance_area();
}
$sql = 'SELECT s.*,c.nombre servicio_nombre,c.categoria,c.sla_horas,sd.nombre sede_nombre FROM solicitudes_aprobacion s LEFT JOIN catalogo_servicios c ON c.id=s.catalogo_id LEFT JOIN sedes sd ON sd.id=s.sede_id WHERE ' . implode(' AND ', $where) . ' ORDER BY CASE s.estado WHEN \'PENDIENTE\' THEN 0 ELSE 1 END,s.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$filtro = strtoupper((string)($_GET['estado'] ?? 'PENDIENTE'));
$areaFiltro = trim((string)($_GET['area'] ?? ''));
$vencidasSolo = !empty($_GET['vencidas']);
$solicitudes = array_values(array_filter($todas, function($s) use ($filtro,$areaFiltro,$vencidasSolo) {
    if ($filtro !== 'TODAS' && $s['estado'] !== $filtro) return false;
    if ($areaFiltro !== '' && $s['area_responsable'] !== $areaFiltro) return false;
    if ($vencidasSolo && solicitud_sla($s) !== 'VENCIDA') return false;
    return true;
}));
$pendientes = count(array_filter($todas, fn($s) => $s['estado']==='PENDIENTE'));
$vencidas = count(array_filter($todas, fn($s) => solicitud_sla($s)==='VENCIDA'));
$aprobadas30 = count(array_filter($todas, fn($s) => $s['estado']==='APROBADA' && strtotime((string)$s['resuelto_en']) >= time()-30*86400));
$areas = array_values(array_unique(array_filter(array_column($todas, 'area_responsable')))); sort($areas);
layout_inicio('Solicitudes y Aprobaciones', 'Solicitudes y Aprobaciones', '../');
?>
<div class="page-kicker">GOBIERNO · DECISIONES INTERÁREAS</div><h1><?= icon('check','icon-lg') ?> Solicitudes y Aprobaciones</h1>
<p class="subtitle">Bandeja con responsable, SLA, doble aprobación por monto y trazabilidad completa de cada decisión.</p>
<div class="toolbar"><a class="btn" href="catalogo_servicios.php"><?= icon('plus') ?> Nueva solicitud</a><?php if (tiene_rol(['SUPER_ADMIN','ADMIN','DIRECTOR','GERENCIA','CEO'])): ?><a class="btn btn-secondary" href="gobierno_operativo.php">Tablero ejecutivo</a><?php endif; ?></div>
<div class="cards approval-cards"><div class="card <?= $pendientes?'card-warn':'card-ok' ?>"><div class="num"><?= $pendientes ?></div><div class="label">Pendientes</div></div><div class="card <?= $vencidas?'card-err':'card-ok' ?>"><div class="num"><?= $vencidas ?></div><div class="label">SLA vencido</div></div><div class="card card-ok"><div class="num"><?= $aprobadas30 ?></div><div class="label">Aprobadas últimos 30 días</div></div></div>
<form method="get" class="toolbar"><select name="estado"><option value="PENDIENTE" <?= $filtro==='PENDIENTE'?'selected':'' ?>>Pendientes</option><option value="APROBADA" <?= $filtro==='APROBADA'?'selected':'' ?>>Aprobadas</option><option value="RECHAZADA" <?= $filtro==='RECHAZADA'?'selected':'' ?>>Rechazadas</option><option value="TODAS" <?= $filtro==='TODAS'?'selected':'' ?>>Todas</option></select><select name="area"><option value="">Todas las áreas</option><?php foreach ($areas as $area): ?><option <?= $areaFiltro===$area?'selected':'' ?>><?= e($area) ?></option><?php endforeach; ?></select><label class="ticket-switch"><input type="checkbox" name="vencidas" value="1" <?= $vencidasSolo?'checked':'' ?>> Solo vencidas</label><button>Filtrar</button></form>
<div class="panel"><div class="table-responsive"><table><thead><tr><th>Código</th><th>Servicio</th><th>Solicitante</th><th>Área responsable</th><th>Prioridad</th><th>Nivel</th><th>SLA</th><th>Estado</th></tr></thead><tbody><?php foreach ($solicitudes as $s): $sla=solicitud_sla($s); ?><tr onclick="location.href='solicitud_detalle.php?id=<?= (int)$s['id'] ?>'" style="cursor:pointer"><td><strong><?= e($s['codigo'] ?: '#'.$s['id']) ?></strong><br><span class="small"><?= e($s['creado_en']) ?></span></td><td><?= e($s['servicio_nombre'] ?: $s['tipo']) ?><?php if ($s['ticket_id']): ?><br><span class="small">Ticket #<?= (int)$s['ticket_id'] ?></span><?php endif; ?></td><td><?= e($s['solicitante_nombre']) ?></td><td><?= e($s['area_responsable']) ?></td><td><span class="badge <?= $s['prioridad']==='URGENTE'?'badge-err':($s['prioridad']==='ALTA'?'badge-warn':'badge-otro') ?>"><?= e($s['prioridad']) ?></span></td><td><?= $s['estado']==='PENDIENTE'?e($s['nivel_actual']):'—' ?></td><td><span class="badge <?= $sla==='VENCIDA'?'badge-err':($sla==='EN_TIEMPO'?'badge-activo':'badge-otro') ?>"><?= e($sla) ?></span></td><td><span class="badge <?= $s['estado']==='APROBADA'?'badge-activo':($s['estado']==='RECHAZADA'?'badge-err':'badge-warn') ?>"><?= e($s['estado']) ?></span></td></tr><?php endforeach; ?><?php if (!$solicitudes): ?><tr><td colspan="8" class="small">No hay solicitudes para este filtro.</td></tr><?php endif; ?></tbody></table></div></div>
<?php layout_fin(); ?>
