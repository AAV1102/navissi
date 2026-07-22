<?php
// Vista de detalle de un equipo del inventario: todo lo que reporta el agente
// en un solo lugar (hardware, parches de Windows, software instalado) y
// acciones que se pueden pedirle de vuelta (buscar actualizaciones,
// desinstalar un programa) via la cola de ordenes que el agente ya consulta
// en cada reporte (api_agente_ordenes.php).
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$puedeOrdenar = tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI']);

$serial = trim((string) ($_GET['serial'] ?? ''));
if ($serial === '') { http_response_code(400); exit('Falta el serial del equipo.'); }

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeOrdenar) {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'orden_actualizaciones') {
        $pdo->prepare("INSERT INTO agente_ordenes (serial_objetivo, tipo, parametros_json, solicitado_por) VALUES (?,?,?,?)")
            ->execute([$serial, 'WINDOWS_UPDATE', '{}', usuario_actual()['nombre'] ?? 'Sistema']);
        $msg = ['ok', 'Orden de actualizaciones de Windows enviada. Se ejecutará en el próximo reporte del equipo (máximo 5 minutos).'];
    } elseif ($accion === 'orden_desinstalar') {
        $nombreSoft = limpio($_POST['nombre_software'] ?? null);
        if ($nombreSoft) {
            $pdo->prepare("INSERT INTO agente_ordenes (serial_objetivo, tipo, parametros_json, solicitado_por) VALUES (?,?,?,?)")
                ->execute([$serial, 'UNINSTALL_SOFTWARE', json_encode(['nombre' => $nombreSoft]), usuario_actual()['nombre'] ?? 'Sistema']);
            $msg = ['ok', 'Orden de desinstalación de "' . $nombreSoft . '" enviada. Se ejecutará en el próximo reporte del equipo.'];
        }
    }
}

$stmt = $pdo->prepare("SELECT i.*, s.nombre AS sede_nombre, s.ciudad AS sede_ciudad
    FROM inventario i LEFT JOIN sedes s ON s.id = i.sede_id WHERE i.serial = ?");
$stmt->execute([$serial]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$equipo) { http_response_code(404); exit('Equipo no encontrado.'); }

$parches = $pdo->prepare("SELECT * FROM parches_equipo WHERE inventario_id = ? ORDER BY fecha_instalado DESC, kb DESC");
$parches->execute([$equipo['id']]);
$parches = $parches->fetchAll(PDO::FETCH_ASSOC);

$software = $pdo->prepare("SELECT * FROM equipos_software WHERE inventario_id = ? ORDER BY nombre COLLATE NOCASE");
$software->execute([$equipo['id']]);
$software = $software->fetchAll(PDO::FETCH_ASSOC);

$ordenes = $pdo->prepare("SELECT * FROM agente_ordenes WHERE lower(serial_objetivo) = lower(?) ORDER BY id DESC LIMIT 20");
$ordenes->execute([$serial]);
$ordenes = $ordenes->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Detalle del equipo', 'Agente de inventario', '../');
?>
<h1><?= icon('inventory', 'icon-lg') ?> <?= e($equipo['hostname'] ?: $equipo['serial']) ?></h1>
<p class="subtitle">Todo lo que el agente reportó de este equipo, en un solo lugar.</p>
<?php if ($msg): ?><div class="msg-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('inventory') ?> Datos del equipo</h3>
    <table>
        <tr><th>Nombre de equipo (hostname)</th><td><?= e($equipo['hostname']) ?: '<span class="small">Aún no reportado — reinstala el agente para obtenerlo</span>' ?></td></tr>
        <tr><th>Serial</th><td><code><?= e($equipo['serial']) ?></code></td></tr>
        <tr><th>Usuario de Windows</th><td><?= e($equipo['asignado_a']) ?: '—' ?></td></tr>
        <tr><th>Sede</th><td><?= e($equipo['sede_nombre']) ?: '—' ?></td></tr>
        <tr><th>Ciudad</th><td><?= e($equipo['sede_ciudad']) ?: '—' ?></td></tr>
        <tr><th>Tipo</th><td><?= e($equipo['tipo']) ?></td></tr>
        <tr><th>Marca / Modelo</th><td><?= e($equipo['marca']) ?> <?= e($equipo['modelo']) ?></td></tr>
        <tr><th>Sistema operativo</th><td><?= e($equipo['sistema_operativo']) ?></td></tr>
        <tr><th>Procesador</th><td><?= e($equipo['procesador']) ?></td></tr>
        <tr><th>Memoria RAM</th><td><?= e($equipo['memoria']) ?></td></tr>
        <tr><th>Almacenamiento</th><td><?= e($equipo['almacenamiento']) ?></td></tr>
        <tr><th>IP local</th><td><?= e($equipo['ip_local']) ?></td></tr>
        <tr><th>Último reporte del agente</th><td><?= e($equipo['ultima_conexion_agente']) ?: 'Nunca' ?></td></tr>
        <tr><th>RustDesk ID</th><td><?= e($equipo['rustdesk_id']) ?: '—' ?></td></tr>
    </table>
    <p class="small" style="margin-top:10px;">El "área" no se detecta automáticamente: el usuario de Windows es un nombre de cuenta local, no siempre coincide con un usuario de NAVISSI, así que no se puede cruzar de forma confiable con el área/cargo de RRHH.</p>
</div>

<?php if ($puedeOrdenar): ?>
<div class="panel">
    <h3><?= icon('zap') ?> Acciones remotas</h3>
    <p class="small">Se ejecutan en el próximo reporte del equipo (cada 5 minutos como máximo).</p>
    <form method="post" class="inline">
        <input type="hidden" name="accion" value="orden_actualizaciones">
        <button type="submit"><?= icon('upload') ?> Buscar e instalar actualizaciones de Windows</button>
    </form>
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

<?php if ($puedeOrdenar): ?>
<div class="panel">
    <h3><?= icon('log') ?> Órdenes enviadas a este equipo</h3>
    <table>
        <tr><th>Tipo</th><th>Estado</th><th>Resultado</th><th>Fecha</th></tr>
        <?php foreach ($ordenes as $o): ?>
        <tr>
            <td><?= e($o['tipo']) ?></td>
            <td><span class="badge <?= $o['estado']==='COMPLETADA'?'badge-activo':($o['estado']==='FALLIDA'?'badge-err':'badge-otro') ?>"><?= e($o['estado']) ?></span></td>
            <td class="small"><?= e($o['resultado'] ?: $o['error']) ?></td>
            <td class="small"><?= e($o['creado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$ordenes): ?><tr><td colspan="4" class="small">Sin órdenes enviadas todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
