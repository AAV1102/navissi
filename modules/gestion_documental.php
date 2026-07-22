<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;
$dirArchivos = __DIR__ . '/../data/gestion_documental';
if (!is_dir($dirArchivos)) mkdir($dirArchivos, 0777, true);

$carpetaId = (int) ($_GET['carpeta'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_carpeta') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if ($nombre) {
            $pdo->prepare("INSERT INTO gd_carpetas (nombre, carpeta_padre_id, area, creado_por, empleado_documento) VALUES (?,?,?,?,?)")
                ->execute([$nombre, $carpetaId ?: null, limpio($_POST['area'] ?? null) ?: null, $u['nombre'], limpio($_POST['empleado_documento'] ?? null) ?: null]);
            $msg = ['ok', 'Carpeta creada.'];
        }
    } elseif ($accion === 'eliminar_carpeta') {
        $pdo->prepare("DELETE FROM gd_carpetas WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Carpeta eliminada (y su contenido).'];
    } elseif ($accion === 'subir_archivo' && !empty($_FILES['archivo']['tmp_name'])) {
        $original = basename($_FILES['archivo']['name']);
        $seguro = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $original);
        $rutaGuardada = uniqid() . '_' . $seguro;
        if (move_uploaded_file($_FILES['archivo']['tmp_name'], $dirArchivos . '/' . $rutaGuardada)) {
            $stmtExiste = $pdo->prepare("SELECT * FROM gd_archivos WHERE carpeta_id = ? AND nombre_archivo = ?");
            $stmtExiste->execute([$carpetaId, $original]);
            $existente = $stmtExiste->fetch(PDO::FETCH_ASSOC);
            if ($existente) {
                // Ya existe un archivo con ese nombre en la carpeta: se guarda como nueva versión.
                $pdo->prepare("INSERT INTO gd_versiones (archivo_id, version, ruta, tamano, subido_por) VALUES (?,?,?,?,?)")
                    ->execute([$existente['id'], $existente['version'], $existente['ruta'], $existente['tamano'], $existente['subido_por']]);
                $pdo->prepare("UPDATE gd_archivos SET ruta = ?, version = version + 1, tamano = ?, subido_por = ?, descripcion = ?, creado_en = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$rutaGuardada, $_FILES['archivo']['size'] ?? null, $u['nombre'], limpio($_POST['descripcion'] ?? null), $existente['id']]);
                $msg = ['ok', "Nueva versión (v" . ($existente['version'] + 1) . ") de \"{$original}\" guardada."];
            } else {
                $pdo->prepare("INSERT INTO gd_archivos (carpeta_id, nombre_archivo, ruta, tipo_mime, tamano, descripcion, subido_por) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$carpetaId, $original, $rutaGuardada, $_FILES['archivo']['type'] ?? null, $_FILES['archivo']['size'] ?? null, limpio($_POST['descripcion'] ?? null), $u['nombre']]);
                $msg = ['ok', "Archivo \"{$original}\" subido."];
            }
        }
    } elseif ($accion === 'eliminar_archivo') {
        $pdo->prepare("DELETE FROM gd_archivos WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Archivo eliminado.'];
    }
}

// Alcance: ADMIN/GERENCIA/CEO ven todo; el resto solo carpetas de su área o sin área (generales).
$verTodo = tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'SUPER_ADMIN']);
$areaUsuario = alcance_area();

$empleadosParaCarpeta = tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'SUPER_ADMIN', 'RRHH'])
    ? $pdo->query("SELECT documento, nombres FROM empleados WHERE estado = 'ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$carpetaActual = null;
if ($carpetaId) {
    $stmt = $pdo->prepare("SELECT * FROM gd_carpetas WHERE id = ?");
    $stmt->execute([$carpetaId]);
    $carpetaActual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$carpetaActual) { $carpetaId = 0; }
    // Igual que en el listado: no se puede entrar directo (por URL) a la
    // carpeta personal de otro empleado aunque no aparezca en la lista.
    if ($carpetaActual && $carpetaActual['empleado_documento'] && !$verTodo && $carpetaActual['empleado_documento'] !== ($u['documento'] ?? null)) {
        layout_inicio('Sin acceso', 'Gestión Documental', '../');
        echo '<div class="msg-error">Esta carpeta es personal de otro empleado.</div>';
        layout_fin();
        exit;
    }
}

