<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;

$stmt = $pdo->prepare("SELECT * FROM actas_equipos WHERE id = ?");
$stmt->execute([$id]);
$acta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$acta) {
    layout_inicio('Acta no encontrada', 'Actas de Equipos', '../');
    echo '<div class="msg-error">Esa acta no existe.</div>';
    layout_fin();
    exit;
}

$esTI = tiene_rol(['ADMIN', 'TI', 'RRHH']);
$esElEmpleado = !empty($u['documento']) && $u['documento'] === $acta['empleado_documento'];
if (!$esTI && !$esElEmpleado) {
    layout_inicio('Sin acceso', 'Actas de Equipos', '../');
    echo '<div class="msg-error">No tienes permiso para ver esta acta.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $firmaDataUrl = $_POST['firma'] ?? '';
    if ($accion === 'firmar_entrega' && $esTI && !$acta['firma_entrega'] && $firmaDataUrl) {
        $pdo->prepare("UPDATE actas_equipos SET firma_entrega = ?, firmado_entrega_por = ?, firmado_entrega_en = CURRENT_TIMESTAMP, firmado_entrega_ip = ? WHERE id = ?")
            ->execute([$firmaDataUrl, $u['nombre'], $_SERVER['REMOTE_ADDR'] ?? null, $id]);
        hoja_vida_registrar($pdo, 'ACTA_EQUIPO', (string) $id, 'FIRMA_ENTREGA', "Acta #{$id} firmada por quien entrega ({$u['nombre']}).", $u['nombre']);
        $msg = ['ok', 'Firma de entrega registrada.'];
    } elseif ($accion === 'firmar_empleado' && $esElEmpleado && !$acta['firma_empleado'] && $firmaDataUrl) {
        $pdo->prepare("UPDATE actas_equipos SET firma_empleado = ?, firmado_empleado_en = CURRENT_TIMESTAMP, firmado_empleado_ip = ? WHERE id = ?")
            ->execute([$firmaDataUrl, $_SERVER['REMOTE_ADDR'] ?? null, $id]);
        hoja_vida_registrar($pdo, 'ACTA_EQUIPO', (string) $id, 'FIRMA_EMPLEADO', "Acta #{$id} firmada por el empleado ({$acta['empleado_nombre']}).", $acta['empleado_nombre']);
        $msg = ['ok', 'Firma registrada. Gracias.'];
    }
    $stmt->execute([$id]);
    $acta = $stmt->fetch(PDO::FETCH_ASSOC);
}

layout_inicio("Acta #{$id}", 'Actas de Equipos', '../');
?>
<p class="small"><a href="actas_equipos.php">← Volver a Actas de Equipos</a></p>
<?php
$tipoEtiquetas = ['ENTREGA' => 'Entrega', 'DEVOLUCION' => 'Devolución', 'PRESTAMO_TEMPORAL' => 'Préstamo temporal',
    'BAJA' => 'Baja', 'MANTENIMIENTO' => 'Mantenimiento', 'CAMBIO_REPUESTO' => 'Cambio de repuesto'];
?>
<h1><?= icon('file','icon-lg') ?> Acta de <?= e($tipoEtiquetas[$acta['tipo']] ?? $acta['tipo']) ?> de Equipo #<?= (int)$acta['id'] ?></h1>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('sliders') ?> Datos del acta</h3>
    <table class="deftable">
        <tr><th>Tipo</th><td><?= e($acta['tipo']) ?></td></tr>
        <tr><th>Empleado</th><td><?= e($acta['empleado_nombre']) ?: e($acta['empleado_documento']) ?> (<?= e($acta['empleado_documento']) ?>)</td></tr>
        <tr><th>Equipo</th><td><?= e($acta['equipo_descripcion']) ?: '—' ?> <?= $acta['equipo_serial'] ? '· ' . e($acta['equipo_serial']) : '' ?></td></tr>
        <tr><th>Accesorios</th><td><?= e($acta['accesorios']) ?: '—' ?></td></tr>
        <tr><th>Estado del equipo</th><td><?= e($acta['estado_equipo']) ?: '—' ?></td></tr>
        <tr><th>Observaciones</th><td><?= nl2br(e($acta['observaciones'])) ?: '—' ?></td></tr>
        <tr><th>Creada</th><td class="small"><?= e($acta['creado_en']) ?> por <?= e($acta['creado_por']) ?></td></tr>
    </table>
