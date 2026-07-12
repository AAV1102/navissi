<?php
/**
 * Redirección real: "Licencias Office 365" (nombre histórico de WorkManager) vive
 * implementado dentro de licencias.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: licencias.php');
exit;
