<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$codigoBuscado = trim((string) ($_GET['codigo'] ?? ''));

if ($id) {
    $stmt = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE e.id = ?");
    $stmt->execute([$id]);
} else {
    // Búsqueda por código único (expediente unificado) - la misma ficha, otra puerta de entrada.
    $stmt = $pdo->prepare("SELECT e.*, s.nombre AS sede_nombre FROM empleados e LEFT JOIN sedes s ON e.sede_id = s.id WHERE e.codigo_empleado = ?");
    $stmt->execute([$codigoBuscado]);
}
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if ($emp) $id = (int) $emp['id'];

if (!$emp) {
    layout_inicio('Empleado no encontrado', 'RRHH', '../');
    echo '<div class="msg-error">Ese empleado no existe.</div><a class="btn" href="rrhh.php">Volver</a>';
    layout_fin();
    exit;
}

// Alcance personal: un EMPLEADO sin rol elevado solo puede ver su propia ficha de RRHH.
$personalEmp = alcance_personal();
if ($personalEmp !== null && $emp['documento'] !== $personalEmp['documento']) {
    layout_inicio('Sin acceso', 'RRHH', '../');
    echo '<div class="msg-error">Solo puedes ver tu propia ficha.</div><a class="btn" href="rrhh.php">Volver</a>';
    layout_fin();
    exit;
}

$msgAcceso = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_acceso') {
    require_once __DIR__ . '/../lib/mailer.php';
    $correoAcceso = trim($_POST['correo_acceso'] ?? $emp['email'] ?? '');
    if (!$correoAcceso) {
        $msgAcceso = ['error', 'Necesitas un correo para crear el acceso.'];
    } else {
        try {
            $palabras = ['Navissi', 'Grupo10z', 'Acceso', 'Bienvenido'];
            $claveTemporal = $palabras[array_rand($palabras)] . random_int(100, 999) . '!';
            // Todo empleado nuevo entra por defecto como EMPLEADO, sin perfil adicional -
            // el admin le agrega un rol elevado después solo si de verdad lo necesita.
            $pdo->prepare("INSERT INTO usuarios_sistema (nombre, email, documento, password_hash, rol, password_temporal) VALUES (?,?,?,?,?,1)")
                ->execute([$emp['nombres'], $correoAcceso, $emp['documento'], password_hash($claveTemporal, PASSWORD_DEFAULT), 'EMPLEADO']);
            if (!$emp['email']) {
                $pdo->prepare("UPDATE empleados SET email = ? WHERE id = ?")->execute([$correoAcceso, $emp['id']]);
                $emp['email'] = $correoAcceso;
            }
            // El Portal de Autogestión se habilita justo cuando se le crea el acceso -
            // antes de tener cuenta no tiene sentido que aparezca habilitado.
            $pdo->prepare("UPDATE empleados SET portal_habilitado = 1 WHERE id = ?")->execute([$emp['id']]);
            $emp['portal_habilitado'] = 1;
            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $html = plantilla_correo_html(
                'Tu cuenta de NAVISSI está lista',
                "<p>Hola " . e($emp['nombres']) . ",</p>
                <p>Ya puedes entrar a <strong>NAVISSI Inventario</strong> con estas credenciales temporales:</p>
                <table style=\"width:100%;background:#f4f6f9;border-radius:8px;padding:4px;margin:14px 0;\">
                    <tr><td style=\"padding:8px 12px;\"><strong>Correo:</strong></td><td style=\"padding:8px 12px;\">" . e($correoAcceso) . "</td></tr>
                    <tr><td style=\"padding:8px 12px;\"><strong>Contraseña temporal:</strong></td><td style=\"padding:8px 12px;font-family:monospace;font-size:15px;\">" . e($claveTemporal) . "</td></tr>
                </table>
                <p>Al ingresar por primera vez, el sistema te va a pedir crear tu propia contraseña.</p>",
                'Ingresar a NAVISSI',
                "{$base}/login.php"
            );
            enviar_correo($correoAcceso, 'Tu cuenta de NAVISSI está lista', $html, $emp['nombres']);
            $msgAcceso = ['ok', "Acceso creado y credenciales enviadas a {$correoAcceso}."];
        } catch (PDOException $e) {
            $msgAcceso = ['error', 'Ya existe una cuenta con ese correo.'];
        }
    }
}

$stmtAcc = $pdo->prepare("SELECT id, email, rol FROM usuarios_sistema WHERE documento = ?");
$stmtAcc->execute([$emp['documento']]);
$cuentaVinculada = $stmtAcc->fetch(PDO::FETCH_ASSOC);

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

