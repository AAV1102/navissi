<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'navissi-ai-desk-'.bin2hex(random_bytes(5));
putenv('NAVISSI_PRIVATE_DIR='.$tmp); putenv('NAVISSI_SKIP_FILE_MIGRATION=1');
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/correo_a_tickets.php';
function desk_ok(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException("FAIL {$msg}");echo "OK  {$msg}\n";}
$pdo=db();
$pdo->prepare("INSERT INTO usuarios_sistema(nombre,email,password_hash,rol,area_responsable,activo)VALUES(?,?,?,?,?,1)")
    ->execute(['Tecnico Real','tecnico@navissi.com',password_hash('Temporal-2026!',PASSWORD_DEFAULT),'TI','Direccion de Tecnologia']);
$pdo->prepare("INSERT INTO inventario(serial,placa,asignado_a,estado,fuente)VALUES(?,?,?,'ACTIVO','TEST')")->execute(['SERIAL-QA-900','PLACA-QA-900','Tecnico Real']);
desk_ok((correo_detectar_equipo($pdo,'tecnico@navissi.com','Falla equipo SERIAL-QA-900','No inicia')['serial']??'')==='SERIAL-QA-900','correo vincula automáticamente inventario y acceso remoto por serial');
$crear=$pdo->prepare("INSERT INTO tickets(titulo,descripcion,categoria,prioridad,estado,origen)VALUES(?,?,'CORREO','MEDIA','ABIERTO','CORREO')");
$crear->execute(['Error en facturas Siesa','No permite contabilizar una factura de proveedor']);$siesa=(int)$pdo->lastInsertId();ia_triage_ticket($pdo,$siesa);
$t=$pdo->query("SELECT * FROM tickets WHERE id={$siesa}")->fetch(PDO::FETCH_ASSOC);
desk_ok($t['categoria']==='SIESA / FACTURACIÓN'&&$t['departamento']==='Direccion de Contabilidad','Siesa y facturas se enrutan a Contabilidad');
desk_ok(empty($t['asignado_a']),'no asigna una cuenta de prueba ni cruza departamentos');
$crear->execute(['Internet caído en tienda','El router y la red no tienen conexión']);$infra=(int)$pdo->lastInsertId();ia_triage_ticket($pdo,$infra);
$t=$pdo->query("SELECT * FROM tickets WHERE id={$infra}")->fetch(PDO::FETCH_ASSOC);
desk_ok($t['categoria']==='INFRAESTRUCTURA'&&$t['departamento']==='Direccion de Tecnologia','infraestructura se enruta a Tecnología');
desk_ok($t['asignado_a']==='Tecnico Real','escala al técnico real del departamento');
$crear->execute(['Traslado a bodega principal','Se requiere despacho y nueva ubicación logística del equipo']);$log=(int)$pdo->lastInsertId();ia_triage_ticket($pdo,$log);
$t=$pdo->query("SELECT * FROM tickets WHERE id={$log}")->fetch(PDO::FETCH_ASSOC);
desk_ok($t['categoria']==='LOGÍSTICA Y BODEGA'&&$t['departamento']==='Direccion de Logistica','traslados y bodega se enrutan a Logística');
$pdo->prepare("INSERT INTO base_conocimiento(titulo,categoria,contenido,autor)VALUES(?,?,?,?)")
    ->execute(['Restablecer contraseña Microsoft','TECNOLOGÍA Y SOFTWARE',str_repeat('Sigue el portal corporativo para restablecer la contraseña de Microsoft de forma segura. ',2),'QA']);
$crear->execute(['Restablecer contraseña Microsoft','Necesito restablecer contraseña Microsoft']);$auto=(int)$pdo->lastInsertId();ia_triage_ticket($pdo,$auto);
$t=$pdo->query("SELECT * FROM tickets WHERE id={$auto}")->fetch(PDO::FETCH_ASSOC);
desk_ok($t['estado']==='RESUELTO POR IA'&&empty($t['asignado_a']),'el agente local resuelve con conocimiento aprobado antes de asignar persona');
echo "PASS ai_service_desk_test\n";
