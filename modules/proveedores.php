<?php
/**
 * Redirección real: "Proveedores" (nombre histórico de WorkManager) vive
 * implementado dentro de contratos.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: contratos.php');
exit;