$sqlSub = "SELECT * FROM gd_carpetas WHERE carpeta_padre_id " . ($carpetaId ? "= ?" : "IS NULL");
$paramsSub = $carpetaId ? [$carpetaId] : [];
if (!$verTodo) {
    // Una carpeta "personal" de un empleado especifico solo la ve ese mismo
    // empleado (o un rol administrativo general, ya cubierto por $verTodo) -
    // sin esto, cualquiera podia entrar a la carpeta personal de otra persona
    // con solo saber su id, porque "area IS NULL" tambien es cierto ahi.
    $sqlSub .= " AND (empleado_documento IS NULL OR empleado_documento = ?) AND (area IS NULL OR area = ?)";
    $paramsSub[] = $u['documento'] ?? '';
    $paramsSub[] = $areaUsuario;
}
$stmt = $pdo->prepare($sqlSub . " ORDER BY nombre");
$stmt->execute($paramsSub);
$subcarpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$archivos = [];
if ($carpetaId) {
    $stmt = $pdo->prepare("SELECT * FROM gd_archivos WHERE carpeta_id = ? ORDER BY nombre_archivo");
    $stmt->execute([$carpetaId]);
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Migas de pan (ruta de carpetas hasta la raíz)
$migas = [];
$actual = $carpetaActual;
while ($actual) {
    array_unshift($migas, $actual);
    if (!$actual['carpeta_padre_id']) break;
    $stmt = $pdo->prepare("SELECT * FROM gd_carpetas WHERE id = ?");
    $stmt->execute([$actual['carpeta_padre_id']]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
}

layout_inicio('Gestión Documental', 'Gestión Documental', '../');
?>
<h1><?= icon('folder','icon-lg') ?> Gestión Documental</h1>
<p class="subtitle">Carpetas por área, versionado automático y control de acceso — un solo lugar para todos los documentos de la empresa.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<p class="small">
    <a href="gestion_documental.php">Raíz</a>
    <?php foreach ($migas as $m): ?> / <a href="gestion_documental.php?carpeta=<?= (int)$m['id'] ?>"><?= e($m['nombre']) ?></a><?php endforeach; ?>
</p>

<div class="panel">
    <h3><?= icon('plus') ?> Nueva carpeta<?= $carpetaActual ? ' dentro de "' . e($carpetaActual['nombre']) . '"' : '' ?></h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="crear_carpeta">
        <input type="text" name="nombre" placeholder="Nombre de la carpeta" required>
        <select name="area">
            <option value="">General (todos la ven)</option>
            <?php foreach (['TI','RRHH','COMERCIAL','CONTABILIDAD','LOGISTICA','MARKETING','DISEÑO','GERENCIA'] as $a): ?>
            <option value="<?= $a ?>"><?= $a ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($empleadosParaCarpeta): ?>
        <select name="empleado_documento">
            <option value="">No es personal de nadie en particular</option>
            <?php foreach ($empleadosParaCarpeta as $e): ?>
            <option value="<?= e($e['documento']) ?>">Personal de: <?= e($e['nombres']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit">Crear carpeta</button>
    </form>
    <?php if ($empleadosParaCarpeta): ?><p class="small">Si marcas la carpeta como "Personal de" un empleado, todo lo que subas ahí aparece automáticamente en su Portal de Autogestión (ej. desprendibles o certificados que bajaste de Siesa).</p><?php endif; ?>
</div>

<?php if ($carpetaId): ?>
<div class="panel">
    <h3><?= icon('upload') ?> Subir archivo</h3>
    <form method="post" enctype="multipart/form-data" class="toolbar">
        <input type="hidden" name="accion" value="subir_archivo">
        <input type="file" name="archivo" required>
        <input type="text" name="descripcion" placeholder="Descripción (opcional)" style="min-width:200px;">
        <button type="submit">Subir</button>
    </form>
    <p class="small">Si subes un archivo con el mismo nombre que uno existente en esta carpeta, se guarda como nueva versión (no se pierde la anterior).</p>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Carpetas (<?= count($subcarpetas) ?>)</h3>
    <?php if (!$subcarpetas): ?><p class="small">Sin subcarpetas.</p><?php else: ?>
    <table>
        <tr><th>Nombre</th><th>Área</th><th>Creado por</th><th></th></tr>
        <?php foreach ($subcarpetas as $c): ?>
        <tr>
            <td><a href="gestion_documental.php?carpeta=<?= (int)$c['id'] ?>"><?= icon('folder') ?> <?= e($c['nombre']) ?></a></td>
            <td><?= $c['area'] ? e($c['area']) : '<span class="small">General</span>' ?><?= $c['empleado_documento'] ? ' · <span class="badge badge-otro">Personal</span>' : '' ?></td>
            <td class="small"><?= e($c['creado_por']) ?></td>
            <td>
                <form method="post" class="inline" onsubmit="return confirm('¿Eliminar esta carpeta y todo su contenido?');">
                    <input type="hidden" name="accion" value="eliminar_carpeta"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php if ($carpetaId): ?>
<div class="panel">
    <h3>Archivos (<?= count($archivos) ?>)</h3>
    <?php if (!$archivos): ?><p class="small">Sin archivos en esta carpeta.</p><?php else: ?>
    <table>
        <tr><th>Archivo</th><th>Versión</th><th>Descripción</th><th>Subido por</th><th>Fecha</th><th></th></tr>
        <?php foreach ($archivos as $a): ?>
        <tr>
            <td><?= icon('file') ?> <?= e($a['nombre_archivo']) ?></td>
            <td><span class="badge badge-otro">v<?= (int)$a['version'] ?></span></td>
            <td class="small"><?= e($a['descripcion']) ?: '—' ?></td>
            <td class="small"><?= e($a['subido_por']) ?></td>
            <td class="small"><?= e($a['creado_en']) ?></td>
            <td>
                <a href="descargar_documento_gd.php?id=<?= (int)$a['id'] ?>" target="_blank">Descargar</a>
                <?php if ($a['version'] > 1): ?> · <a href="gd_historial.php?id=<?= (int)$a['id'] ?>">Historial</a><?php endif; ?>
                <form method="post" class="inline" onsubmit="return confirm('¿Eliminar este archivo?');">
                    <input type="hidden" name="accion" value="eliminar_archivo"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="btn-danger" style="padding:2px 8px;font-size:11px;">Eliminar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
