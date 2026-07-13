<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

requiere_login('../');
// Alcance personal: un EMPLEADO sin rol elevado solo puede generar SU PROPIO certificado.
$personalCertRet = alcance_personal();
$documento = $personalCertRet !== null ? (string) ($personalCertRet['documento'] ?? '') : trim($_GET['documento'] ?? '');
$stmt = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE e.documento = ?");
$stmt->execute([$documento]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

layout_inicio('Certificado de Retiro', 'Certificados RRHH', '../');

if (!$emp) {
    echo '<div class="msg-error">No se encontró ningún empleado con ese documento.</div><a class="btn" href="rrhh_certificados.php">Volver</a>';
    layout_fin();
    exit;
}
if (strtoupper($emp['estado']) === 'ACTIVO') {
    echo '<div class="msg-error">Este empleado figura como ACTIVO en el sistema — el certificado de retiro solo aplica a empleados retirados. Si es un error, corrige el estado en RRHH.</div><a class="btn" href="rrhh_certificados.php">Volver</a>';
    layout_fin();
    exit;
}

$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy = new DateTime();
$fechaTexto = $hoy->format('d') . ' de ' . $meses[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');
$ingresoTexto = $emp['fecha_ingreso'] ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : '[fecha no registrada]';
?>
<p class="small no-print">
    <a href="rrhh_certificados.php">← Volver</a> ·
    <button onclick="window.print()" class="btn btn-secondary" style="padding:4px 10px;">🖨 Imprimir</button> ·
    <a class="btn" href="certificado_pdf.php?tipo=retiro&documento=<?= urlencode($emp['documento']) ?>" target="_blank" style="padding:4px 10px;">📄 Descargar PDF real</a>
</p>

<div class="panel" style="max-width:720px;margin:0 auto;line-height:1.8;">
    <h2 style="text-align:center;">CERTIFICADO DE RETIRO</h2>
    <p style="text-align:right;">Medellín, <?= $fechaTexto ?></p>

    <p><strong>GRUPO 10Z SAS (NAVISSI)</strong> certifica que:</p>

    <p style="text-align:justify;">
        <strong><?= e($emp['nombres']) ?></strong>, identificado(a) con cédula de ciudadanía número
        <strong><?= e($emp['documento']) ?></strong>, laboró en nuestra empresa desempeñando el cargo de
        <strong><?= e($emp['cargo']) ?: '[cargo no registrado]' ?></strong> en el área de <strong><?= e($emp['area']) ?: '[área no registrada]' ?></strong>,
        desde el <?= $ingresoTexto ?>, y a la fecha se encuentra <strong>retirado(a)</strong> de la compañía.
    </p>

    <p style="text-align:justify;">
        Se expide la presente certificación a solicitud del interesado(a) para los fines que estime convenientes.
    </p>

    <div style="margin-top:60px;">
        <p>Atentamente,</p>
        <div style="margin-top:60px;border-top:1px solid #333;width:280px;padding-top:6px;">
            Recursos Humanos<br>Grupo 10Z SAS
        </div>
    </div>
</div>

<style>
@media print {
    .topbar, .no-print, nav { display: none !important; }
    main { margin: 0; max-width: 100%; }
}
</style>
<?php layout_fin(); ?>
