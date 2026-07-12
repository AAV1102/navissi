<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Políticas SLA', 'Políticas SLA', '../');
    echo '<div class="msg-error">Solo TI puede gestionar las políticas de SLA.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $datos = [
            'nombre' => limpio($_POST['nombre'] ?? null),
            'descripcion' => limpio($_POST['descripcion'] ?? null),
            'prioridad' => $_POST['prioridad'] ?? 'MEDIA',
            'tiempo_respuesta_horas' => (float) ($_POST['tiempo_respuesta_horas'] ?? 4),
            'tiempo_resolucion_horas' => (float) ($_POST['tiempo_resolucion_horas'] ?? 24),
            'horario_laboral' => limpio($_POST['horario_laboral'] ?? null) ?: 'L-V 8:00-18:00',
            'activo' => isset($_POST['activo']) ? 1 : 0,
        ];
        if (!$datos['nombre']) {
            $msg = ['error', 'El nombre es obligatorio.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE sla_politicas SET {$set} WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Política SLA actualizada.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO sla_politicas ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Política SLA creada.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM sla_politicas WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM sla_politicas WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$politicas = $pdo->query("SELECT * FROM sla_politicas ORDER BY tiempo_respuesta_horas ASC")->fetchAll(PDO::FETCH_ASSOC);

// Cumplimiento real: de los tickets con sla_limite, cuantos se cerraron a tiempo vs vencidos
$totalConSla = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE sla_limite IS NOT NULL")->fetchColumn();
$vencidos = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE sla_limite IS NOT NULL AND
    ((estado NOT IN ('CERRADO','RESUELTO POR IA') AND sla_limite < datetime('now'))
     OR (estado IN ('CERRADO','RESUELTO POR IA') AND cerrado_en IS NOT NULL AND cerrado_en > sla_limite))")->fetchColumn();
$cumplimiento = $totalConSla > 0 ? round((($totalConSla - $vencidos) / $totalConSla) * 100, 1) : 100;

layout_inicio('Políticas SLA', 'Políticas SLA', '../');
?>
<h1><?= icon('shield','icon-lg') ?> Políticas SLA</h1>
<p class="subtitle">Tiempos de respuesta y resolución por prioridad — se aplican automáticamente a cada ticket nuevo en Mesa de Ayuda.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= count($politicas) ?></div><div class="label">Políticas configuradas</div></div>
    <div class="card"><div class="num"><?= $totalConSla ?></div><div class="label">Tickets con SLA aplicado</div></div>
    <div class="card" style="border-left-color:<?= $cumplimiento < 90 ? '#b3392c' : '#0d9488' ?>"><div class="num"><?= $cumplimiento ?>%</div><div class="label">Cumplimiento real de SLA</div></div>
</div>

<div class="panel">
    <h3><?= $editar ? 'Editar política' : 'Nueva política SLA' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Prioridad</label>
                <select name="prioridad">
                    <?php foreach (['URGENTE','ALTA','MEDIA','BAJA'] as $p): ?>
                    <option <?= ($editar['prioridad'] ?? '')===$p?'selected':'' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Tiempo de respuesta (horas) *</label><input type="number" step="0.5" name="tiempo_respuesta_horas" required value="<?= e($editar['tiempo_respuesta_horas'] ?? '4') ?>"></div>
            <div><label>Tiempo de resolución (horas) *</label><input type="number" step="0.5" name="tiempo_resolucion_horas" required value="<?= e($editar['tiempo_resolucion_horas'] ?? '24') ?>"></div>
            <div><label>Horario laboral</label><input type="text" name="horario_laboral" value="<?= e($editar['horario_laboral'] ?? 'L-V 8:00-18:00') ?>"></div>
            <div><label>Activa</label><label style="display:flex;align-items:center;gap:8px;margin-top:8px;"><input type="checkbox" name="activo" <?= ($editar['activo'] ?? 1) ? 'checked' : '' ?> style="width:18px;height:18px;"> Sí</label></div>
        </div>
        <label>Descripción</label>
        <textarea name="descripcion" rows="2" style="width:100%;"><?= e($editar['descripcion'] ?? '') ?></textarea>
        <button type="submit" style="margin-top:10px;"><?= icon('check') ?> <?= $editar ? 'Guardar cambios' : 'Crear política' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="sla_politicas.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Nombre</th><th>Prioridad</th><th>Respuesta</th><th>Resolución</th><th>Horario</th><th>Estado</th><th></th></tr>
    <?php foreach ($politicas as $p): ?>
    <tr>
        <td><?= e($p['nombre']) ?><?php if ($p['descripcion']): ?><br><span class="small"><?= e($p['descripcion']) ?></span><?php endif; ?></td>
        <td><span class="badge badge-otro"><?= e($p['prioridad']) ?></span></td>
        <td><?= e($p['tiempo_respuesta_horas']) ?> h</td>
        <td><?= e($p['tiempo_resolucion_horas']) ?> h</td>
        <td class="small"><?= e($p['horario_laboral']) ?></td>
        <td><span class="badge <?= $p['activo']?'badge-activo':'badge-otro' ?>"><?= $p['activo']?'ACTIVA':'INACTIVA' ?></span></td>
        <td>
            <a href="?editar=<?= (int)$p['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar esta política?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php layout_fin(); ?>
