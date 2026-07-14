<?php
require_once __DIR__.'/retail_analytics.php';
require_once __DIR__.'/gobierno_secretos.php';

/**
 * Capa de inteligencia verificable: reglas sobre datos NAVISSI, sin enviar
 * información a proveedores externos ni inventar conclusiones con un LLM.
 */

function inteligencia_registrar(PDO $pdo,int $ejecucionId,array $hallazgo):void{
    $sql="INSERT INTO inteligencia_hallazgos(clave_unica,dominio,agente,severidad,titulo,resumen,evidencia_json,accion_url,ultima_ejecucion_id)
          VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(clave_unica) DO UPDATE SET
          dominio=excluded.dominio,agente=excluded.agente,severidad=excluded.severidad,titulo=excluded.titulo,
          resumen=excluded.resumen,evidencia_json=excluded.evidencia_json,accion_url=excluded.accion_url,
          ultima_ejecucion_id=excluded.ultima_ejecucion_id,actualizado_en=CURRENT_TIMESTAMP,resuelto_en=NULL,
          estado=CASE WHEN inteligencia_hallazgos.estado='DESCARTADO' THEN 'DESCARTADO' ELSE 'ACTIVO' END";
    $pdo->prepare($sql)->execute([
        $hallazgo['clave'],$hallazgo['dominio'],$hallazgo['agente'],$hallazgo['severidad'],$hallazgo['titulo'],
        $hallazgo['resumen'],json_encode($hallazgo['evidencia']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        $hallazgo['accion_url']??null,$ejecucionId
    ]);
}

function inteligencia_hallazgo(string $clave,string $dominio,string $agente,string $severidad,string $titulo,string $resumen,array $evidencia,string $url):array{
    return compact('clave','dominio','agente','severidad','titulo','resumen','evidencia')+['accion_url'=>$url];
}

function inteligencia_agente_operacion(PDO $pdo):array{
    $r=[];
    $pendientes=(int)$pdo->query("SELECT COUNT(*) FROM alertas_sistema WHERE estado='ACTIVA' AND (clave_unica LIKE 'APERTURA_PENDIENTE_%' OR clave_unica LIKE 'CIERRE_PENDIENTE_%')")->fetchColumn();
    if($pendientes)$r[]=inteligencia_hallazgo('INT-OPERACION-TIENDAS','OPERACION','Control de Tiendas',$pendientes>=3?'CRITICA':'ALTA','Tiendas sin validación operativa',"Hay {$pendientes} controles de apertura o cierre pendientes.",['controles_pendientes'=>$pendientes],'salud_tiendas.php');
    $devoluciones=(int)$pdo->query("SELECT COUNT(*) FROM devoluciones_producto WHERE estado NOT IN('RESUELTA','CERRADA','RECHAZADA')")->fetchColumn();
    if($devoluciones)$r[]=inteligencia_hallazgo('INT-RETAIL-DEVOLUCIONES','RETAIL','Control Retail',$devoluciones>=10?'ALTA':'MEDIA','Devoluciones pendientes de cierre',"Hay {$devoluciones} devoluciones o garantías sin cierre.",['devoluciones_pendientes'=>$devoluciones],'devoluciones.php');
    $mermas=$pdo->query("SELECT COUNT(*) cantidad,COALESCE(SUM(valor_estimado),0) valor FROM mermas_inventario WHERE estado NOT IN('APROBADA','RECHAZADA','CERRADA')")->fetch(PDO::FETCH_ASSOC);
    if((int)$mermas['cantidad']>0)$r[]=inteligencia_hallazgo('INT-RETAIL-MERMAS','RETAIL','Control Retail',(float)$mermas['valor']>=3000000?'ALTA':'MEDIA','Mermas pendientes de revisión',"Hay {$mermas['cantidad']} reportes por $".number_format((float)$mermas['valor'],0,',','.')." pendientes de decisión.",['reportes'=>(int)$mermas['cantidad'],'valor_estimado'=>(float)$mermas['valor']],'mermas.php');
    $retail=retail_analitica($pdo);if($retail['corte']){$m=$retail['metricas'];$exposicion=(int)$m['quiebres']+(int)$m['riesgos']+(int)$m['huecos_talla'];if($exposicion)$r[]=inteligencia_hallazgo('INT-RETAIL-COBERTURA','RETAIL','Control Retail',(int)$m['quiebres']>0?'CRITICA':'ALTA','Cobertura comercial requiere acción',"Hay {$m['quiebres']} quiebres, {$m['riesgos']} variantes con cobertura baja y {$m['huecos_talla']} huecos de talla.",['fecha_corte'=>$retail['corte'],'quiebres'=>(int)$m['quiebres'],'riesgos'=>(int)$m['riesgos'],'huecos_talla'=>(int)$m['huecos_talla']],'retail_inteligencia.php');if((int)$m['sobrestock']>0)$r[]=inteligencia_hallazgo('INT-RETAIL-SOBRESTOCK','RETAIL','Control Retail','MEDIA','Inventario sin rotación o con sobrestock',"Hay {$m['sobrestock']} combinaciones SKU-tienda sin rotación o por encima del umbral de cobertura.",['fecha_corte'=>$retail['corte'],'combinaciones'=>(int)$m['sobrestock']],'retail_inteligencia.php');}
    return $r;
}

function inteligencia_agente_servicio(PDO $pdo,DateTimeImmutable $now):array{
    $r=[];$fecha=$now->format('Y-m-d H:i:s');
    $sla=(int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN('CERRADO','RESUELTO POR IA') AND sla_limite IS NOT NULL AND sla_limite<'".$fecha."'")->fetchColumn();
    if($sla)$r[]=inteligencia_hallazgo('INT-SERVICIO-SLA','SERVICIO','Mesa de Ayuda',$sla>=5?'CRITICA':'ALTA','SLA vencidos requieren intervención',"Hay {$sla} tickets abiertos fuera de su SLA.",['tickets_sla_vencido'=>$sla],'mesa_ayuda.php');
    $sinAsignar=(int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN('CERRADO','RESUELTO POR IA') AND prioridad IN('URGENTE','ALTA') AND (asignado_a IS NULL OR asignado_a='')")->fetchColumn();
    if($sinAsignar)$r[]=inteligencia_hallazgo('INT-SERVICIO-SIN-ASIGNAR','SERVICIO','Mesa de Ayuda','ALTA','Tickets prioritarios sin responsable',"Hay {$sinAsignar} tickets urgentes o altos sin asignación.",['tickets_sin_asignar'=>$sinAsignar],'mesa_ayuda.php');
    return $r;
}

function inteligencia_agente_identidad(PDO $pdo):array{
    $r=[];
    $sinNavissi=(int)$pdo->query("SELECT COUNT(*) FROM empleados e LEFT JOIN usuarios_sistema u ON u.documento=e.documento AND u.activo=1 WHERE e.estado='ACTIVO' AND u.id IS NULL")->fetchColumn();
    if($sinNavissi)$r[]=inteligencia_hallazgo('INT-IDENTIDAD-SIN-NAVISSI','IDENTIDAD','Gobierno de Identidades','MEDIA','Empleados sin identidad NAVISSI',"Hay {$sinNavissi} empleados activos sin usuario NAVISSI vinculado.",['empleados'=>$sinNavissi],'identidades.php?brecha=SIN_NAVISSI');
    $retiros=(int)$pdo->query("SELECT COUNT(*) FROM empleados e JOIN ms365_usuarios m ON lower(m.correo)=lower(e.email) AND m.cuenta_activa=1 WHERE e.estado!='ACTIVO' AND e.email IS NOT NULL AND e.email!=''")->fetchColumn();
    if($retiros)$r[]=inteligencia_hallazgo('INT-IDENTIDAD-RETIROS','IDENTIDAD','Gobierno de Identidades','CRITICA','Retiros con Microsoft 365 activo',"Hay {$retiros} retiros que conservan una cuenta Microsoft activa.",['retiros_expuestos'=>$retiros],'identidades.php?brecha=RETIRO_ACTIVO');
    return $r;
}

function inteligencia_agente_activos(PDO $pdo,DateTimeImmutable $now):array{
    $r=[];
    $reparacion=(int)$pdo->query("SELECT COUNT(*) FROM inventario WHERE estado='EN REPARACION'")->fetchColumn();
    if($reparacion)$r[]=inteligencia_hallazgo('INT-ACTIVOS-REPARACION','ACTIVOS','Control de Activos',$reparacion>=10?'ALTA':'MEDIA','Equipos concentrados en reparación',"Hay {$reparacion} equipos en reparación.",['equipos'=>$reparacion],'inventario.php?estado=EN%20REPARACION');
    $limite=$now->modify('-7 days')->format('Y-m-d H:i:s');$q=$pdo->prepare("SELECT COUNT(*) FROM inventario WHERE estado='ACTIVO' AND ultima_conexion_agente IS NOT NULL AND ultima_conexion_agente!='' AND ultima_conexion_agente<?");$q->execute([$limite]);$desconectados=(int)$q->fetchColumn();
    if($desconectados)$r[]=inteligencia_hallazgo('INT-ACTIVOS-SIN-AGENTE','ACTIVOS','Control de Activos',$desconectados>=10?'ALTA':'MEDIA','Agentes sin conexión reciente',"Hay {$desconectados} equipos activos sin reporte del agente en siete días.",['equipos'=>$desconectados,'limite_utc'=>$limite],'agente_inventario.php');
    return $r;
}

function inteligencia_agente_finanzas(PDO $pdo,DateTimeImmutable $now):array{
    $r=[];
    $lic=$pdo->query("SELECT COUNT(*) skus,COALESCE(SUM(compradas),0) compradas,COALESCE(SUM(consumidas),0) consumidas FROM ms365_licencias WHERE compradas>0 AND consumidas*100.0/compradas>=90")->fetch(PDO::FETCH_ASSOC);
    if((int)$lic['skus']>0)$r[]=inteligencia_hallazgo('INT-COSTOS-LICENCIAS','COSTOS','Control Financiero','MEDIA','Licencias Microsoft cerca del límite',"{$lic['skus']} referencias de licencia superan 90% de utilización.",['skus'=>(int)$lic['skus'],'compradas'=>(int)$lic['compradas'],'consumidas'=>(int)$lic['consumidas']],'microsoft365.php');
    $hoy=$now->format('Y-m-d');$hasta=$now->modify('+60 days')->format('Y-m-d');$q=$pdo->prepare("SELECT COUNT(*) cantidad,COALESCE(SUM(valor),0) valor FROM contratos WHERE estado='VIGENTE' AND fecha_fin BETWEEN ? AND ?");$q->execute([$hoy,$hasta]);$contratos=$q->fetch(PDO::FETCH_ASSOC);
    if((int)$contratos['cantidad']>0)$r[]=inteligencia_hallazgo('INT-COSTOS-CONTRATOS','COSTOS','Control Financiero','ALTA','Contratos próximos a vencer',"Hay {$contratos['cantidad']} contratos por $".number_format((float)$contratos['valor'],0,',','.')." con vencimiento en 60 días.",['contratos'=>(int)$contratos['cantidad'],'valor'=>(float)$contratos['valor'],'hasta'=>$hasta],'contratos.php');
    $q=$pdo->prepare("SELECT COUNT(*) FROM campanas_coleccion WHERE estado NOT IN('FINALIZADA','CANCELADA') AND fecha_lanzamiento BETWEEN ? AND ?");$q->execute([$hoy,$now->modify('+14 days')->format('Y-m-d')]);$campanas=(int)$q->fetchColumn();
    if($campanas)$r[]=inteligencia_hallazgo('INT-RETAIL-LANZAMIENTOS','RETAIL','Calendario Comercial','ALTA','Lanzamientos próximos',"Hay {$campanas} campañas o colecciones con lanzamiento en los próximos 14 días.",['lanzamientos'=>$campanas],'campanas.php');
    return $r;
}

function inteligencia_agente_seguridad(PDO $pdo):array{
    $r=[];$m=secretos_metricas($pdo);
    if($m['activos'])$r[]=inteligencia_hallazgo('INT-SEGURIDAD-SECRETOS','SEGURIDAD','Gobierno de Secretos',$m['criticos']?'CRITICA':'ALTA','Archivos con credenciales requieren tratamiento',"Hay {$m['activos']} hallazgos activos en {$m['archivos']} archivos; {$m['criticos']} tienen severidad crítica.",['hallazgos_activos'=>$m['activos'],'archivos'=>$m['archivos'],'criticos'=>$m['criticos']],'gobierno_secretos.php');
    if($m['vencidos'])$r[]=inteligencia_hallazgo('INT-SEGURIDAD-SECRETOS-VENCIDOS','SEGURIDAD','Gobierno de Secretos','CRITICA','Planes de rotación vencidos',"Hay {$m['vencidos']} tratamientos de credenciales fuera de la fecha objetivo.",['planes_vencidos'=>$m['vencidos']],'gobierno_secretos.php');
    return $r;
}

function inteligencia_ejecutar(PDO $pdo,string $disparador='MANUAL',?string $correlacion=null,?DateTimeImmutable $now=null):array{
    $now=($now?:new DateTimeImmutable('now',new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    $correlacion=preg_replace('/[^A-Za-z0-9_.:\-]/','',(string)($correlacion?:strtolower($disparador).'-'.$now->format('YmdHi')));
    $prev=$pdo->prepare('SELECT * FROM inteligencia_ejecuciones WHERE correlacion=?');$prev->execute([$correlacion]);
    if($x=$prev->fetch(PDO::FETCH_ASSOC))return ['ok'=>$x['estado']==='COMPLETADA','duplicada'=>true,'ejecucion_id'=>(int)$x['id'],'hallazgos'=>(int)$x['hallazgos_generados'],'resumen'=>$x['resumen']];
    try{$pdo->prepare('INSERT INTO inteligencia_ejecuciones(correlacion,disparador)VALUES(?,?)')->execute([$correlacion,strtoupper($disparador)]);}catch(PDOException $e){$prev->execute([$correlacion]);if($x=$prev->fetch(PDO::FETCH_ASSOC))return ['ok'=>$x['estado']==='COMPLETADA','duplicada'=>true,'ejecucion_id'=>(int)$x['id'],'hallazgos'=>(int)$x['hallazgos_generados'],'resumen'=>$x['resumen']];throw $e;}$id=(int)$pdo->lastInsertId();
    try{
        $agentes=['OPERACION'=>inteligencia_agente_operacion($pdo),'SERVICIO'=>inteligencia_agente_servicio($pdo,$now),'IDENTIDAD'=>inteligencia_agente_identidad($pdo),'ACTIVOS'=>inteligencia_agente_activos($pdo,$now),'COSTOS'=>inteligencia_agente_finanzas($pdo,$now),'SEGURIDAD'=>inteligencia_agente_seguridad($pdo)];
        $total=0;$conteos=[];foreach($agentes as $agente=>$hallazgos){$conteos[$agente]=count($hallazgos);foreach($hallazgos as $h){inteligencia_registrar($pdo,$id,$h);$total++;}}
        $resolver=$pdo->prepare("UPDATE inteligencia_hallazgos SET estado='RESUELTO',resuelto_en=CURRENT_TIMESTAMP,actualizado_en=CURRENT_TIMESTAMP WHERE estado IN('ACTIVO','GESTIONADO') AND (ultima_ejecucion_id IS NULL OR ultima_ejecucion_id!=?)");$resolver->execute([$id]);
        $resumen="{$total} hallazgos activos; {$resolver->rowCount()} resueltos por cambio de evidencia";
        $pdo->prepare("UPDATE inteligencia_ejecuciones SET estado='COMPLETADA',agentes_json=?,hallazgos_generados=?,resumen=?,finalizado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([json_encode($conteos),$total,$resumen,$id]);
        return ['ok'=>true,'duplicada'=>false,'ejecucion_id'=>$id,'hallazgos'=>$total,'resueltos'=>$resolver->rowCount(),'agentes'=>$conteos,'resumen'=>$resumen];
    }catch(Throwable $e){$pdo->prepare("UPDATE inteligencia_ejecuciones SET estado='ERROR',resumen='Error interno',finalizado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);throw $e;}
}

function inteligencia_gestionar(PDO $pdo,int $id,string $accion,array $actor):void{
    $estado=match($accion){'gestionar'=>'GESTIONADO','descartar'=>'DESCARTADO','resolver'=>'RESUELTO',default=>throw new InvalidArgumentException('Acción inválida.')};
    $pdo->prepare("UPDATE inteligencia_hallazgos SET estado=?,gestionado_por=?,gestionado_en=CURRENT_TIMESTAMP,resuelto_en=CASE WHEN ?='RESUELTO' THEN CURRENT_TIMESTAMP ELSE resuelto_en END WHERE id=?")->execute([$estado,$actor['nombre'],$estado,$id]);
}

function inteligencia_crear_ticket(PDO $pdo,int $id,array $actor):int{
    $s=$pdo->prepare('SELECT * FROM inteligencia_hallazgos WHERE id=?');$s->execute([$id]);$h=$s->fetch(PDO::FETCH_ASSOC);if(!$h)throw new RuntimeException('Hallazgo no encontrado.');if($h['ticket_id'])return (int)$h['ticket_id'];
    $prioridad=in_array($h['severidad'],['CRITICA','ALTA'],true)?'ALTA':'MEDIA';$categoria=match($h['dominio']){'IDENTIDAD'=>'ACCESOS','ACTIVOS'=>'EQUIPOS','RETAIL'=>'OPERACION','COSTOS'=>'GESTION',default=>'SOPORTE'};
    $pdo->prepare("INSERT INTO tickets(titulo,descripcion,categoria,prioridad,estado,solicitante,origen)VALUES(?,?,?,?,'ABIERTO',?,'INTELIGENCIA')")->execute([$h['titulo'],$h['resumen']."\n\nEvidencia: ".$h['evidencia_json'],$categoria,$prioridad,$actor['nombre']]);$ticket=(int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE inteligencia_hallazgos SET ticket_id=?,estado='GESTIONADO',gestionado_por=?,gestionado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([$ticket,$actor['nombre'],$id]);
    if(function_exists('hoja_vida_registrar'))hoja_vida_registrar($pdo,'TICKET',(string)$ticket,'CREADO_DESDE_INTELIGENCIA',$h['clave_unica'],$actor['nombre'],$ticket);
    return $ticket;
}

function inteligencia_metricas(PDO $pdo):array{
    return [
        'activos'=>(int)$pdo->query("SELECT COUNT(*) FROM inteligencia_hallazgos WHERE estado='ACTIVO'")->fetchColumn(),
        'criticos'=>(int)$pdo->query("SELECT COUNT(*) FROM inteligencia_hallazgos WHERE estado='ACTIVO' AND severidad='CRITICA'")->fetchColumn(),
        'gestionados'=>(int)$pdo->query("SELECT COUNT(*) FROM inteligencia_hallazgos WHERE estado='GESTIONADO'")->fetchColumn(),
        'resueltos_30d'=>(int)$pdo->query("SELECT COUNT(*) FROM inteligencia_hallazgos WHERE estado='RESUELTO' AND resuelto_en>=datetime('now','-30 days')")->fetchColumn(),
    ];
}
