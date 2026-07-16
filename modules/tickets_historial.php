<?php
require_once __DIR__ . '/../config.php'; require_once __DIR__ . '/../lib/layout.php';
$pdo=db(); requiere_login('');
$tickets=$pdo->query("SELECT t.*,s.nombre sede_nombre FROM tickets t LEFT JOIN sedes s ON s.id=t.sede_id WHERE COALESCE(t.archivado,0)=1 ORDER BY t.id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
layout_inicio('Historial de tickets','Historial de tickets','../');
?><h1><?=icon('file','icon-lg')?> Historial de tickets</h1><p class="subtitle">Registros anteriores conservados para auditoría. El panel operativo solo muestra tickets nuevos.</p>
<div class="panel"><table><tr><th>Ticket</th><th>Título</th><th>Solicitante</th><th>Estado</th><th>Fecha</th><th></th></tr><?php foreach($tickets as $t):?><tr><td>#<?=$t['id']?></td><td><?=e($t['titulo'])?></td><td><?=e($t['solicitante'])?></td><td><?=e($t['estado'])?></td><td><?=e($t['creado_en'])?></td><td><a class="btn btn-secondary" href="ticket_detalle.php?id=<?=$t['id']?>">Abrir</a></td></tr><?php endforeach;?><?php if(!$tickets):?><tr><td colspan="6">No hay tickets archivados.</td></tr><?php endif;?></table></div><?php layout_fin();
