<?php
require_once __DIR__ . '/config.php';
requiere_login('');
$u = usuario_actual();

if ($u['rol'] === 'SUPER_ADMIN' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rol = trim($_POST['rol'] ?? '');
    if ($rol === '' || $rol === 'SUPER_ADMIN') {
        unset($_SESSION['ver_como_rol'], $_SESSION['ver_como_area']);
    } else {
        $_SESSION['ver_como_rol'] = $rol;
        $_SESSION['ver_como_area'] = trim($_POST['area'] ?? '') ?: null;
    }
}

$volver = $_POST['volver'] ?? 'index.php';
header('Location: ' . ($volver ?: 'index.php'));
