<?php
/**
 * Redirección real: "Dashboard Mejorado" (nombre histórico de WorkManager) vive
 * implementado dentro de ../index.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: ../index.php');
exit;
