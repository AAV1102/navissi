<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI', 'COORDINADOR'])) {
    layout_inicio('Logística y Bodega', 'Logística y Bodega', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar bodega/logística.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_ubicacion') {
    $serial = limpio($_POST['serial'] ?? null);
    $nuevaUbicacion = limpio($_POST['ubicacion'] ?? null);
    if ($serial) {
        $stmt = $pdo->prepare("SELECT ubicacion_bodega FROM inventario WHERE serial = ?");
        $stmt->execute([$serial]);
        $anterior = $stmt->fetchColumn();
        if ($anterior !== false) {
            $pdo->prepare("UPDATE inventario SET ubicacion_bodega = ? WHERE serial = ?")->execute([$nuevaUbicacion, $serial]);
            $pdo->prepare("INSERT INTO movimientos_bodega (equipo_serial, ubicacion_anterior, ubicacion_nueva, movido_por) VALUES (?,?,?,?)")
                ->execute([$serial, $anterior ?: null, $nuevaUbicacion, $u['nombre']]);
            $msg = ['ok', "Ubicación de {$serial} actualizada a \"{$nuevaUbicacion}\"."];
        } else {
            $msg = ['error', "No se encontró ningún equipo con el serial/código \"{$serial}\"."];
        }
    }
}

$busqueda = trim($_GET['q'] ?? '');
$equipoEncontrado = null;
$historialMovimientos = [];
if ($busqueda) {
    $stmt = $pdo->prepare("SELECT i.*, s.nombre AS sede_nombre FROM inventario i LEFT JOIN sedes s ON i.sede_id = s.id WHERE i.serial = ? OR i.placa = ?");
    $stmt->execute([$busqueda, $busqueda]);
    $equipoEncontrado = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($equipoEncontrado) {
        $stmtH = $pdo->prepare("SELECT * FROM movimientos_bodega WHERE equipo_serial = ? ORDER BY id DESC LIMIT 10");
        $stmtH->execute([$equipoEncontrado['serial']]);
        $historialMovimientos = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    }
}

$porUbicacion = $pdo->query("SELECT ubicacion_bodega, COUNT(*) c FROM inventario WHERE ubicacion_bodega IS NOT NULL AND ubicacion_bodega != '' GROUP BY ubicacion_bodega ORDER BY ubicacion_bodega")->fetchAll(PDO::FETCH_ASSOC);
$sinUbicacion = (int) $pdo->query("SELECT COUNT(*) FROM inventario WHERE ubicacion_bodega IS NULL OR ubicacion_bodega = ''")->fetchColumn();

layout_inicio('Logística y Bodega', 'Logística y Bodega', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Logística y Bodega</h1>
<p class="subtitle">Escanea con un lector físico de código de barras/QR (funciona como teclado), con la cámara del celular, o escribe el serial/código manualmente.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel" style="border-left:4px solid var(--accent-600);">
    <h3><?= icon('search') ?> Escanear / buscar equipo</h3>
    <form method="get" class="toolbar" id="form-buscar-equipo">
        <input type="text" name="q" id="campo-busqueda-equipo" value="<?= e($busqueda) ?>" placeholder="Escanea el código o escribe el serial/placa" autofocus style="min-width:320px;font-size:16px;">
        <button type="submit"><?= icon('search') ?> Buscar</button>
        <button type="button" id="btn-camara-equipo" class="btn-secondary"><?= icon('zap') ?> Usar cámara</button>
    </form>
    <div id="camara-equipo-wrap" style="display:none;margin-top:12px;">
        <video id="camara-equipo-video" style="width:100%;max-width:420px;border-radius:8px;border:1px solid var(--line);" playsinline muted></video>
        <p class="small" id="camara-equipo-estado">Apunta la cámara al código de barras o QR del equipo.</p>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('btn-camara-equipo');
    var wrap = document.getElementById('camara-equipo-wrap');
    var video = document.getElementById('camara-equipo-video');
    var estado = document.getElementById('camara-equipo-estado');
    var campo = document.getElementById('campo-busqueda-equipo');
    var form = document.getElementById('form-buscar-equipo');
    var stream = null;

    if (!('BarcodeDetector' in window)) {
        btn.style.display = 'none';
        return;
    }

    btn.addEventListener('click', async function () {
        if (wrap.style.display === 'none') {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                video.srcObject = stream;
                await video.play();
                wrap.style.display = 'block';
                btn.textContent = 'Detener cámara';
                var detector = new BarcodeDetector({ formats: ['qr_code', 'code_128', 'ean_13', 'code_39', 'upc_a'] });
                var intervalo = setInterval(async function () {
                    if (!stream) { clearInterval(intervalo); return; }
                    try {
                        var codigos = await detector.detect(video);
                        if (codigos.length > 0) {
                            campo.value = codigos[0].rawValue;
                            estado.textContent = 'Código detectado: ' + codigos[0].rawValue;
                            clearInterval(intervalo);
                            stream.getTracks().forEach(function (t) { t.stop(); });
                            stream = null;
                            form.requestSubmit();
                        }
                    } catch (e) { /* frame sin lectura valida, se reintenta */ }
                }, 400);
            } catch (e) {
                estado.textContent = 'No se pudo acceder a la cámara: ' + e.message;
            }
        } else {
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            wrap.style.display = 'none';
            btn.textContent = 'Usar cámara';
        }
    });
})();
</script>

