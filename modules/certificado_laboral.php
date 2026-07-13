<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

requiere_login('../');
// Alcance personal: un EMPLEADO sin rol elevado solo puede generar SU PROPIO certificado,
// nunca el de otra cédula por más que la escriba en la URL.
$personalCertLab = alcance_personal();
$documento = $personalCertLab !== null ? (string) ($personalCertLab['documento'] ?? '') : trim($_GET['documento'] ?? '');
$stmt = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre, s.direccion AS sede_direccion, s.ciudad AS sede_ciudad
    FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE e.documento = ?");
$stmt->execute([$documento]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

layout_inicio('Certificado Laboral', 'Certificados RRHH', '../');

if (!$emp) {
    echo '<div class="msg-error">No se encontró ningún empleado con ese documento.</div><a class="btn" href="rrhh_certificados.php">Volver</a>';
    layout_fin();
    exit;
}

$fechaEmision = date('d/m/Y');
setlocale(LC_TIME, 'es_ES.UTF-8', 'esp');
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy = new DateTime();
$fechaTexto = $hoy->format('d') . ' de ' . $meses[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');

$salarioTexto = $emp['salario'] ? '$' . number_format($emp['salario'], 0, ',', '.') . ' pesos m/cte mensuales' : '[salario no registrado en el sistema]';
$ingresoTexto = $emp['fecha_ingreso'] ? date('d \d\e \\M', strtotime($emp['fecha_ingreso'])) : '[fecha no registrada]';
?>
<p class="small no-print">
    <a href="rrhh_certificados.php">← Volver</a> ·
    <button onclick="window.print()" class="btn btn-secondary" style="padding:4px 10px;">🖨 Imprimir</button> ·
    <a class="btn" href="certificado_pdf.php?tipo=laboral&documento=<?= urlencode($emp['documento']) ?>" target="_blank" style="padding:4px 10px;">📄 Descargar PDF real</a>
</p>

<div class="panel" style="max-width:720px;margin:0 auto;line-height:1.8;">
    <h2 style="text-align:center;">CERTIFICADO LABORAL</h2>
    <p style="text-align:right;">Medellín, <?= $fechaTexto ?></p>

    <p><strong>GRUPO 10Z SAS (NAVISSI)</strong> certifica que:</p>

    <p style="text-align:justify;">
        <strong><?= e($emp['nombres']) ?></strong>, identificado(a) con cédula de ciudadanía número
        <strong><?= e($emp['documento']) ?></strong>, labora / laboró en nuestra empresa desempeñando el cargo de
        <strong><?= e($emp['cargo']) ?: '[cargo no registrado]' ?></strong> en el área de <strong><?= e($emp['area']) ?: '[área no registrada]' ?></strong>,
        <?= $emp['fecha_ingreso'] ? "desde el {$ingresoTexto}" : "(fecha de ingreso no registrada en el sistema)" ?>,
        <?= $emp['tipo_contrato'] ? "bajo contrato a {$emp['tipo_contrato']}" : "" ?>,
        con una asignación salarial de <?= $salarioTexto ?>.
    </p>

    <p style="text-align:justify;">
        Sede de trabajo: <?= e($emp['sede_nombre']) ?: '—' ?><?= $emp['sede_direccion'] ? ', ' . e($emp['sede_direccion']) : '' ?>.
        Estado actual: <strong><?= e($emp['estado']) ?></strong>.
    </p>

    <p style="text-align:justify;">
        Se expide la presente certificación a solicitud del interesado(a) para los fines que estime convenientes, el día <?= $fechaTexto ?>.
    </p>

    <div style="margin-top:60px;">
        <p>Atentamente,</p>
        <div style="margin-top:60px;border-top:1px solid var(--ink-900);width:280px;padding-top:6px;">
            Recursos Humanos<br>Grupo 10Z SAS
        </div>
    </div>

    <p class="small" style="margin-top:30px;color:var(--accent-600);">
        <?php if (!$emp['fecha_ingreso'] || !$emp['salario'] || !$emp['tipo_contrato']): ?>
        ⚠ Este certificado tiene datos incompletos (fecha de ingreso, contrato o salario sin registrar). Complétalos en RRHH → editar empleado antes de entregarlo oficialmente.
        <?php endif; ?>
    </p>
</div>

<style>
@media print {
    .topbar, .no-print, nav { display: none !important; }
    main { margin: 0; max-width: 100%; }
}
</style>
<?php layout_fin(); ?>
