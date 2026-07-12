<?php
/**
 * Redirección real: "Gestión Humana" (nombre histórico de WorkManager) vive
 * implementado dentro de rrhh.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: rrhh.php');
exit;
