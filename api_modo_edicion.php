<?php
/** Prende/apaga el Modo edición (editor de textos en vivo) de la sesión actual. */
require_once __DIR__ . '/config.php';
requiere_login('');
if (!tiene_rol(['ADMIN'])) { http_response_code(403); exit('No autorizado.'); }

$_SESSION['modo_edicion'] = empty($_SESSION['modo_edicion']) ? 1 : 0;

$volver = $_POST['volver'] ?? 'index.php';
// Evita redirigir a otro dominio (open redirect) - solo rutas propias.
if (!preg_match('#^/[^/\\\\]#', $volver) && !preg_match('#^[a-zA-Z0-9_./-]+$#', $volver)) $volver = 'index.php';
header('Location: ' . $volver);
