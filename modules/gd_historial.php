<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM gd_archivos WHERE id = ?");
$stmt->execute([$id]);
$archivo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$archivo) {
    layout_inicio('Historial', 'Gestión Documental', '../');
    echo '<div class="msg-error">Ese archivo no existe.</div>';
    layout_fin();
    exit;
}

$stmtV = $pdo->prepare("SELECT * FROM gd_versiones WHERE archivo_id = ? ORDER BY version DESC");
$stmtV->execute([$id]);
$versiones = $stmtV->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Historial de versiones', 'Gestión Documental', '../');
?>
<p class="small"><a href="gestion_documental.php?carpeta=<?= (int)$archivo['carpeta_id'] ?>">← Volver a la carpeta</a></p>
<h1><?= icon('file','icon-lg') ?> Historial de <?= e($archivo['nombre_archivo']) ?></h1>
<p class="subtitle">Versión actual: v<?= (int)$archivo['version'] ?> · subida por <?= e($archivo['subido_por']) ?> el <?= e($archivo['creado_en']) ?></p>

<div class="panel">
    <table>
        <tr><th>Versión</th><th>Subido por</th><th>Fecha</th><th></th></tr>
        <tr>
            <td><span class="badge badge-activo">v<?= (int)$archivo['version'] ?> (actual)</span></td>
            <td><?= e($archivo['subido_por']) ?></td>
            <td class="small"><?= e($archivo['creado_en']) ?></td>
            <td><a href="descargar_documento_gd.php?id=<?= (int)$archivo['id'] ?>" target="_blank">Descargar</a></td>
        </tr>
        <?php foreach ($versiones as $v): ?>
        <tr>
            <td><span class="badge badge-otro">v<?= (int)$v['version'] ?></span></td>
            <td><?= e($v['subido_por']) ?></td>
            <td class="small"><?= e($v['creado_en']) ?></td>
            <td><a href="descargar_version_gd.php?id=<?= (int)$v['id'] ?>" target="_blank">Descargar</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php layout_fin(); ?>
