<?php
/**
 * Redirección real: "Impresoras" (nombre histórico de WorkManager) vive
 * implementado dentro de inventario.php?q=IMPRESORA en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: inventario.php?q=IMPRESORA');
exit;
