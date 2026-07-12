<?php
/**
 * Redirección real: "Prioridades" (nombre histórico de WorkManager) vive
 * implementado dentro de categorias_tickets.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: categorias_tickets.php');
exit;
