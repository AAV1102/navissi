<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI', 'GERENCIA', 'CEO'])) {
    layout_inicio('Ciberseguridad y Seguridad', 'Ciberseguridad', '../');
    echo '<div class="msg-error">No tienes permiso para ver este módulo.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $titulo = limpio($_POST['titulo'] ?? null);
        if ($titulo) {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $pdo->prepare("INSERT INTO incidentes_seguridad (tipo, titulo, severidad, descripcion, sede_id, reportado_por) VALUES (?,?,?,?,?,?)")
                ->execute([limpio($_POST['tipo'] ?? null) ?: 'CIBER', $titulo, limpio($_POST['severidad'] ?? null) ?: 'MEDIA',
                    limpio($_POST['descripcion'] ?? null), $sedeId, $u['nombre']]);
            $msg = ['ok', 'Incidente registrado.'];
        }
    } elseif ($accion === 'actualizar') {
        $id = (int) $_POST['id'];
        $estado = limpio($_POST['estado'] ?? null);
        $resueltoEn = $estado === 'RESUELTO' ? "CURRENT_TIMESTAMP" : "NULL";
        $pdo->prepare("UPDATE incidentes_seguridad SET estado = ?, resolucion = ?, resuelto_en = {$resueltoEn} WHERE id = ?")
            ->execute([$estado, limpio($_POST['resolucion'] ?? null), $id]);
        $msg = ['ok', 'Incidente actualizado.'];
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM incidentes_seguridad WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$filtroTipo = trim($_GET['tipo'] ?? '');
$sql = "SELECT i.*, s.nombre AS sede_nombre FROM incidentes_seguridad i LEFT JOIN sedes s ON i.sede_id = s.id" . ($filtroTipo ? " WHERE i.tipo = ?" : "") . " ORDER BY i.creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($filtroTipo ? [$filtroTipo] : []);
$incidentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$abiertos = (int) $pdo->query("SELECT COUNT(*) FROM incidentes_seguridad WHERE estado != 'RESUELTO'")->fetchColumn();
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Ciberseguridad y Seguridad', 'Ciberseguridad', '../');
?>
<h1><?= icon('shield','icon-lg') ?> Ciberseguridad y Seguridad Física</h1>
<p class="subtitle">Registro de incidentes de seguridad informática y física — un solo lugar para ambos.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= count($incidentes) ?></div><div class="label">Total (filtro actual)</div></div>
    <div class="card" style="border-left-color:#b3392c"><div class="num"><?= $abiertos ?></div><div class="label">Sin resolver</div></div>
</div>

<div class="panel">
    <h3><?= icon('plus') ?> Reportar incidente</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Tipo</label>
                <select name="tipo"><option value="CIBER">Ciberseguridad</option><option value="FISICA">Seguridad física</option></select>
            </div>
            <div><label>Severidad</label>
                <select name="severidad">
                    <?php foreach (['BAJA','MEDIA','ALTA','CRITICA'] as $s): ?><option <?= $s==='MEDIA'?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:span 2;"><label>Título *</label><input type="text" name="titulo" required></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <textarea name="descripcion" rows="3" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Descripción del incidente"></textarea>
        <button type="submit">Reportar</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="tipo" onchange="this.form.submit()">
        <option value="">Todos</option>
        <option value="CIBER" <?= $filtroTipo==='CIBER'?'selected':'' ?>>Ciberseguridad</option>
        <option value="FISICA" <?= $filtroTipo==='FISICA'?'selected':'' ?>>Seguridad física</option>
    </select>
</form>

<?php foreach ($incidentes as $i): ?>
<div class="panel">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <div>
            <strong><?= e($i['titulo']) ?></strong> <span class="badge badge-otro"><?= e($i['tipo']) ?></span>
            <span class="badge <?= $i['severidad']==='CRITICA'||$i['severidad']==='ALTA'?'badge-err':'badge-warn' ?>"><?= e($i['severidad']) ?></span>
            <br><span class="small">Sede: <?= e($i['sede_nombre']) ?: '—' ?> · Reportado por <?= e($i['reportado_por']) ?> · <?= e($i['creado_en']) ?></span>
        </div>
        <span class="badge <?= $i['estado']==='RESUELTO'?'badge-activo':'badge-warn' ?>"><?= e($i['estado']) ?></span>
    </div>
    <p style="margin:10px 0;"><?= nl2br(e($i['descripcion'])) ?></p>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="actualizar"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
        <div><label>Estado</label>
            <select name="estado">
                <?php foreach (['ABIERTO','EN_PROCESO','RESUELTO'] as $e): ?><option value="<?= $e ?>" <?= $i['estado']===$e?'selected':'' ?>><?= $e ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="grid-column:span 2;"><label>Resolución</label><input type="text" name="resolucion" value="<?= e($i['resolucion'] ?? '') ?>"></div>
        <div style="align-self:end;"><button type="submit">Guardar</button></div>
    </form>
</div>
<?php endforeach; ?>
<?php if (!$incidentes): ?><p class="small">No hay incidentes registrados.</p><?php endif; ?>
<?php layout_fin(); ?>
