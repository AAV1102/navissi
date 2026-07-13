<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

// Deliberadamente SIN tiene_rol() - cualquier usuario logueado ve su propio espacio
// documental, sin importar rol ni perfil. Cada quien ve solo lo suyo.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'firmar') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM documentos WHERE id = ? AND empleado_documento = ?");
    $stmt->execute([$id, $u['documento']]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($doc && !$doc['firmado_en']) {
        $pdo->prepare("UPDATE documentos SET firmado_en = CURRENT_TIMESTAMP, firmado_ip = ? WHERE id = ?")
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $id]);
        hoja_vida_registrar($pdo, 'EMPLEADO', $u['documento'], 'DOCUMENTO_FIRMADO', $doc['nombre_archivo'], $u['nombre']);
        $msg = ['ok', 'Documento firmado correctamente.'];
    }
}

$misDocumentos = [];
if ($u['documento']) {
    $stmt = $pdo->prepare("SELECT * FROM documentos WHERE empleado_documento = ? ORDER BY (firmado_en IS NULL AND requiere_firma = 1) DESC, creado_en DESC");
    $stmt->execute([$u['documento']]);
    $misDocumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$pendientesFirma = array_filter($misDocumentos, fn($d) => $d['requiere_firma'] && !$d['firmado_en']);

layout_inicio('Mis Documentos', 'Mis Documentos', '../');
?>
<h1><?= icon('folder','icon-lg') ?> Mis Documentos</h1>
<p class="subtitle">Todos tus documentos personales - contratos, aprobaciones, certificados - y lo que RRHH o tu Director te envíen para firmar.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if (!$u['documento']): ?>
<div class="msg-error">Tu usuario no está vinculado a un registro de RRHH (falta el número de documento) - pide que lo completen para ver tus documentos.</div>
<?php else: ?>

<?php if ($pendientesFirma): ?>
<div class="panel" style="border-left:4px solid var(--accent-600);">
    <h3><?= icon('bell') ?> Pendientes de tu firma (<?= count($pendientesFirma) ?>)</h3>
    <table>
        <tr><th>Documento</th><th>Categoría</th><th>Enviado por</th><th>Fecha</th><th></th></tr>
        <?php foreach ($pendientesFirma as $d): ?>
        <tr>
            <td><?= e($d['nombre_archivo']) ?><?= $d['descripcion'] ? '<br><span class="small">' . e($d['descripcion']) . '</span>' : '' ?></td>
            <td><?= e($d['categoria']) ?></td>
            <td><?= e($d['subido_por']) ?: '—' ?></td>
            <td class="small"><?= e($d['creado_en']) ?></td>
            <td>
                <a href="descargar_documento_personal.php?id=<?= (int)$d['id'] ?>" target="_blank" class="small">Ver</a>
                <form method="post" class="inline" onsubmit="return confirm('Al firmar confirmas que leíste y aceptas este documento. ¿Continuar?');">
                    <input type="hidden" name="accion" value="firmar"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <button type="submit"><?= icon('check') ?> Firmar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="panel">
    <h3><?= icon('folder') ?> Todos mis documentos (<?= count($misDocumentos) ?>)</h3>
    <table>
        <tr><th>Documento</th><th>Categoría</th><th>Fecha</th><th>Firma</th><th></th></tr>
        <?php foreach ($misDocumentos as $d): ?>
        <tr>
            <td><?= e($d['nombre_archivo']) ?></td>
            <td><?= e($d['categoria']) ?></td>
            <td class="small"><?= e($d['creado_en']) ?></td>
            <td>
                <?php if (!$d['requiere_firma']): ?><span class="small">No requiere</span>
                <?php elseif ($d['firmado_en']): ?><span class="badge badge-activo"><?= icon('check') ?> Firmado <?= e($d['firmado_en']) ?></span>
                <?php else: ?><span class="badge badge-warn">Pendiente</span>
                <?php endif; ?>
            </td>
            <td><a href="descargar_documento_personal.php?id=<?= (int)$d['id'] ?>" target="_blank">Ver / descargar</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$misDocumentos): ?><tr><td colspan="5" class="small">Aún no tienes documentos personales cargados.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
