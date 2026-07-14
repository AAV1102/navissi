<?php
function agente_token_emitir(PDO $pdo, string $nombre, ?int $sedeId, ?string $creadoPor): string {
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $pdo->prepare("INSERT INTO agentes_tokens(nombre,token_hash,token_prefijo,sede_id,expira_en,creado_por) VALUES (?,?,?,?,datetime('now','+365 days'),?)")
        ->execute([$nombre, hash('sha256',$token), substr($token,0,8), $sedeId, $creadoPor]);
    return $token;
}
function agente_token_header(): ?string {
    $auth=trim((string)($_SERVER['HTTP_AUTHORIZATION']??''));
    if(preg_match('/^Bearer\s+(.+)$/i',$auth,$m)) return trim($m[1]);
    $otro=trim((string)($_SERVER['HTTP_X_NAVISSI_AGENT_TOKEN']??'')); return $otro!==''?$otro:null;
}
function agente_autenticar(PDO $pdo, ?string $serial=null, bool $vincular=false): array {
    $token=agente_token_header();
    if(!$token||strlen($token)<32){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'Credencial de agente requerida.']);exit;}
    $s=$pdo->prepare("SELECT * FROM agentes_tokens WHERE token_hash=? AND activo=1 AND (expira_en IS NULL OR expira_en>datetime('now')) LIMIT 1");
    $s->execute([hash('sha256',$token)]); $a=$s->fetch(PDO::FETCH_ASSOC);
    if(!$a){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'Credencial de agente inválida o vencida.']);exit;}
    if($serial&&$a['serial_vinculado']&&strcasecmp($a['serial_vinculado'],$serial)!==0){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'La credencial pertenece a otro equipo.']);exit;}
    if($serial&&$vincular&&!$a['serial_vinculado']){$pdo->prepare("UPDATE agentes_tokens SET serial_vinculado=? WHERE id=? AND serial_vinculado IS NULL")->execute([$serial,$a['id']]);$a['serial_vinculado']=$serial;}
    $pdo->prepare("UPDATE agentes_tokens SET ultimo_uso_en=CURRENT_TIMESTAMP,ultima_ip=? WHERE id=?")->execute([substr((string)($_SERVER['REMOTE_ADDR']??''),0,64)?:null,$a['id']]);
    return $a;
}
