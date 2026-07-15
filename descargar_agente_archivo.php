<?php
// Entrega componentes del agente a un instalador recién emitido. El hosting
// bloquea extensiones .ps1 directas, por eso se sirven desde PHP y con token.
require_once __DIR__ . '/config.php';
$archivo=(string)($_GET['archivo']??'');
$token=(string)($_GET['token']??'');
$permitidos=['inventario'=>'agente_navissi.ps1','reportar'=>'reportar_problema.ps1'];
if(!isset($permitidos[$archivo])||strlen($token)<32){http_response_code(404);exit;}
$pdo=db();$q=$pdo->prepare("SELECT id FROM agentes_tokens WHERE token_hash=? AND activo=1 AND (expira_en IS NULL OR expira_en>datetime('now')) LIMIT 1");
$q->execute([hash('sha256',$token)]);if(!$q->fetchColumn()){http_response_code(403);exit('Credencial inválida.');}
$ruta=__DIR__.'/data/'.$permitidos[$archivo];if(!is_file($ruta)){http_response_code(404);exit;}
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$permitidos[$archivo].'"');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
