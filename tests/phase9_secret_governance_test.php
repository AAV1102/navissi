<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/xlsx_writer.php';
require_once __DIR__.'/../lib/gobierno_secretos.php';
require_once __DIR__.'/../lib/inteligencia_operativa.php';
function fase9_ok(bool $condicion,string $mensaje):void{if(!$condicion)throw new RuntimeException("FAIL {$mensaje}");echo "OK  {$mensaje}\n";}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('PRAGMA foreign_keys=ON');crear_esquema($pdo);migrar_esquema($pdo);migrar_fases_operativas($pdo);
$dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'navissi-secretos-'.bin2hex(random_bytes(4));mkdir($dir);
$sensible=$dir.DIRECTORY_SEPARATOR.'control-accesos.xlsx';$limpio=$dir.DIRECTORY_SEPARATOR.'inventario.xlsx';
xlsx_write(['CREDENCIALES'=>[['USUARIO','CONTRASEÑA','ÁREA'],['qa@example.test','valor-que-no-debe-persistir','TI']]],$sensible);
xlsx_write(['EQUIPOS'=>[['SERIAL','MARCA'],['QA-001','Prueba']]],$limpio);
$actor=['nombre'=>'QA Seguridad','rol'=>'TI'];$r=secretos_escanear_raices($pdo,[['etiqueta'=>'Laboratorio QA','ruta'=>$dir]],$actor,'TEST');
fase9_ok($r['archivos']===2&&$r['hallazgos']===1,'detecta solo el libro con encabezados sensibles');
fase9_ok(secretos_escanear_programado($pdo)===null,'evita repetir el escaneo programado durante 24 horas');
$h=$pdo->query('SELECT * FROM secretos_hallazgos')->fetch(PDO::FETCH_ASSOC);
fase9_ok($h['archivo_nombre']==='control-accesos.xlsx'&&$h['hoja']==='CREDENCIALES'&&$h['severidad']==='CRITICA','conserva únicamente metadatos útiles para el tratamiento');
$persistido=json_encode($pdo->query('SELECT * FROM secretos_hallazgos')->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE);
fase9_ok(!str_contains((string)$persistido,'valor-que-no-debe-persistir')&&!str_contains((string)$persistido,'qa@example.test'),'no persiste usuarios ni secretos de las celdas');
$intel=inteligencia_ejecutar($pdo,'TEST','fase9-inteligencia');
fase9_ok((int)$pdo->query("SELECT COUNT(*) FROM inteligencia_hallazgos WHERE clave_unica='INT-SEGURIDAD-SECRETOS' AND estado='ACTIVO'")->fetchColumn()===1,'publica el riesgo en Inteligencia Operativa');
$fallo=false;try{secretos_gestionar($pdo,(int)$h['id'],['estado'=>'ROTADO','evidencia'=>''],$actor);}catch(InvalidArgumentException $e){$fallo=true;}fase9_ok($fallo,'exige evidencia antes de marcar una rotación');
secretos_gestionar($pdo,(int)$h['id'],['estado'=>'PLANIFICADO','responsable'=>'Tecnología','fecha_objetivo'=>'2026-07-20','evidencia'=>'Ticket QA-9'],$actor);
fase9_ok((int)$pdo->query("SELECT COUNT(*) FROM secretos_hallazgos WHERE estado='PLANIFICADO'")->fetchColumn()===1,'asigna responsable y fecha al plan de tratamiento');
secretos_gestionar($pdo,(int)$h['id'],['estado'=>'ROTADO','responsable'=>'Tecnología','fecha_objetivo'=>'2026-07-20','evidencia'=>'Rotación validada en ticket QA-9; archivo saneado.'],$actor);
fase9_ok((int)$pdo->query("SELECT COUNT(*) FROM secretos_hallazgos WHERE estado='ROTADO' AND resuelto_en IS NOT NULL")->fetchColumn()===1,'cierra el riesgo con evidencia trazable');
@unlink($sensible);@unlink($limpio);@rmdir($dir);
echo "PASS phase9_secret_governance_test\n";
