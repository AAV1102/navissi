<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/simple_pdf.php';
$pdo = db();
requiere_login('../');

$tipo = trim($_GET['tipo'] ?? 'laboral');
$personal = alcance_personal();
$documento = $personal !== null ? (string) ($personal['documento'] ?? '') : trim($_GET['documento'] ?? '');
$stmt = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre, s.direccion AS sede_direccion
    FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE e.documento = ?");
$stmt->execute([$documento]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$emp) { http_response_code(404); exit('Empleado no encontrado.'); }
if ($tipo === 'retiro' && strtoupper($emp['estado']) === 'ACTIVO') { http_response_code(400); exit('Este empleado figura como ACTIVO - el certificado de retiro no aplica.'); }

$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy = new DateTime();
$fechaTexto = $hoy->format('d') . ' de ' . $meses[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');
$ingresoTexto = $emp['fecha_ingreso'] ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : '(fecha no registrada)';
$salarioTexto = $emp['salario'] ? '$' . number_format($emp['salario'], 0, ',', '.') . ' pesos m/cte mensuales' : '(salario no registrado)';

$pdf = new SimplePDF();

if ($tipo === 'retiro') {
    $pdf->titulo('CERTIFICADO DE RETIRO', 18);
    $pdf->espacio(10);
    $pdf->parrafo("Medellín, {$fechaTexto}");
    $pdf->espacio(10);
    $pdf->parrafo('GRUPO 10Z SAS (NAVISSI) certifica que:');
    $pdf->espacio(6);
    $pdf->parrafo(
        "{$emp['nombres']}, identificado(a) con cedula de ciudadania numero {$emp['documento']}, laboro en nuestra empresa "
        . "desempeñando el cargo de " . ($emp['cargo'] ?: '(cargo no registrado)') . " en el area de " . ($emp['area'] ?: '(area no registrada)')
        . ", desde el {$ingresoTexto}, y a la fecha se encuentra retirado(a) de la compañia."
    );
    $pdf->espacio(8);
    $pdf->parrafo('Se expide la presente certificacion a solicitud del interesado(a) para los fines que estime convenientes.');
    $nombreArchivo = "certificado_retiro_{$emp['documento']}.pdf";
} elseif ($tipo === 'aportes') {
    $stmtNom = $pdo->prepare("SELECT n.*, p.nombre AS periodo_nombre FROM nominas n JOIN periodos_nomina p ON p.id = n.periodo_id WHERE n.empleado_documento = ? ORDER BY p.fecha_inicio DESC LIMIT 1");
    $stmtNom->execute([$documento]);
    $ultimaNomina = $stmtNom->fetch(PDO::FETCH_ASSOC);

    $pdf->titulo('CERTIFICACION DE APORTES A SEGURIDAD SOCIAL', 16);
    $pdf->espacio(10);
    $pdf->parrafo("Medellín, {$fechaTexto}");
    $pdf->espacio(10);
    $pdf->parrafo('GRUPO 10Z SAS (NAVISSI) certifica que, en su calidad de empleador, realiza los aportes de ley a nombre de:');
    $pdf->espacio(6);
    $pdf->parrafo(
        "{$emp['nombres']}, identificado(a) con cedula de ciudadania numero {$emp['documento']}, correspondientes a Salud, "
        . "Pension, Riesgos Laborales (ARL), Caja de Compensacion Familiar y demas prestaciones sociales de ley, conforme a la "
        . "normatividad laboral colombiana vigente."
    );
    if ($ultimaNomina) {
        $pdf->espacio(8);
        $pdf->parrafo(
            "En el periodo {$ultimaNomina['periodo_nombre']} se liquidaron aportes de salud por $"
            . number_format((float) $ultimaNomina['salud'], 0, ',', '.') . " y de pension por $"
            . number_format((float) $ultimaNomina['pension'], 0, ',', '.') . "."
        );
    }
    $pdf->espacio(8);
    $pdf->parrafo('Se expide la presente certificacion a solicitud del interesado(a) para los fines que estime convenientes.');
    $nombreArchivo = "certificado_aportes_{$emp['documento']}.pdf";
} else {
    $pdf->titulo('CERTIFICADO LABORAL', 18);
    $pdf->espacio(10);
    $pdf->parrafo("Medellín, {$fechaTexto}");
    $pdf->espacio(10);
    $pdf->parrafo('GRUPO 10Z SAS (NAVISSI) certifica que:');
    $pdf->espacio(6);
    $pdf->parrafo(
        "{$emp['nombres']}, identificado(a) con cedula de ciudadania numero {$emp['documento']}, labora/laboro en nuestra empresa "
        . "desempeñando el cargo de " . ($emp['cargo'] ?: '(cargo no registrado)') . " en el area de " . ($emp['area'] ?: '(area no registrada)')
        . ", desde el {$ingresoTexto}, con una asignacion salarial de {$salarioTexto}."
    );
    $pdf->espacio(8);
    $pdf->parrafo('Sede de trabajo: ' . ($emp['sede_nombre'] ?: '-') . ($emp['sede_direccion'] ? ', ' . $emp['sede_direccion'] : '') . '. Estado actual: ' . $emp['estado'] . '.');
    $pdf->espacio(8);
    $pdf->parrafo("Se expide la presente certificacion a solicitud del interesado(a) para los fines que estime convenientes, el dia {$fechaTexto}.");
    $nombreArchivo = "certificado_laboral_{$emp['documento']}.pdf";
}

$pdf->espacio(30);
$pdf->parrafo('Atentamente,');
$pdf->espacio(30);
$pdf->linea();
$pdf->parrafo('Recursos Humanos - Grupo 10Z SAS');

$pdf->salida($nombreArchivo, false);
