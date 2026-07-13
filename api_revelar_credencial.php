<?php
require_once __DIR__ . '/config.php';
requiere_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$id = (int) ($_GET['id'] ?? 0);
$u = usuario_actual();
$stmt = db()->prepare("SELECT id, usuario_id, contrasena FROM credenciales WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$fila = $stmt->fetch(PDO::FETCH_ASSOC);
$rol = rol_efectivo();
$puedeAdministrar = in_array($rol, ['SUPER_ADMIN', 'ADMIN', 'TI'], true);
if (!$fila || (!$puedeAdministrar && (int) ($fila['usuario_id'] ?? 0) !== (int) ($u['id'] ?? 0))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
    exit;
}

echo json_encode(['ok' => true, 'secreto' => secreto_descifrar($fila['contrasena'])]);
