<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$u = usuario_actual();

$tipos = ['COMPRA' => 'Compra / Gasto', 'CAMBIO_EQUIPO' => 'Cambio de equipo', 'PERMISO' => 'Permiso / Ausencia', 'ACCESO' => 'Acceso a sistema', 'OTRO' => 'Otro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $doc = limpio($_POST['solicitante_documento'] ?? null);
        $desc = limpio($_POST['descripcion'] ?? null);
        if (!$desc) {
            $msg = ['error', 'La descripción es obligatoria.'];
        } else {
            $nombre = $u['nombre'];
            if ($doc) {
                $stmt = $pdo->prepare("SELECT nombres FROM empleados WHERE documento = ?");
                $stmt->execute([$doc]);
                $nombre = $stmt->fetchColumn() ?: $u['nombre'];
            }
            $pdo->prepare("INSERT INTO solicitudes_aprobacion (tipo, solicitante_documento, solicitante_nombre, area_responsable, descripcion, monto, prioridad, estado)
                VALUES (?,?,?,?,?,?,?,'PENDIENTE')")
                ->execute([limpio($_POST['tipo'] ?? null) ?: 'OTRO', $doc, $nombre, limpio($_POST['area_responsable'] ?? null),
                    $desc, $_POST['monto'] !== '' ? (float) $_POST['monto'] : null, limpio($_POST['prioridad'] ?? null) ?: 'NORMAL']);
            $id = $pdo->lastInsertId();
            hoja_vida_registrar($pdo, 'EMPLEADO', $doc ?: $u['documento'] ?: (string)$u['id'], 'SOLICITUD_CREADA', "Solicitud #{$id}: {$desc}", $u['nombre']);
            $msg = ['ok', 'Solicitud enviada para aprobación.'];
        }
    } elseif ($accion === 'resolver' && tiene_rol(['ADMIN', 'COORDINADOR', 'RRHH'])) {
        $id = (int) $_POST['id'];
        $estado = $_POST['estado'] === 'APROBADA' ? 'APROBADA' : 'RECHAZADA';
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_aprobacion WHERE id = ?");
        $stmt->execute([$id]);
        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE solicitudes_aprobacion SET estado=?, aprobador=?, comentario_aprobador=?, resuelto_en=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$estado, $u['nombre'], limpio($_POST['comentario'] ?? null), $id]);
        if ($sol) {
            hoja_vida_registrar($pdo, 'EMPLEADO', $sol['solicitante_documento'] ?: (string)$id, 'SOLICITUD_' . $estado, "Solicitud #{$id}: {$sol['descripcion']}", $u['nombre']);
        }
        $msg = ['ok', "Solicitud #{$id} marcada como {$estado}."];
    }
}

$filtroEstado = $_GET['estado'] ?? 'PENDIENTE';
$sql = "SELECT * FROM solicitudes_aprobacion WHERE 1=1";
$params = [];
if ($filtroEstado !== 'TODAS') { $sql .= " AND estado = ?"; $params[] = $filtroEstado; }
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$empleados = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);
$pendientes = (int) $pdo->query("SELECT COUNT(*) FROM solicitudes_aprobacion WHERE estado='PENDIENTE'")->fetchColumn();

layout_inicio('Solicitudes y Aprobaciones', 'Solicitudes y Aprobaciones', '../');
?>
<h1><?= icon('check','icon-lg') ?> Solicitudes y Aprobaciones</h1>
<p class="subtitle">Flujo genérico de aprobación: compras, cambios de equipo, permisos, accesos, etc. — con trazabilidad en la hoja de vida.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="stat-cards" style="margin-bottom:18px;">
    <div class="stat-card"><div class="stat-num"><?= $pendientes ?></div><div class="stat-label">Pendientes por aprobar</div></div>
</div>

