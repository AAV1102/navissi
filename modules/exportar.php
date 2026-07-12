<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/xlsx_writer.php';
$pdo = db();

if (isset($_GET['descargar'])) {
    $sheets = [];

    $inv = $pdo->query("
        SELECT i.serial, i.placa, i.asignado_a, s.nombre AS sede, i.area, i.cargo, i.tipo, i.marca, i.modelo,
               i.sistema_operativo, i.procesador, i.memoria, i.almacenamiento, i.estado
        FROM inventario i LEFT JOIN sedes s ON i.sede_id = s.id ORDER BY i.serial
    ")->fetchAll(PDO::FETCH_ASSOC);
    $rows = [['SERIAL','PLACA','ASIGNADO_A','SEDE','AREA','CARGO','TIPO','MARCA','MODELO','SO','PROCESADOR','MEMORIA','ALMACENAMIENTO','ESTADO']];
    foreach ($inv as $r) $rows[] = array_values($r);
    $sheets['INVENTARIO'] = $rows;

    $cred = $pdo->query("
        SELECT c.nombre, s.nombre AS sede, c.sistema, c.usuario, c.contrasena, c.categoria, c.estado
        FROM credenciales c LEFT JOIN sedes s ON c.sede_id = s.id ORDER BY c.sistema, s.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
    $rows = [['NOMBRE','SEDE','SISTEMA','USUARIO','CONTRASEÑA','CATEGORIA','ESTADO']];
    foreach ($cred as $r) $rows[] = array_values($r);
    $sheets['CREDENCIALES'] = $rows;

    $sedes = $pdo->query("SELECT nombre, ciudad, direccion, proveedor_internet, ip_red, estado FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $rows = [['NOMBRE','CIUDAD','DIRECCION','PROVEEDOR_INTERNET','IP_RED','ESTADO']];
    foreach ($sedes as $r) $rows[] = array_values($r);
    $sheets['SEDES'] = $rows;

    $lic = $pdo->query("SELECT proveedor, tipo, cantidad, valor_mes, valor_anual, observaciones FROM licencias ORDER BY proveedor")->fetchAll(PDO::FETCH_ASSOC);
    $rows = [['PROVEEDOR','TIPO','CANTIDAD','VALOR_MES','VALOR_ANUAL','OBSERVACIONES']];
    foreach ($lic as $r) $rows[] = array_values($r);
    $sheets['LICENCIAS'] = $rows;

    $emp = $pdo->query("
        SELECT e.documento, e.nombres, e.cargo, e.area, s.nombre AS sede, e.email, e.estado
        FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id ORDER BY e.nombres
    ")->fetchAll(PDO::FETCH_ASSOC);
    $rows = [['DOCUMENTO','NOMBRES','CARGO','AREA','SEDE','EMAIL','ESTADO']];
    foreach ($emp as $r) $rows[] = array_values($r);
    $sheets['RRHH'] = $rows;

    $tmp = __DIR__ . '/../data/export_' . date('Ymd_His') . '.xlsx';
    xlsx_write($sheets, $tmp);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="NAVISSI_INVENTARIO_' . date('Y-m-d') . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

require_once __DIR__ . '/../lib/layout.php';
$totales = [
    'Inventario' => $pdo->query("SELECT COUNT(*) FROM inventario")->fetchColumn(),
    'Credenciales' => $pdo->query("SELECT COUNT(*) FROM credenciales")->fetchColumn(),
    'Sedes' => $pdo->query("SELECT COUNT(*) FROM sedes")->fetchColumn(),
    'Licencias' => $pdo->query("SELECT COUNT(*) FROM licencias")->fetchColumn(),
    'RRHH' => $pdo->query("SELECT COUNT(*) FROM empleados")->fetchColumn(),
];
layout_inicio('Exportar', 'Exportar', '../');
?>
<h1><?= icon('file','icon-lg') ?> Exportar a Excel</h1>
<p class="subtitle">Genera un .xlsx con todo lo que hay hoy en el software (útil como respaldo o para llevarlo a otra parte).</p>

<div class="panel">
    <table>
        <tr><th>Módulo</th><th>Registros a exportar</th></tr>
        <?php foreach ($totales as $k => $v): ?>
        <tr><td><?= e($k) ?></td><td><?= (int)$v ?></td></tr>
        <?php endforeach; ?>
    </table>
    <br>
    <a class="btn" href="?descargar=1">⬇ Descargar Excel completo</a>
</div>
<?php layout_fin(); ?>
