<?php
/**
 * Redirección real: "Config Correo" (nombre histórico de WorkManager) vive
 * implementado dentro de plantillas_correo.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: plantillas_correo.php');
exit;
