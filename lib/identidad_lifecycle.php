<?php
require_once __DIR__ . '/notificaciones.php';
require_once __DIR__ . '/graph_client.php';

function identidad_diagnostico(PDO $pdo): array {
    return [
        'empleados_activos' => (int)$pdo->query("SELECT COUNT(*) FROM empleados WHERE estado='ACTIVO'")->fetchColumn(),
        'sin_usuario_navissi' => (int)$pdo->query("SELECT COUNT(*) FROM empleados e LEFT JOIN usuarios_sistema u ON u.documento=e.documento AND u.activo=1 WHERE e.estado='ACTIVO' AND u.id IS NULL")->fetchColumn(),
        'sin_microsoft' => (int)$pdo->query("SELECT COUNT(*) FROM empleados e LEFT JOIN ms365_usuarios m ON lower(m.correo)=lower(e.email) AND m.cuenta_activa=1 WHERE e.estado='ACTIVO' AND e.email IS NOT NULL AND e.email!='' AND m.id IS NULL")->fetchColumn(),
        'retiros_con_microsoft_activo' => (int)$pdo->query("SELECT COUNT(*) FROM empleados e JOIN ms365_usuarios m ON lower(m.correo)=lower(e.email) AND m.cuenta_activa=1 WHERE e.estado!='ACTIVO' AND e.email IS NOT NULL AND e.email!=''")->fetchColumn(),
        'microsoft_sin_empleado' => (int)$pdo->query("SELECT COUNT(*) FROM ms365_usuarios m LEFT JOIN empleados e ON lower(e.email)=lower(m.correo) AND e.estado='ACTIVO' WHERE m.cuenta_activa=1 AND e.id IS NULL")->fetchColumn(),
        'usuarios_sin_documento' => (int)$pdo->query("SELECT COUNT(*) FROM usuarios_sistema WHERE activo=1 AND (documento IS NULL OR documento='')")->fetchColumn(),
    ];
}

function identidad_brechas(PDO $pdo, string $tipo, int $limite = 50): array {
    $limite = min(200, max(1, $limite));
    $sql = match ($tipo) {
        'SIN_NAVISSI' => "SELECT e.documento,e.nombres,e.email,e.area,e.cargo,'Crear/vincular usuario NAVISSI' accion FROM empleados e LEFT JOIN usuarios_sistema u ON u.documento=e.documento AND u.activo=1 WHERE e.estado='ACTIVO' AND u.id IS NULL",
        'SIN_MICROSOFT' => "SELECT e.documento,e.nombres,e.email,e.area,e.cargo,'Revisar cuenta Microsoft 365' accion FROM empleados e LEFT JOIN ms365_usuarios m ON lower(m.correo)=lower(e.email) AND m.cuenta_activa=1 WHERE e.estado='ACTIVO' AND e.email IS NOT NULL AND e.email!='' AND m.id IS NULL",
        'RETIRO_ACTIVO' => "SELECT e.documento,e.nombres,e.email,e.area,e.cargo,'Iniciar ciclo de retiro' accion FROM empleados e JOIN ms365_usuarios m ON lower(m.correo)=lower(e.email) AND m.cuenta_activa=1 WHERE e.estado!='ACTIVO' AND e.email IS NOT NULL AND e.email!=''",
        default => "SELECT NULL documento,m.nombre nombres,m.correo email,m.departamento area,m.cargo,'Vincular a empleado o clasificar cuenta técnica' accion FROM ms365_usuarios m LEFT JOIN empleados e ON lower(e.email)=lower(m.correo) AND e.estado='ACTIVO' WHERE m.cuenta_activa=1 AND e.id IS NULL",
    };
    return $pdo->query($sql . " ORDER BY nombres LIMIT {$limite}")->fetchAll(PDO::FETCH_ASSOC);
}

