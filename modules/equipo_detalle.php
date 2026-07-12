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

layout_inicio($eq['serial'] ?: 'Equipo', 'Inventario', '../');
?>
<p class="small"><a href="inventario.php">← Volver al Inventario</a></p>
<h1><?= icon('inventory','icon-lg') ?> <?= e($eq['marca']) ?> <?= e($eq['modelo']) ?> <span class="badge <?= $eq['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($eq['estado']) ?></span></h1>
<p class="subtitle">Serial <?= e($eq['serial']) ?: '—' ?> · Placa <?= e($eq['placa']) ?: '—' ?> · Esta ficha se actualiza en tiempo real — el mismo código QR pegado en el equipo siempre trae la información más reciente.</p>

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
        <tr><th>Asignado a</th><td><?= e($eq['asignado_a']) ?: 'Sin asignar' ?></td><th>Sede</th><td><?= e($eq['sede_nombre']) ?: '—' ?></td></tr>
        <tr><th>Tipo</th><td><?= e($eq['tipo']) ?></td><th>Área / Cargo</th><td><?= e($eq['area']) ?> / <?= e($eq['cargo']) ?></td></tr>
        <tr><th>Procesador</th><td><?= e($eq['procesador']) ?: '—' ?></td><th>Memoria / Almacenamiento</th><td><?= e($eq['memoria']) ?: '—' ?> / <?= e($eq['almacenamiento']) ?: '—' ?></td></tr>
        <tr><th>Sistema operativo</th><td><?= e($eq['sistema_operativo']) ?: '—' ?></td><th>IP local</th><td><?= e($eq['ip_local']) ?: '—' ?></td></tr>
        <tr><th>Último reporte del agente</th><td><?= e($eq['ultima_conexion_agente']) ?: '—' ?></td><th>Acceso remoto</th>
            <td><?php if ($eq['rustdesk_id']): ?><a href="rustdesk://<?= e($eq['rustdesk_id']) ?>?password=<?= e($eq['rustdesk_password']) ?>"><?= icon('zap') ?> Conectar</a><?php else: ?>—<?php endif; ?></td></tr>
    </table>
</div>

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
