<?php
/**
 * Redirección real: "IA" (nombre histórico de WorkManager) vive
 * implementado dentro de ia_multiagente.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: ia_multiagente.php');
exit;
