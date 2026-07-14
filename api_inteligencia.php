<?php
define('CSRF_EXEMPT',true);
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/inteligencia_operativa.php';
header('Content-Type: application/json; charset=utf-8');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
$raw=(string)file_get_contents('php://input');
if(!firma_hmac_valida($raw,$_SERVER['HTTP_X_NAVISSI_SIGNATURE']??null,navissi_webhook_secret())){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'Firma inválida.']);exit;}
$datos=json_decode($raw,true);if(!is_array($datos)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'JSON inválido.']);exit;}
try{
    $pdo=db();$accion=$datos['action']??'run';
    if($accion==='run')$salida=inteligencia_ejecutar($pdo,'N8N',limpio($datos['correlation_id']??null));
    elseif($accion==='status')$salida=['ok'=>true,'metricas'=>inteligencia_metricas($pdo),'ultima'=>$pdo->query("SELECT estado,resumen,finalizado_en FROM inteligencia_ejecuciones ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC)?:null];
    else{http_response_code(400);$salida=['ok'=>false,'error'=>'Acción inválida.'];}
    echo json_encode($salida,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'No se pudo completar.']);}
