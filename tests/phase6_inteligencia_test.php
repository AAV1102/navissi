<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';require_once __DIR__.'/../lib/inteligencia_operativa.php';
function fase6_ok(bool $c,string $m):void{if(!$c)throw new RuntimeException("FAIL {$m}");echo "OK  {$m}\n";}
$pdo=db();iniciar_sesion_segura();$_SESSION=[];$admin=$pdo->query("SELECT * FROM usuarios_sistema WHERE email='admin@navissi.com'")->fetch(PDO::FETCH_ASSOC);$_SESSION['usuario']=sesion_desde_usuario($admin);
$pdo->prepare("INSERT INTO empleados(documento,nombres,email,estado)VALUES(?,?,?,'RETIRADO')")->execute(['INT-RET-1','Retiro Inteligencia','retiro.int@navissi.com']);
$pdo->prepare("INSERT INTO ms365_usuarios(graph_id,nombre,correo,cuenta_activa)VALUES(?,?,?,1)")->execute(['graph-int-1','Retiro Inteligencia','retiro.int@navissi.com']);
$pdo->prepare("INSERT INTO tickets(titulo,prioridad,estado,sla_limite)VALUES('SLA Inteligencia','ALTA','ABIERTO','2026-07-10 10:00:00')")->execute();$ticketFuente=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO ms365_licencias(sku_id,nombre,compradas,consumidas)VALUES('SKU-INT','Licencia Inteligencia',10,10)")->execute();
$now=new DateTimeImmutable('2026-07-13 12:00:00',new DateTimeZone('UTC'));$r=inteligencia_ejecutar($pdo,'TEST','fase6-qa-1',$now);
fase6_ok($r['ok']&&!$r['duplicada']&&$r['hallazgos']>=3,'ejecuta seis agentes sobre evidencia real');
$claves=$pdo->query("SELECT clave_unica FROM inteligencia_hallazgos WHERE estado='ACTIVO'")->fetchAll(PDO::FETCH_COLUMN);
fase6_ok(in_array('INT-SERVICIO-SLA',$claves,true)&&in_array('INT-IDENTIDAD-RETIROS',$claves,true)&&in_array('INT-COSTOS-LICENCIAS',$claves,true),'detecta SLA, retiro expuesto y consumo de licencias');
fase6_ok(inteligencia_ejecutar($pdo,'TEST','fase6-qa-1',$now)['duplicada'],'mantiene idempotencia por correlación');
$hallazgo=(int)$pdo->query("SELECT id FROM inteligencia_hallazgos WHERE clave_unica='INT-SERVICIO-SLA'")->fetchColumn();$ticket=inteligencia_crear_ticket($pdo,$hallazgo,$admin);
fase6_ok($ticket>0&&(int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE id={$ticket} AND origen='INTELIGENCIA'")->fetchColumn()===1,'convierte un hallazgo en ticket trazable');
$pdo->exec("UPDATE tickets SET estado='CERRADO' WHERE id={$ticketFuente}");$pdo->exec("UPDATE ms365_usuarios SET cuenta_activa=0 WHERE graph_id='graph-int-1'");$pdo->exec("UPDATE ms365_licencias SET consumidas=1 WHERE sku_id='SKU-INT'");
$r2=inteligencia_ejecutar($pdo,'TEST','fase6-qa-2',$now->modify('+1 hour'));fase6_ok($r2['resueltos']>=3,'resuelve hallazgos cuando cambia la evidencia');
fase6_ok((int)$pdo->query("SELECT COUNT(*) FROM inteligencia_hallazgos WHERE clave_unica IN('INT-SERVICIO-SLA','INT-IDENTIDAD-RETIROS','INT-COSTOS-LICENCIAS') AND estado='RESUELTO'")->fetchColumn()===3,'conserva el historial resuelto para auditoría');
echo "PASS phase6_inteligencia_test\n";
