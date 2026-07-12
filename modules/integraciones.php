<?php
/**
 * Redirección real: "Integraciones" (nombre histórico de WorkManager) vive
 * implementado dentro de centro_aplicaciones.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: centro_aplicaciones.php');
exit;