// Proceso de selección del que viene (si fue contratado desde Vacantes)
$procesoSeleccion = null;
$citasProceso = [];
$documentosProceso = [];
if ($emp['candidato_id']) {
    $stmt = $pdo->prepare("SELECT c.*, v.titulo AS vacante_titulo FROM candidatos c JOIN vacantes v ON v.id = c.vacante_id WHERE c.id = ?");
    $stmt->execute([$emp['candidato_id']]);
    $procesoSeleccion = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($procesoSeleccion) {
        $stmt = $pdo->prepare("SELECT * FROM candidatos_citas WHERE candidato_id = ? ORDER BY fecha_hora DESC");
        $stmt->execute([$emp['candidato_id']]);
        $citasProceso = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT * FROM candidatos_documentos WHERE candidato_id = ? ORDER BY creado_en DESC");
        $stmt->execute([$emp['candidato_id']]);
        $documentosProceso = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Documentos de ingreso/contratación (contrato, afiliaciones) con firma y OneDrive
$stmt = $pdo->prepare("SELECT * FROM documentos_rrhh WHERE empleado_documento = ? ORDER BY creado_en DESC");
$stmt->execute([$emp['documento']]);
$documentosIngreso = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dotación / elementos asignados (actas de equipos: entregas, devoluciones, paz y salvo)
$stmt = $pdo->prepare("SELECT * FROM actas_equipos WHERE empleado_documento = ? ORDER BY creado_en DESC");
$stmt->execute([$emp['documento']]);
$actasEquipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Movimientos de equipos (préstamo, asignación, devolución, baja, etc.)
$stmt = $pdo->prepare("SELECT m.*, i.serial, i.marca, i.modelo FROM movimientos_equipos m LEFT JOIN inventario i ON i.id = m.inventario_id WHERE m.destinatario_documento = ? OR m.destinatario = ? ORDER BY m.creado_en DESC LIMIT 10");
$stmt->execute([$emp['documento'], $emp['nombres']]);
$movimientosEquipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio($emp['nombres'], 'RRHH', '../');
?>
<p class="small"><a href="rrhh.php">← Volver a RRHH</a></p>
<h1><?= icon('users','icon-lg') ?> <?= e($emp['nombres']) ?> <span class="badge <?= $emp['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($emp['estado']) ?></span></h1>
<p class="subtitle">Código <strong><?= e($emp['codigo_empleado']) ?: 'Sin asignar' ?></strong> · <?= e($emp['cargo']) ?> · <?= e($emp['area']) ?> · <?= e($emp['sede_nombre']) ?> ·
    <span class="badge <?= $emp['portal_habilitado'] ? 'badge-activo' : 'badge-otro' ?>"><?= $emp['portal_habilitado'] ? 'Portal Autogestión habilitado' : 'Portal Autogestión no habilitado' ?></span>
</p>

<div class="cards">
    <div class="card"><div class="num"><?= count($equipos) ?></div><div class="label">Equipos asignados</div></div>
    <div class="card"><div class="num"><?= count($credenciales) ?></div><div class="label">Credenciales</div></div>
    <div class="card"><div class="num"><?= count($tickets) ?></div><div class="label">Tickets reportados</div></div>
    <div class="card"><div class="num"><?= count($progreso) ?></div><div class="label">Lecciones completadas</div></div>
</div>

<?php if ($personalEmp === null): ?>
<div class="panel" style="border-left:4px solid var(--teal-500);">
    <h3><?= icon('shield') ?> Acceso a NAVISSI</h3>
    <?php if ($msgAcceso): ?><div class="msg-<?= $msgAcceso[0] ?>"><?= e($msgAcceso[1]) ?></div><?php endif; ?>
    <?php if ($cuentaVinculada): ?>
        <p><span class="badge badge-activo"><?= icon('check') ?> Tiene acceso</span> · <?= e($cuentaVinculada['email']) ?> · Rol <?= e($cuentaVinculada['rol']) ?></p>
        <p class="small"><a href="usuario_modulos.php?id=<?= (int)$cuentaVinculada['id'] ?>">Ver/editar sus módulos individuales</a> · <a href="usuarios.php">Ir a Usuarios y roles</a></p>
    <?php else: ?>
        <p class="small">Este empleado todavía no tiene cuenta para entrar a NAVISSI. Crea su acceso con un clic: queda con rol <strong>EMPLEADO</strong> por defecto (el más limitado y seguro), con una contraseña temporal que le llega por correo y que debe cambiar al ingresar por primera vez.</p>
        <form method="post" class="toolbar" style="margin-bottom:0;">
            <input type="hidden" name="accion" value="crear_acceso">
            <input type="email" name="correo_acceso" required placeholder="Correo para el acceso" value="<?= e($emp['email'] ?? '') ?>" style="min-width:260px;">
            <button type="submit"><?= icon('plus') ?> Crear acceso y enviar credenciales</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

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

<?php if ($procesoSeleccion): ?>
<div class="panel">
    <h3><?= icon('briefcase') ?> Proceso de selección del que viene</h3>
    <p class="small">Vacante: <strong><?= e($procesoSeleccion['vacante_titulo']) ?></strong> · <a href="candidato_detalle.php?id=<?= (int) $procesoSeleccion['id'] ?>">Ver proceso completo →</a></p>
    <?php if ($citasProceso): ?>
    <table>
        <tr><th>Etapa</th><th>Fecha</th><th>Modalidad</th><th>Estado</th></tr>
        <?php foreach ($citasProceso as $c): ?>
        <tr><td><?= e($c['etapa']) ?></td><td class="small"><?= e($c['fecha_hora']) ?></td><td><?= e($c['modalidad']) ?></td><td><span class="badge badge-otro"><?= e($c['estado']) ?></span></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php if ($documentosProceso): ?>
    <p class="small" style="margin-top:8px;"><strong>Documentos del proceso:</strong>
        <?php foreach ($documentosProceso as $d): ?>
        <a href="descargar_documento_candidato.php?id=<?= (int) $d['id'] ?>" target="_blank"><?= e($d['nombre_archivo']) ?></a><?= $d !== end($documentosProceso) ? ', ' : '' ?>
        <?php endforeach; ?>
    </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h3><?= icon('file') ?> Documentos de ingreso y contratación (<?= count($documentosIngreso) ?>)</h3>
    <p class="small">Contrato, afiliaciones y demás documentos de vinculación — firma electrónica y respaldo en OneDrive/SharePoint. <a href="rrhh_documentos.php?empleado=<?= urlencode($emp['documento']) ?>">Gestionar documentos de ingreso →</a></p>
    <?php if ($documentosIngreso): ?>
    <table>
        <tr><th>Tipo</th><th>Archivo</th><th>Firma</th><th>OneDrive</th><th>Fecha</th></tr>
        <?php foreach ($documentosIngreso as $d): ?>
        <tr>
            <td><?= e($d['tipo']) ?></td>
            <td><?= e($d['nombre_archivo']) ?></td>
            <td><span class="badge <?= $d['estado_firma']==='FIRMADO'?'badge-activo':'badge-otro' ?>"><?= e($d['estado_firma']) ?></span><?= $d['firmado_por'] ? ' · ' . e($d['firmado_por']) : '' ?></td>
            <td><?php if ($d['onedrive_url']): ?><a href="<?= e($d['onedrive_url']) ?>" target="_blank">Ver en OneDrive</a><?php else: ?>—<?php endif; ?></td>
            <td class="small"><?= e($d['creado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?><p class="small">Sin documentos de ingreso cargados todavía.</p><?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('inventory') ?> Dotación y elementos asignados (<?= count($actasEquipos) ?>)</h3>
    <p class="small">Actas de entrega/devolución de equipos y dotación de TI, firmadas digitalmente. Al retirarse, la devolución debe quedar con paz y salvo o con la autorización de descuento correspondiente. <a href="actas_equipos.php">Gestionar actas →</a></p>
    <?php if ($actasEquipos): ?>
    <table>
        <tr><th>Tipo</th><th>Elemento</th><th>Firmas</th><th>Paz y salvo</th><th>Fecha</th><th></th></tr>
        <?php foreach ($actasEquipos as $a): ?>
        <tr>
            <td><?= e($a['tipo']) ?></td>
            <td><?= e($a['equipo_descripcion']) ?: '—' ?> <?= $a['equipo_serial'] ? '· ' . e($a['equipo_serial']) : '' ?></td>
            <td class="small"><?= $a['firma_entrega'] ? '✓ Entrega' : '— Entrega' ?> · <?= $a['firma_empleado'] ? '✓ Empleado' : '— Empleado' ?></td>
            <td>
                <?php if ($a['tipo'] === 'DEVOLUCION' || $a['tipo'] === 'BAJA'): ?>
                    <?php if ($a['paz_y_salvo']): ?><span class="badge badge-activo">Paz y salvo</span>
                    <?php elseif ($a['autoriza_descuento']): ?><span class="badge badge-err">Descuento autorizado<?= $a['monto_descuento'] ? ': $' . number_format((float) $a['monto_descuento'], 0, ',', '.') : '' ?></span>
                    <?php else: ?><span class="badge badge-otro">Pendiente definir</span><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="small"><?= e($a['creado_en']) ?></td>
            <td><a href="acta_equipo_firmar.php?id=<?= (int) $a['id'] ?>">Ver</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?><p class="small">Sin actas registradas.</p><?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('arrow-right') ?> Movimientos de equipos (<?= count($movimientosEquipos) ?>)</h3>
    <?php if ($movimientosEquipos): ?>
    <table>
        <tr><th>Tipo</th><th>Equipo</th><th>Fecha</th></tr>
        <?php foreach ($movimientosEquipos as $m): ?>
        <tr><td><span class="badge badge-otro"><?= e($m['tipo']) ?></span></td><td><?= e($m['marca']) ?> <?= e($m['modelo']) ?> <?= $m['serial'] ? '· ' . e($m['serial']) : '' ?></td><td class="small"><?= e($m['creado_en']) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?><p class="small">Sin movimientos registrados.</p><?php endif; ?>
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
