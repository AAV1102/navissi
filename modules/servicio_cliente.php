<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'COORDINADOR', 'RRHH'])) {
    layout_inicio('Servicio al Cliente', 'Servicio al Cliente', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar PQRS.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_manual') {
        $nombre = limpio($_POST['cliente_nombre'] ?? null);
        $descripcion = limpio($_POST['descripcion'] ?? null);
        if ($nombre && $descripcion) {
            $pdo->prepare("INSERT INTO pqrs (tipo, cliente_nombre, cliente_documento, cliente_contacto, canal, referencia_liveconnect, descripcion, estado) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([
                    limpio($_POST['tipo'] ?? null) ?: 'PETICION', $nombre, limpio($_POST['cliente_documento'] ?? null),
                    limpio($_POST['cliente_contacto'] ?? null), limpio($_POST['canal'] ?? null) ?: 'LIVECONNECT',
                    limpio($_POST['referencia_liveconnect'] ?? null), $descripcion, 'RECIBIDA',
                ]);
            $msg = ['ok', 'PQRS registrada.'];
        }
    } elseif ($accion === 'actualizar') {
        $id = (int) $_POST['id'];
        $estado = limpio($_POST['estado'] ?? null);
        $resueltoEn = in_array($estado, ['RESUELTA', 'CERRADA'], true) ? 'CURRENT_TIMESTAMP' : 'NULL';
        $pdo->prepare("UPDATE pqrs SET estado = ?, respuesta = ?, atendido_por = ?, resuelto_en = {$resueltoEn} WHERE id = ?")
            ->execute([$estado, limpio($_POST['respuesta'] ?? null), $u['nombre'], $id]);
        $msg = ['ok', 'PQRS actualizada.'];
    }
}

$filtroTipo = trim($_GET['tipo'] ?? '');
$filtroEstado = trim($_GET['estado'] ?? '');
$sql = "SELECT * FROM pqrs WHERE 1=1";
$params = [];
if ($filtroTipo) { $sql .= " AND tipo = ?"; $params[] = $filtroTipo; }
if ($filtroEstado) { $sql .= " AND estado = ?"; $params[] = $filtroEstado; }
$stmt = $pdo->prepare($sql . " ORDER BY creado_en DESC");
$stmt->execute($params);
$pqrsLista = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totales = $pdo->query("SELECT tipo, COUNT(*) c FROM pqrs GROUP BY tipo")->fetchAll(PDO::FETCH_ASSOC);
$abiertas = (int) $pdo->query("SELECT COUNT(*) FROM pqrs WHERE estado NOT IN ('RESUELTA','CERRADA')")->fetchColumn();

layout_inicio('Servicio al Cliente', 'Servicio al Cliente', '../');
?>
<h1><?= icon('chat','icon-lg') ?> Servicio al Cliente — PQRS</h1>
<p class="subtitle">Peticiones, quejas, reclamos y sugerencias — del formulario público y de LiveConnect.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= count($pqrsLista) ?></div><div class="label">Total (filtro actual)</div></div>
    <div class="card" style="border-left-color:#c98a1f"><div class="num"><?= $abiertas ?></div><div class="label">Abiertas</div></div>
    <?php foreach ($totales as $t): ?>
    <div class="card"><div class="num"><?= (int)$t['c'] ?></div><div class="label"><?= e($t['tipo']) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="panel">
    <h3><?= icon('external') ?> Sobre la integración con LiveConnect</h3>
    <p class="small">Aún no hay una conexión API en vivo con LiveConnect en este sistema — no tengo credenciales de esa plataforma configuradas.
        Por ahora, cuando un caso venga de LiveConnect, regístralo aquí manualmente con su número de referencia (campo "Referencia LiveConnect")
        para mantener todo centralizado. Si me compartes las credenciales/API de LiveConnect, puedo construir la sincronización automática real.</p>
</div>

<div class="panel">
    <h3><?= icon('plus') ?> Registrar caso manual (ej. desde LiveConnect, teléfono)</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear_manual">
        <div class="grid-form">
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach (['PETICION'=>'Petición','QUEJA'=>'Queja','RECLAMO'=>'Reclamo','SUGERENCIA'=>'Sugerencia'] as $v=>$l): ?>
                    <option value="<?= $v ?>"><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Canal</label>
                <select name="canal">
                    <?php foreach (['LIVECONNECT','TELEFONO','WHATSAPP','PRESENCIAL','OTRO'] as $c): ?><option><?= $c ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Referencia LiveConnect</label><input type="text" name="referencia_liveconnect" placeholder="ID del caso en LiveConnect (opcional)"></div>
            <div><label>Cliente *</label><input type="text" name="cliente_nombre" required></div>
            <div><label>Documento</label><input type="text" name="cliente_documento"></div>
            <div><label>Contacto</label><input type="text" name="cliente_contacto"></div>
        </div>
        <textarea name="descripcion" rows="3" required style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Descripción del caso"></textarea>
        <button type="submit">Registrar</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="tipo" onchange="this.form.submit()">
        <option value="">Todos los tipos</option>
        <?php foreach (['PETICION','QUEJA','RECLAMO','SUGERENCIA'] as $t): ?><option value="<?= $t ?>" <?= $filtroTipo===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
    </select>
    <select name="estado" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <?php foreach (['RECIBIDA','EN_PROCESO','RESUELTA','CERRADA'] as $e): ?><option value="<?= $e ?>" <?= $filtroEstado===$e?'selected':'' ?>><?= $e ?></option><?php endforeach; ?>
    </select>
</form>

<?php foreach ($pqrsLista as $p): ?>
<div class="panel">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <div>
            <strong>#<?= (int)$p['id'] ?> — <?= e($p['tipo']) ?></strong>
            <span class="small"> · <?= e($p['canal']) ?><?= $p['referencia_liveconnect'] ? ' (ref: ' . e($p['referencia_liveconnect']) . ')' : '' ?></span>
            <br><span class="small"><?= e($p['cliente_nombre']) ?> · <?= e($p['cliente_contacto']) ?: 'sin contacto' ?> · <?= e($p['creado_en']) ?></span>
        </div>
        <span class="badge <?= $p['estado']==='RESUELTA'||$p['estado']==='CERRADA' ? 'badge-activo' : 'badge-warn' ?>"><?= e($p['estado']) ?></span>
    </div>
    <p style="margin:10px 0;"><?= nl2br(e($p['descripcion'])) ?></p>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="actualizar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <div><label>Estado</label>
            <select name="estado">
                <?php foreach (['RECIBIDA','EN_PROCESO','RESUELTA','CERRADA'] as $e): ?><option value="<?= $e ?>" <?= $p['estado']===$e?'selected':'' ?>><?= $e ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="grid-column:span 2;"><label>Respuesta</label><input type="text" name="respuesta" value="<?= e($p['respuesta'] ?? '') ?>"></div>
        <div style="align-self:end;"><button type="submit">Guardar</button></div>
    </form>
    <?php if ($p['atendido_por']): ?><p class="small">Atendido por <?= e($p['atendido_por']) ?><?= $p['resuelto_en'] ? ' el ' . e($p['resuelto_en']) : '' ?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$pqrsLista): ?><p class="small">No hay casos registrados.</p><?php endif; ?>
<?php layout_fin(); ?>
