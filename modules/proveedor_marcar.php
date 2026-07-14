<?php
require_once __DIR__ . '/../config.php';
requiere_login('../');
if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'COORDINADOR', 'TI'])) { http_response_code(403); exit('Sin permiso.'); }
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE proveedores_actualizaciones SET estado = 'REVISADA', revisado_por = ? WHERE id = ?")
        ->execute([usuario_actual()['nombre'] ?? null, (int) $_POST['id']]);
}
header('Location: contratos.php');
