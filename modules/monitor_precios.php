<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/monitor_precios.php';
requiere_roles(['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO', 'COORDINADOR'], '../');
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar_sitio') {
        $nombre = limpio($_POST['nombre'] ?? null);
        $url = trim((string) ($_POST['url'] ?? ''));
        if ($nombre && filter_var($url, FILTER_VALIDATE_URL)) {
            [$tipo, $mensajeTipo] = mp_detectar_tipo($url);
            $pdo->prepare("INSERT INTO monitor_precios_sitios (nombre, url, tipo, creado_por) VALUES (?,?,?,?)")
                ->execute([$nombre, $url, $tipo, usuario_actual()['nombre'] ?? 'Sistema']);
            $msg = ['ok', "Sitio agregado. {$mensajeTipo}"];
        } else {
            $msg = ['error', 'Nombre y una URL válida son obligatorios.'];
        }
    }

    if ($accion === 'toggle_sitio') {
        $pdo->prepare("UPDATE monitor_precios_sitios SET activo = 1 - activo WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
        $msg = ['ok', 'Disponibilidad actualizada.'];
    }

    if ($accion === 'escanear') {
        $sitioId = (int) ($_POST['sitio_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM monitor_precios_sitios WHERE id = ?");
        $stmt->execute([$sitioId]);
        $sitio = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sitio) {
            $resultado = mp_escanear_sitio($pdo, $sitio);
            $msg = $resultado['error']
                ? ['error', "Escaneo con error: {$resultado['error']}"]
                : ['ok', "Escaneo completado: {$resultado['productos']} productos encontrados."];
        }
    }
}

$sitios = $pdo->query("SELECT s.*, (SELECT MAX(id) FROM monitor_precios_escaneos e WHERE e.sitio_id = s.id) AS ultimo_escaneo_id FROM monitor_precios_sitios s ORDER BY s.nombre")->fetchAll(PDO::FETCH_ASSOC);

$sitioVer = (int) ($_GET['sitio'] ?? 0);
$sitioActivo = null;
$productos = [];
$comparacion = null;
$soloDescuento = !empty($_GET['solo_descuento']);
$escaneos = [];
if ($sitioVer) {
    $stmt = $pdo->prepare("SELECT * FROM monitor_precios_sitios WHERE id = ?");
    $stmt->execute([$sitioVer]);
    $sitioActivo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sitioActivo) {
        $stmtE = $pdo->prepare("SELECT * FROM monitor_precios_escaneos WHERE sitio_id = ? ORDER BY id DESC LIMIT 20");
        $stmtE->execute([$sitioVer]);
        $escaneos = $stmtE->fetchAll(PDO::FETCH_ASSOC);
        $ultimoEscaneoId = $escaneos[0]['id'] ?? null;
        if ($ultimoEscaneoId) {
            $sql = "SELECT * FROM monitor_precios_productos WHERE escaneo_id = ?" . ($soloDescuento ? " AND descuento_pct IS NOT NULL" : "") . " ORDER BY (descuento_pct IS NULL), descuento_pct DESC, producto";
            $stmtP = $pdo->prepare($sql);
            $stmtP->execute([$ultimoEscaneoId]);
            $productos = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        }
        if (count($escaneos) >= 2) {
            $comparacion = mp_comparar($pdo, $escaneos[1]['id'], $escaneos[0]['id']);
        }
    }
}

layout_inicio('Monitor de Precios', 'Monitor de Precios', '../');
?>
<h1><?= icon('dollar', 'icon-lg') ?> Monitor de Precios</h1>
<p class="subtitle">Vigila precios de cualquier tienda online: nuestra tienda oficial (navissi.com), cualquier otra tienda Shopify, Zara, o sitios con datos estructurados — todo detectado automáticamente. Trae precio lleno, precio con descuento y % de descuento, no solo el precio final.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Agregar sitio a vigilar</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="agregar_sitio">
        <div><label>Nombre</label><input type="text" name="nombre" required placeholder="Zara Mujer, Tennis..."></div>
        <div style="grid-column:span 2;"><label>URL (tienda, colección o categoría)</label><input type="url" name="url" required placeholder="https://www.zara.com/us/en/woman-shirts-l1217.html"></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Agregar y detectar tipo</button></div>
    </form>
    <p class="small" style="margin-top:8px;">Detecta automáticamente tiendas Shopify (datos limpios) y tiene soporte especial para Zara (precio lleno + descuento real). Cualquier otro sitio se intenta leer por datos estructurados (JSON-LD).</p>
</div>

