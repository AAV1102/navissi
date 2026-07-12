<?php
/**
 * Redirección real: "Plantillas de Formulario" (nombre histórico de WorkManager) vive
 * implementado dentro de formulario_tienda.php en NAVISSI, para no duplicar lógica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: formulario_tienda.php');
exit;
