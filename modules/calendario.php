<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $titulo = limpio($_POST['titulo'] ?? null);
        $fechaInicio = limpio($_POST['fecha_inicio'] ?? null);
        if (!$titulo || !$fechaInicio) {
            $msg = ['error', 'Título y fecha de inicio son obligatorios.'];
        } else {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $pdo->prepare("INSERT INTO calendario_eventos (titulo, descripcion, tipo, fecha_inicio, fecha_fin, todo_el_dia, responsable, sede_id, creado_por)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$titulo, limpio($_POST['descripcion'] ?? null), $_POST['tipo'] ?? 'REUNION', $fechaInicio,
                    limpio($_POST['fecha_fin'] ?? null) ?: null, isset($_POST['todo_el_dia']) ? 1 : 0,
                    limpio($_POST['responsable'] ?? null), $sedeId, usuario_actual()['nombre'] ?? 'Sistema']);
            $msg = ['ok', 'Evento agregado al calendario.'];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM calendario_eventos WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$mes = trim($_GET['mes'] ?? date('Y-m'));
$eventos = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre FROM calendario_eventos e LEFT JOIN sedes s ON e.sede_id = s.id
    WHERE strftime('%Y-%m', fecha_inicio) = ? ORDER BY fecha_inicio ASC");
$eventos->execute([$mes]);
$eventos = $eventos->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$tipos = ['REUNION' => 'Reunión', 'MANTENIMIENTO' => 'Mantenimiento', 'CAPACITACION' => 'Capacitación', 'VISITA_TIENDA' => 'Visita a tienda', 'RECORDATORIO' => 'Recordatorio', 'VACACIONES' => 'Vacaciones/Permiso'];

layout_inicio('Calendario', 'Calendario', '../');
?>
<h1><?= icon('dashboard','icon-lg') ?> Calendario</h1>
<p class="subtitle">Reuniones, mantenimientos programados, visitas a tienda y capacitaciones — en un solo lugar, ligado a sedes y tickets.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nuevo evento</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Título *</label><input type="text" name="titulo" required></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach ($tipos as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Fecha y hora de inicio *</label><input type="datetime-local" name="fecha_inicio" required></div>
            <div><label>Fecha y hora de fin</label><input type="datetime-local" name="fecha_fin"></div>
            <div><label>Responsable</label><input type="text" name="responsable" value="<?= e(usuario_actual()['nombre'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <label>Descripción</label>
        <textarea name="descripcion" rows="2" style="width:100%;"></textarea>
        <button type="submit" style="margin-top:10px;"><?= icon('check') ?> Agregar al calendario</button>
    </form>
</div>

<form class="toolbar" method="get">
    <input type="month" name="mes" value="<?= e($mes) ?>" onchange="this.form.submit()">
</form>

<table>
    <tr><th>Fecha</th><th>Título</th><th>Tipo</th><th>Responsable</th><th>Sede</th><th></th></tr>
    <?php foreach ($eventos as $e): ?>
    <tr>
        <td class="small"><?= date('d/m/Y H:i', strtotime($e['fecha_inicio'])) ?><?= $e['fecha_fin'] ? ' → ' . date('d/m/Y H:i', strtotime($e['fecha_fin'])) : '' ?></td>
        <td><strong><?= e($e['titulo']) ?></strong><?php if ($e['descripcion']): ?><br><span class="small"><?= e($e['descripcion']) ?></span><?php endif; ?></td>
        <td><span class="badge badge-otro"><?= e($tipos[$e['tipo']] ?? $e['tipo']) ?></span></td>
        <td><?= e($e['responsable']) ?: '—' ?></td>
        <td><?= e($e['sede_nombre']) ?: '—' ?></td>
        <td>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar este evento?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$eventos): ?><tr><td colspan="6" class="small">Sin eventos este mes.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