</div>

<div class="panel-grid-2">
    <div class="panel">
        <h3><?= icon('check') ?> Firma de quien entrega/recibe (TI)</h3>
        <?php if ($acta['firma_entrega']): ?>
        <img src="<?= e($acta['firma_entrega']) ?>" alt="Firma" style="max-width:100%;border:1px solid var(--line);border-radius:6px;background:#fff;">
        <p class="small">Firmado por <?= e($acta['firmado_entrega_por']) ?> el <?= e($acta['firmado_entrega_en']) ?></p>
        <?php elseif ($esTI): ?>
        <canvas id="canvas-entrega" width="400" height="150" style="border:1px solid var(--line);border-radius:6px;background:#fff;width:100%;touch-action:none;"></canvas>
        <div class="toolbar" style="margin-top:8px;">
            <button type="button" class="btn-secondary" onclick="limpiarCanvas('canvas-entrega')">Limpiar</button>
            <button type="button" onclick="guardarFirma('canvas-entrega','firmar_entrega')"><?= icon('check') ?> Guardar firma</button>
        </div>
        <?php else: ?><p class="small">Pendiente — la firma quien entrega/recibe.</p><?php endif; ?>
    </div>

    <div class="panel">
        <h3><?= icon('check') ?> Firma del empleado</h3>
        <?php if ($acta['firma_empleado']): ?>
        <img src="<?= e($acta['firma_empleado']) ?>" alt="Firma" style="max-width:100%;border:1px solid var(--line);border-radius:6px;background:#fff;">
        <p class="small">Firmado el <?= e($acta['firmado_empleado_en']) ?></p>
        <?php elseif ($esElEmpleado): ?>
        <canvas id="canvas-empleado" width="400" height="150" style="border:1px solid var(--line);border-radius:6px;background:#fff;width:100%;touch-action:none;"></canvas>
        <div class="toolbar" style="margin-top:8px;">
            <button type="button" class="btn-secondary" onclick="limpiarCanvas('canvas-empleado')">Limpiar</button>
            <button type="button" onclick="guardarFirma('canvas-empleado','firmar_empleado')"><?= icon('check') ?> Firmar</button>
        </div>
        <?php else: ?><p class="small">Pendiente — la firma <?= e($acta['empleado_nombre']) ?>.</p><?php endif; ?>
    </div>
</div>

<form method="post" id="form-firma" style="display:none;">
    <input type="hidden" name="accion" id="campo-accion">
    <input type="hidden" name="firma" id="campo-firma">
</form>

<script>
function prepararCanvas(id) {
    var canvas = document.getElementById(id);
    if (!canvas) return null;
    var ctx = canvas.getContext('2d');
    ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1c1712';
    var dibujando = false;
    function pos(e) {
        var r = canvas.getBoundingClientRect();
        var x = (e.touches ? e.touches[0].clientX : e.clientX) - r.left;
        var y = (e.touches ? e.touches[0].clientY : e.clientY) - r.top;
        return { x: x * canvas.width / r.width, y: y * canvas.height / r.height };
    }
    function iniciar(e) { dibujando = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
    function mover(e) { if (!dibujando) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
    function terminar() { dibujando = false; }
    canvas.addEventListener('mousedown', iniciar);
    canvas.addEventListener('mousemove', mover);
    canvas.addEventListener('mouseup', terminar);
    canvas.addEventListener('mouseleave', terminar);
    canvas.addEventListener('touchstart', iniciar);
    canvas.addEventListener('touchmove', mover);
    canvas.addEventListener('touchend', terminar);
    return canvas;
}
['canvas-entrega', 'canvas-empleado'].forEach(prepararCanvas);

function limpiarCanvas(id) {
    var canvas = document.getElementById(id);
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
}
function guardarFirma(idCanvas, accion) {
    var canvas = document.getElementById(idCanvas);
    document.getElementById('campo-accion').value = accion;
    document.getElementById('campo-firma').value = canvas.toDataURL('image/png');
    document.getElementById('form-firma').submit();
}
</script>
<?php layout_fin(); ?>
