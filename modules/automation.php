<?php
/**
 * Redirección real: "Automation" (nombre histórico de WorkManager) vive
 * implementado dentro de n8n.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: n8n.php');
exit;
