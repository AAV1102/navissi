<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['SUPER_ADMIN', 'ADMIN'])) {
    layout_inicio('Personalizar Marca', 'Personalizar Marca', '../');
    echo '<div class="msg-error">Solo un administrador puede cambiar el logo y el nombre del sitio.</div>';
    layout_fin();
    exit;
}

$configPath = __DIR__ . '/../data/marca_config.json';
$uploadsDir = __DIR__ . '/../assets/uploads';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
$config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['nombre_sitio'] = limpio($_POST['nombre_sitio'] ?? null) ?: 'NAVISSI';
    $config['subtitulo'] = limpio($_POST['subtitulo'] ?? null) ?: 'Inventario · Grupo 10Z';
    $colorAcento = trim((string) ($_POST['color_acento'] ?? ''));
    $config['color_acento'] = preg_match('/^#[0-9a-fA-F]{6}$/', $colorAcento) ? $colorAcento : null;
    $config['texto_footer'] = limpio($_POST['texto_footer'] ?? null) ?: null;
    $config['texto_bienvenida_login'] = limpio($_POST['texto_bienvenida_login'] ?? null) ?: null;

    if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $extPermitidas = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $extPermitidas, true) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            $nombreArchivo = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadsDir . '/' . $nombreArchivo)) {
                // Borra el logo anterior para no acumular archivos sueltos
                if (!empty($config['logo']) && file_exists($uploadsDir . '/' . $config['logo'])) {
                    @unlink($uploadsDir . '/' . $config['logo']);
                }
                $config['logo'] = $nombreArchivo;
            }
        } else {
            $msg = ['error', 'El logo debe ser PNG, JPG, SVG o WEBP y pesar menos de 2 MB.'];
        }
    }

    if (!isset($msg)) {
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $msg = ['ok', 'Marca actualizada — recarga cualquier página para verla en el menú.'];
    }
}

layout_inicio('Personalizar Marca', 'Personalizar Marca', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Editor visual en vivo</h1>
<p class="subtitle">Cambia el logo, el nombre, el color de marca y los textos que se ven en todo el sitio — sin tocar código. Se aplica al instante en cuanto guardas.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Logo, nombre y color de marca</h3>
    <form method="post" enctype="multipart/form-data">
        <div class="grid-form">
            <div><label>Nombre del sitio</label><input type="text" name="nombre_sitio" value="<?= e($config['nombre_sitio'] ?? 'NAVISSI') ?>"></div>
            <div><label>Subtítulo</label><input type="text" name="subtitulo" value="<?= e($config['subtitulo'] ?? 'Inventario · Grupo 10Z') ?>"></div>
            <div><label>Color de marca (acento)</label><input type="color" name="color_acento" value="<?= e($config['color_acento'] ?? '#d0342c') ?>" style="height:42px;"></div>
        </div>
        <label>Logo (PNG, JPG, SVG o WEBP — máx. 2 MB)</label>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
        <?php if (!empty($config['logo'])): ?>
        <p class="small" style="margin-top:10px;">Logo actual:</p>
        <img src="../assets/uploads/<?= e($config['logo']) ?>" alt="Logo actual" style="max-width:120px;max-height:60px;border-radius:8px;border:1px solid var(--line);padding:6px;background:var(--navy-900);">
        <?php endif; ?>
        <div class="grid-form" style="margin-top:14px;">
            <div><label>Texto del pie de página</label><input type="text" name="texto_footer" value="<?= e($config['texto_footer'] ?? '') ?>" placeholder="NAVISSI Inventario · Grupo 10Z SAS"></div>
            <div><label>Texto de bienvenida en el login</label><input type="text" name="texto_bienvenida_login" value="<?= e($config['texto_bienvenida_login'] ?? '') ?>" placeholder="La operación detrás de cada tienda."></div>
        </div>
        <br><button type="submit" style="margin-top:14px;"><?= icon('check') ?> Guardar marca</button>
    </form>
</div>
<?php layout_fin(); ?>
