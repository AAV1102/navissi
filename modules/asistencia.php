<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$fechaFiltro = trim($_GET['fecha'] ?? date('Y-m-d'));
$sql = "SELECT * FROM asistencia WHERE fecha = ?";
$params = [$fechaFiltro];
if (alcance_area() !== null) {
    $sql = "SELECT a.* FROM asistencia a JOIN empleados e ON e.documento = a.empleado_documento WHERE a.fecha = ? AND e.area = ?";
    $params[] = alcance_area();
}
$stmt = $pdo->prepare($sql . " ORDER BY hora_entrada");
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Control de Asistencia', 'Control de Asistencia', '../');
?>
<h1><?= icon('briefcase','icon-lg') ?> Control de Asistencia</h1>
<p class="subtitle">Entradas y salidas registradas por cada empleado desde su Portal de Empleado.</p>

<form class="toolbar" method="get">
    <input type="date" name="fecha" value="<?= e($fechaFiltro) ?>" onchange="this.form.submit()">
</form>

<div class="cards">
    <div class="card"><div class="num"><?= count($registros) ?></div><div class="label">Marcaciones ese día</div></div>
    <div class="card"><div class="num"><?= count(array_filter($registros, fn($r) => !$r['hora_salida'])) ?></div><div class="label">Sin marcar salida todavía</div></div>
</div>

<table>
    <tr><th>Empleado</th><th>Entrada</th><th>Salida</th><th>Horas</th></tr>
    <?php foreach ($registros as $r):
        $horas = '—';
        if ($r['hora_entrada'] && $r['hora_salida']) {
            $mins = (strtotime($r['fecha'] . ' ' . $r['hora_salida']) - strtotime($r['fecha'] . ' ' . $r['hora_entrada'])) / 60;
            $horas = $mins > 0 ? round($mins / 60, 1) . ' h' : '—';
        }
    ?>
    <tr>
        <td><?= e($r['empleado_nombre']) ?: e($r['empleado_documento']) ?></td>
        <td><?= e($r['hora_entrada']) ?: '—' ?></td>
        <td><?= e($r['hora_salida']) ?: '<span class="badge badge-warn">Sin salida</span>' ?></td>
        <td><?= $horas ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$registros): ?><tr><td colspan="4" class="small">Sin marcaciones ese día.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
