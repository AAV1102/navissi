<?php
if (getenv('NAVISSI_DESIGN_PREVIEW') !== '1' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config.php';
iniciar_sesion_segura();
$stmt = db()->prepare("SELECT * FROM usuarios_sistema WHERE email = ? AND activo = 1 LIMIT 1");
$stmt->execute(['design.preview@navissi.local']);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usuario) { http_response_code(404); exit; }
session_regenerate_id(true);
$_SESSION['usuario'] = sesion_desde_usuario($usuario);
$destinos = ['index.php', 'modules/inventario.php', 'modules/mesa_ayuda.php', 'modules/inteligencia_operativa.php', 'modules/retail_inteligencia.php', 'modules/siesa_integracion.php'];
$destino = (string) ($_GET['to'] ?? 'index.php');
if (!in_array($destino, $destinos, true)) $destino = 'index.php';
header('Location: ../' . $destino);
