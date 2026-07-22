<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT i.*, s.nombre AS sede_nombre FROM inventario i LEFT JOIN sedes s ON i.sede_id = s.id WHERE i.id = ?");
$stmt->execute([$id]);
$eq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eq) {
    layout_inicio('Equipo no encontrado', 'Inventario', '../');
    echo '<div class="msg-error">Ese equipo no existe (puede haber sido eliminado del inventario).</div><a class="btn" href="inventario.php">Volver</a>';
    layout_fin();
    exit;
}

// Alcance personal: si al usuario le habilitaron Inventario individualmente pero
// no tiene rol elevado, solo puede ver la ficha de SU propio equipo por URL directa.
$personalEq = alcance_personal();
if ($personalEq !== null && $eq['asignado_documento'] !== $personalEq['documento']) {
    layout_inicio('Sin acceso', 'Inventario', '../');
    echo '<div class="msg-error">Solo puedes ver la ficha de los equipos asignados a ti.</div><a class="btn" href="inventario.php">Volver</a>';
    layout_fin();
    exit;
}

$puedeOrdenar = tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI']);
$msgOrden = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeOrdenar && ($_POST['accion'] ?? '') === 'orden_actualizaciones') {
    $pdo->prepare("INSERT INTO agente_ordenes (serial_objetivo, tipo, parametros_json, solicitado_por) VALUES (?,?,?,?)")
        ->execute([$eq['serial'], 'WINDOWS_UPDATE', '{}', usuario_actual()['nombre'] ?? 'Sistema']);
    $msgOrden = ['ok', 'Orden de actualizaciones de Windows enviada. Se ejecutará en el próximo reporte del equipo (máximo 5 minutos).'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeOrdenar && ($_POST['accion'] ?? '') === 'orden_desinstalar') {
    $nombreSoft = limpio($_POST['nombre_software'] ?? null);
    if ($nombreSoft) {
        $pdo->prepare("INSERT INTO agente_ordenes (serial_objetivo, tipo, parametros_json, solicitado_por) VALUES (?,?,?,?)")
            ->execute([$eq['serial'], 'UNINSTALL_SOFTWARE', json_encode(['nombre' => $nombreSoft]), usuario_actual()['nombre'] ?? 'Sistema']);
        $msgOrden = ['ok', 'Orden de desinstalación de "' . $nombreSoft . '" enviada. Se ejecutará en el próximo reporte del equipo.'];
    }
}

// Campos personalizados definidos para "inventario" — se guardan igual para
// cualquier equipo, sin tocar código cada vez que se agrega uno nuevo.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_campos') {
    foreach ($_POST['campo'] ?? [] as $campoId => $valor) {
        $pdo->prepare("INSERT INTO campos_personalizados_valor (campo_id, entidad_id, valor) VALUES (?,?,?)
            ON CONFLICT(campo_id, entidad_id) DO UPDATE SET valor = excluded.valor")
            ->execute([(int) $campoId, $id, limpio($valor)]);
    }
    header("Location: equipo_detalle.php?id={$id}&campos_guardados=1");
    exit;
}
$camposDef = $pdo->query("SELECT * FROM campos_personalizados_def WHERE entidad = 'inventario' ORDER BY nombre_campo")->fetchAll(PDO::FETCH_ASSOC);
$camposValores = [];
if ($camposDef) {
    $stmt = $pdo->prepare("SELECT campo_id, valor FROM campos_personalizados_valor WHERE entidad_id = ?");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) $camposValores[$v['campo_id']] = $v['valor'];
}