function ciclo_identidad_plantilla(string $tipo): array {
    return match ($tipo) {
        'ALTA' => [
            ['VALIDAR_RRHH','Validar datos, cargo y fecha de ingreso','RRHH','Direccion Recursos Humanos','MANUAL'],
            ['CREAR_NAVISSI','Crear o habilitar usuario NAVISSI','NAVISSI','Direccion de Tecnologia','MANUAL'],
            ['VINCULAR_M365','Vincular identidad NAVISSI con Microsoft 365','MICROSOFT 365','Direccion de Tecnologia','AUTO_LOCAL'],
            ['ASIGNAR_LICENCIAS','Asignar licencias y grupos autorizados','MICROSOFT 365','Direccion de Tecnologia','MANUAL'],
            ['ASIGNAR_EQUIPO','Asignar equipo y acta de entrega','INVENTARIO','Direccion de Tecnologia','MANUAL'],
            ['CONFIRMAR_ENTREGA','Confirmar accesos con líder y empleado','OPERACION','Direccion de Operaciones','MANUAL'],
        ],
        'TRASLADO' => [
            ['VALIDAR_TRASLADO','Validar nuevo cargo, área y fecha efectiva','RRHH','Direccion Recursos Humanos','MANUAL'],
            ['ACTUALIZAR_NAVISSI','Actualizar área del usuario NAVISSI','NAVISSI','Direccion de Tecnologia','AUTO_LOCAL'],
            ['REVISAR_M365','Revisar departamento, grupos y licencias Microsoft 365','MICROSOFT 365','Direccion de Tecnologia','MANUAL'],
            ['MOVER_EQUIPO','Actualizar asignación y ubicación del equipo','INVENTARIO','Direccion de Tecnologia','MANUAL'],
            ['CONFIRMAR_TRASLADO','Confirmar entrega con líder de destino','OPERACION','Direccion de Operaciones','MANUAL'],
        ],
        'RETIRO' => [
            ['VALIDAR_RETIRO','Validar aprobación y fecha efectiva del retiro','RRHH','Direccion Recursos Humanos','MANUAL'],
            ['BLOQUEAR_NAVISSI','Bloquear acceso local a NAVISSI','NAVISSI','Direccion de Tecnologia','AUTO_LOCAL'],
            ['BLOQUEAR_M365','Bloquear cuenta Microsoft 365','MICROSOFT 365','Direccion de Tecnologia','AUTO_GRAPH'],
            ['REVOCAR_SESIONES','Revocar sesiones activas de Microsoft 365','MICROSOFT 365','Direccion de Tecnologia','AUTO_GRAPH'],
            ['RECUPERAR_EQUIPO','Recuperar equipo, accesorios y acta','INVENTARIO','Direccion de Tecnologia','MANUAL'],
            ['CERRAR_ACCESOS','Retirar accesos de aplicaciones y terceros','ACCESOS','Direccion de Tecnologia','MANUAL'],
            ['CUSTODIA_DATOS','Confirmar custodia de correo, OneDrive y documentos','GOBIERNO','Gerencia','MANUAL'],
        ],
        default => throw new InvalidArgumentException('Tipo de ciclo inválido.'),
    };
}

function ciclo_identidad_crear(PDO $pdo, array $datos, array $actor): array {
    $tipo = strtoupper((string)($datos['tipo'] ?? ''));
    $plantilla = ciclo_identidad_plantilla($tipo);
    $nombre = trim((string)($datos['empleado_nombre'] ?? ''));
    if ($nombre === '') throw new InvalidArgumentException('El nombre del empleado es obligatorio.');
    $correo = strtolower(trim((string)($datos['correo_corporativo'] ?? '')));
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('El correo corporativo no es válido.');
    $solicitudId = (int)($datos['solicitud_id'] ?? 0) ?: null;
    $estado = 'PENDIENTE';
    if ($solicitudId) {
        $stmt = $pdo->prepare("SELECT estado,tipo FROM solicitudes_aprobacion WHERE id=?"); $stmt->execute([$solicitudId]); $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$solicitud || $solicitud['estado'] !== 'APROBADA') throw new RuntimeException('La solicitud vinculada debe estar aprobada.');
        $estado = 'APROBADO';
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO ciclos_identidad(tipo,empleado_documento,empleado_nombre,correo_corporativo,area_origen,area_destino,cargo,fecha_efectiva,solicitud_id,estado,creado_por,aprobado_por,aprobado_en,observaciones) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$tipo,limpio($datos['empleado_documento']??null),$nombre,$correo?:null,limpio($datos['area_origen']??null),limpio($datos['area_destino']??null),limpio($datos['cargo']??null),limpio($datos['fecha_efectiva']??null),$solicitudId,$estado,$actor['nombre'],$estado==='APROBADO'?'Solicitud '.$solicitudId:null,$estado==='APROBADO'?gmdate('Y-m-d H:i:s'):null,limpio($datos['observaciones']??null)]);
        $id = (int)$pdo->lastInsertId();
        $codigo = 'ID-' . $tipo . '-' . gmdate('Y') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE ciclos_identidad SET codigo=? WHERE id=?')->execute([$codigo,$id]);
        $insert = $pdo->prepare('INSERT INTO ciclos_identidad_tareas(ciclo_id,codigo,titulo,sistema,area_responsable,modo,orden) VALUES(?,?,?,?,?,?,?)');
        foreach ($plantilla as $orden => $tarea) $insert->execute([$id,$tarea[0],$tarea[1],$tarea[2],$tarea[3],$tarea[4],($orden+1)*10]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    notificaciones_encolar_operacion($pdo,"CICLO_IDENTIDAD_{$id}_CREADO","{$codigo} · Ciclo de {$tipo}","Ciclo creado para {$nombre}. Estado: {$estado}.",[],['ciclo_identidad_id'=>$id]);
    return ciclo_identidad_obtener($pdo,$id);
}

