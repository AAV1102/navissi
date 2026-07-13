<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['GERENCIA', 'CEO', 'ADMIN', 'RRHH', 'DIRECTOR'])) {
    layout_inicio('Gestión de Denuncias', 'Canal de Denuncias', '../');
    echo '<div class="msg-error">No tienes permiso para revisar denuncias.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $estado = limpio($_POST['estado'] ?? null);
    $respuesta = limpio($_POST['respuesta'] ?? null);
    if ($id && $estado) {
        $resueltoEn = in_array($estado, ['RESUELTA', 'DESCARTADA'], true) ? "CURRENT_TIMESTAMP" : "NULL";
        $pdo->prepare("UPDATE denuncias SET estado = ?, respuesta = ?, atendido_por = ?, resuelto_en = {$resueltoEn} WHERE id = ?")
            ->execute([$estado, $respuesta, $u['nombre'], $id]);
        $msg = ['ok', 'Denuncia actualizada.'];
    }
}

$filtroEstado = trim($_GET['estado'] ?? '');
$sql = "SELECT * FROM denuncias";
$params = [];
if ($filtroEstado) { $sql .= " WHERE estado = ?"; $params[] = $filtroEstado; }
$stmt = $pdo->prepare($sql . " ORDER BY creado_en DESC");
$stmt->execute($params);
$denuncias = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Gestión de Denuncias', 'Canal de Denuncias', '../');
?>
<h1><?= icon('shield','icon-lg') ?> Gestión de Denuncias</h1>
<p class="subtitle">Revisión confidencial. Las denuncias anónimas no muestran identidad del denunciante.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form class="toolbar" method="get">
    <select name="estado" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?php foreach (['RECIBIDA','EN_REVISION','RESUELTA','DESCARTADA'] as $s): ?>
        <option value="<?= $s ?>" <?= $filtroEstado===$s?'selected':'' ?>><?= ucfirst(strtolower(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div class="cards">
    <div class="card"><div class="num"><?= count($denuncias) ?></div><div class="label">Total</div></div>
    <div class="card"><div class="num"><?= count(array_filter($denuncias, fn($d)=>$d['estado']==='RECIBIDA')) ?></div><div class="label">Sin revisar</div></div>
    <div class="card"><div class="num"><?= count(array_filter($denuncias, fn($d)=>$d['anonimo'])) ?></div><div class="label">Anónimas</div></div>
</div>

<?php foreach ($denuncias as $d): ?>
<div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
        <div>
            <strong><?= e($d['categoria']) ?></strong>
            <?php if ($d['area_involucrada']): ?><span class="small"> · Área: <?= e($d['area_involucrada']) ?></span><?php endif; ?>
            <br><span class="small"><?= e($d['creado_en']) ?> · <?= $d['anonimo'] ? '<strong>Anónima</strong>' : 'Reportado por ' . e($d['denunciante_nombre']) ?></span>
        </div>
        <span class="badge <?= $d['estado']==='RESUELTA'?'badge-activo':($d['estado']==='DESCARTADA'?'badge-otro':'badge-warn') ?>"><?= e($d['estado']) ?></span>
    </div>
    <p style="margin:10px 0;"><?= nl2br(e($d['descripcion'])) ?></p>
    <form method="post" class="grid-form">
        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <div><label>Estado</label>
            <select name="estado">
                <?php foreach (['RECIBIDA','EN_REVISION','RESUELTA','DESCARTADA'] as $s): ?>
                <option value="<?= $s ?>" <?= $d['estado']===$s?'selected':'' ?>><?= ucfirst(strtolower(str_replace('_',' ',$s))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="grid-column:span 2;"><label>Respuesta / nota interna</label>
            <input type="text" name="respuesta" value="<?= e($d['respuesta'] ?? '') ?>" placeholder="Acciones tomadas o comentario">
        </div>
        <div style="align-self:end;"><button type="submit">Guardar</button></div>
    </form>
    <?php if ($d['atendido_por']): ?><p class="small">Atendido por <?= e($d['atendido_por']) ?><?= $d['resuelto_en'] ? ' el ' . e($d['resuelto_en']) : '' ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$denuncias): ?><p class="small">No hay denuncias registradas.</p><?php endif; ?>
<?php layout_fin(); ?>
