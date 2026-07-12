<?php
/**
 * Redirección real: "Educación" (nombre histórico de WorkManager) vive
 * implementado dentro de documentacion.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: documentacion.php');
exit;
