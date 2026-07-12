<?php
/**
 * Redirección real: "Ubicaciones" (nombre histórico de WorkManager) vive
 * implementado dentro de sedes.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: sedes.php');
exit;
