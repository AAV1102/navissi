<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['ADMIN', 'RRHH', 'TI'])) {
    layout_inicio('Firmas Oficiales', 'Firmas Oficiales', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar firmas oficiales.</div>';
    layout_fin();
    exit;
}

$areasDisponibles = ['RRHH' => 'Recursos Humanos (certificados)', 'TI' => 'TI (actas de equipos)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $area = limpio($_POST['area'] ?? null) ?: 'RRHH';
    $firma = $_POST['firma'] ?? '';
    if ($firma && str_starts_with($firma, 'data:image')) {
        $pdo->prepare("INSERT INTO firmas_oficiales (area, firma_jpeg_base64, nombre_firmante, cargo_firmante, actualizado_por) VALUES (?,?,?,?,?)
            ON CONFLICT(area) DO UPDATE SET firma_jpeg_base64 = excluded.firma_jpeg_base64, nombre_firmante = excluded.nombre_firmante, cargo_firmante = excluded.cargo_firmante, actualizado_por = excluded.actualizado_por, actualizado_en = CURRENT_TIMESTAMP")
            ->execute([$area, $firma, limpio($_POST['nombre_firmante'] ?? null), limpio($_POST['cargo_firmante'] ?? null), $u['nombre']]);
        $msg = ['ok', "Firma oficial de {$area} guardada. A partir de ahora se adjunta automáticamente en los documentos de esa área."];
    } else {
        $msg = ['error', 'Dibuja la firma antes de guardar.'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $pdo->prepare("DELETE FROM firmas_oficiales WHERE area = ?")->execute([$_POST['area'] ?? '']);
    $msg = ['ok', 'Firma eliminada.'];
}

$firmasPorArea = [];
foreach ($pdo->query("SELECT * FROM firmas_oficiales") as $f) { $firmasPorArea[$f['area']] = $f; }

layout_inicio('Firmas Oficiales', 'Firmas Oficiales', '../');
?>
<h1><?= icon('check','icon-lg') ?> Firmas Oficiales Guardadas</h1>
<p class="subtitle">Guarda la firma una sola vez por área — se adjunta automáticamente en certificados, actas y demás documentos generados para esa área.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php foreach ($areasDisponibles as $areaKey => $areaLabel): $existente = $firmasPorArea[$areaKey] ?? null; ?>
<div class="panel">
    <h3><?= icon('shield') ?> <?= e($areaLabel) ?></h3>
    <?php if ($existente): ?>
    <img src="<?= e($existente['firma_jpeg_base64']) ?>" alt="Firma <?= e($areaKey) ?>" style="max-width:280px;border:1px solid var(--line);border-radius:6px;background:#fff;display:block;margin-bottom:8px;">
    <p class="small">Firmante: <?= e($existente['nombre_firmante']) ?: '—' ?> · <?= e($existente['cargo_firmante']) ?: '—' ?> · Actualizada el <?= e($existente['actualizado_en']) ?></p>
    <form method="post" onsubmit="return confirm('¿Quitar esta firma guardada?');" style="margin-bottom:14px;">
        <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="area" value="<?= e($areaKey) ?>">
        <button type="submit" class="btn-danger" style="padding:5px 12px;font-size:12px;">Quitar y volver a firmar</button>
    </form>
    <?php else: ?>
    <form method="post" onsubmit="document.getElementById('firma-<?= $areaKey ?>').value = document.getElementById('canvas-<?= $areaKey ?>').toDataURL('image/jpeg', 0.9);">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="area" value="<?= e($areaKey) ?>">
        <input type="hidden" name="firma" id="firma-<?= $areaKey ?>">
        <div class="grid-form">
            <div><label>Nombre de quien firma</label><input type="text" name="nombre_firmante" value="<?= e($u['nombre']) ?>"></div>
            <div><label>Cargo</label><input type="text" name="cargo_firmante" placeholder="Ej. Coordinadora de RRHH"></div>
        </div>
        <canvas id="canvas-<?= $areaKey ?>" width="400" height="150" style="border:1px solid var(--line);border-radius:6px;background:#fff;width:100%;max-width:400px;touch-action:none;display:block;"></canvas>
        <div class="toolbar" style="margin-top:8px;">
            <button type="button" class="btn-secondary" onclick="document.getElementById('canvas-<?= $areaKey ?>').getContext('2d').clearRect(0,0,400,150)">Limpiar</button>
            <button type="submit"><?= icon('check') ?> Guardar como firma oficial</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<script>
(function () {
    document.querySelectorAll('canvas[id^="canvas-"]').forEach(function (canvas) {
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
    });
})();
</script>
<?php layout_fin(); ?>
