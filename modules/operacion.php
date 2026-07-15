<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_login('../');
$pdo = db();
$msg = null;
$puedeGestionar = tiene_rol(['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO', 'COORDINADOR']);
$u = usuario_actual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_checklist' && $puedeGestionar) {
        $nombre = limpio($_POST['nombre'] ?? null);
        if ($nombre) {
            $pdo->prepare("INSERT INTO checklist_operativo (nombre, frecuencia) VALUES (?,?)")
                ->execute([$nombre, limpio($_POST['frecuencia'] ?? null) ?: 'DIARIA']);
            $msg = ['ok', 'Checklist creado.'];
        }
    }

    if ($accion === 'toggle_checklist' && $puedeGestionar) {
        $pdo->prepare("UPDATE checklist_operativo SET activo = 1 - activo WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
        $msg = ['ok', 'Disponibilidad actualizada.'];
    }

    if ($accion === 'registrar_completado') {
        $checklistId = (int) ($_POST['checklist_id'] ?? 0);
        if ($checklistId) {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $pdo->prepare("INSERT INTO checklist_operativo_registros (checklist_id, sede_id, completado_por, observaciones) VALUES (?,?,?,?)")
                ->execute([$checklistId, $sedeId, $u['nombre'] ?? 'Sistema', limpio($_POST['observaciones'] ?? null)]);
            $msg = ['ok', 'Registrado como completado.'];
        }
    }
}

$checklists = $pdo->query("SELECT * FROM checklist_operativo ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$hoy = gmdate('Y-m-d');
$registrosHoy = $pdo->query("SELECT r.*, c.nombre AS checklist_nombre, s.nombre AS sede_nombre FROM checklist_operativo_registros r
    JOIN checklist_operativo c ON c.id = r.checklist_id LEFT JOIN sedes s ON s.id = r.sede_id
    WHERE date(r.completado_en) = date('now') ORDER BY r.completado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$registrosRecientes = $pdo->query("SELECT r.*, c.nombre AS checklist_nombre, s.nombre AS sede_nombre FROM checklist_operativo_registros r
    JOIN checklist_operativo c ON c.id = r.checklist_id LEFT JOIN sedes s ON s.id = r.sede_id
    ORDER BY r.completado_en DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Operación', 'Operación', '../');
?>
<h1><?= icon('check', 'icon-lg') ?> Operación</h1>
<p class="subtitle">Checklists operativos (apertura, cierre, arqueo, limpieza...) con registro real de quién y cuándo se completó.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if ($puedeGestionar): ?>
<div class="panel">
    <h3>Nuevo checklist</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="crear_checklist">
        <div><label>Nombre *</label><input type="text" name="nombre" required placeholder="Apertura de tienda"></div>
        <div><label>Frecuencia</label><select name="frecuencia"><option>DIARIA</option><option>SEMANAL</option><option>MENSUAL</option></select></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Crear</button></div>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Registrar checklist completado</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="registrar_completado">
        <div><label>Checklist *</label><select name="checklist_id" required><option value="">Selecciona...</option><?php foreach ($checklists as $c): if (!$c['activo']) continue; ?><option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?> (<?= e($c['frecuencia']) ?>)</option><?php endforeach; ?></select></div>
        <div><label>Sede</label><input type="text" name="sede"></div>
        <div style="grid-column:1/-1;"><label>Observaciones</label><input type="text" name="observaciones"></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('check') ?> Marcar completado</button></div>
    </form>
</div>

<div class="panel">
    <h3>Completados hoy (<?= count($registrosHoy) ?>)</h3>
    <table>
        <tr><th>Checklist</th><th>Sede</th><th>Completado por</th><th>Hora</th></tr>
        <?php foreach ($registrosHoy as $r): ?>
        <tr><td><?= e($r['checklist_nombre']) ?></td><td><?= e($r['sede_nombre'] ?: '—') ?></td><td><?= e($r['completado_por']) ?></td><td class="small"><?= e($r['completado_en']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$registrosHoy): ?><tr><td colspan="4" class="small">Nada completado hoy todavía.</td></tr><?php endif; ?>
    </table>
</div>

<?php if ($puedeGestionar): ?>
<div class="panel">
    <h3>Checklists configurados</h3>
    <table>
        <tr><th>Nombre</th><th>Frecuencia</th><th>Estado</th><th></th></tr>
        <?php foreach ($checklists as $c): ?>
        <tr>
            <td><?= e($c['nombre']) ?></td><td><?= e($c['frecuencia']) ?></td>
            <td><span class="badge <?= $c['activo'] ? 'badge-activo' : '' ?>"><?= $c['activo'] ? 'ACTIVO' : 'PAUSADO' ?></span></td>
            <td><form method="post" class="inline"><input type="hidden" name="accion" value="toggle_checklist"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button type="submit" style="padding:2px 6px;font-size:11px;"><?= $c['activo'] ? 'Pausar' : 'Activar' ?></button></form></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$checklists): ?><tr><td colspan="4" class="small">Sin checklists todavía.</td></tr><?php endif; ?>
    </table>
</div>
<div class="panel">
    <h3>Historial reciente</h3>
    <table>
        <tr><th>Checklist</th><th>Sede</th><th>Completado por</th><th>Fecha</th></tr>
        <?php foreach ($registrosRecientes as $r): ?>
        <tr><td><?= e($r['checklist_nombre']) ?></td><td><?= e($r['sede_nombre'] ?: '—') ?></td><td><?= e($r['completado_por']) ?></td><td class="small"><?= e($r['completado_en']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$registrosRecientes): ?><tr><td colspan="4" class="small">Sin historial todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
