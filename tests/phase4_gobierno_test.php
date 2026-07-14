<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/gobierno_operativo.php';
function fase4_ok(bool $condicion, string $mensaje): void { if (!$condicion) throw new RuntimeException("FAIL {$mensaje}"); echo "OK  {$mensaje}\n"; }
    $pdo = db();
    iniciar_sesion_segura();
    $_SESSION = [];
    notificaciones_config_guardar(['correo_habilitado'=>true,'correos_operacion'=>'qa@navissi.com']);
    $servicio = $pdo->query("SELECT * FROM catalogo_servicios WHERE codigo='EQ-CAMBIO'")->fetch(PDO::FETCH_ASSOC);
    fase4_ok((bool)$servicio, 'encuentra servicio de cambio de equipo');
    $empleado = ['id'=>20,'nombre'=>'Empleado QA','email'=>'empleado@navissi.com','documento'=>'QA-100','rol'=>'EMPLEADO','rol_secundario'=>null,'sede_id'=>null,'area_responsable'=>'Direccion de Operaciones'];
    $_SESSION['usuario'] = $empleado;
    $solicitud = solicitud_crear($pdo, $empleado, (int)$servicio['id'], 'Equipo para apertura de nueva tienda', 4500000, 'ALTA');
    fase4_ok(str_starts_with($solicitud['codigo'], 'SOL-') && $solicitud['nivel_actual']==='DIRECTOR', 'crea código, SLA y nivel inicial');
    fase4_ok(count(solicitud_eventos($pdo,(int)$solicitud['id']))===1, 'registra evento de creación');
    $director = ['id'=>21,'nombre'=>'Director TI QA','email'=>'director@navissi.com','documento'=>'QA-200','rol'=>'DIRECTOR','rol_secundario'=>null,'sede_id'=>null,'area_responsable'=>'Direccion de Tecnologia'];
    $_SESSION['usuario'] = $director;
    fase4_ok(solicitud_puede_aprobar($solicitud), 'autoriza al director del área responsable');
    $escalada = solicitud_resolver($pdo,(int)$solicitud['id'],'APROBAR','Presupuesto técnico validado',$director);
    fase4_ok($escalada['estado']==='PENDIENTE' && $escalada['nivel_actual']==='GERENCIA', 'escala por superar el umbral de monto');
    $gerencia = ['id'=>22,'nombre'=>'Gerencia QA','email'=>'gerencia@navissi.com','documento'=>'QA-300','rol'=>'GERENCIA','rol_secundario'=>null,'sede_id'=>null,'area_responsable'=>null];
    $_SESSION['usuario'] = $gerencia;
    $aprobada = solicitud_resolver($pdo,(int)$solicitud['id'],'APROBAR','Aprobación final',$gerencia);
    fase4_ok($aprobada['estado']==='APROBADA' && (int)$aprobada['ticket_id']>0, 'aprueba y crea ticket operativo');
    fase4_ok(count(solicitud_eventos($pdo,(int)$solicitud['id']))===3, 'conserva la línea de tiempo completa');
    fase4_ok((int)$pdo->query("SELECT COUNT(*) FROM notificaciones_cola")->fetchColumn()>=3, 'encola notificaciones auditables');
echo "PASS phase4_gobierno_test\n";
