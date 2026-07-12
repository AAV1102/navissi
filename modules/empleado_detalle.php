<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE e.id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    layout_inicio('Empleado no encontrado', 'RRHH', '../');
    echo '<div class="msg-error">Ese empleado no existe.</div><a class="btn" href="rrhh.php">Volver</a>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_campos') {
    foreach ($_POST['campo'] ?? [] as $campoId => $valor) {
        $pdo->prepare("INSERT INTO campos_personalizados_valor (campo_id, entidad_id, valor) VALUES (?,?,?)
            ON CONFLICT(campo_id, entidad_id) DO UPDATE SET valor = excluded.valor")
            ->execute([(int) $campoId, $id, limpio($valor)]);
    }
    header("Location: empleado_detalle.php?id={$id}&campos_guardados=1");
    exit;
}
$camposDef = $pdo->query("SELECT * FROM campos_personalizados_def WHERE entidad = 'empleados' ORDER BY nombre_campo")->fetchAll(PDO::FETCH_ASSOC);
$camposValores = [];
if ($camposDef) {
    $stmt = $pdo->prepare("SELECT campo_id, valor FROM campos_personalizados_valor WHERE entidad_id = ?");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) $camposValores[$v['campo_id']] = $v['valor'];
}

// Cuenta Microsoft 365 relacionada, cruzando por correo
$ms365 = null;
if ($emp['email']) {
    $stmt = $pdo->prepare("SELECT * FROM ms365_usuarios WHERE correo = ? COLLATE NOCASE");
    $stmt->execute([$emp['email']]);
    $ms365 = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Credenciales relacionadas por nombre (aproximado, ya que credenciales no tiene FK a empleados)
$stmt = $pdo->prepare("SELECT c.*, s.nombre AS sede_nombre FROM credenciales c LEFT JOIN sedes s ON c.sede_id = s.id
    WHERE c.nombre LIKE ? OR c.usuario = ?");
$partesNombre = '%' . explode(' ', trim($emp['nombres']))[0] . '%';
$stmt->execute([$partesNombre, $emp['email']]);
$credenciales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Equipos asignados: primero por vinculo real (documento), y como respaldo
// por coincidencia de nombre para equipos viejos que aun no se han vuelto a
// guardar con el selector nuevo.
$stmt = $pdo->prepare("SELECT * FROM inventario WHERE asignado_documento = ? OR (asignado_documento IS NULL AND asignado_a LIKE ?)");
$stmt->execute([$emp['documento'], $partesNombre]);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tickets donde aparece como solicitante
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE solicitante LIKE ? ORDER BY creado_en DESC LIMIT 10");
$stmt->execute([$partesNombre]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Progreso de cursos
$stmt = $pdo->prepare("SELECT l.titulo AS leccion, c.titulo AS curso, p.completado_en
    FROM progreso_cursos p JOIN lecciones l ON p.leccion_id = l.id JOIN cursos c ON l.curso_id = c.id
    WHERE p.empleado_documento = ? ORDER BY p.completado_en DESC");
$stmt->execute([$emp['documento']]);
$progreso = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Desprendibles
$stmt = $pdo->prepare("SELECT * FROM desprendibles WHERE empleado_documento = ? ORDER BY periodo DESC");
$stmt->execute([$emp['documento']]);
$desprendibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio($emp['nombres'], 'RRHH', '../');
?>
<p class="small"><a href="rrhh.php">← Volver a RRHH</a></p>
<h1><?= icon('users','icon-lg') ?> <?= e($emp['nombres']) ?> <span class="badge <?= $emp['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($emp['estado']) ?></span></h1>
<p class="subtitle"><?= e($emp['cargo']) ?> · <?= e($emp['area']) ?> · <?= e($emp['sede_nombre']) ?></p>

<div class="cards">
    <div class="card"><div class="num"><?= count($equipos) ?></div><div class="label">Equipos asignados</div></div>
    <div class="card"><div class="num"><?= count($credenciales) ?></div><div class="label">Credenciales</div></div>
    <div class="card"><div class="num"><?= count($tickets) ?></div><div class="label">Tickets reportados</div></div>
    <div class="card"><div class="num"><?= count($progreso) ?></div><div class="label">Lecciones completadas</div></div>
</div>

<div class="panel">
    <h3>Datos personales
        <a href="rrhh.php?editar=<?= (int)$emp['id'] ?>" class="btn btn-secondary" style="float:right;font-size:12px;padding:5px 12px;">Editar</a>
    </h3>
    <table class="deftable">
        <tr><th>Documento</th><td><?= e($emp['documento']) ?></td><th>Email</th><td><?= e($emp['email']) ?: '—' ?></td></tr>
        <tr><th>Cargo</th><td><?= e($emp['cargo']) ?></td><th>Área</th><td><?= e($emp['area']) ?></td></tr>
        <tr><th>Sede</th><td><?= e($emp['sede_nombre']) ?></td><th>Tipo de contrato</th><td><?= e($emp['tipo_contrato']) ?: '—' ?></td></tr>
        <tr><th>Fecha de ingreso</th><td><?= e($emp['fecha_ingreso']) ?: '—' ?></td><th>Salario</th><td><?= $emp['salario'] ? '$' . number_format($emp['salario'],0,',','.') : '—' ?></td></tr>
    </table>
    <a class="btn" style="margin-top:10px;" href="certificado_laboral.php?documento=<?= urlencode($emp['documento']) ?>" target="_blank">📄 Certificado laboral</a>
    <a class="btn btn-secondary" style="margin-top:10px;" href="rrhh_certificados.php?documento=<?= urlencode($emp['documento']) ?>">💰 Desprendibles (<?= count($desprendibles) ?>)</a>
    <a class="btn btn-secondary" style="margin-top:10px;" href="hoja_vida.php?tipo=EMPLEADO&id=<?= urlencode($emp['documento']) ?>">📋 Hoja de vida completa</a>
</div>

<?php if ($camposDef): ?>
<div class="panel">
    <h3><?= icon('users') ?> Campos personalizados</h3>
    <?php if (isset($_GET['campos_guardados'])): ?><div class="msg-ok"><?= icon('check') ?> Guardado.</div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="accion" value="guardar_campos">
        <div class="grid-form">
            <?php foreach ($camposDef as $cd): $valorActual = $camposValores[$cd['id']] ?? ''; ?>
            <div>
                <label><?= e($cd['nombre_campo']) ?></label>
                <?php if ($cd['tipo'] === 'LISTA' && $cd['opciones']): ?>
                <select name="campo[<?= (int)$cd['id'] ?>]">
                    <option value="">-- sin definir --</option>
                    <?php foreach (array_map('trim', explode(',', $cd['opciones'])) as $op): ?>
                    <option <?= $valorActual===$op?'selected':'' ?>><?= e($op) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php elseif ($cd['tipo'] === 'FECHA'): ?>
                <input type="date" name="campo[<?= (int)$cd['id'] ?>]" value="<?= e($valorActual) ?>">
                <?php elseif ($cd['tipo'] === 'NUMERO'): ?>
                <input type="number" name="campo[<?= (int)$cd['id'] ?>]" value="<?= e($valorActual) ?>">
                <?php else: ?>
                <input type="text" name="campo[<?= (int)$cd['id'] ?>]" value="<?= e($valorActual) ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit"><?= icon('check') ?> Guardar campos</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Cuenta Microsoft 365</h3>
    <?php if ($ms365): ?>
        <table class="deftable">
            <tr><th>Correo</th><td><?= e($ms365['correo']) ?></td><th>Cuenta</th><td><span class="badge <?= $ms365['cuenta_activa']?'badge-activo':'badge-otro' ?>"><?= $ms365['cuenta_activa'] ? 'ACTIVA' : 'BLOQUEADA' ?></span></td></tr>
            <tr><th>Licencias</th><td colspan="3"><?= e($ms365['licencias']) ?: '—' ?></td></tr>
        </table>
    <?php else: ?>
        <p class="small">No se encontró una cuenta de Microsoft 365 con este correo. <?= $emp['email'] ? '' : 'Este empleado no tiene correo registrado.' ?> <a href="microsoft365.php">Sincronizar Microsoft 365</a></p>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Equipos asignados (<?= count($equipos) ?>)</h3>
    <?php if (!$equipos): ?><p class="small">Sin equipos encontrados a este nombre.</p><?php else: ?>
    <table>
        <tr><th>Serial</th><th>Tipo</th><th>Marca/Modelo</th><th>Estado</th><th></th></tr>
        <?php foreach ($equipos as $eq): ?>
        <tr><td><?= e($eq['serial']) ?></td><td><?= e($eq['tipo']) ?></td><td><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?></td>
            <td><span class="badge <?= $eq['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($eq['estado']) ?></span></td>
            <td><a href="inventario.php?editar=<?= (int)$eq['id'] ?>">Ver</a></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Credenciales relacionadas (<?= count($credenciales) ?>)</h3>
    <?php if (!$credenciales): ?><p class="small">Sin credenciales encontradas a este nombre/correo.</p><?php else: ?>
    <table>
        <tr><th>Sistema</th><th>Usuario</th><th>Sede</th><th></th></tr>
        <?php foreach ($credenciales as $c): ?>
        <tr><td><?= e($c['sistema']) ?></td><td><?= e($c['usuario']) ?></td><td><?= e($c['sede_nombre']) ?></td>
            <td><a href="credenciales.php?editar=<?= (int)$c['id'] ?>">Ver</a></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Tickets reportados (<?= count($tickets) ?>)</h3>
    <?php if (!$tickets): ?><p class="small">Sin tickets a este nombre.</p><?php else: ?>
    <table>
        <tr><th>#</th><th>Título</th><th>Estado</th><th></th></tr>
        <?php foreach ($tickets as $t): ?>
        <tr><td>#<?= (int)$t['id'] ?></td><td><?= e($t['titulo']) ?></td>
            <td><span class="badge <?= $t['estado']==='CERRADO'?'badge-otro':'badge-activo' ?>"><?= e($t['estado']) ?></span></td>
            <td><a href="ticket_detalle.php?id=<?= (int)$t['id'] ?>">Ver</a></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Capacitación completada (<?= count($progreso) ?>)</h3>
    <?php if (!$progreso): ?><p class="small">Sin lecciones completadas registradas para este documento.</p><?php else: ?>
    <table>
        <tr><th>Curso</th><th>Lección</th><th>Completada</th></tr>
        <?php foreach ($progreso as $p): ?>
        <tr><td><?= e($p['curso']) ?></td><td><?= e($p['leccion']) ?></td><td class="small"><?= e($p['completado_en']) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php layout_fin(); ?>
