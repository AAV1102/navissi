<?php
/**
 * Redirección real: "Config Alertas" (nombre histórico de WorkManager) vive
 * implementado dentro de umbrales_alertas.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: umbrales_alertas.php');
exit;
