<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

if (!tiene_rol(['GERENCIA', 'CEO', 'ADMIN', 'RRHH', 'DIRECTOR'])) {
    layout_inicio('Organigrama', 'Organigrama', '../');
    echo '<div class="msg-error">No tienes permiso para ver el organigrama.</div>';
    layout_fin();
    exit;
}

$departamentos = $pdo->query("SELECT * FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$empleadosPorArea = [];
$stmt = $pdo->query("SELECT documento, nombres, cargo, area FROM empleados WHERE estado = 'ACTIVO' ORDER BY nombres");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
    $empleadosPorArea[$e['area'] ?: 'Sin área'][] = $e;
}
$sinDepartamento = array_diff(array_keys($empleadosPorArea), array_column($departamentos, 'nombre'));

layout_inicio('Organigrama', 'Organigrama', '../');
?>
<h1><?= icon('users','icon-lg') ?> Organigrama de la Empresa</h1>
<p class="subtitle">Estructura organizacional generada a partir de Departamentos y Cargos + empleados activos en RRHH.</p>

<div class="org-chart">
    <div class="org-nodo org-raiz">
        <strong>Grupo 10Z SAS</strong>
    </div>
    <div class="org-ramas">
        <?php foreach ($departamentos as $d): $miembros = $empleadosPorArea[$d['nombre']] ?? []; ?>
        <div class="org-columna">
            <div class="org-nodo org-departamento">
                <strong><?= e($d['nombre']) ?></strong>
                <?php if ($d['responsable']): ?><span class="small">Responsable: <?= e($d['responsable']) ?></span><?php endif; ?>
                <span class="small"><?= count($miembros) ?> persona(s)</span>
            </div>
            <?php foreach ($miembros as $m): ?>
            <div class="org-nodo org-empleado">
                <?= e($m['nombres']) ?>
                <?php if ($m['cargo']): ?><span class="small"><?= e($m['cargo']) ?></span><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$miembros): ?><p class="small" style="text-align:center;">Sin personal asignado.</p><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$departamentos): ?><p class="small">Aún no hay departamentos creados. <a href="departamentos.php">Crear el primero</a>.</p><?php endif; ?>
    </div>
</div>

<?php if ($sinDepartamento): ?>
<div class="panel" style="margin-top:20px;">
    <h3><?= icon('bell') ?> Áreas sin departamento formal</h3>
    <p class="small">Estos empleados tienen un área asignada en RRHH que no coincide con ningún departamento registrado en <a href="departamentos.php">Departamentos y Cargos</a>.</p>
    <ul>
        <?php foreach ($sinDepartamento as $area): ?>
        <li><?= e($area) ?> (<?= count($empleadosPorArea[$area]) ?> persona(s))</li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
