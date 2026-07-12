<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Plantillas de Correo', 'Plantillas de Correo', '../');
    echo '<div class="msg-error">Solo TI puede gestionar plantillas de correo.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $datos = [
            'nombre' => limpio($_POST['nombre'] ?? null),
            'evento' => limpio($_POST['evento'] ?? null),
            'asunto' => limpio($_POST['asunto'] ?? null),
            'cuerpo' => $_POST['cuerpo'] ?? '',
            'activa' => isset($_POST['activa']) ? 1 : 0,
        ];
        if (!$datos['nombre'] || !$datos['asunto'] || !$datos['cuerpo']) {
            $msg = ['error', 'Nombre, asunto y cuerpo son obligatorios.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE plantillas_correo SET {$set} WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Plantilla actualizada.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO plantillas_correo ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Plantilla creada.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM plantillas_correo WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM plantillas_correo WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$plantillas = $pdo->query("SELECT * FROM plantillas_correo ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Plantillas de Correo', 'Plantillas de Correo', '../');
?>
<h1><?= icon('chat','icon-lg') ?> Plantillas de Correo</h1>
<p class="subtitle">Textos reutilizables para las notificaciones automáticas de tickets. Usa <code>{id}</code>, <code>{titulo}</code>, <code>{solicitante}</code> como variables — se reemplazan al enviar.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar plantilla' : 'Nueva plantilla' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Evento (opcional, para automatizar)</label>
                <select name="evento">
                    <option value="">-- manual --</option>
                    <?php foreach (['TICKET_CREADO'=>'Ticket creado','TICKET_RESUELTO'=>'Ticket resuelto','TICKET_ASIGNADO'=>'Ticket asignado','SLA_ALERTA'=>'SLA por vencer'] as $val=>$label): ?>
                    <option value="<?= $val ?>" <?= ($editar['evento'] ?? '')===$val?'selected':'' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Activa</label><label style="display:flex;align-items:center;gap:8px;margin-top:8px;"><input type="checkbox" name="activa" <?= ($editar['activa'] ?? 1) ? 'checked' : '' ?> style="width:18px;height:18px;"> Sí</label></div>
        </div>
        <label>Asunto *</label>
        <input type="text" name="asunto" required style="width:100%;margin-bottom:10px;" value="<?= e($editar['asunto'] ?? '') ?>">
        <label>Cuerpo *</label>
        <textarea name="cuerpo" rows="6" required style="width:100%;font-family:inherit;"><?= e($editar['cuerpo'] ?? '') ?></textarea>
        <button type="submit" style="margin-top:10px;"><?= icon('check') ?> <?= $editar ? 'Guardar cambios' : 'Crear plantilla' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="plantillas_correo.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Nombre</th><th>Evento</th><th>Asunto</th><th>Estado</th><th></th></tr>
    <?php foreach ($plantillas as $p): ?>
    <tr>
        <td><?= e($p['nombre']) ?></td>
        <td><?= $p['evento'] ? '<span class="badge badge-otro">' . e($p['evento']) . '</span>' : '<span class="small">manual</span>' ?></td>
        <td><?= e($p['asunto']) ?></td>
        <td><span class="badge <?= $p['activa']?'badge-activo':'badge-otro' ?>"><?= $p['activa']?'ACTIVA':'INACTIVA' ?></span></td>
        <td>
            <a href="?editar=<?= (int)$p['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar esta plantilla?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php layout_fin(); ?>
