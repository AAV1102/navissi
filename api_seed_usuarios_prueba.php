<?php
/**
 * Script de un solo uso: crea los usuarios de prueba (uno por rol + uno
 * "completo" con empleado/equipo/credencial/ticket) en produccion. Protegido
 * con un token derivado del dominio, igual que los demas endpoints de
 * mantenimiento. Se borra a si mismo despues de ejecutar una vez.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$tokenEsperado = hash('sha256', 'navissi-seed-' . ($_SERVER['HTTP_HOST'] ?? ''));
if (($_GET['token'] ?? '') !== $tokenEsperado) {
    http_response_code(403);
    echo "Token invalido.\n";
    exit;
}

$pdo = db();

$usuarios = [
    ['ADMIN', 'Direccion de Tecnologia'],
    ['DIRECTOR', 'Direccion Comercial'],
    ['GERENCIA', 'Gerencia'],
    ['CEO', 'CEOs'],
    ['COORDINADOR', 'Servicio al Cliente'],
    ['ANALISTA', 'Direccion de Contabilidad'],
    ['TI', 'Direccion de Tecnologia'],
    ['RRHH', 'Direccion Recursos Humanos'],
    ['EMPLEADO', 'Direccion de Logistica'],
];

foreach ($usuarios as [$rol, $area]) {
    $email = 'prueba.' . strtolower($rol) . '@navissi.local';
    $clave = 'Prueba' . ucfirst(strtolower($rol)) . '2026*';
    $pdo->prepare("DELETE FROM usuarios_sistema WHERE email = ?")->execute([$email]);
    $pdo->prepare("INSERT INTO usuarios_sistema (nombre, email, password_hash, rol, area_responsable, activo, password_temporal) VALUES (?,?,?,?,?,1,0)")
        ->execute(['Prueba ' . ucfirst(strtolower($rol)), $email, password_hash($clave, PASSWORD_DEFAULT), $rol, $area]);
    echo "OK: {$email} | {$rol} | {$area}\n";
}

// Usuario "completo": empleado real + cuenta + equipo + credencial + ticket
$doc = '1099999001';
$email = 'prueba.completo@navissi.local';
$clave = 'PruebaCompleto2026*';
$sede = $pdo->query("SELECT id FROM sedes ORDER BY id LIMIT 1")->fetchColumn();

$pdo->prepare("DELETE FROM empleados WHERE documento = ?")->execute([$doc]);
$pdo->prepare("INSERT INTO empleados (documento, nombres, cargo, area, sede_id, email, estado, fecha_ingreso, tipo_contrato, salario) VALUES (?,?,?,?,?,?,?,?,?,?)")
    ->execute([$doc, 'Usuario Prueba Completo', 'Analista de Pruebas', 'Direccion de Tecnologia', $sede, $email, 'ACTIVO', date('Y-m-d'), 'INDEFINIDO', 3000000]);

$pdo->prepare("DELETE FROM usuarios_sistema WHERE email = ?")->execute([$email]);
$pdo->prepare("INSERT INTO usuarios_sistema (nombre, email, documento, password_hash, rol, area_responsable, sede_id, activo, password_temporal) VALUES (?,?,?,?,?,?,?,1,0)")
    ->execute(['Usuario Prueba Completo', $email, $doc, password_hash($clave, PASSWORD_DEFAULT), 'EMPLEADO', 'Direccion de Tecnologia', $sede]);

$serialPrueba = 'PRUEBA-EQ-' . $doc;
$pdo->prepare("DELETE FROM inventario WHERE serial = ?")->execute([$serialPrueba]);
$pdo->prepare("INSERT INTO inventario (serial, placa, asignado_a, asignado_documento, sede_id, area, cargo, tipo, marca, modelo, sistema_operativo, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$serialPrueba, 'PL-' . $doc, 'Usuario Prueba Completo', $doc, $sede, 'Direccion de Tecnologia', 'Analista de Pruebas', 'PORTATIL', 'Dell', 'Latitude 5420', 'Windows 11', 'ACTIVO']);

$pdo->prepare("DELETE FROM credenciales WHERE nombre = ?")->execute(['Prueba Completo - Correo']);
$pdo->prepare("INSERT INTO credenciales (nombre, sede_id, sistema, usuario, contrasena, categoria, estado) VALUES (?,?,?,?,?,?,?)")
    ->execute(['Prueba Completo - Correo', $sede, 'Microsoft 365', $email, secreto_cifrar('ClaveDePrueba123*'), 'CORREO', 'ACTIVA']);

$pdo->prepare("DELETE FROM tickets WHERE titulo = ?")->execute(['Ticket de prueba - usuario completo']);
$pdo->prepare("INSERT INTO tickets (titulo, descripcion, solicitante, creado_por_documento, solicitante_area, sede_id, estado, prioridad, equipo_serial) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute(['Ticket de prueba - usuario completo', 'Ticket generado para probar el flujo end-to-end del usuario de prueba completo.', 'Usuario Prueba Completo', $doc, 'Direccion de Tecnologia', $sede, 'ABIERTO', 'MEDIA', $serialPrueba]);

echo "OK: {$email} | {$clave} | documento={$doc} | equipo={$serialPrueba}\n";
echo "\nTodo listo.\n";
