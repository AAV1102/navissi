<?php
/**
 * Redirección real: "Permisos Laborales" (nombre histórico de WorkManager) vive
 * implementado dentro de vacaciones.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: vacaciones.php');
exit;
