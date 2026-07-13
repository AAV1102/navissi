<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

requiere_login('../');
// Alcance personal: un EMPLEADO sin rol elevado solo puede generar SU PROPIA certificación.
$personalCertAp = alcance_personal();
$documento = $personalCertAp !== null ? (string) ($personalCertAp['documento'] ?? '') : trim($_GET['documento'] ?? '');
$stmt = $pdo->prepare("SELECT * FROM empleados WHERE documento = ?");
$stmt->execute([$documento]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

layout_inicio('Certificación de Aportes', 'Certificados RRHH', '../');

if (!$emp) {
    echo '<div class="msg-error">No se encontró ningún empleado con ese documento.</div><a class="btn" href="rrhh_certificados.php">Volver</a>';
    layout_fin();
    exit;
}

// Último periodo de nómina real donde se le liquidó salud/pensión, para dar un dato
// concreto y verificable en vez de solo texto genérico.
$stmtUltNom = $pdo->prepare("SELECT n.*, p.nombre AS periodo_nombre FROM nominas n
    JOIN periodos_nomina p ON p.id = n.periodo_id
    WHERE n.empleado_documento = ? ORDER BY p.fecha_inicio DESC LIMIT 1");
$stmtUltNom->execute([$documento]);
$ultimaNomina = $stmtUltNom->fetch(PDO::FETCH_ASSOC);

$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy = new DateTime();
$fechaTexto = $hoy->format('d') . ' de ' . $meses[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');
?>
<p class="small no-print"><a href="rrhh_certificados.php">← Volver</a> · <button onclick="window.print()" class="btn btn-secondary" style="padding:4px 10px;">🖨 Imprimir / Guardar PDF</button></p>

<div class="panel" style="max-width:720px;margin:0 auto;line-height:1.8;">
    <h2 style="text-align:center;">CERTIFICACIÓN DE APORTES A SEGURIDAD SOCIAL Y PRESTACIONES</h2>
    <p style="text-align:right;">Medellín, <?= $fechaTexto ?></p>

    <p><strong>GRUPO 10Z SAS (NAVISSI)</strong> certifica que, en su calidad de empleador, realiza los aportes de ley a nombre de:</p>

    <p style="text-align:justify;">
        <strong><?= e($emp['nombres']) ?></strong>, identificado(a) con cédula de ciudadanía número
        <strong><?= e($emp['documento']) ?></strong>, correspondientes a Salud, Pensión, Riesgos Laborales (ARL),
        Caja de Compensación Familiar y demás prestaciones sociales de ley (cesantías, intereses a las cesantías y prima de servicios),
        conforme a la normatividad laboral colombiana vigente.
    </p>

    <?php if ($ultimaNomina): ?>
    <p style="text-align:justify;">
        Como referencia, en el periodo de nómina <strong><?= e($ultimaNomina['periodo_nombre']) ?></strong> se liquidaron aportes de
        salud por <strong>$<?= number_format((float)$ultimaNomina['salud'], 0, ',', '.') ?></strong> y de pensión por
        <strong>$<?= number_format((float)$ultimaNomina['pension'], 0, ',', '.') ?></strong> (retención del empleado, sobre la cual
        la empresa realiza el aporte patronal complementario de ley).
    </p>
    <?php else: ?>
    <p class="small" style="color:#a12b1f;">⚠ No hay periodos de nómina registrados todavía para este empleado en el sistema - esta certificación se expide de forma general, sin cifras de un periodo específico.</p>
    <?php endif; ?>

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
