<?php
/**
 * Redirección real: "Empresas" (nombre histórico de WorkManager) vive
 * implementado dentro de crm.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: crm.php');
exit;
