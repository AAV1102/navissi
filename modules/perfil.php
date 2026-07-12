<?php
/**
 * Redirección real: "Perfil" (nombre histórico de WorkManager) vive
 * implementado dentro de 2fa_configurar.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: 2fa_configurar.php');
exit;
