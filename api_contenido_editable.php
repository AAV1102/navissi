<?php
/** Guarda un texto editado en vivo (editor estilo WordPress) - ver editable() en config.php. */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
requiere_login('');
if (!tiene_rol(['ADMIN'])) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'No autorizado.']); exit; }

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$clave = limpio($data['clave'] ?? null);
$valor = trim((string) ($data['valor'] ?? ''));
$defecto = (string) ($data['defecto'] ?? '');
if (!$clave) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Falta la clave.']); exit; }

$pdo = db();
$u = usuario_actual();
if ($valor === '' || $valor === $defecto) {
    // Vacío o igual al texto de fábrica: no hace falta guardar un override.
    $pdo->prepare("DELETE FROM contenido_editable WHERE clave = ?")->execute([$clave]);
} else {
    $pdo->prepare("INSERT INTO contenido_editable (clave, valor, actualizado_por) VALUES (?,?,?)
        ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor, actualizado_por = excluded.actualizado_por, actualizado_en = CURRENT_TIMESTAMP")
        ->execute([$clave, $valor, $u['nombre'] ?? null]);
}
echo json_encode(['ok' => true]);
