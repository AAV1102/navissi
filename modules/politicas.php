<?php
/**
 * Redirección real: "Políticas" (nombre histórico de WorkManager) vive
 * implementado dentro de sla_politicas.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: sla_politicas.php');
exit;
