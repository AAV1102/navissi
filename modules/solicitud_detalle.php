<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/gobierno_operativo.php';
requiere_login('../');
$pdo = db();
$usuario = usuario_actual();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$solicitud = solicitud_obtener($pdo, $id);
if (!$solicitud) { http_response_code(404); exit('Solicitud no encontrada.'); }
$personal = alcance_personal();
if ($personal !== null && (string)$solicitud['solicitante_documento'] !== (string)$personal['documento']) { http_response_code(403); exit('No autorizado.'); }
if (in_array(rol_efectivo(), ['DIRECTOR','COORDINADOR','RRHH'], true) && alcance_area() !== null && strcasecmp((string)$solicitud['area_responsable'], (string)alcance_area()) !== 0) { http_response_code(403); exit('No autorizado.'); }
$msg = isset($_GET['creada']) ? ['ok', 'Solicitud creada y enviada al responsable.'] : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'resolver') {
    try {
        $decision = strtoupper((string)($_POST['decision'] ?? ''));
        $comentario = trim((string)($_POST['comentario'] ?? ''));
        if (in_array($decision, ['RECHAZAR','ESCALAR'], true) && $comentario === '') throw new InvalidArgumentException('La decisión requiere un comentario.');
        $solicitud = solicitud_resolver($pdo, $id, $decision, $comentario, $usuario);
        $msg = ['ok', 'Decisión registrada. Estado actual: ' . $solicitud['estado'] . '.'];
    } catch (Throwable $e) { $msg = ['error', $e->getMessage()]; }
}
$eventos = solicitud_eventos($pdo, $id);
$puedeAprobar = solicitud_puede_aprobar($solicitud);
$sla = solicitud_sla($solicitud);
$datos = json_decode((string)$solicitud['datos_json'], true) ?: [];
layout_inicio($solicitud['codigo'] ?: 'Solicitud', 'Detalle de solicitud', '../');
?>
<div class="page-kicker">SOLICITUD · TRAZABILIDAD</div><h1><?= icon('check','icon-lg') ?> <?= e($solicitud['codigo'] ?: '#'.$id) ?></h1><p class="subtitle"><?= e($solicitud['servicio_nombre'] ?: $solicitud['tipo']) ?> · <?= e($solicitud['area_responsable']) ?></p>
<?php if ($msg): ?><div class="msg-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div><?php endif; ?>
<div class="approval-detail-grid"><section class="panel"><div class="approval-heading"><div><span class="page-kicker">NECESIDAD</span><h2><?= e($solicitud['servicio_nombre'] ?: $solicitud['tipo']) ?></h2></div><span class="badge <?= $solicitud['estado']==='APROBADA'?'badge-activo':($solicitud['estado']==='RECHAZADA'?'badge-err':'badge-warn') ?>"><?= e($solicitud['estado']) ?></span></div><p class="approval-description"><?= nl2br(e($solicitud['descripcion'])) ?></p><dl class="detail-list"><div><dt>Solicitante</dt><dd><?= e($solicitud['solicitante_nombre']) ?></dd></div><div><dt>Sede</dt><dd><?= e($solicitud['sede_nombre'] ?: 'No aplica') ?></dd></div><div><dt>Prioridad</dt><dd><?= e($solicitud['prioridad']) ?></dd></div><div><dt>Monto</dt><dd><?= $solicitud['monto']!==null?'$'.number_format((float)$solicitud['monto'],0,',','.'):'No aplica' ?></dd></div><div><dt>Nivel actual</dt><dd><?= e($solicitud['nivel_actual']) ?></dd></div><div><dt>SLA</dt><dd><span class="badge <?= $sla==='VENCIDA'?'badge-err':($sla==='EN_TIEMPO'?'badge-activo':'badge-otro') ?>"><?= e($sla) ?></span><br><small><?= e($solicitud['fecha_limite']) ?> UTC</small></dd></div></dl><?php if ($solicitud['ticket_id']): ?><a class="btn" href="ticket_detalle.php?id=<?= (int)$solicitud['ticket_id'] ?>">Abrir ticket #<?= (int)$solicitud['ticket_id'] ?></a><?php endif; ?></section>
<aside><?php if ($puedeAprobar): ?><section class="panel decision-panel"><h3>Registrar decisión</h3><p class="small">Estás decidiendo como <?= e(rol_efectivo()) ?> en el nivel <?= e($solicitud['nivel_actual']) ?>.</p><form method="post"><input type="hidden" name="accion" value="resolver"><input type="hidden" name="id" value="<?= $id ?>"><label>Comentario</label><textarea name="comentario" rows="4" placeholder="Contexto, condiciones o motivo"></textarea><div class="decision-actions"><button name="decision" value="APROBAR"><?= icon('check') ?> Aprobar</button><button class="btn-danger" name="decision" value="RECHAZAR"><?= icon('x') ?> Rechazar</button><?php if ($solicitud['nivel_actual']==='DIRECTOR'): ?><button class="btn-secondary" name="decision" value="ESCALAR">Escalar</button><?php endif; ?></div></form></section><?php else: ?><section class="panel"><h3>Próximo responsable</h3><p><?= $solicitud['estado']==='PENDIENTE'?'Esperando decisión de '.e($solicitud['nivel_actual']).' para '.e($solicitud['area_responsable']).'.':'Esta solicitud ya fue cerrada.' ?></p></section><?php endif; ?></aside></div>
<section class="panel"><h3><?= icon('log') ?> Línea de tiempo</h3><div class="approval-timeline"><?php foreach ($eventos as $evento): ?><div class="timeline-event"><span class="timeline-dot"></span><div><strong><?= e(str_replace('_',' ',$evento['accion'])) ?></strong><p><?= e($evento['actor']) ?> · <?= e($evento['creado_en']) ?> UTC</p><?php if ($evento['comentario']): ?><blockquote><?= e($evento['comentario']) ?></blockquote><?php endif; ?></div></div><?php endforeach; ?><?php if (!$eventos): ?><p class="small">Solicitud histórica sin eventos detallados.</p><?php endif; ?></div></section>
<div class="toolbar"><a class="btn btn-secondary" href="aprobaciones.php">Volver a la bandeja</a><a class="btn btn-secondary" href="catalogo_servicios.php">Catálogo</a><?php if ($solicitud['estado']==='APROBADA' && $solicitud['tipo']==='USR-ALTA-BAJA' && tiene_rol(['ADMIN','TI','RRHH'])): ?><a class="btn" href="identidades.php?solicitud_id=<?= (int)$solicitud['id'] ?>">Crear ciclo de identidad</a><?php endif; ?></div>
<?php layout_fin(); ?>
