<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/simple_pdf.php';
$pdo = db();
requiere_login('../');

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT n.*, p.nombre AS periodo_nombre, p.fecha_inicio, p.fecha_fin
    FROM nominas n JOIN periodos_nomina p ON p.id = n.periodo_id WHERE n.id = ?");
$stmt->execute([$id]);
$n = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$n) { http_response_code(404); exit('Comprobante no encontrado.'); }

// Solo RRHH/Admin/Gerencia/CEO/Director (gestion), o el propio empleado
// descargando su comprobante, pueden ver este PDF.
$u = usuario_actual();
$esPropio = !empty($u['documento']) && $u['documento'] === $n['empleado_documento'];
$esGestion = tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'DIRECTOR', 'RRHH']);
if (!$esPropio && !$esGestion) {
    http_response_code(403);
    exit('No tienes permiso para ver este comprobante.');
}

$stmtEmp = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE e.documento = ?");
$stmtEmp->execute([$n['empleado_documento']]);
$emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy = new DateTime();
$fechaTexto = $hoy->format('d') . ' de ' . $meses[(int)$hoy->format('n') - 1] . ' de ' . $hoy->format('Y');
$fmt = fn($v) => '$' . number_format((float) $v, 0, ',', '.');

$pdf = new SimplePDF();
$pdf->titulo('COMPROBANTE DE PAGO DE NÓMINA', 16);
$pdf->espacio(6);
$pdf->parrafo("Grupo 10Z SAS (NAVISSI) · Expedido el {$fechaTexto}");
$pdf->linea();
$pdf->espacio(10);

$pdf->parrafo('EMPLEADO: ' . $n['empleado_nombre'], 11);
$pdf->parrafo('DOCUMENTO: ' . $n['empleado_documento'], 11);
if ($emp) {
    $pdf->parrafo('CARGO: ' . ($emp['cargo'] ?: '-') . '   ÁREA: ' . ($emp['area'] ?: '-'));
    $pdf->parrafo('SEDE: ' . ($emp['sede_nombre'] ?: '-'));
}
$pdf->parrafo('PERIODO: ' . $n['periodo_nombre'] . ' (' . $n['fecha_inicio'] . ' a ' . $n['fecha_fin'] . ')');
$pdf->espacio(10);
$pdf->linea();
$pdf->espacio(10);

$pdf->titulo('DEVENGADOS', 12);
$pdf->parrafo('Salario base (' . $n['dias_trabajados'] . ' días trabajados): ' . $fmt($n['salario_devengado']));
$pdf->parrafo('Auxilio de transporte: ' . $fmt($n['auxilio_transporte']));
$pdf->parrafo('Otras bonificaciones: ' . $fmt($n['otras_bonificaciones']));
$totalDevengado = (float) $n['salario_devengado'] + (float) $n['auxilio_transporte'] + (float) $n['otras_bonificaciones'];
$pdf->parrafo('TOTAL DEVENGADO: ' . $fmt($totalDevengado), 11);
$pdf->espacio(10);

$pdf->titulo('DEDUCCIONES', 12);
$pdf->parrafo('Salud (4%): ' . $fmt($n['salud']));
$pdf->parrafo('Pensión (4%): ' . $fmt($n['pension']));
$pdf->parrafo('Otras deducciones: ' . $fmt($n['otras_deducciones']));
$totalDeducido = (float) $n['salud'] + (float) $n['pension'] + (float) $n['otras_deducciones'];
$pdf->parrafo('TOTAL DEDUCIDO: ' . $fmt($totalDeducido), 11);
$pdf->espacio(14);
$pdf->linea();
$pdf->espacio(8);
$pdf->titulo('NETO A PAGAR: ' . $fmt($n['neto_pagar']), 15);
$pdf->espacio(20);

$pdf->parrafo('Estado del pago: ' . $n['estado'], 10);
$pdf->espacio(20);
$pdf->parrafo('Este comprobante se genera automáticamente a partir de la nómina liquidada en el sistema NAVISSI y no requiere firma manuscrita.', 9);

$nombreArchivo = 'comprobante_nomina_' . $n['empleado_documento'] . '_' . preg_replace('/[^A-Za-z0-9]/', '_', $n['periodo_nombre']) . '.pdf';
$pdf->salida($nombreArchivo, false);
