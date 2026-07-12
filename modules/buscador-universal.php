<?php
require_once __DIR__ . '/../config.php';
requiere_login('../');
header('Location: buscador_universal.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
exit;