function ciclo_identidad_obtener(PDO $pdo, int $id): ?array {
    $stmt=$pdo->prepare('SELECT c.*,s.codigo solicitud_codigo FROM ciclos_identidad c LEFT JOIN solicitudes_aprobacion s ON s.id=c.solicitud_id WHERE c.id=?');$stmt->execute([$id]);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
}
function ciclo_identidad_tareas(PDO $pdo,int $id):array{$stmt=$pdo->prepare('SELECT * FROM ciclos_identidad_tareas WHERE ciclo_id=? ORDER BY orden,id');$stmt->execute([$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC);}

function ciclo_identidad_aprobar(PDO $pdo,int $id,array $actor):array{
    if(!tiene_rol(['ADMIN','RRHH']))throw new RuntimeException('Solo Administración o RRHH pueden aprobar el ciclo.');
    $c=ciclo_identidad_obtener($pdo,$id);if(!$c||$c['estado']!=='PENDIENTE')throw new RuntimeException('El ciclo no está pendiente de aprobación.');
    $pdo->prepare("UPDATE ciclos_identidad SET estado='APROBADO',aprobado_por=?,aprobado_en=CURRENT_TIMESTAMP,actualizado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([$actor['nombre'],$id]);
    return ciclo_identidad_obtener($pdo,$id);
}

function ciclo_identidad_actualizar_progreso(PDO $pdo,int $id):void{
    $tareas=ciclo_identidad_tareas($pdo,$id);$obligatorias=array_filter($tareas,fn($t)=>(int)$t['obligatoria']);$completas=count(array_filter($obligatorias,fn($t)=>$t['estado']==='COMPLETADA'));$total=max(1,count($obligatorias));$progreso=(int)round($completas/$total*100);$estado=$progreso>=100?'COMPLETADO':($completas>0?'EN_EJECUCION':null);$sql='UPDATE ciclos_identidad SET progreso=?,actualizado_en=CURRENT_TIMESTAMP'.($estado?',estado=?,completado_en='.($estado==='COMPLETADO'?'CURRENT_TIMESTAMP':'completado_en'):'').' WHERE id=?';$args=$estado?[$progreso,$estado,$id]:[$progreso,$id];$pdo->prepare($sql)->execute($args);
}

function ciclo_tarea_disponible(PDO $pdo,array $tarea):bool{
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM ciclos_identidad_tareas WHERE ciclo_id=? AND obligatoria=1 AND orden<? AND estado!='COMPLETADA'");$stmt->execute([$tarea['ciclo_id'],$tarea['orden']]);return (int)$stmt->fetchColumn()===0;
}

function ciclo_identidad_completar_manual(PDO $pdo,int $tareaId,string $evidencia,array $actor):void{
    $stmt=$pdo->prepare('SELECT t.*,c.estado ciclo_estado FROM ciclos_identidad_tareas t JOIN ciclos_identidad c ON c.id=t.ciclo_id WHERE t.id=?');$stmt->execute([$tareaId]);$t=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$t||!in_array($t['ciclo_estado'],['APROBADO','EN_EJECUCION'],true)||$t['modo']!=='MANUAL')throw new RuntimeException('La tarea no puede completarse manualmente.');
    if(!ciclo_tarea_disponible($pdo,$t))throw new RuntimeException('Completa primero las tareas anteriores.');
    if(trim($evidencia)==='')throw new InvalidArgumentException('Registra una evidencia breve.');
    $pdo->prepare("UPDATE ciclos_identidad_tareas SET estado='COMPLETADA',evidencia=?,ultimo_error=NULL,ejecutado_por=?,ejecutado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([trim($evidencia),$actor['nombre'],$tareaId]);ciclo_identidad_actualizar_progreso($pdo,(int)$t['ciclo_id']);
}

function ciclo_identidad_ejecutar_automatica(PDO $pdo,int $tareaId,array $actor,string $confirmacionCorreo=''):void{
    $stmt=$pdo->prepare('SELECT t.id tarea_id,t.ciclo_id,t.codigo tarea_codigo,t.modo tarea_modo,t.orden tarea_orden,c.estado ciclo_estado,c.empleado_documento,c.correo_corporativo,c.area_destino FROM ciclos_identidad_tareas t JOIN ciclos_identidad c ON c.id=t.ciclo_id WHERE t.id=?');$stmt->execute([$tareaId]);$x=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$x||!in_array($x['ciclo_estado'],['APROBADO','EN_EJECUCION'],true)||!in_array($x['tarea_modo'],['AUTO_LOCAL','AUTO_GRAPH'],true))throw new RuntimeException('La tarea automática no está disponible.');
    if($x['tarea_modo']==='AUTO_GRAPH'&&(!is_string($x['correo_corporativo'])||$x['correo_corporativo']===''||strcasecmp(trim($confirmacionCorreo),$x['correo_corporativo'])!==0))throw new RuntimeException('Escribe el correo corporativo exacto para confirmar la acción en Microsoft 365.');
    $tarea=['ciclo_id'=>$x['ciclo_id'],'orden'=>$x['tarea_orden']];if(!ciclo_tarea_disponible($pdo,$tarea))throw new RuntimeException('Completa primero las tareas anteriores.');
    try{
        if($x['tarea_codigo']==='VINCULAR_M365'){
            $m=$pdo->prepare('SELECT graph_id FROM ms365_usuarios WHERE lower(correo)=lower(?) AND cuenta_activa=1 LIMIT 1');$m->execute([$x['correo_corporativo']]);$graphId=$m->fetchColumn();if(!$graphId)throw new RuntimeException('No se encontró una cuenta Microsoft activa con ese correo.');
            $u=$pdo->prepare('UPDATE usuarios_sistema SET sso_microsoft_id=? WHERE (documento=? AND ? IS NOT NULL) OR lower(email)=lower(?)');$u->execute([$graphId,$x['empleado_documento'],$x['empleado_documento'],$x['correo_corporativo']]);if(!$u->rowCount())throw new RuntimeException('No existe todavía el usuario NAVISSI para vincular.');
            $evidencia='Identidad vinculada con el objeto Microsoft sincronizado.';
        }elseif($x['tarea_codigo']==='ACTUALIZAR_NAVISSI'){
            $u=$pdo->prepare('UPDATE usuarios_sistema SET area_responsable=? WHERE (documento=? AND ? IS NOT NULL) OR lower(email)=lower(?)');$u->execute([$x['area_destino'],$x['empleado_documento'],$x['empleado_documento'],$x['correo_corporativo']]);if(!$u->rowCount())throw new RuntimeException('No se encontró el usuario NAVISSI.');$evidencia='Área NAVISSI actualizada a '.$x['area_destino'].'.';
        }elseif($x['tarea_codigo']==='BLOQUEAR_NAVISSI'){
            $u=$pdo->prepare("UPDATE usuarios_sistema SET activo=0 WHERE (documento=? AND ? IS NOT NULL) OR lower(email)=lower(?)");$u->execute([$x['empleado_documento'],$x['empleado_documento'],$x['correo_corporativo']]);if(!$u->rowCount())throw new RuntimeException('No se encontró una cuenta NAVISSI activa.');$evidencia='Cuenta NAVISSI bloqueada.';
        }elseif(in_array($x['tarea_codigo'],['BLOQUEAR_M365','REVOCAR_SESIONES'],true)){
            if(!ms365_configurado())throw new RuntimeException('Microsoft Graph no está configurado.');$m=$pdo->prepare('SELECT graph_id FROM ms365_usuarios WHERE lower(correo)=lower(?) LIMIT 1');$m->execute([$x['correo_corporativo']]);$graphId=$m->fetchColumn();if(!$graphId)throw new RuntimeException('No se encontró la identidad Microsoft sincronizada.');$cfg=ms365_config();$graph=new GraphClient($cfg['tenant_id'],$cfg['client_id'],$cfg['client_secret']);if($x['tarea_codigo']==='BLOQUEAR_M365'){$graph->cambiarEstadoCuenta($graphId,false);$pdo->prepare('UPDATE ms365_usuarios SET cuenta_activa=0,actualizado_en=CURRENT_TIMESTAMP WHERE graph_id=?')->execute([$graphId]);$evidencia='Cuenta Microsoft 365 bloqueada mediante Graph.';}else{$graph->revocarSesiones($graphId);$evidencia='Sesiones Microsoft 365 revocadas mediante Graph.';}
        }else throw new RuntimeException('Acción automática no implementada.');
        $pdo->prepare("UPDATE ciclos_identidad_tareas SET estado='COMPLETADA',evidencia=?,ultimo_error=NULL,ejecutado_por=?,ejecutado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([$evidencia,$actor['nombre'],$tareaId]);ciclo_identidad_actualizar_progreso($pdo,(int)$x['ciclo_id']);
    }catch(Throwable $e){$pdo->prepare("UPDATE ciclos_identidad_tareas SET estado='ERROR',ultimo_error=? WHERE id=?")->execute([substr($e->getMessage(),0,500),$tareaId]);throw $e;}
}
