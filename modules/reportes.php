<?php
/**
 * Redirección real: "Reportes" (nombre histórico de WorkManager) vive
 * implementado dentro de informes.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: informes.php');
exit;
