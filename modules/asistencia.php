<?php
/**
 * Redirección real: "Asistencia" (nombre histórico de WorkManager) vive
 * implementado dentro de mesa_ayuda.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: mesa_ayuda.php');
exit;
