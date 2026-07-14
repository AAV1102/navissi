<?php
/**
 * Envía el "Pulso operativo" del día por correo a Gerencia/CEO, para que no
 * tengan que entrar a mirar el dashboard. Protegido igual que la sincronización
 * de correo: token derivado del propio dominio, pensado para GitHub Actions.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/mailer.php';
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(60);

$tokenEsperado = hash('sha256', 'navissi-resumen-' . ($_SERVER['HTTP_HOST'] ?? ''));
if (($_GET['token'] ?? '') !== $tokenEsperado) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido.']);
    exit;
}

$pdo = db();
$ticketsAbiertos = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('CERRADO','RESUELTO POR IA')")->fetchColumn();
$ticketsSlaVencido = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('CERRADO','RESUELTO POR IA') AND sla_limite IS NOT NULL AND sla_limite < datetime('now')")->fetchColumn();
$equipos = (int) $pdo->query("SELECT COUNT(*) FROM inventario")->fetchColumn();
$credenciales = (int) $pdo->query("SELECT COUNT(*) FROM credenciales")->fetchColumn();
$sedesActivas = (int) $pdo->query("SELECT COUNT(*) FROM sedes WHERE estado = 'ACTIVO'")->fetchColumn();
$solicitudesPendientes = (int) $pdo->query("SELECT COUNT(*) FROM solicitudes_aprobacion WHERE estado = 'PENDIENTE'")->fetchColumn();
$devolucionesPendientes = (int) $pdo->query("SELECT COUNT(*) FROM devoluciones_producto WHERE estado != 'RESUELTA'")->fetchColumn();
$mermasPendientes = (int) $pdo->query("SELECT COUNT(*) FROM mermas_inventario WHERE estado = 'REPORTADA'")->fetchColumn();
$contratosPorVencer = (int) $pdo->query("SELECT COUNT(*) FROM contratos WHERE estado = 'VIGENTE' AND fecha_fin IS NOT NULL AND fecha_fin BETWEEN date('now') AND date('now','+30 days')")->fetchColumn();

$fecha = (new DateTime())->format('d/m/Y');
$filas = [
    ['Tickets abiertos', $ticketsAbiertos, $ticketsSlaVencido > 0 ? "{$ticketsSlaVencido} con SLA vencido" : 'SLA al día'],
    ['Solicitudes pendientes de aprobación', $solicitudesPendientes, ''],
    ['Devoluciones/garantías sin resolver', $devolucionesPendientes, ''],
    ['Mermas pendientes de aprobar', $mermasPendientes, ''],
    ['Contratos que vencen en 30 días', $contratosPorVencer, ''],
    ['Equipos en inventario', $equipos, ''],
    ['Credenciales registradas', $credenciales, ''],
    ['Sedes activas', $sedesActivas, ''],
];

$cuerpo = "<p style='color:#3d3d3d;'>Resumen operativo del {$fecha}, generado automáticamente.</p><table style='width:100%;border-collapse:collapse;margin-top:12px;'>";
foreach ($filas as [$etiqueta, $valor, $nota]) {
    $cuerpo .= "<tr><td style='padding:8px;border-bottom:1px solid #e5e2dc;'>{$etiqueta}</td><td style='padding:8px;border-bottom:1px solid #e5e2dc;text-align:right;font-weight:700;'>{$valor}</td><td style='padding:8px;border-bottom:1px solid #e5e2dc;color:#767676;font-size:12px;'>{$nota}</td></tr>";
}
$cuerpo .= '</table>';

$destinatarios = $pdo->query("SELECT nombre, email FROM usuarios_sistema WHERE rol IN ('GERENCIA','CEO') AND activo = 1")->fetchAll(PDO::FETCH_ASSOC);
$enviados = 0;
foreach ($destinatarios as $d) {
    if (!$d['email']) continue;
    $html = plantilla_correo_html("Pulso operativo · {$fecha}", $cuerpo, 'Ver dashboard completo', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'grupo10z.com.co') . '/index.php');
    if (enviar_correo($d['email'], "Pulso operativo NAVISSI · {$fecha}", $html, $d['nombre'])) $enviados++;
}

echo json_encode(['ok' => true, 'destinatarios' => count($destinatarios), 'enviados' => $enviados], JSON_UNESCAPED_UNICODE);