<div class="panel">
    <h3><?= icon('plus') ?> Nueva solicitud</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach ($tipos as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Solicitante</label>
                <input type="text" name="solicitante_documento" list="lista-emp" placeholder="Documento del empleado (opcional)">
                <datalist id="lista-emp">
                    <?php foreach ($empleados as $emp): ?><option value="<?= e($emp['documento']) ?>"><?= e($emp['nombres']) ?></option><?php endforeach; ?>
                </datalist>
            </div>
            <div><label>Área responsable</label><input type="text" name="area_responsable" placeholder="TI, RRHH, Compras..."></div>
            <div><label>Monto (si aplica)</label><input type="number" step="0.01" name="monto"></div>
            <div><label>Prioridad</label>
                <select name="prioridad">
                    <option value="BAJA">Baja</option>
                    <option value="NORMAL" selected>Normal</option>
                    <option value="ALTA">Alta</option>
                    <option value="URGENTE">Urgente</option>
                </select>
            </div>
        </div>
        <label>Descripción *</label>
        <textarea name="descripcion" rows="2" style="width:100%;" required></textarea>
        <button type="submit" style="margin-top:10px;"><?= icon('send') ?> Enviar solicitud</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="estado" onchange="this.form.submit()">
        <option value="PENDIENTE" <?= $filtroEstado==='PENDIENTE'?'selected':'' ?>>Pendientes</option>
        <option value="APROBADA" <?= $filtroEstado==='APROBADA'?'selected':'' ?>>Aprobadas</option>
        <option value="RECHAZADA" <?= $filtroEstado==='RECHAZADA'?'selected':'' ?>>Rechazadas</option>
        <option value="TODAS" <?= $filtroEstado==='TODAS'?'selected':'' ?>>Todas</option>
    </select>
</form>

<table>
    <tr><th>#</th><th>Tipo</th><th>Solicitante</th><th>Descripción</th><th>Prioridad</th><th>Estado</th><th>Fecha</th><?php if (tiene_rol(['ADMIN','COORDINADOR','RRHH'])): ?><th></th><?php endif; ?></tr>
    <?php foreach ($solicitudes as $s): ?>
    <tr>
        <td>#<?= (int)$s['id'] ?></td>
        <td><?= e($tipos[$s['tipo']] ?? $s['tipo']) ?></td>
        <td><?= e($s['solicitante_nombre']) ?: '—' ?></td>
        <td><?= e($s['descripcion']) ?><?php if ($s['monto']): ?><br><span class="small">$<?= number_format((float)$s['monto'],0,',','.') ?></span><?php endif; ?></td>
        <td><span class="badge <?= $s['prioridad']==='URGENTE'?'badge-err':'badge-otro' ?>"><?= e($s['prioridad']) ?></span></td>
        <td><span class="badge <?= $s['estado']==='APROBADA'?'badge-activo':($s['estado']==='RECHAZADA'?'badge-err':'badge-otro') ?>"><?= e($s['estado']) ?></span></td>
        <td class="small"><?= e($s['creado_en']) ?></td>
        <?php if (tiene_rol(['ADMIN','COORDINADOR','RRHH'])): ?>
        <td>
            <?php if ($s['estado'] === 'PENDIENTE'): ?>
            <form class="inline" method="post" style="display:flex;gap:4px;">
                <input type="hidden" name="accion" value="resolver">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <input type="hidden" name="comentario" value="">
                <button type="submit" name="estado" value="APROBADA" style="padding:4px 10px;font-size:12px;"><?= icon('check') ?> Aprobar</button>
                <button type="submit" name="estado" value="RECHAZADA" class="btn-danger" style="padding:4px 10px;font-size:12px;"><?= icon('x') ?> Rechazar</button>
            </form>
            <?php else: ?>
            <span class="small">por <?= e($s['aprobador']) ?></span>
            <?php endif; ?>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (!$solicitudes): ?><tr><td colspan="8" class="small">Sin solicitudes en este estado.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
