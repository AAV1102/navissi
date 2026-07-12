<?php
/**
 * Redirección real: "Documental" (nombre histórico de WorkManager) vive
 * implementado dentro de documentos.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: documentos.php');
exit;
