<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$token = $_SERVER['HTTP_X_NAVISSI_TOKEN'] ?? ($_POST['token'] ?? '');
$esperado = getenv('NAVISSI_MONITOR_TOKEN') ?: 'NAVISSI-MONITOR-2026';
if (!hash_equals($esperado, (string)$token)) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Token inválido']); exit; }
$raw = file_get_contents('php://input'); $data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'JSON inválido']); exit; }
$pdo = db();
$sitio = trim((string)($data['sitio'] ?? 'Monitor local'));
$url = trim((string)($data['url'] ?? ''));
$tipo = trim((string)($data['tipo'] ?? 'jsonld'));
$pdo->prepare('INSERT OR IGNORE INTO monitor_precios_sitios(nombre,url,tipo,activo) VALUES(?,?,?,1)')->execute([$sitio,$url,$tipo]);
$st=$pdo->prepare('SELECT id FROM monitor_precios_sitios WHERE nombre=? LIMIT 1'); $st->execute([$sitio]); $sid=(int)$st->fetchColumn();
$filas = is_array($data['productos'] ?? null) ? $data['productos'] : [];
$pdo->prepare('INSERT INTO monitor_precios_escaneos(sitio_id,productos_encontrados,error) VALUES(?,?,?)')->execute([$sid,count($filas),$data['error'] ?? null]);
$eid=(int)$pdo->lastInsertId(); $ins=$pdo->prepare('INSERT INTO monitor_precios_productos(escaneo_id,clave,producto,variante,precio,precio_antes,descuento_pct,disponible,url) VALUES(?,?,?,?,?,?,?,?,?)');
foreach($filas as $f) $ins->execute([$eid,(string)($f['clave']??uniqid()),$f['producto']??'',$f['variante']??'', $f['precio']??null,$f['precio_antes']??null,$f['descuento_pct']??null,!empty($f['disponible'])?1:0,$f['url']??'']);
echo json_encode(['ok'=>true,'escaneo_id'=>$eid,'productos'=>count($filas)]);
