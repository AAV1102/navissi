<?php
/**
 * Redirección real: "Dispositivos" (nombre histórico de WorkManager) vive
 * implementado dentro de inventario.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: inventario.php');
exit;
