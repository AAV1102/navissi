<?php
/**
 * Redirección real: "Importer" (nombre histórico de WorkManager) vive
 * implementado dentro de importar.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: importar.php');
exit;
