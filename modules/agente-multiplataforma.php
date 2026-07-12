<?php
/**
 * Redirección real: "Agente Multiplataforma" (nombre histórico de WorkManager) vive
 * implementado dentro de agente_inventario.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: agente_inventario.php');
exit;
