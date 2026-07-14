<?php
define('CSRF_EXEMPT',true);
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/automatizacion_operativa.php';
header('Content-Type: application/json; charset=utf-8');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
$raw=(string)file_get_contents('php://input');
if(!firma_hmac_valida($raw,$_SERVER['HTTP_X_NAVISSI_SIGNATURE']??null,navissi_webhook_secret())){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'Firma inválida.']);exit;}
$d=json_decode($raw,true);
if(!is_array($d)||($d['action']??'run')!=='run'){http_response_code(400);echo json_encode(['ok'=>false]);exit;}
try{echo json_encode(automatizacion_operativa_ejecutar(db(),'N8N',limpio($d['correlation_id']??null)),JSON_UNESCAPED_UNICODE);}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'No se pudo completar.']);}