<?php if ($busqueda && !$equipoEncontrado): ?>
<div class="msg-error">No se encontró ningún equipo con el serial/código "<?= e($busqueda) ?>".</div>
<?php elseif ($equipoEncontrado): ?>
<div class="panel">
    <h3><?= icon('zap') ?> <?= e($equipoEncontrado['marca']) ?> <?= e($equipoEncontrado['modelo']) ?></h3>
    <table class="deftable">
        <tr><th>Serial</th><td><?= e($equipoEncontrado['serial']) ?></td></tr>
        <tr><th>Placa/código</th><td><?= e($equipoEncontrado['placa']) ?: '—' ?></td></tr>
        <tr><th>Asignado a</th><td><?= e($equipoEncontrado['asignado_a']) ?: '—' ?></td></tr>
        <tr><th>Sede</th><td><?= e($equipoEncontrado['sede_nombre']) ?: '—' ?></td></tr>
        <tr><th>Estado</th><td><span class="badge badge-activo"><?= e($equipoEncontrado['estado']) ?></span></td></tr>
        <tr><th>Ubicación actual en bodega</th><td><strong><?= e($equipoEncontrado['ubicacion_bodega']) ?: 'Sin ubicación asignada' ?></strong></td></tr>
    </table>
    <form method="post" class="toolbar" style="margin-top:14px;">
        <input type="hidden" name="accion" value="actualizar_ubicacion">
        <input type="hidden" name="serial" value="<?= e($equipoEncontrado['serial']) ?>">
        <input type="text" name="ubicacion" placeholder="Ej. Pasillo 3 - Estante B - Nivel 2" value="<?= e($equipoEncontrado['ubicacion_bodega']) ?>" style="min-width:280px;">
        <button type="submit"><?= icon('check') ?> Actualizar ubicación</button>
    </form>

    <?php if ($historialMovimientos): ?>
    <h3 style="margin-top:18px;font-size:13px;">Historial de movimientos</h3>
    <table>
        <tr><th>De</th><th>A</th><th>Por</th><th>Fecha</th></tr>
        <?php foreach ($historialMovimientos as $m): ?>
        <tr>
            <td class="small"><?= e($m['ubicacion_anterior']) ?: '—' ?></td>
            <td class="small"><?= e($m['ubicacion_nueva']) ?: '—' ?></td>
            <td class="small"><?= e($m['movido_por']) ?></td>
            <td class="small"><?= e($m['creado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= count($porUbicacion) ?></div><div class="label">Ubicaciones de bodega en uso</div></div>
    <div class="card" style="border-left-color:#c98a1f"><div class="num"><?= $sinUbicacion ?></div><div class="label">Equipos sin ubicación asignada</div></div>
</div>

<div class="panel">
    <h3><?= icon('inventory') ?> Equipos por ubicación</h3>
    <?php if (!$porUbicacion): ?><p class="small">Aún no hay equipos con ubicación de bodega asignada — búscalos arriba y asígnales una.</p>
    <?php else: ?>
    <table>
        <tr><th>Ubicación</th><th>Equipos</th></tr>
        <?php foreach ($porUbicacion as $r): ?>
        <tr><td><?= e($r['ubicacion_bodega']) ?></td><td><?= (int)$r['c'] ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<p class="small">
    Para imprimir los códigos QR/barras de cada equipo, usa <a href="qr_equipos.php">Códigos QR de Equipos</a> o
    <a href="grupos_codigos.php">Códigos Agrupados</a> — un lector físico de código de barras USB/Bluetooth funciona
    directo en el buscador de arriba, sin configuración adicional (actúa como si escribieras en el teclado).
</p>
<?php layout_fin(); ?>
