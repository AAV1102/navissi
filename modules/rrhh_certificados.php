<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_login('../');
$pdo = db();
$msg = null;
$dirDesp = __DIR__ . '/../data/desprendibles';
if (!is_dir($dirDesp)) mkdir($dirDesp, 0777, true);

// RRHH sube un desprendible para un empleado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir_desprendible') {
    $doc = limpio($_POST['empleado_documento'] ?? null);
    $periodo = limpio($_POST['periodo'] ?? null);
    if ($doc && $periodo && !empty($_FILES['archivo']['tmp_name'])) {
        $original = basename($_FILES['archivo']['name']);
        $seguro = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $original);
        $destino = $dirDesp . '/' . uniqid() . '_' . $seguro;
        if (move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            $pdo->prepare("INSERT INTO desprendibles (empleado_documento, periodo, nombre_archivo, ruta, subido_por) VALUES (?,?,?,?,?)")
                ->execute([$doc, $periodo, $original, basename($destino), limpio($_POST['subido_por'] ?? null) ?: 'RRHH']);
            $msg = ['ok', "Desprendible de {$periodo} cargado para el documento {$doc}."];
        }
    } else {
        $msg = ['error', 'Documento, periodo y archivo son obligatorios.'];
    }
}

if (isset($_GET['descargar'])) {
    $stmt = $pdo->prepare("SELECT nombre_archivo, ruta, empleado_documento FROM desprendibles WHERE id = ?");
    $stmt->execute([(int) $_GET['descargar']]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);
    // Alcance personal: no se puede descargar el desprendible de otra persona cambiando el id en la URL.
    $personalDesc = alcance_personal();
    if ($d && $personalDesc !== null && $d['empleado_documento'] !== $personalDesc['documento']) {
        $d = null;
    }
    $ruta = $d ? $dirDesp . '/' . $d['ruta'] : null;
    if ($d && file_exists($ruta)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $d['nombre_archivo'] . '"');
        readfile($ruta);
        exit;
    }
}

// Consulta por cédula (autoservicio del empleado o de RRHH)
$empleado = null;
$desprendibles = [];
// Alcance personal: un EMPLEADO sin rol elevado con este módulo habilitado individualmente
// NO puede escribir el documento de otra persona - se le fuerza siempre el suyo propio.
$personalCert = alcance_personal();
$documentoConsultado = $personalCert !== null
    ? (string) ($personalCert['documento'] ?? '')
    : trim($_GET['documento'] ?? $_POST['documento_consulta'] ?? '');
if ($documentoConsultado !== '') {
    $stmt = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE e.documento = ?");
    $stmt->execute([$documentoConsultado]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empleado) {
        $stmt = $pdo->prepare("SELECT * FROM desprendibles WHERE empleado_documento = ? ORDER BY periodo DESC");
        $stmt->execute([$documentoConsultado]);
        $desprendibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Comprobantes de nómina generados automáticamente (PDF real desde la nómina
// liquidada), distintos de los "desprendibles" que RRHH sube a mano arriba.
$comprobantesNomina = [];
if ($documentoConsultado !== '' && $empleado) {
    $stmt = $pdo->prepare("SELECT n.id, n.neto_pagar, n.estado, p.nombre AS periodo_nombre
        FROM nominas n JOIN periodos_nomina p ON p.id = n.periodo_id
        WHERE n.empleado_documento = ? ORDER BY p.fecha_inicio DESC");
    $stmt->execute([$documentoConsultado]);
    $comprobantesNomina = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

layout_inicio('Certificados RRHH', 'Certificados RRHH', '../');
?>
<h1><?= icon('dollar','icon-lg') ?> Certificados y Desprendibles - Autoservicio</h1>
<p class="subtitle">El empleado escribe su número de documento y descarga su certificado laboral y desprendibles de pago. RRHH tiene todo centralizado aquí mismo.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if ($personalCert === null): ?>
<div class="panel">
    <h3>Consultar por documento</h3>
    <form method="get">
        <input type="text" name="documento" placeholder="Número de cédula" required value="<?= e($documentoConsultado) ?>" style="min-width:260px;">
        <button type="submit">Consultar</button>
    </form>
</div>
<?php endif; ?>

<?php if ($documentoConsultado !== '' && !$empleado): ?>
<div class="msg-error">No se encontró ningún empleado con el documento <?= e($documentoConsultado) ?>.</div>
<?php elseif ($empleado): ?>
<div class="panel">
    <h3><?= e($empleado['nombres']) ?> <span class="badge <?= $empleado['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($empleado['estado']) ?></span></h3>
    <table>
        <tr><th>Documento</th><td><?= e($empleado['documento']) ?></td><th>Cargo</th><td><?= e($empleado['cargo']) ?></td></tr>
        <tr><th>Área</th><td><?= e($empleado['area']) ?></td><th>Sede</th><td><?= e($empleado['sede_nombre']) ?></td></tr>
        <tr><th>Tipo de contrato</th><td><?= e($empleado['tipo_contrato']) ?: '—' ?></td><th>Fecha de ingreso</th><td><?= e($empleado['fecha_ingreso']) ?: '—' ?></td></tr>
    </table>
    <a class="btn" style="margin-top:12px;" href="certificado_laboral.php?documento=<?= urlencode($empleado['documento']) ?>" target="_blank">📄 Descargar / imprimir certificado laboral</a>
</div>

<div class="panel">
    <h3>Comprobantes de pago de nómina (<?= count($comprobantesNomina) ?>)</h3>
    <?php if (!$comprobantesNomina): ?><p class="small">Aún no hay nómina liquidada para este empleado.</p><?php endif; ?>
    <table>
        <tr><th>Periodo</th><th>Neto pagado</th><th>Estado</th><th></th></tr>
        <?php foreach ($comprobantesNomina as $c): ?>
        <tr>
            <td><?= e($c['periodo_nombre']) ?></td>
            <td>$<?= number_format((float)$c['neto_pagar'],0,',','.') ?></td>
            <td><span class="badge <?= $c['estado']==='PAGADA'?'badge-activo':'badge-otro' ?>"><?= e($c['estado']) ?></span></td>
            <td><a href="comprobante_nomina_pdf.php?id=<?= (int)$c['id'] ?>" target="_blank"><?= icon('file') ?> Descargar PDF</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="panel">
    <h3>Desprendibles de pago (<?= count($desprendibles) ?>)</h3>
    <?php if (!$desprendibles): ?><p class="small">Aún no hay desprendibles cargados para este empleado.</p><?php endif; ?>
    <table>
        <tr><th>Periodo</th><th>Archivo</th><th>Cargado</th><th></th></tr>
        <?php foreach ($desprendibles as $d): ?>
        <tr>
            <td><?= e($d['periodo']) ?></td>
            <td><?= e($d['nombre_archivo']) ?></td>
            <td class="small"><?= e($d['creado_en']) ?></td>
            <td><a href="?descargar=<?= (int)$d['id'] ?>">Descargar</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($personalCert === null): ?>
<div class="panel">
    <h3>RRHH: cargar un desprendible de pago</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="subir_desprendible">
        <div class="grid-form">
            <div><label>Documento del empleado *</label><input type="text" name="empleado_documento" required value="<?= e($documentoConsultado) ?>"></div>
            <div><label>Periodo *</label><input type="text" name="periodo" placeholder="2026-06" required></div>
            <div><label>Archivo (PDF) *</label><input type="file" name="archivo" required></div>
            <div><label>Cargado por</label><input type="text" name="subido_por" placeholder="RRHH"></div>
        </div>
        <button type="submit">Cargar desprendible</button>
    </form>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
