<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/identidad_lifecycle.php';
function fase5_ok(bool $condicion,string $mensaje):void{if(!$condicion)throw new RuntimeException("FAIL {$mensaje}");echo "OK  {$mensaje}\n";}
$pdo=db();iniciar_sesion_segura();$_SESSION=[];
$admin=$pdo->query("SELECT * FROM usuarios_sistema WHERE email='admin@navissi.com'")->fetch(PDO::FETCH_ASSOC);$_SESSION['usuario']=sesion_desde_usuario($admin);
$pdo->prepare("INSERT INTO empleados(documento,nombres,cargo,area,email,estado)VALUES(?,?,?,?,?,'ACTIVO')")->execute(['ID-QA-1','Persona Identidad QA','Asesor','Direccion de Operaciones','identidad.qa@navissi.com']);
$pdo->prepare("INSERT INTO usuarios_sistema(nombre,email,documento,password_hash,rol,area_responsable,activo)VALUES(?,?,?,?,?,?,1)")->execute(['Persona Identidad QA','identidad.qa@navissi.com','ID-QA-1',password_hash('Temporal-QA-2026!',PASSWORD_DEFAULT),'EMPLEADO','Direccion de Operaciones']);
$usuarioId=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO ms365_usuarios(graph_id,nombre,correo,departamento,cargo,cuenta_activa)VALUES(?,?,?,?,?,1)")->execute(['graph-qa-1','Persona Identidad QA','identidad.qa@navissi.com','Operaciones','Asesor']);
$diag=identidad_diagnostico($pdo);fase5_ok($diag['sin_usuario_navissi']===0&&$diag['sin_microsoft']===0,'reconcilia empleado, NAVISSI y Microsoft');
$alta=ciclo_identidad_crear($pdo,['tipo'=>'ALTA','empleado_documento'=>'ID-QA-1','empleado_nombre'=>'Persona Identidad QA','correo_corporativo'=>'identidad.qa@navissi.com','area_destino'=>'Direccion de Operaciones','fecha_efectiva'=>'2026-07-20'],$admin);
fase5_ok($alta['estado']==='PENDIENTE'&&count(ciclo_identidad_tareas($pdo,(int)$alta['id']))===6,'crea alta pendiente con seis tareas');
ciclo_identidad_aprobar($pdo,(int)$alta['id'],$admin);$tareas=ciclo_identidad_tareas($pdo,(int)$alta['id']);
ciclo_identidad_completar_manual($pdo,(int)$tareas[0]['id'],'Datos confirmados por RRHH',$admin);
ciclo_identidad_completar_manual($pdo,(int)$tareas[1]['id'],'Usuario NAVISSI preparado',$admin);
ciclo_identidad_ejecutar_automatica($pdo,(int)$tareas[2]['id'],$admin);
fase5_ok($pdo->query("SELECT sso_microsoft_id FROM usuarios_sistema WHERE id={$usuarioId}")->fetchColumn()==='graph-qa-1','vincula el objeto Microsoft sin modificar el tenant');
$alta=ciclo_identidad_obtener($pdo,(int)$alta['id']);fase5_ok((int)$alta['progreso']===50&&$alta['estado']==='EN_EJECUCION','calcula progreso del ciclo');
$retiro=ciclo_identidad_crear($pdo,['tipo'=>'RETIRO','empleado_documento'=>'ID-QA-1','empleado_nombre'=>'Persona Identidad QA','correo_corporativo'=>'identidad.qa@navissi.com','area_origen'=>'Direccion de Operaciones','fecha_efectiva'=>'2026-07-31'],$admin);ciclo_identidad_aprobar($pdo,(int)$retiro['id'],$admin);$rt=ciclo_identidad_tareas($pdo,(int)$retiro['id']);
ciclo_identidad_completar_manual($pdo,(int)$rt[0]['id'],'Retiro validado por RRHH',$admin);ciclo_identidad_ejecutar_automatica($pdo,(int)$rt[1]['id'],$admin);
fase5_ok((int)$pdo->query("SELECT activo FROM usuarios_sistema WHERE id={$usuarioId}")->fetchColumn()===0,'bloquea únicamente la cuenta NAVISSI al ejecutar la tarea local');
$confirmacionRechazada=false;try{ciclo_identidad_ejecutar_automatica($pdo,(int)$rt[2]['id'],$admin,'otro.usuario@navissi.com');}catch(RuntimeException $e){$confirmacionRechazada=str_contains($e->getMessage(),'correo corporativo exacto');}fase5_ok($confirmacionRechazada,'exige el correo exacto antes de una acción Graph');
$rt=ciclo_identidad_tareas($pdo,(int)$retiro['id']);fase5_ok($rt[2]['modo']==='AUTO_GRAPH'&&$rt[2]['estado']==='PENDIENTE'&&$rt[3]['estado']==='PENDIENTE','mantiene bloqueos Microsoft pendientes de confirmación explícita');
echo "PASS phase5_identidades_test\n";
