<?php
/**
 * Redirección real: "Office 365" (nombre histórico de WorkManager) vive
 * implementado dentro de microsoft365.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: microsoft365.php');
exit;
