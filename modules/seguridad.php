<?php
/**
 * Redirección real: "Seguridad" (nombre histórico de WorkManager) vive
 * implementado dentro de ciberseguridad.php en NAVISSI (incidentes de
 * seguridad fisica y ciber en un solo modulo), para no duplicar logica.
 */
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: ciberseguridad.php');
exit;
