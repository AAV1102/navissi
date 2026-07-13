<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$dirDocs = __DIR__ . '/../data/documentos';
if (!is_dir($dirDocs)) mkdir($dirDocs, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'subir' && !empty($_FILES['archivo']['tmp_name'])) {
        $original = basename($_FILES['archivo']['name']);
        $seguro = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $original);
        $destino = $dirDocs . '/' . uniqid() . '_' . $seguro;
        if (move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $empleadoDoc = limpio($_POST['empleado_documento'] ?? null);
            $requiereFirma = !empty($_POST['requiere_firma']) && $empleadoDoc ? 1 : 0;
            $pdo->prepare("INSERT INTO documentos (nombre_archivo, ruta, categoria, sede_id, descripcion, subido_por, empleado_documento, requiere_firma) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$original, basename($destino), limpio($_POST['categoria'] ?? null) ?: 'OTRO', $sedeId,
                    limpio($_POST['descripcion'] ?? null), limpio($_POST['subido_por'] ?? null), $empleadoDoc, $requiereFirma]);
            $msg = ['ok', $requiereFirma ? 'Documento enviado - le llegará a la persona para firmar en "Mis Documentos".' : 'Documento subido.'];
        } else {
            $msg = ['error', 'No se pudo guardar el archivo.'];
        }
    } elseif ($accion === 'eliminar') {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare("SELECT ruta FROM documentos WHERE id = ?");
        $stmt->execute([$id]);
        $ruta = $stmt->fetchColumn();
        if ($ruta && file_exists($dirDocs . '/' . $ruta)) @unlink($dirDocs . '/' . $ruta);
        $pdo->prepare("DELETE FROM documentos WHERE id = ?")->execute([$id]);
        $msg = ['ok', 'Documento eliminado.'];
    }
}

if (isset($_GET['descargar'])) {
    $id = (int) $_GET['descargar'];
    $stmt = $pdo->prepare("SELECT nombre_archivo, ruta FROM documentos WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    $ruta = $doc ? $dirDocs . '/' . $doc['ruta'] : null;
    if ($doc && file_exists($ruta)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $doc['nombre_archivo'] . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}

$categoriaFiltro = trim($_GET['categoria'] ?? '');
$sql = "SELECT d.*, s.nombre AS sede_nombre FROM documentos d LEFT JOIN sedes s ON d.sede_id = s.id WHERE 1=1";
$params = [];
if ($categoriaFiltro !== '') { $sql .= " AND d.categoria = ?"; $params[] = $categoriaFiltro; }
$sql .= " ORDER BY d.creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$empleadosDoc = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Documentos', 'Documentos', '../');
?>
<h1><?= icon('folder','icon-lg') ?> Espacio Documental de Tecnología</h1>
<p class="subtitle">Cotizaciones, evidencias, actas y cualquier archivo de soporte de TI, organizados por categoría y sede.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Subir documento</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="subir">
        <div class="grid-form">
            <div><label>Archivo *</label><input type="file" name="archivo" required></div>
            <div><label>Categoría</label>
                <select name="categoria">
                    <?php foreach (['COTIZACION','EVIDENCIA','ACTA','CONTRATO','FACTURA','OTRO'] as $c): ?>
                    <option><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Sede relacionada</label>
                <select name="sede">
                    <option value="">-- ninguna / general --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Subido por</label><input type="text" name="subido_por"></div>
            <div><label>Enviar a un empleado (documento)</label><input type="text" name="empleado_documento" list="lista-empleados-doc" placeholder="Opcional - deja vacío si es general">
                <datalist id="lista-empleados-doc"><?php foreach ($empleadosDoc as $ed): ?><option value="<?= e($ed['documento']) ?>"><?= e($ed['nombres']) ?><?php endforeach; ?></datalist>
            </div>
        </div>
        <label class="small" style="display:flex;align-items:center;gap:8px;margin:6px 0;">
            <input type="checkbox" name="requiere_firma" value="1"> Requiere firma del empleado (contrato, aprobación de permiso, etc.) - aparecerá en su "Mis Documentos" hasta que la confirme.
        </label>
        <textarea name="descripcion" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Descripción breve"></textarea>
        <button type="submit">Subir</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="categoria" onchange="this.form.submit()">
        <option value="">-- todas las categorías --</option>
        <?php foreach (['COTIZACION','EVIDENCIA','ACTA','CONTRATO','FACTURA','OTRO'] as $c): ?>
        <option <?= $categoriaFiltro===$c?'selected':'' ?>><?= $c ?></option>
        <?php endforeach; ?>
    </select>
</form>

<table>
    <tr><th>Archivo</th><th>Categoría</th><th>Sede</th><th>Descripción</th><th>Subido por</th><th>Fecha</th><th></th></tr>
    <?php foreach ($documentos as $d): ?>
    <tr>
        <td><?= e($d['nombre_archivo']) ?></td>
        <td><?= e($d['categoria']) ?></td>
        <td><?= e($d['sede_nombre']) ?></td>
        <td><?= e($d['descripcion']) ?></td>
        <td><?= e($d['subido_por']) ?></td>
        <td class="small"><?= e($d['creado_en']) ?></td>
        <td>
            <a href="?descargar=<?= (int)$d['id'] ?>">Descargar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar este documento?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$documentos): ?><tr><td colspan="7" class="small">No hay documentos con ese filtro.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
