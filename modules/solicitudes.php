<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM solicitudes_actualizacion WHERE id = ?");
    $stmt->execute([$id]);
    $sol = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sol && $accion === 'aprobar') {
        $datos = json_decode($sol['datos'], true) ?: [];
        $serial = $datos['serial'] ?? null;
        if ($serial) {
            $campos = [
                'serial' => $serial, 'placa' => $datos['placa'] ?? null, 'tipo' => $datos['tipo'] ?? null,
                'marca' => $datos['marca'] ?? null, 'modelo' => $datos['modelo'] ?? null,
                'asignado_a' => $datos['asignado_a'] ?? null, 'sede_id' => $sol['sede_id'],
                'estado' => 'ACTIVO', 'fuente' => 'Formulario tienda (aprobado)',
            ];
            $stmt2 = $pdo->prepare("SELECT id FROM inventario WHERE serial = ?");
            $stmt2->execute([$serial]);
            $ex = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($ex) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($campos)));
                $stmt2 = $pdo->prepare("UPDATE inventario SET {$set} WHERE id = :id");
                $campos['id'] = $ex['id'];
                $stmt2->execute($campos);
            } else {
                $cols = implode(', ', array_keys($campos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($campos)));
                $pdo->prepare("INSERT INTO inventario ({$cols}) VALUES ({$ph})")->execute($campos);
            }
            $msg = ['ok', "Solicitud aprobada y aplicada al inventario (serial {$serial})."];
        } else {
            $msg = ['ok', 'Solicitud aprobada (sin serial, quedó marcada como revisada - no se creó equipo en inventario).'];
        }
        $pdo->prepare("UPDATE solicitudes_actualizacion SET estado='APROBADA', revisado_por=?, revisado_en=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([limpio($_POST['revisor'] ?? null) ?: 'TI', $id]);
    } elseif ($sol && $accion === 'rechazar') {
        $pdo->prepare("UPDATE solicitudes_actualizacion SET estado='RECHAZADA', revisado_por=?, revisado_en=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([limpio($_POST['revisor'] ?? null) ?: 'TI', $id]);
        $msg = ['ok', 'Solicitud rechazada.'];
    }
}

$estadoFiltro = trim($_GET['estado'] ?? 'PENDIENTE');
$sql = "SELECT sa.*, s.nombre AS sede_nombre FROM solicitudes_actualizacion sa LEFT JOIN sedes s ON sa.sede_id = s.id WHERE 1=1";
$params = [];
if ($estadoFiltro !== '') { $sql .= " AND sa.estado = ?"; $params[] = $estadoFiltro; }
$sql .= " ORDER BY sa.creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Solicitudes de tiendas', 'Solicitudes', '../');
?>
<h1><?= icon('store','icon-lg') ?> Solicitudes de actualización (tiendas)</h1>
<p class="subtitle">Lo que las tiendas reportan desde el formulario público, para que TI apruebe antes de que impacte el inventario real.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form class="toolbar" method="get">
    <select name="estado" onchange="this.form.submit()">
        <?php foreach (['PENDIENTE','APROBADA','RECHAZADA',''] as $es): ?>
        <option value="<?= e($es) ?>" <?= $estadoFiltro===$es?'selected':'' ?>><?= $es ?: 'Todas' ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php foreach ($solicitudes as $s): $datos = json_decode($s['datos'], true) ?: []; ?>
<div class="panel">
    <h3><?= e($s['tipo']) ?> — <?= e($s['sede_nombre']) ?: 'Sin sede' ?>
        <span class="badge <?= $s['estado']==='PENDIENTE' ? 'badge-otro' : ($s['estado']==='APROBADA' ? 'badge-activo' : '') ?>" style="<?= $s['estado']==='RECHAZADA' ? 'background:#fbdada;color:#a12b1f;' : '' ?>"><?= e($s['estado']) ?></span>
    </h3>
    <p class="small">Reportado por <?= e($s['reporta_nombre']) ?> (<?= e($s['reporta_cargo']) ?>) — <?= e($s['creado_en']) ?></p>
    <table>
        <tr><th>Serial</th><td><?= e($datos['serial'] ?? '—') ?></td><th>Placa</th><td><?= e($datos['placa'] ?? '—') ?></td></tr>
        <tr><th>Tipo</th><td><?= e($datos['tipo'] ?? '—') ?></td><th>Marca/Modelo</th><td><?= e($datos['marca'] ?? '') ?> <?= e($datos['modelo'] ?? '') ?></td></tr>
        <tr><th>Asignado a</th><td><?= e($datos['asignado_a'] ?? '—') ?></td><th>Estado reportado</th><td><?= e($datos['estado_reportado'] ?? '—') ?></td></tr>
        <tr><th>Observaciones</th><td colspan="3"><?= nl2br(e($datos['observaciones'] ?? '')) ?></td></tr>
    </table>
    <?php if ($s['estado'] === 'PENDIENTE'): ?>
    <form method="post" style="margin-top:10px;">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <input type="text" name="revisor" placeholder="Tu nombre (revisor)" style="margin-right:8px;">
        <button type="submit" name="accion" value="aprobar">✔ Aprobar y aplicar al inventario</button>
        <button type="submit" name="accion" value="rechazar" class="btn-danger">✘ Rechazar</button>
    </form>
    <?php else: ?>
        <p class="small">Revisado por <?= e($s['revisado_por']) ?> el <?= e($s['revisado_en']) ?></p>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$solicitudes): ?><p class="small">No hay solicitudes con ese filtro.</p><?php endif; ?>
<?php layout_fin(); ?>
