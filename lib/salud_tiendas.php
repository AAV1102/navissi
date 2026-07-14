<?php
function salud_tiendas_resumen(PDO $pdo,?string $buscar=null,?string $zona=null): array {
    $inicio=(new DateTimeImmutable('today',new DateTimeZone('America/Bogota')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $sql="SELECT s.id,s.nombre,s.ciudad,s.zona,s.estado,COUNT(DISTINCT i.id) equipos,
      COUNT(DISTINCT CASE WHEN i.ultima_conexion_agente IS NOT NULL THEN i.id END) agentes_instalados,
      COUNT(DISTINCT CASE WHEN i.ultima_conexion_agente>=datetime('now','-24 hours') THEN i.id END) agentes_recientes,
      COUNT(DISTINCT CASE WHEN t.estado NOT IN ('CERRADO','RESUELTO POR IA') THEN t.id END) tickets_abiertos,
      COUNT(DISTINCT CASE WHEN t.estado NOT IN ('CERRADO','RESUELTO POR IA') AND t.prioridad='URGENTE' THEN t.id END) tickets_urgentes,
      COUNT(DISTINCT CASE WHEN t.estado NOT IN ('CERRADO','RESUELTO POR IA') AND t.sla_limite<datetime('now') THEN t.id END) sla_vencidos,
      r.momento ultimo_momento,r.estado ultimo_estado,r.observaciones ultima_observacion,r.ticket_id registro_ticket_id,r.creado_en ultima_validacion,
      CASE WHEN r.creado_en>=:inicio THEN 1 ELSE 0 END validada_hoy
      FROM sedes s LEFT JOIN inventario i ON i.sede_id=s.id AND i.estado!='DADO DE BAJA' LEFT JOIN tickets t ON t.sede_id=s.id
      LEFT JOIN salud_tiendas_registros r ON r.id=(SELECT id FROM salud_tiendas_registros WHERE sede_id=s.id ORDER BY creado_en DESC,id DESC LIMIT 1)
      WHERE s.estado='ACTIVO'";$p=['inicio'=>$inicio];
    if($buscar){$sql.=" AND (s.nombre LIKE :b OR s.ciudad LIKE :b OR s.zona LIKE :b)";$p['b']="%{$buscar}%";}if($zona){$sql.=" AND s.zona=:z";$p['z']=$zona;}$sql.=" GROUP BY s.id ORDER BY s.nombre";
    $st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$r){$r['puntaje']=salud_tienda_puntaje($r);$r['nivel']=salud_tienda_nivel($r);}return $rows;
}
function salud_tienda_puntaje(array $r): int {$p=100-min(45,(int)$r['sla_vencidos']*22)-min(20,(int)$r['tickets_urgentes']*15)-min(15,(int)$r['tickets_abiertos']*3);$i=(int)$r['agentes_instalados'];if($i)$p-=(int)round((max(0,$i-(int)$r['agentes_recientes'])/$i)*20);if(!(int)$r['validada_hoy'])$p-=8;if(($r['ultimo_estado']??'')==='ALERTA')$p-=15;if(($r['ultimo_estado']??'')==='BLOQUEO')$p-=35;return max(0,min(100,$p));}
function salud_tienda_nivel(array $r): string {if(($r['ultimo_estado']??'')==='BLOQUEO'||(int)$r['sla_vencidos']>=2||(int)$r['puntaje']<50)return 'CRITICO';if(($r['ultimo_estado']??'')==='ALERTA'||(int)$r['puntaje']<80||!(int)$r['validada_hoy'])return 'ATENCION';return 'ESTABLE';}
