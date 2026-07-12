<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/graph_client.php';
$pdo = db();
$msg = null;
$dirLocal = __DIR__ . '/../data/documentos_rrhh';
if (!is_dir($dirLocal)) mkdir($dirLocal, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'subir' && !empty($_FILES['archivo']['tmp_name'])) {
        $doc = limpio($_POST['empleado_documento'] ?? null);
        $original = basename($_FILES['archivo']['name']);
        $seguro = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $original);
        $destino = $dirLocal . '/' . uniqid() . '_' . $seguro;
        if ($doc && move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            $onedriveUrl = null;
            // Si Microsoft 365 está conectado, se sube también a OneDrive (carpeta Documentos RRHH).
            if (ms365_configurado()) {
                try {
                    $c = ms365_config();
                    $gc = new GraphClient($c['tenant_id'], $c['client_id'], $c['client_secret']);
                    $subidoPor = limpio($_POST['subido_por'] ?? null) ?: 'RRHH';
                    $adminCorreo = trim($_POST['correo_onedrive'] ?? '') ?: null;
                    if ($adminCorreo && filesize($destino) < 4 * 1024 * 1024) {
                        $resultado = $gc->subirArchivoOneDrive($adminCorreo, "Documentos RRHH/{$doc}", $seguro, file_get_contents($destino));
                        $onedriveUrl = $resultado['webUrl'] ?? null;
                    }
                } catch (GraphClientException $e) {
                    $msg = ['error', "Se guardó localmente, pero OneDrive falló: {$e->getMessage()}"];
                }
            }
            $pdo->prepare("INSERT INTO documentos_rrhh (empleado_documento, tipo, nombre_archivo, ruta_local, onedrive_url, subido_por) VALUES (?,?,?,?,?,?)")
                ->execute([$doc, limpio($_POST['tipo'] ?? null) ?: 'CONTRATO', $original, basename($destino), $onedriveUrl, limpio($_POST['subido_por'] ?? null) ?: 'RRHH']);
            if (!$msg) $msg = ['ok', 'Documento cargado' . ($onedriveUrl ? ' y sincronizado a OneDrive.' : ' (solo local; agrega tu correo de OneDrive para sincronizar).')];
        } else {
            $msg = ['error', 'Documento del empleado y archivo son obligatorios.'];
        }
    }

    if ($accion === 'firmar') {
        $id = (int) $_POST['id'];
        $nombreFirma = limpio($_POST['nombre_firma'] ?? null);
        if ($nombreFirma) {
            $pdo->prepare("UPDATE documentos_rrhh SET estado_firma='FIRMADO', firmado_por=?, firmado_en=CURRENT_TIMESTAMP, firmado_ip=? WHERE id=?")
                ->execute([$nombreFirma, $_SERVER['REMOTE_ADDR'] ?? 'local', $id]);
            $msg = ['ok', 'Firmado. Queda registrado el nombre, fecha/hora e IP como evidencia.'];
        }
    }
}

if (isset($_GET['descargar'])) {
    $stmt = $pdo->prepare("SELECT nombre_archivo, ruta_local FROM documentos_rrhh WHERE id = ?");
    $stmt->execute([(int) $_GET['descargar']]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);
    $ruta = $d ? $dirLocal . '/' . $d['ruta_local'] : null;
    if ($d && file_exists($ruta)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $d['nombre_archivo'] . '"');
        readfile($ruta);
        exit;
    }
}

$documentoFiltro = trim($_GET['documento'] ?? '');
$sql = "SELECT * FROM documentos_rrhh WHERE 1=1";
$params = [];
if ($documentoFiltro !== '') { $sql .= " AND empleado_documento = ?"; $params[] = $documentoFiltro; }
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Documentos RRHH', 'Documentos y firmas (OneDrive)', '../');
?>
<h1><?= icon('file','icon-lg') ?> Documentos y Firmas — Recursos Humanos</h1>
<p class="subtitle">Contratos, otrosí y documentos por empleado, sincronizados a OneDrive, con firma electrónica simple (nombre + fecha/hora + IP, quedan en Auditoría).</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Subir documento</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="subir">
        <div class="grid-form">
            <div><label>Documento del empleado *</label><input type="text" name="empleado_documento" required></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach (['CONTRATO','OTROSI','HOJA DE VIDA','CERTIFICADO','INCAPACIDAD','OTRO'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Archivo *</label><input type="file" name="archivo" required></div>
            <div><label>Tu correo (para subir a tu OneDrive)</label><input type="email" name="correo_onedrive" placeholder="rrhh@navissi.com (opcional)"></div>
            <div><label>Subido por</label><input type="text" name="subido_por" placeholder="RRHH"></div>
        </div>
        <button type="submit">Subir</button>
    </form>
    <p class="small" style="margin-top:8px;">Si dejas el correo de OneDrive en blanco, el archivo queda guardado localmente en el software igual (sin perder nada), solo no se sincroniza a la nube.</p>
</div>

<form class="toolbar" method="get">
    <input type="text" name="documento" placeholder="Filtrar por documento del empleado" value="<?= e($documentoFiltro) ?>">
    <button type="submit">Filtrar</button>
</form>

<table>
    <tr><th>Empleado</th><th>Tipo</th><th>Archivo</th><th>OneDrive</th><th>Firma</th><th>Fecha</th><th></th></tr>
    <?php foreach ($documentos as $d): ?>
    <tr>
        <td><?= e($d['empleado_documento']) ?></td>
        <td><?= e($d['tipo']) ?></td>
        <td><?= e($d['nombre_archivo']) ?></td>
        <td><?= $d['onedrive_url'] ? '<a href="'.e($d['onedrive_url']).'" target="_blank">Ver en OneDrive</a>' : '<span class="small">Solo local</span>' ?></td>
        <td>
            <?php if ($d['estado_firma'] === 'FIRMADO'): ?>
                <span class="badge badge-activo">FIRMADO</span>
                <div class="small"><?= e($d['firmado_por']) ?> — <?= e($d['firmado_en']) ?></div>
            <?php else: ?>
                <form method="post" class="inline" onsubmit="return confirm('Al firmar aceptas que este documento queda vinculado a tu nombre, con fecha, hora e IP como evidencia. ¿Continuar?');">
                    <input type="hidden" name="accion" value="firmar"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <input type="text" name="nombre_firma" placeholder="Tu nombre completo" required style="width:150px;">
                    <button type="submit" style="padding:4px 8px;font-size:11px;">Firmar</button>
                </form>
            <?php endif; ?>
        </td>
        <td class="small"><?= e($d['creado_en']) ?></td>
        <td><a href="?descargar=<?= (int)$d['id'] ?>">Descargar</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$documentos): ?><tr><td colspan="7" class="small">Sin documentos todavía.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
