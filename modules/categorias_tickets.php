<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Categorías de Tickets', 'Categorías de Tickets', '../');
    echo '<div class="msg-error">Solo TI puede gestionar categorías.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $nombre = strtoupper(limpio($_POST['nombre'] ?? null) ?: '');
        if (!$nombre) {
            $msg = ['error', 'El nombre es obligatorio.'];
        } else {
            try {
                $pdo->prepare("INSERT INTO categorias_tickets (nombre, descripcion, area_responsable, color, tecnico_default) VALUES (?,?,?,?,?)")
                    ->execute([$nombre, limpio($_POST['descripcion'] ?? null), limpio($_POST['area_responsable'] ?? null), $_POST['color'] ?? '#e31c6c', limpio($_POST['tecnico_default'] ?? null)]);
                $msg = ['ok', 'Categoría creada.'];
            } catch (PDOException $e) {
                $msg = ['error', 'Ya existe una categoría con ese nombre.'];
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $pdo->prepare("UPDATE categorias_tickets SET activa = ? WHERE id = ?")->execute([(int) $_POST['activa'], (int) $_POST['id']]);
        $msg = ['ok', 'Actualizado.'];
    } elseif ($accion === 'guardar_tecnico') {
        $pdo->prepare("UPDATE categorias_tickets SET tecnico_default = ? WHERE id = ?")
            ->execute([limpio($_POST['tecnico_default'] ?? null), (int) $_POST['id']]);
        $msg = ['ok', 'Técnico por defecto actualizado.'];
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM categorias_tickets WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$categorias = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM tickets t WHERE t.categoria = c.nombre) AS uso
    FROM categorias_tickets c ORDER BY c.nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Categorías de Tickets', 'Categorías de Tickets', '../');
?>
<h1><?= icon('ticket','icon-lg') ?> Categorías de Tickets</h1>
<p class="subtitle">Las categorías que ves aquí son las mismas que aparecen en el formulario de Mesa de Ayuda — cámbialas aquí y se actualizan solas allá.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nueva categoría</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required placeholder="Ej. IMPRESORAS"></div>
            <div><label>Área responsable</label><input type="text" name="area_responsable" placeholder="Ej. TI, RRHH..."></div>
            <div><label>Color</label><input type="color" name="color" value="#e31c6c" style="height:38px;padding:2px;"></div>
        </div>
        <label>Descripción</label>
        <input type="text" name="descripcion" style="width:100%;margin-bottom:10px;">
        <label>Técnico por defecto (se asigna automáticamente cuando la IA escala un ticket de correo a esta categoría/área)</label>
        <input type="text" name="tecnico_default" style="width:100%;margin-bottom:10px;" placeholder="Nombre del técnico">
        <button type="submit"><?= icon('check') ?> Crear categoría</button>
    </form>
</div>

<table>
    <tr><th>Color</th><th>Nombre</th><th>Área</th><th>Descripción</th><th>Técnico por defecto</th><th>Tickets con esta categoría</th><th>Estado</th><th></th></tr>
    <?php foreach ($categorias as $c): ?>
    <tr>
        <td><span style="display:inline-block;width:16px;height:16px;border-radius:4px;background:<?= e($c['color']) ?>;"></span></td>
        <td><strong><?= e($c['nombre']) ?></strong></td>
        <td><?= e($c['area_responsable']) ?: '—' ?></td>
        <td><?= e($c['descripcion']) ?: '—' ?></td>
        <td>
            <form method="post" class="inline">
                <input type="hidden" name="accion" value="guardar_tecnico"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input type="text" name="tecnico_default" value="<?= e($c['tecnico_default'] ?? '') ?>" placeholder="Sin asignar" style="width:140px;font-size:12px;" onblur="this.form.requestSubmit()">
            </form>
        </td>
        <td><?= (int)$c['uso'] ?></td>
        <td>
            <form method="post" class="inline">
                <input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" name="activa" value="<?= $c['activa'] ? 0 : 1 ?>" class="badge <?= $c['activa']?'badge-activo':'badge-otro' ?>" style="border:none;cursor:pointer;">
                    <?= $c['activa'] ? 'ACTIVA' : 'INACTIVA' ?>
                </button>
            </form>
        </td>
        <td>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar esta categoría?<?= $c['uso'] > 0 ? ' Hay ' . (int)$c['uso'] . ' tickets que la usan.' : '' ?>');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php layout_fin(); ?>
