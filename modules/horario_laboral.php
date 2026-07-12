<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI', 'RRHH'])) {
    layout_inicio('Horario Laboral', 'Horario Laboral', '../');
    echo '<div class="msg-error">No tienes permiso para editar el horario laboral.</div>';
    layout_fin();
    exit;
}

$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];

$sedeId = (int) ($_GET['sede_id'] ?? 0);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
if (!$sedeId && $sedes) $sedeId = (int) $sedes[0]['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($dias as $num => $nombre) {
        $apertura = limpio($_POST["apertura_{$num}"] ?? null);
        $cierre = limpio($_POST["cierre_{$num}"] ?? null);
        $cerrado = isset($_POST["cerrado_{$num}"]) ? 1 : 0;
        $pdo->prepare("INSERT INTO horarios_sede (sede_id, dia_semana, hora_apertura, hora_cierre, cerrado) VALUES (?,?,?,?,?)
            ON CONFLICT(sede_id, dia_semana) DO UPDATE SET hora_apertura=excluded.hora_apertura, hora_cierre=excluded.hora_cierre, cerrado=excluded.cerrado")
            ->execute([(int) $_POST['sede_id'], $num, $apertura, $cierre, $cerrado]);
    }
    $sedeId = (int) $_POST['sede_id'];
    $msg = ['ok', 'Horario actualizado.'];
}

$horarioActual = [];
if ($sedeId) {
    $stmt = $pdo->prepare("SELECT * FROM horarios_sede WHERE sede_id = ?");
    $stmt->execute([$sedeId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) $horarioActual[$h['dia_semana']] = $h;
}

layout_inicio('Horario Laboral', 'Horario Laboral', '../');
?>
<h1><?= icon('briefcase','icon-lg') ?> Horario Laboral por Sede</h1>
<p class="subtitle">Define el horario de atención de cada sede — se puede usar para calcular SLA en horario hábil y para saber si una tienda está abierta ahora mismo.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form class="toolbar" method="get">
    <select name="sede_id" onchange="this.form.submit()">
        <?php foreach ($sedes as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sedeId==$s['id']?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
    </select>
</form>

<?php if ($sedeId): ?>
<div class="panel">
    <form method="post">
        <input type="hidden" name="sede_id" value="<?= $sedeId ?>">
        <table>
            <tr><th>Día</th><th>Apertura</th><th>Cierre</th><th>Cerrado todo el día</th></tr>
            <?php foreach ($dias as $num => $nombre): $h = $horarioActual[$num] ?? null; ?>
            <tr>
                <td><strong><?= $nombre ?></strong></td>
                <td><input type="time" name="apertura_<?= $num ?>" value="<?= e($h['hora_apertura'] ?? '08:00') ?>"></td>
                <td><input type="time" name="cierre_<?= $num ?>" value="<?= e($h['hora_cierre'] ?? '18:00') ?>"></td>
                <td><input type="checkbox" name="cerrado_<?= $num ?>" <?= ($h['cerrado'] ?? ($num==0?1:0)) ? 'checked' : '' ?> style="width:18px;height:18px;"></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <button type="submit" style="margin-top:14px;"><?= icon('check') ?> Guardar horario</button>
    </form>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
