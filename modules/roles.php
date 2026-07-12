<?php
/**
 * Redirección real: "Roles" (nombre histórico de WorkManager) vive
 * implementado dentro de usuarios.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: usuarios.php');
exit;
