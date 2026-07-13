<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM sedes WHERE id = ?");
$stmt->execute([$id]);
$sede = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sede) {
    layout_inicio('Sede no encontrada', 'Sedes', '../');
    echo '<div class="msg-error">Esa sede no existe.</div><a class="btn" href="sedes.php">Volver a Sedes</a>';
    layout_fin();
    exit;
}

$estadoHorario = sede_esta_abierta($pdo, $id);

$stmt = $pdo->prepare("SELECT * FROM inventario WHERE sede_id = ? ORDER BY tipo, marca");
$stmt->execute([$id]);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM empleados WHERE sede_id = ? ORDER BY nombres");
$stmt->execute([$id]);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM credenciales WHERE sede_id = ? ORDER BY sistema");
$stmt->execute([$id]);
$credenciales = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio($sede['nombre'], 'Sedes', '../');
?>
<p class="small"><a href="sedes.php">← Volver a Sedes</a></p>
<h1><?= icon('building','icon-lg') ?> <?= e($sede['nombre']) ?> <span class="badge <?= $sede['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($sede['estado']) ?></span>
    <?php if ($estadoHorario === null): ?>
        <a href="horario_laboral.php?sede_id=<?= (int)$id ?>" class="badge badge-otro small"><?= icon('bell') ?> Configurar horario</a>
    <?php elseif ($estadoHorario['abierta']): ?>
        <span class="badge badge-activo"><?= icon('check') ?> Abierto ahora (<?= e($estadoHorario['apertura']) ?>–<?= e($estadoHorario['cierre']) ?>)</span>
    <?php else: ?>
        <span class="badge badge-err"><?= icon('x') ?> Cerrado ahora</span>
    <?php endif; ?>
</h1>
<p class="subtitle">Ficha completa de la sede: contactos, equipos, personal y credenciales relacionados.</p>

<div class="cards">
    <div class="card"><div class="num"><?= count($equipos) ?></div><div class="label">Equipos asignados</div></div>
    <div class="card"><div class="num"><?= count($empleados) ?></div><div class="label">Empleados</div></div>
    <div class="card"><div class="num"><?= count($credenciales) ?></div><div class="label">Credenciales</div></div>
</div>

<div class="panel">
    <h3>Contacto y ubicación
        <a href="sedes.php?editar=<?= (int)$sede['id'] ?>" class="btn btn-secondary" style="float:right;font-size:12px;padding:5px 12px;">Editar datos</a>
    </h3>
    <table class="deftable">
        <tr><th>Ciudad</th><td><?= e($sede['ciudad']) ?: '—' ?></td><th>Zona</th><td><?= e($sede['zona']) ?: '—' ?></td></tr>
        <tr><th>Dirección</th><td colspan="3"><?= e($sede['direccion']) ?: '—' ?></td></tr>
        <tr><th>Correo corporativo</th><td colspan="3"><?= e($sede['correo_corporativo']) ?: '—' ?></td></tr>
        <tr><th>Coordinadora</th><td><?= e($sede['coordinadora']) ?: '—' ?></td><th>Celular</th><td><?= e($sede['coordinadora_celular']) ?: '—' ?></td></tr>
        <tr><th>Administradora</th><td><?= e($sede['administradora']) ?: '—' ?></td><th>Celular</th><td><?= e($sede['administradora_celular']) ?: '—' ?></td></tr>
        <tr><th>Segunda encargada</th><td><?= e($sede['segunda_encargada']) ?: '—' ?></td><th>Celular</th><td><?= e($sede['segunda_encargada_celular']) ?: '—' ?></td></tr>
        <tr><th>Proveedor internet</th><td><?= e($sede['proveedor_internet']) ?: '—' ?></td><th>IP red</th><td><?= e($sede['ip_red']) ?: '—' ?></td></tr>
        <tr><th>IP asignada</th><td colspan="3"><?= e($sede['ip_asignada']) ?: '—' ?></td></tr>
    </table>
</div>

<div class="panel">
    <h3>Equipos / tecnología asignada (<?= count($equipos) ?>)
        <a href="inventario.php" class="btn btn-secondary" style="float:right;font-size:12px;padding:5px 12px;">+ Agregar equipo</a>
    </h3>
    <?php if (!$equipos): ?><p class="small">Sin equipos registrados en esta sede todavía.</p><?php else: ?>
    <table>
        <tr><th>Serial</th><th>Placa</th><th>Asignado a</th><th>Tipo</th><th>Marca/Modelo</th><th>Estado</th><th></th></tr>
        <?php foreach ($equipos as $eq): ?>
        <tr>
            <td><?= e($eq['serial']) ?></td>
            <td><?= e($eq['placa']) ?></td>
            <td><?= e($eq['asignado_a']) ?></td>
            <td><?= e($eq['tipo']) ?></td>
            <td><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?></td>
            <td><span class="badge <?= $eq['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($eq['estado']) ?></span></td>
            <td><a href="inventario.php?editar=<?= (int)$eq['id'] ?>">Ver hoja de vida</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Empleados en esta sede (<?= count($empleados) ?>)
        <a href="rrhh.php" class="btn btn-secondary" style="float:right;font-size:12px;padding:5px 12px;">+ Agregar empleado</a>
    </h3>
    <?php if (!$empleados): ?><p class="small">Sin empleados registrados en esta sede todavía.</p><?php else: ?>
    <table>
        <tr><th>Nombres</th><th>Cargo</th><th>Documento</th><th>Email</th><th>Estado</th><th></th></tr>
        <?php foreach ($empleados as $emp): ?>
        <tr>
            <td><?= e($emp['nombres']) ?></td>
            <td><?= e($emp['cargo']) ?></td>
            <td><?= e($emp['documento']) ?></td>
            <td><?= e($emp['email']) ?></td>
            <td><span class="badge <?= $emp['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($emp['estado']) ?></span></td>
            <td><a href="rrhh.php?editar=<?= (int)$emp['id'] ?>">Editar</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Credenciales de esta sede (<?= count($credenciales) ?>)
        <a href="credenciales.php" class="btn btn-secondary" style="float:right;font-size:12px;padding:5px 12px;">+ Agregar credencial</a>
    </h3>
    <?php if (!$credenciales): ?><p class="small">Sin credenciales registradas en esta sede todavía.</p><?php else: ?>
    <table>
        <tr><th>Sistema</th><th>Usuario</th><th>Contraseña</th><th>Categoría</th><th></th></tr>
        <?php foreach ($credenciales as $c): ?>
        <tr>
            <td><?= e($c['sistema']) ?></td>
            <td><?= e($c['usuario']) ?></td>
            <td><code id="credencial-<?= (int)$c['id'] ?>">••••••••</code><?php if (tiene_rol(['ADMIN', 'TI'])): ?> <button type="button" class="btn btn-secondary revelar-credencial" data-id="<?= (int)$c['id'] ?>" data-target="credencial-<?= (int)$c['id'] ?>" style="padding:3px 8px;font-size:12px;">Ver</button><?php endif; ?></td>
            <td><?= e($c['categoria']) ?></td>
            <td><a href="credenciales.php?editar=<?= (int)$c['id'] ?>">Editar</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php layout_fin(); ?>
