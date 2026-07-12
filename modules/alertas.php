<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

sincronizar_alertas($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if ($ids) {
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        if ($accion === 'resolver') {
            $pdo->prepare("UPDATE alertas_sistema SET estado='RESUELTA', resuelto_en=CURRENT_TIMESTAMP WHERE id IN ({$marcadores})")->execute($ids);
            $msg = ['ok', count($ids) . ' alerta(s) resuelta(s).'];
        } elseif ($accion === 'posponer') {
            $pdo->prepare("UPDATE alertas_sistema SET estado='POSPUESTA', pospuesta_hasta=datetime('now','+3 days') WHERE id IN ({$marcadores})")->execute($ids);
            $msg = ['ok', count($ids) . ' alerta(s) pospuesta(s) 3 días.'];
        } elseif ($accion === 'eliminar') {
            $pdo->prepare("DELETE FROM alertas_sistema WHERE id IN ({$marcadores})")->execute($ids);
            $msg = ['ok', count($ids) . ' alerta(s) eliminada(s).'];
        }
    }
}

// Las pospuestas cuyo plazo ya paso vuelven a estar activas
$pdo->exec("UPDATE alertas_sistema SET estado='ACTIVA' WHERE estado='POSPUESTA' AND pospuesta_hasta < datetime('now')");

$estadoFiltro = trim($_GET['estado'] ?? 'ACTIVA');
$sql = "SELECT * FROM alertas_sistema WHERE 1=1";
$params = [];
if ($estadoFiltro !== 'TODAS') { $sql .= " AND estado = ?"; $params[] = $estadoFiltro; }
$sql .= " ORDER BY (gravedad='CRITICO') DESC, creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Alertas', 'Alertas', '../');
?>
<h1><?= icon('bell','icon-lg') ?> Alertas</h1>
<p class="subtitle">Todo lo que necesita tu atención, generado automáticamente desde el estado real del sistema: equipos que dejaron de reportar, contratos por vencer, tickets con SLA vencido.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form method="get" class="toolbar">
    <select name="estado" onchange="this.form.submit()">
        <option value="ACTIVA" <?= $estadoFiltro==='ACTIVA'?'selected':'' ?>>Activas</option>
        <option value="POSPUESTA" <?= $estadoFiltro==='POSPUESTA'?'selected':'' ?>>Pospuestas</option>
        <option value="RESUELTA" <?= $estadoFiltro==='RESUELTA'?'selected':'' ?>>Resueltas</option>
        <option value="TODAS" <?= $estadoFiltro==='TODAS'?'selected':'' ?>>Todas</option>
    </select>
</form>

<form method="post" id="form-alertas">
    <input type="hidden" name="accion" id="accion-alertas" value="">
    <div class="tabla-toolbar">
        <label class="small chk-todos"><input type="checkbox" id="marcar-todas"> Seleccionar todo</label>
        <span class="tabla-toolbar-acciones">
            <button type="button" class="link-btn" onclick="document.getElementById('accion-alertas').value='eliminar';document.getElementById('form-alertas').requestSubmit();"><?= icon('trash') ?> Eliminar</button>
            <button type="button" class="link-btn" onclick="document.getElementById('accion-alertas').value='posponer';document.getElementById('form-alertas').requestSubmit();"><?= icon('bell') ?> Posponer</button>
            <button type="button" class="link-btn" onclick="document.getElementById('accion-alertas').value='resolver';document.getElementById('form-alertas').requestSubmit();"><?= icon('check') ?> Resolver</button>
        </span>
        <span class="small" style="margin-left:auto;">Mostrando <?= count($alertas) ?> de <?= count($alertas) ?> alertas</span>
    </div>
    <table class="tabla-tickets">
        <thead>
        <tr>
            <th style="width:30px;"><input type="checkbox" id="marcar-todas-th" style="display:none;"></th>
            <th>Detalles</th><th>Creado</th><th>Gravedad</th><th>Categoría</th><th>Ticket</th><th>Estado</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($alertas as $a): ?>
        <tr>
            <td onclick="event.stopPropagation()"><input type="checkbox" name="ids[]" value="<?= (int)$a['id'] ?>" class="chk-alerta"></td>
            <td><div class="t-title"><?= e($a['titulo']) ?></div></td>
            <td class="small"><?= e($a['creado_en']) ?></td>
            <td><span class="badge <?= $a['gravedad']==='CRITICO'?'badge-err':'badge-warn' ?>"><?= e($a['gravedad']) ?></span></td>
            <td><?= e($a['categoria']) ?></td>
            <td><?= $a['ticket_id'] ? '<a href="ticket_detalle.php?id='.(int)$a['ticket_id'].'">#'.(int)$a['ticket_id'].'</a>' : '—' ?></td>
            <td><span class="badge <?= $a['estado']==='ACTIVA'?'badge-err':($a['estado']==='POSPUESTA'?'badge-warn':'badge-activo') ?>"><?= e($a['estado']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$alertas): ?>
        <tr><td colspan="7" style="text-align:center;padding:60px 14px;border-bottom:none;">
            <div style="font-size:44px;opacity:.5;"><?= icon('bell','icon-lg') ?></div>
            <strong>Uhmmm... no hay nada aquí</strong><br>
            <span class="small">No hay alertas <?= strtolower($estadoFiltro) === 'todas' ? '' : strtolower($estadoFiltro) . 's' ?> en este momento.</span>
        </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</form>
<script>
document.getElementById('marcar-todas')?.addEventListener('change', function (e) {
    document.querySelectorAll('.chk-alerta').forEach(function (c) { c.checked = e.target.checked; });
});
</script>
<?php layout_fin(); ?>
