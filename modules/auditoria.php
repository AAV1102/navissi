<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

// Consolida los distintos rastros que el sistema ya guarda por módulo en una
// sola línea de tiempo: importaciones, sincronizaciones Microsoft, y eventos
// de tickets (creación, cambios de estado, asignaciones).
$eventos = [];

foreach ($pdo->query("SELECT fecha, resultado, detalle FROM ms365_sync_log ORDER BY id DESC LIMIT 30") as $r) {
    $eventos[] = ['fecha' => $r['fecha'], 'origen' => 'Microsoft 365', 'evento' => $r['resultado'], 'detalle' => $r['detalle']];
}
foreach ($pdo->query("SELECT fecha, archivo, hoja, motivo FROM importaciones_log ORDER BY id DESC LIMIT 30") as $r) {
    $eventos[] = ['fecha' => $r['fecha'], 'origen' => 'Importación', 'evento' => 'Fila omitida', 'detalle' => "{$r['archivo']} / {$r['hoja']}: {$r['motivo']}"];
}
foreach ($pdo->query("SELECT tc.creado_en AS fecha, tc.autor, tc.comentario, t.id AS ticket_id FROM tickets_comentarios tc JOIN tickets t ON tc.ticket_id = t.id WHERE tc.tipo='SISTEMA' ORDER BY tc.id DESC LIMIT 30") as $r) {
    $eventos[] = ['fecha' => $r['fecha'], 'origen' => "Ticket #{$r['ticket_id']}", 'evento' => 'Cambio', 'detalle' => $r['comentario']];
}
foreach ($pdo->query("SELECT creado_en AS fecha, tipo, responsable, destinatario FROM movimientos_equipos ORDER BY id DESC LIMIT 30") as $r) {
    $eventos[] = ['fecha' => $r['fecha'], 'origen' => 'Movimiento equipo', 'evento' => $r['tipo'], 'detalle' => "Responsable: {$r['responsable']} → {$r['destinatario']}"];
}
foreach ($pdo->query("SELECT creado_en AS fecha, estado, revisado_por FROM solicitudes_actualizacion WHERE estado != 'PENDIENTE' ORDER BY id DESC LIMIT 30") as $r) {
    $eventos[] = ['fecha' => $r['fecha'], 'origen' => 'Solicitud tienda', 'evento' => $r['estado'], 'detalle' => "Revisado por: {$r['revisado_por']}"];
}

usort($eventos, fn($a, $b) => strcmp($b['fecha'] ?? '', $a['fecha'] ?? ''));
$eventos = array_slice($eventos, 0, 100);

layout_inicio('Auditoría', 'Auditoría', '../');
?>
<h1><?= icon('log','icon-lg') ?> Auditoría y Trazabilidad</h1>
<p class="subtitle">Línea de tiempo unificada de lo que pasa en el sistema: sincronizaciones, importaciones, tickets y movimientos de equipos.</p>

<table>
    <tr><th>Fecha</th><th>Origen</th><th>Evento</th><th>Detalle</th></tr>
    <?php foreach ($eventos as $ev): ?>
    <tr>
        <td class="small"><?= e($ev['fecha']) ?></td>
        <td><?= e($ev['origen']) ?></td>
        <td><span class="badge badge-otro"><?= e($ev['evento']) ?></span></td>
        <td><?= e($ev['detalle']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$eventos): ?><tr><td colspan="4" class="small">Sin eventos registrados todavía.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
