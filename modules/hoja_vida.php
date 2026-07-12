<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$tipo = trim($_GET['tipo'] ?? 'EMPLEADO');
$id = trim($_GET['id'] ?? '');

$eventos = [];
if ($id !== '') {
    $stmt = $pdo->prepare("SELECT * FROM hoja_vida WHERE entidad_tipo = ? AND entidad_id = ? ORDER BY id DESC");
    $stmt->execute([$tipo, $id]);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$nombreEntidad = null;
if ($tipo === 'EMPLEADO' && $id) {
    $stmt = $pdo->prepare("SELECT nombres FROM empleados WHERE documento = ?");
    $stmt->execute([$id]);
    $nombreEntidad = $stmt->fetchColumn();
} elseif ($tipo === 'EQUIPO' && $id) {
    $stmt = $pdo->prepare("SELECT marca || ' ' || modelo FROM inventario WHERE serial = ?");
    $stmt->execute([$id]);
    $nombreEntidad = $stmt->fetchColumn();
}

layout_inicio('Hoja de Vida', 'Auditoría', '../');
?>
<h1><?= icon('file','icon-lg') ?> Hoja de Vida</h1>
<p class="subtitle">Trazabilidad completa de un empleado o un equipo: todo lo que ha pasado, en orden, sin poder editarse ni borrarse.</p>

<form class="toolbar" method="get">
    <select name="tipo">
        <option value="EMPLEADO" <?= $tipo==='EMPLEADO'?'selected':'' ?>>Empleado (por documento)</option>
        <option value="EQUIPO" <?= $tipo==='EQUIPO'?'selected':'' ?>>Equipo (por serial)</option>
    </select>
    <input type="text" name="id" value="<?= e($id) ?>" placeholder="<?= $tipo==='EMPLEADO' ? 'Número de documento' : 'Serial del equipo' ?>" style="min-width:220px;">
    <button type="submit">Consultar</button>
</form>

<?php if ($id !== ''): ?>
<div class="panel">
    <h3><?= $nombreEntidad ? e($nombreEntidad) : "Sin nombre encontrado" ?> <span class="small">(<?= e($tipo) ?>: <?= e($id) ?>)</span></h3>
    <table>
        <tr><th>Fecha</th><th>Evento</th><th>Detalle</th><th>Autor</th><th>Ticket</th></tr>
        <?php foreach ($eventos as $ev): ?>
        <tr>
            <td class="small"><?= e($ev['creado_en']) ?></td>
            <td><span class="badge badge-otro"><?= e($ev['evento']) ?></span></td>
            <td><?= e($ev['detalle']) ?></td>
            <td><?= e($ev['autor']) ?></td>
            <td><?= $ev['ticket_id'] ? '<a href="ticket_detalle.php?id='.(int)$ev['ticket_id'].'">#'.(int)$ev['ticket_id'].'</a>' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$eventos): ?><tr><td colspan="5" class="small">Sin eventos registrados todavía para este <?= $tipo==='EMPLEADO'?'empleado':'equipo' ?>.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
