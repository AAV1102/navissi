<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null);
        $datos = [
            'documento' => limpio($_POST['documento'] ?? null),
            'nombres' => limpio($_POST['nombres'] ?? null),
            'cargo' => limpio($_POST['cargo'] ?? null),
            'area' => limpio($_POST['area'] ?? null),
            'sede_id' => $sedeId,
            'email' => limpio($_POST['email'] ?? null),
            'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVO',
            'fecha_ingreso' => limpio($_POST['fecha_ingreso'] ?? null),
            'tipo_contrato' => limpio($_POST['tipo_contrato'] ?? null),
            'salario' => $_POST['salario'] !== '' ? (float) ($_POST['salario'] ?? 0) : null,
        ];
        if (!$datos['nombres']) {
            $msg = ['error', 'El nombre es obligatorio.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE empleados SET {$set} WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Empleado actualizado.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO empleados ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Empleado agregado.'];
            }
            // Si este empleado ya tiene cuenta de NAVISSI, su área queda sincronizada
            // automáticamente - así el alcance por área de un Director no se desactualiza.
            sincronizar_usuario_desde_empleado($pdo, $datos['documento'], $datos['area'], $datos['cargo']);
            // Si quedó INACTIVO (retiro) o se reactivó, se propaga a toda la cuenta
            // de NAVISSI y al perfil de SST, sin tener que tocar cada módulo a mano.
            sincronizar_retiro_empleado($pdo, $datos['documento'], $datos['estado'], usuario_actual()['nombre'] ?? null);
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM empleados WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Empleado eliminado.'];
    } elseif ($accion === 'guardar_campos') {
        $idCampos = (int) ($_POST['id'] ?? 0);
        foreach ($_POST['campo'] ?? [] as $campoId => $valor) {
            $pdo->prepare("INSERT INTO campos_personalizados_valor (campo_id, entidad_id, valor) VALUES (?,?,?)
                ON CONFLICT(campo_id, entidad_id) DO UPDATE SET valor = excluded.valor")
                ->execute([(int) $campoId, $idCampos, limpio($valor)]);
        }
        $msg = ['ok', 'Campos personalizados guardados.'];
    } elseif ($accion === 'asignar_rol') {
        // Asignación masiva de rol desde el listado: elige el rol del sistema para
        // el usuario ya vinculado a este empleado por documento.
        $stmtEmp = $pdo->prepare("SELECT documento FROM empleados WHERE id = ?");
        $stmtEmp->execute([(int) $_POST['id']]);
        $documentoObjetivo = $stmtEmp->fetchColumn();
        $rolNuevo = trim($_POST['rol'] ?? '');
        if ($documentoObjetivo && in_array($rolNuevo, ROLES_DISPONIBLES, true)) {
            $stmtU = $pdo->prepare("SELECT id FROM usuarios_sistema WHERE documento = ?");
            $stmtU->execute([$documentoObjetivo]);
            $usuarioObjetivo = $stmtU->fetchColumn();
            if ($usuarioObjetivo) {
                $pdo->prepare("UPDATE usuarios_sistema SET rol = ? WHERE id = ?")->execute([$rolNuevo, $usuarioObjetivo]);
                $msg = ['ok', "Rol actualizado a {$rolNuevo}."];
            } else {
                $msg = ['error', 'Este empleado todavía no tiene cuenta de NAVISSI - créala primero desde su ficha.'];
            }
        }
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT e.*, s.nombre AS sede_nombre FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE 1=1";
$params = [];
if ($busqueda !== '') {
    $sql .= " AND (e.nombres LIKE :b OR e.documento LIKE :b OR e.cargo LIKE :b OR e.area LIKE :b)";
    $params['b'] = "%{$busqueda}%";
}
if (alcance_area() !== null) {
    $sql .= " AND e.area = :area";
    $params['area'] = alcance_area();
}
// RRHH ve a todo el mundo excepto a Gerencia/CEO - esas fichas quedan reservadas
// para SUPER_ADMIN o para ellos mismos.
if (rol_efectivo() === 'RRHH') {
    $sql .= " AND e.documento NOT IN (SELECT documento FROM usuarios_sistema WHERE rol IN ('GERENCIA','CEO') AND documento IS NOT NULL)";
}
// Alcance personal: un EMPLEADO sin rol elevado solo se ve a sí mismo en el listado.
$personalRr = alcance_personal();
if ($personalRr !== null) {
    $sql .= " AND e.documento = :doc_personal";
    $params['doc_personal'] = $personalRr['documento'];
}
$sql .= " ORDER BY e.nombres";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$departamentosCat = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$cargosCat = $pdo->query("SELECT nombre FROM cargos ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
// Para la asignación de rol en bloque: qué documentos ya tienen cuenta de NAVISSI y con qué rol.
$cuentasPorDocumento = [];
foreach ($pdo->query("SELECT documento, rol FROM usuarios_sistema WHERE documento IS NOT NULL AND documento != ''") as $fila) {
    $cuentasPorDocumento[$fila['documento']] = $fila['rol'];
}

// Campos personalizados (definidos sin tocar código desde Seguridad -> Campos Personalizados).
$camposDefEmp = $pdo->query("SELECT * FROM campos_personalizados_def WHERE entidad = 'empleados' ORDER BY nombre_campo")->fetchAll(PDO::FETCH_ASSOC);
$camposValoresEmp = [];
if ($editar && $camposDefEmp) {
    $stmt = $pdo->prepare("SELECT campo_id, valor FROM campos_personalizados_valor WHERE entidad_id = ?");
    $stmt->execute([$editar['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) $camposValoresEmp[$v['campo_id']] = $v['valor'];
}

layout_inicio('RRHH', 'RRHH', '../');
?>
<h1><?= icon('users','icon-lg') ?> Recursos Humanos</h1>
<p class="subtitle"><?= count($empleados) ?> empleados.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar empleado' : 'Agregar empleado' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Documento</label><input type="text" name="documento" value="<?= e($editar['documento'] ?? '') ?>"></div>
            <div><label>Nombres *</label><input type="text" name="nombres" required value="<?= e($editar['nombres'] ?? '') ?>"></div>
            <div><label>Cargo</label><input type="text" name="cargo" list="lista-cargos" value="<?= e($editar['cargo'] ?? '') ?>">
                <datalist id="lista-cargos"><?php foreach ($cargosCat as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
            </div>
            <div><label>Área / Departamento</label><input type="text" name="area" list="lista-departamentos" value="<?= e($editar['area'] ?? '') ?>">
                <datalist id="lista-departamentos"><?php foreach ($departamentosCat as $d): ?><option value="<?= e($d) ?>"><?php endforeach; ?></datalist>
            </div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- seleccionar --</option>
                    <?php foreach ($sedes as $s): ?>
                    <option <?= (($editar['sede_id'] ?? null) == $s['id']) ? 'selected' : '' ?> value="<?= e($s['nombre']) ?>"><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Email</label><input type="text" name="email" value="<?= e($editar['email'] ?? '') ?>"></div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['ACTIVO','INACTIVO'] as $es): ?>
                    <option <?= ($editar['estado'] ?? 'ACTIVO') === $es ? 'selected' : '' ?>><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Fecha de ingreso</label><input type="date" name="fecha_ingreso" value="<?= e($editar['fecha_ingreso'] ?? '') ?>"></div>
            <div><label>Tipo de contrato</label>
                <select name="tipo_contrato">
                    <option value="">-- sin definir --</option>
                    <?php foreach (['TERMINO INDEFINIDO','TERMINO FIJO','OBRA O LABOR','PRESTACIÓN DE SERVICIOS','APRENDIZAJE'] as $tc): ?>
                    <option <?= ($editar['tipo_contrato'] ?? '') === $tc ? 'selected' : '' ?>><?= $tc ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Salario</label><input type="number" step="0.01" name="salario" value="<?= e($editar['salario'] ?? '') ?>"></div>
        </div>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar empleado' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="rrhh.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<?php if ($editar && $camposDefEmp): ?>
<div class="panel">
    <h3><?= icon('inventory') ?> Campos personalizados <a href="campos_personalizados.php" class="small" style="float:right;font-weight:400;">Agregar/quitar campos →</a></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar_campos">
        <input type="hidden" name="id" value="<?= (int) $editar['id'] ?>">
        <?php foreach ($camposDefEmp as $cd): $valorActual = $camposValoresEmp[$cd['id']] ?? ''; ?>
        <label class="small"><?= e($cd['nombre_campo']) ?></label>
        <?php if ($cd['tipo'] === 'LISTA' && $cd['opciones']): ?>
        <select name="campo[<?= (int)$cd['id'] ?>]" style="width:100%;margin-bottom:10px;">
            <option value="">-- sin definir --</option>
            <?php foreach (array_map('trim', explode(',', $cd['opciones'])) as $op): ?>
            <option <?= $valorActual===$op?'selected':'' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="<?= $cd['tipo']==='FECHA'?'date':($cd['tipo']==='NUMERO'?'number':'text') ?>" name="campo[<?= (int)$cd['id'] ?>]" value="<?= e($valorActual) ?>" style="width:100%;margin-bottom:10px;">
        <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit"><?= icon('check') ?> Guardar campos</button>
    </form>
</div>
<?php endif; ?>

<form class="toolbar" method="get">
    <input type="search" name="q" placeholder="Buscar por nombre, documento, cargo, área..." value="<?= e($busqueda) ?>" style="min-width:320px">
    <button type="submit">Buscar</button>
    <?php if ($busqueda): ?><a class="btn btn-secondary" href="rrhh.php">Limpiar</a><?php endif; ?>
</form>

<table>
    <tr><th>Documento</th><th>Nombres</th><th>Cargo</th><th>Área</th><th>Sede</th><th>Email</th><th>Estado</th><th>Rol en NAVISSI</th><th></th></tr>
    <?php foreach ($empleados as $emp): $rolActual = $cuentasPorDocumento[$emp['documento']] ?? null; ?>
    <tr>
        <td><?= e($emp['documento']) ?></td>
        <td><a href="empleado_detalle.php?id=<?= (int)$emp['id'] ?>"><strong><?= e($emp['nombres']) ?></strong></a></td>
        <td><?= e($emp['cargo']) ?></td>
        <td><?= e($emp['area']) ?></td>
        <td><?= e($emp['sede_nombre']) ?></td>
        <td><?= e($emp['email']) ?></td>
        <td><span class="badge <?= $emp['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($emp['estado']) ?></span></td>
        <td>
            <?php if ($rolActual): ?>
            <form method="post" class="inline">
                <input type="hidden" name="accion" value="asignar_rol"><input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                <select name="rol" onchange="this.form.requestSubmit()" style="font-size:12px;">
                    <?php foreach (ROLES_DISPONIBLES as $r): if ($r === 'SUPER_ADMIN' && !usuario_ve_todo()) continue; ?>
                    <option <?= $rolActual === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php else: ?>
                <span class="small">Sin cuenta — <a href="empleado_detalle.php?id=<?= (int)$emp['id'] ?>">crear acceso</a></span>
            <?php endif; ?>
        </td>
        <td>
            <a href="?editar=<?= (int)$emp['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php layout_fin(); ?>
