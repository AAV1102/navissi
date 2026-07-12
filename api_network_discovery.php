<?php
/**
 * Recibe el resultado del barrido de red (ping sweep + ARP) que hace
 * agente_navissi.ps1 -EscanearRed. Guarda cada dispositivo visto, con MAC
 * como identificador estable (una IP puede rotar por DHCP, la MAC no).
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = db();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || empty($data['dispositivos']) || !is_array($data['dispositivos'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta la lista de dispositivos.']);
    exit;
}

$sedeId = !empty($data['sede']) ? sede_id_por_nombre($pdo, $data['sede'], false) : null;
$nuevos = 0;
$actualizados = 0;

$stmtBuscar = $pdo->prepare("SELECT id FROM dispositivos_red WHERE mac = ?");
$stmtInsert = $pdo->prepare("INSERT INTO dispositivos_red (ip, mac, hostname, sede_id, primera_vez_visto, ultima_vez_visto, estado) VALUES (?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,'ACTIVO')");
$stmtUpdate = $pdo->prepare("UPDATE dispositivos_red SET ip = ?, ultima_vez_visto = CURRENT_TIMESTAMP, estado = 'ACTIVO' WHERE id = ?");

foreach ($data['dispositivos'] as $d) {
    $ip = limpio($d['ip'] ?? null);
    $mac = strtoupper(limpio($d['mac'] ?? null) ?: '');
    if (!$ip || !$mac) continue;

    $stmtBuscar->execute([$mac]);
    $existente = $stmtBuscar->fetchColumn();
    if ($existente) {
        $stmtUpdate->execute([$ip, $existente]);
        $actualizados++;
    } else {
        $stmtInsert->execute([$ip, $mac, gethostbyaddr($ip) ?: null, $sedeId]);
        $nuevos++;
    }
}

// Cualquier dispositivo que no se vio en ESTE barrido pero llevaba mucho sin
// verse, se marca inactivo (no se borra - queda el historial).
$diasInactivo = umbral($pdo, 'DISPOSITIVO_RED_INACTIVO_DIAS', 7);
$pdo->prepare("UPDATE dispositivos_red SET estado = 'INACTIVO' WHERE estado = 'ACTIVO' AND julianday('now') - julianday(ultima_vez_visto) > CAST(? AS REAL)")->execute([$diasInactivo]);

echo json_encode(['ok' => true, 'nuevos' => $nuevos, 'actualizados' => $actualizados]);