// Hoja de vida en vivo — siempre al dia, se lee directo de la base de datos en cada escaneo del QR.
$stmt = $pdo->prepare("SELECT * FROM hoja_vida WHERE entidad_tipo = 'EQUIPO' AND entidad_id = ? ORDER BY id DESC LIMIT 30");
$stmt->execute([$eq['serial']]);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Movimientos formales (prestamo, asignacion, devolucion, etc.) de este equipo
$stmt = $pdo->prepare("SELECT * FROM movimientos_equipos WHERE inventario_id = ? ORDER BY creado_en DESC LIMIT 15");
$stmt->execute([$eq['id']]);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tickets de mesa de ayuda relacionados (por coincidencia de serial en la descripcion,
// igual que hace api_reportar_problema.php al adjuntar la ficha tecnica al crear el ticket)
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE descripcion LIKE ? OR titulo LIKE ? ORDER BY creado_en DESC LIMIT 15");
$stmt->execute(["%{$eq['serial']}%", "%{$eq['serial']}%"]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ticketsAbiertos = count(array_filter($tickets, fn($t) => !in_array($t['estado'], ['CERRADO', 'RESUELTO POR IA'], true)));

// Parches de Windows y software instalado, reportados por el agente.
$stmt = $pdo->prepare("SELECT * FROM parches_equipo WHERE inventario_id = ? ORDER BY fecha_instalado DESC, kb DESC");
$stmt->execute([$eq['id']]);
$parches = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT * FROM equipos_software WHERE inventario_id = ? ORDER BY nombre COLLATE NOCASE");
$stmt->execute([$eq['id']]);
$software = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT * FROM agente_ordenes WHERE lower(serial_objetivo) = lower(?) ORDER BY id DESC LIMIT 10");
$stmt->execute([$eq['serial']]);
$ordenesAgente = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_inicio($eq['serial'] ?: 'Equipo', 'Inventario', '../');
?>
<p class="small"><a href="inventario.php">← Volver al Inventario</a></p>
<h1><?= icon('inventory','icon-lg') ?> <?= e($eq['marca']) ?> <?= e($eq['modelo']) ?> <span class="badge <?= $eq['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($eq['estado']) ?></span></h1>
<p class="subtitle">Serial <?= e($eq['serial']) ?: '—' ?> · Placa <?= e($eq['placa']) ?: '—' ?> · Esta ficha se actualiza en tiempo real — el mismo código QR pegado en el equipo siempre trae la información más reciente.</p>
<?php if ($msgOrden): ?><div class="msg-<?= e($msgOrden[0]) ?>"><?= e($msgOrden[1]) ?></div><?php endif; ?>

<div class="toolbar" style="margin-bottom:14px;">
    <a class="btn" href="mesa_ayuda.php?titulo=<?= urlencode('Problema con equipo ' . ($eq['serial'] ?: $eq['hostname'])) ?>&descripcion=<?= urlencode('Equipo: ' . ($eq['hostname'] ?: $eq['serial']) . ' · Serial: ' . $eq['serial'] . ' · Asignado a: ' . ($eq['asignado_a'] ?: 'Sin asignar')) ?>#nuevo-ticket"><?= icon('ticket') ?> Crear ticket para este equipo</a>
    <a class="btn btn-secondary" href="movimientos.php?equipo_id=<?= (int) $eq['id'] ?>"><?= icon('arrow-right') ?> Registrar movimiento</a>
    <?php if ($eq['rustdesk_id']): ?><a class="btn btn-secondary" href="rustdesk://<?= e($eq['rustdesk_id']) ?>?password=<?= e($eq['rustdesk_password']) ?>"><?= icon('zap') ?> Conectar remoto</a><?php endif; ?>
</div>

<div class="cards">
    <div class="card"><div class="num"><?= count($movimientos) ?></div><div class="label">Movimientos registrados</div></div>
    <div class="card"><div class="num"><?= count($eventos) ?></div><div class="label">Eventos en hoja de vida</div></div>
    <div class="card" style="border-left-color:<?= $ticketsAbiertos ? '#b3392c' : '#1f4e78' ?>"><div class="num"><?= $ticketsAbiertos ?></div><div class="label">Tickets abiertos de este equipo</div></div>
</div>

<div class="panel">
    <h3>Ficha técnica
        <a href="inventario.php?editar=<?= (int)$eq['id'] ?>" class="btn btn-secondary" style="float:right;font-size:12px;padding:5px 12px;">Editar</a>
    </h3>
    <table class="deftable">
        <tr><th>Nombre del equipo (hostname)</th><td><?= e($eq['hostname']) ?: '—' ?></td><th>Asignado a</th><td><?= e($eq['asignado_a']) ?: 'Sin asignar' ?></td></tr>
        <tr><th>Sede</th><td><?= e($eq['sede_nombre']) ?: '—' ?></td><th>Área / Cargo</th><td><?= e($eq['area']) ?> / <?= e($eq['cargo']) ?></td></tr>
        <tr><th>Tipo</th><td><?= e($eq['tipo']) ?></td><th>Procesador</th><td><?= e($eq['procesador']) ?: '—' ?></td></tr>
        <tr><th>Memoria / Almacenamiento</th><td><?= e($eq['memoria']) ?: '—' ?> / <?= e($eq['almacenamiento']) ?: '—' ?></td><th>Sistema operativo</th><td><?= e($eq['sistema_operativo']) ?: '—' ?></td></tr>
        <tr><th>IP local</th><td><?= e($eq['ip_local']) ?: '—' ?></td><th>Último reporte del agente</th><td><?= e($eq['ultima_conexion_agente']) ?: '—' ?></td></tr>
        <tr><th>Acceso remoto</th>
            <td><?php if ($eq['rustdesk_id']): ?><a href="rustdesk://<?= e($eq['rustdesk_id']) ?>?password=<?= e($eq['rustdesk_password']) ?>"><?= icon('zap') ?> Conectar</a><?php else: ?>—<?php endif; ?></td>
            <th></th><td></td></tr>
    </table>
</div>

<?php if ($puedeOrdenar): ?>
<div class="panel">
    <h3><?= icon('zap') ?> Acciones remotas</h3>
    <p class="small">Se ejecutan en el próximo reporte del equipo (cada 5 minutos como máximo).</p>
    <form method="post" class="inline">
        <input type="hidden" name="accion" value="orden_actualizaciones">
        <button type="submit"><?= icon('upload') ?> Buscar e instalar actualizaciones de Windows</button>
    </form>
    <a class="btn btn-secondary" href="ordenes_agente.php?serial=<?= urlencode($eq['serial']) ?>"><?= icon('zap') ?> Instalar/actualizar programa o activar licencia</a>
</div>
<?php endif; ?>

<div class="panel">
    <h3><?= icon('shield') ?> Parches de Windows instalados (<?= count($parches) ?>)</h3>
    <table>
        <tr><th>KB</th><th>Descripción</th><th>Tipo</th><th>Fecha instalado</th></tr>
        <?php foreach ($parches as $p): ?>
        <tr><td><?= e($p['kb']) ?></td><td><?= e($p['descripcion']) ?></td><td><?= e($p['tipo']) ?></td><td><?= e($p['fecha_instalado']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$parches): ?><tr><td colspan="4" class="small">Sin parches reportados todavía.</td></tr><?php endif; ?>
    </table>
</div>

<div class="panel">
    <h3><?= icon('inventory') ?> Software instalado (<?= count($software) ?>)</h3>
    <table>
        <tr><th>Programa</th><th>Versión</th><th>Editor</th><th>Reportado</th><?php if ($puedeOrdenar): ?><th></th><?php endif; ?></tr>
        <?php foreach ($software as $s): ?>
        <tr>
            <td><?= e($s['nombre']) ?></td>
            <td><?= e($s['version']) ?></td>
            <td><?= e($s['editor']) ?></td>
            <td class="small"><?= e($s['reportado_en']) ?></td>
            <?php if ($puedeOrdenar): ?>
            <td>
                <form method="post" class="inline" onsubmit="return confirm('¿Pedir al agente que desinstale &quot;<?= e(addslashes($s['nombre'])) ?>&quot; en este equipo?');">
                    <input type="hidden" name="accion" value="orden_desinstalar">
                    <input type="hidden" name="nombre_software" value="<?= e($s['nombre']) ?>">
                    <button type="submit" class="btn-danger" style="padding:3px 8px;font-size:11px;">Desinstalar</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$software): ?><tr><td colspan="<?= $puedeOrdenar ? 5 : 4 ?>" class="small">Sin software reportado todavía.</td></tr><?php endif; ?>
    </table>
</div>

<?php if ($puedeOrdenar && $ordenesAgente): ?>
<div class="panel">
    <h3><?= icon('log') ?> Órdenes enviadas a este equipo</h3>
    <table>
        <tr><th>Tipo</th><th>Estado</th><th>Resultado</th><th>Fecha</th></tr>
        <?php foreach ($ordenesAgente as $o): ?>
        <tr>
            <td><?= e($o['tipo']) ?></td>
            <td><span class="badge <?= $o['estado']==='COMPLETADA'?'badge-activo':($o['estado']==='FALLIDA'?'badge-err':'badge-otro') ?>"><?= e($o['estado']) ?></span></td>
            <td class="small"><?= e($o['resultado'] ?: $o['error']) ?></td>
            <td class="small"><?= e($o['creado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($camposDef): ?>
<div class="panel">
    <h3><?= icon('inventory') ?> Campos personalizados</h3>
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
    <h3><?= icon('arrow-right') ?> Movimientos (préstamo, asignación, devolución, renting, baja, bodega, repotenciamiento)</h3>
    <?php if (!$movimientos): ?><p class="small">Sin movimientos registrados para este equipo.</p><?php else: ?>
    <table>
        <tr><th>Tipo</th><th>Empleado</th><th>Fecha</th><th></th></tr>
        <?php foreach ($movimientos as $m): ?>
        <tr>
            <td><span class="badge badge-otro"><?= e($m['tipo']) ?></span></td>
            <td><?= e($m['destinatario'] ?: $m['destinatario_documento']) ?: '—' ?></td>
            <td class="small"><?= e($m['creado_en']) ?></td>
            <td><a href="movimiento_detalle.php?id=<?= (int)$m['id'] ?>">Ver formato</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('ticket') ?> Tickets de mesa de ayuda relacionados</h3>
    <?php if (!$tickets): ?><p class="small">Sin tickets reportados para este equipo.</p><?php else: ?>
    <table>
        <tr><th>#</th><th>Título</th><th>Estado</th><th>Fecha</th><th></th></tr>
        <?php foreach ($tickets as $t): ?>
        <tr>
            <td>#<?= (int)$t['id'] ?></td><td><?= e($t['titulo']) ?></td>
            <td><span class="badge <?= in_array($t['estado'], ['CERRADO','RESUELTO POR IA'], true) ?'badge-otro':'badge-activo' ?>"><?= e($t['estado']) ?></span></td>
            <td class="small"><?= e($t['creado_en']) ?></td>
            <td><a href="ticket_detalle.php?id=<?= (int)$t['id'] ?>">Ver</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('log') ?> Hoja de vida (trazabilidad completa)
        <a href="hoja_vida.php?tipo=EQUIPO&id=<?= urlencode($eq['serial']) ?>" class="btn btn-secondary" style="float:right;font-size:12px;padding:5px 12px;">Ver todo el historial</a>
    </h3>
    <?php if (!$eventos): ?><p class="small">Sin eventos registrados todavía.</p><?php else: ?>
    <table>
        <tr><th>Fecha</th><th>Evento</th><th>Detalle</th><th>Autor</th></tr>
        <?php foreach ($eventos as $ev): ?>
        <tr>
            <td class="small"><?= e($ev['creado_en']) ?></td>
            <td><span class="badge badge-otro"><?= e($ev['evento']) ?></span></td>
            <td><?= e($ev['detalle']) ?></td>
            <td><?= e($ev['autor']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php layout_fin(); ?>
