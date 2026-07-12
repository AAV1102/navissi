<?php
/**
 * Redirección real: "Seguridad" (nombre histórico de WorkManager) vive
 * implementado dentro de alertas.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: alertas.php');
exit;
