<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $doc = limpio($_POST['empleado_documento'] ?? null);
        if (!$doc) {
            $msg = ['error', 'El documento del empleado es obligatorio.'];
        } else {
            $stmt = $pdo->prepare("SELECT nombres FROM empleados WHERE documento = ?");
            $stmt->execute([$doc]);
            $nombre = $stmt->fetchColumn();
            $dias = null;
            if (!empty($_POST['fecha_inicio']) && !empty($_POST['fecha_fin'])) {
                $dias = (int) ((strtotime($_POST['fecha_fin']) - strtotime($_POST['fecha_inicio'])) / 86400) + 1;
            }
            $pdo->prepare("INSERT INTO vacaciones_permisos (empleado_documento, empleado_nombre, tipo, fecha_inicio, fecha_fin, dias, motivo)
                VALUES (?,?,?,?,?,?,?)")
                ->execute([$doc, $nombre ?: null, limpio($_POST['tipo'] ?? null) ?: 'VACACIONES',
                    limpio($_POST['fecha_inicio'] ?? null), limpio($_POST['fecha_fin'] ?? null), $dias, limpio($_POST['motivo'] ?? null)]);
            $msg = ['ok', $nombre ? "Solicitud registrada para {$nombre}." : 'Solicitud registrada (documento no encontrado en RRHH, revisa que esté bien escrito).'];
        }
    } elseif ($accion === 'cambiar_estado') {
        $pdo->prepare("UPDATE vacaciones_permisos SET estado = ?, aprobado_por = ? WHERE id = ?")
            ->execute([$_POST['estado'], limpio($_POST['aprobado_por'] ?? null) ?: 'RRHH', (int) $_POST['id']]);
        $msg = ['ok', 'Estado actualizado.'];
    }
}

$estadoFiltro = trim($_GET['estado'] ?? '');
$sql = "SELECT * FROM vacaciones_permisos WHERE 1=1";
$params = [];
if ($estadoFiltro !== '') { $sql .= " AND estado = ?"; $params[] = $estadoFiltro; }
// Alcance personal: un EMPLEADO sin rol elevado solo ve sus propias solicitudes de vacaciones/permisos.
$personalVac = alcance_personal();
if ($personalVac !== null) {
    $sql .= " AND empleado_documento = ?";
    $params[] = $personalVac['documento'];
}
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Vacaciones y Permisos', 'Vacaciones y Permisos', '../');
?>
<h1><?= icon('briefcase','icon-lg') ?> Vacaciones y Permisos</h1>
<p class="subtitle">Solicitudes de vacaciones, permisos e incapacidades por empleado.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Nueva solicitud</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Documento del empleado *</label><input type="text" name="empleado_documento" required></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach (['VACACIONES','PERMISO','INCAPACIDAD','LICENCIA'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Fecha inicio</label><input type="date" name="fecha_inicio"></div>
            <div><label>Fecha fin</label><input type="date" name="fecha_fin"></div>
        </div>
        <textarea name="motivo" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Motivo / observación"></textarea>
        <button type="submit">Registrar solicitud</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="estado" onchange="this.form.submit()">
        <option value="">-- todos los estados --</option>
        <?php foreach (['SOLICITADO','APROBADO','RECHAZADO'] as $es): ?>
        <option <?= $estadoFiltro===$es?'selected':'' ?>><?= $es ?></option>
        <?php endforeach; ?>
    </select>
</form>

<table>
    <tr><th>Empleado</th><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Estado</th><th>Acciones</th></tr>
    <?php foreach ($solicitudes as $s): ?>
    <tr>
        <td><?= e($s['empleado_nombre']) ?: e($s['empleado_documento']) ?></td>
        <td><?= e($s['tipo']) ?></td>
        <td><?= e($s['fecha_inicio']) ?></td>
        <td><?= e($s['fecha_fin']) ?></td>
        <td><?= e($s['dias']) ?></td>
        <td><span class="badge <?= $s['estado']==='APROBADO'?'badge-activo':($s['estado']==='RECHAZADO'?'badge-otro':'') ?>" style="<?= $s['estado']==='SOLICITADO' ? 'background:#fff3cd;color:#7a5c00;' : '' ?>"><?= e($s['estado']) ?></span></td>
        <td>
            <?php if ($s['estado'] === 'SOLICITADO'): ?>
            <form method="post" class="inline">
                <input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" name="estado" value="APROBADO" style="padding:4px 10px;font-size:12px;">Aprobar</button>
                <button type="submit" name="estado" value="RECHAZADO" class="btn-danger" style="padding:4px 10px;font-size:12px;">Rechazar</button>
            </form>
            <?php else: ?><span class="small">Por <?= e($s['aprobado_por']) ?></span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$solicitudes): ?><tr><td colspan="7" class="small">Sin solicitudes.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