<div class="panel">
    <h3>Sitios vigilados (<?= count($sitios) ?>)</h3>
    <table>
        <tr><th>Nombre</th><th>Tipo</th><th>URL</th><th>Estado</th><th></th></tr>
        <?php foreach ($sitios as $s): ?>
        <tr>
            <td><a href="?sitio=<?= (int) $s['id'] ?>"><strong><?= e($s['nombre']) ?></strong></a></td>
            <td><span class="badge badge-otro"><?= e(strtoupper($s['tipo'])) ?></span></td>
            <td class="small"><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><?= e(mb_strimwidth($s['url'], 0, 50, '...')) ?></a></td>
            <td><span class="badge <?= $s['activo'] ? 'badge-activo' : '' ?>"><?= $s['activo'] ? 'ACTIVO' : 'PAUSADO' ?></span></td>
            <td>
                <form method="post" class="inline"><input type="hidden" name="accion" value="escanear"><input type="hidden" name="sitio_id" value="<?= (int) $s['id'] ?>"><button type="submit" style="padding:2px 8px;font-size:11px;"><?= icon('zap') ?> Escanear ahora</button></form>
                <form method="post" class="inline"><input type="hidden" name="accion" value="toggle_sitio"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button type="submit" style="padding:2px 6px;font-size:11px;"><?= $s['activo'] ? 'Pausar' : 'Activar' ?></button></form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$sitios): ?><tr><td colspan="5" class="small">Sin sitios agregados todavía.</td></tr><?php endif; ?>
    </table>
</div>

<?php if ($sitioActivo): ?>
<div class="panel">
    <h3><?= icon('inventory') ?> <?= e($sitioActivo['nombre']) ?> — último escaneo</h3>
    <?php if (!$escaneos): ?>
        <p class="small">Todavía no se ha escaneado este sitio — usa "Escanear ahora" arriba.</p>
    <?php else: ?>
    <p class="small">Último escaneo: <?= e($escaneos[0]['creado_en']) ?> UTC · <?= (int) $escaneos[0]['productos_encontrados'] ?> productos<?= $escaneos[0]['error'] ? ' · <span style="color:var(--err-fg)">Error: ' . e($escaneos[0]['error']) . '</span>' : '' ?></p>
    <form method="get" class="toolbar">
        <input type="hidden" name="sitio" value="<?= (int) $sitioVer ?>">
        <label class="ticket-switch"><input type="checkbox" name="solo_descuento" value="1" <?= $soloDescuento ? 'checked' : '' ?> onchange="this.form.requestSubmit()"> Solo en descuento</label>
    </form>
    <table style="margin-top:10px;">
        <tr><th>Producto</th><th>Variante</th><th>Precio lleno</th><th>Descuento</th><th>Precio final</th><th>Disponible</th></tr>
        <?php foreach (array_slice($productos, 0, 300) as $p): ?>
        <tr>
            <td><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener"><?= e($p['producto']) ?></a></td>
            <td class="small"><?= e($p['variante'] ?: '—') ?></td>
            <td><?= $p['precio_antes'] !== null ? '<span style="text-decoration:line-through;color:var(--ink-500);">$' . number_format((float) $p['precio_antes'], 2) . '</span>' : '—' ?></td>
            <td><?= $p['descuento_pct'] !== null ? '<span class="badge badge-err">-' . number_format((float) $p['descuento_pct'], 0) . '%</span>' : '—' ?></td>
            <td><strong>$<?= number_format((float) $p['precio'], 2) ?></strong></td>
            <td><?= $p['disponible'] ? 'Sí' : 'No' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$productos): ?><tr><td colspan="6" class="small">Sin productos<?= $soloDescuento ? ' en descuento' : '' ?> en el último escaneo.</td></tr><?php endif; ?>
    </table>
    <?php if (count($productos) > 300): ?><p class="small">Mostrando 300 de <?= count($productos) ?> productos.</p><?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($comparacion): ?>
<div class="panel">
    <h3><?= icon('bell') ?> Cambios desde el escaneo anterior</h3>
    <div class="cards">
        <div class="card"><div class="num"><?= count($comparacion['nuevos']) ?></div><div class="label">Productos nuevos</div></div>
        <div class="card"><div class="num"><?= count($comparacion['retirados']) ?></div><div class="label">Retirados</div></div>
        <div class="card"><div class="num"><?= count($comparacion['cambios']) ?></div><div class="label">Cambios de precio</div></div>
    </div>
    <?php if ($comparacion['cambios']): ?>
    <table style="margin-top:14px;">
        <tr><th>Producto</th><th>Precio anterior</th><th>Precio nuevo</th><th>Variación</th></tr>
        <?php foreach (array_slice($comparacion['cambios'], 0, 100) as $c): ?>
        <tr>
            <td><a href="<?= e($c['url']) ?>" target="_blank" rel="noopener"><?= e($c['producto']) ?></a></td>
            <td>$<?= number_format((float) $c['precio_anterior'], 2) ?></td>
            <td>$<?= number_format((float) $c['precio_nuevo'], 2) ?></td>
            <td><span class="badge <?= $c['variacion_pct'] < 0 ? 'badge-activo' : 'badge-err' ?>"><?= $c['variacion_pct'] > 0 ? '+' : '' ?><?= e($c['variacion_pct']) ?>%</span></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php layout_fin(); ?>
