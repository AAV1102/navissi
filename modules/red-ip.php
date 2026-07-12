<?php
/**
 * Redirección real: "Red IP" (nombre histórico de WorkManager) vive
 * implementado dentro de network_discovery.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: network_discovery.php');
exit;
