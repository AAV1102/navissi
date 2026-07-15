<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_login('../');
$pdo = db();
$msg = null;
$estadosValidos = ['PENDIENTE', 'EN_PROCESO', 'TERMINADA', 'CANCELADA'];

function produccion_codigo(PDO $pdo): string {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM ordenes_produccion")->fetchColumn() + 1;
    return 'OP-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $producto = limpio($_POST['producto'] ?? null);
        $cantidad = trim((string) ($_POST['cantidad'] ?? ''));
        if ($producto && $cantidad !== '') {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $u = usuario_actual();
            $codigo = produccion_codigo($pdo);
            $pdo->prepare("INSERT INTO ordenes_produccion (codigo, producto, cantidad, unidad, sede_id, responsable_usuario_id, fecha_programada, observaciones) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$codigo, $producto, (float) $cantidad, limpio($_POST['unidad'] ?? null) ?: 'UND', $sedeId, $u['id'] ?? null,
                    limpio($_POST['fecha_programada'] ?? null), limpio($_POST['observaciones'] ?? null)]);
            $msg = ['ok', "Orden {$codigo} creada."];
        } else {
            $msg = ['error', 'Producto y cantidad son obligatorios.'];
        }
    }

    if ($accion === 'cambiar_estado') {
        $id = (int) ($_POST['id'] ?? 0);
        $estado = strtoupper((string) ($_POST['estado'] ?? ''));
        if (in_array($estado, $estadosValidos, true)) {
            $stmt = $pdo->prepare("SELECT estado, fecha_inicio_real FROM ordenes_produccion WHERE id = ?");
            $stmt->execute([$id]);
            $actual = $stmt->fetch(PDO::FETCH_ASSOC);
            $fechaInicio = ($estado === 'EN_PROCESO' && !$actual['fecha_inicio_real']) ? gmdate('Y-m-d H:i:s') : null;
            $fechaFin = $estado === 'TERMINADA' ? gmdate('Y-m-d H:i:s') : null;
            $pdo->prepare("UPDATE ordenes_produccion SET estado = ?, fecha_inicio_real = COALESCE(?, fecha_inicio_real), fecha_fin_real = COALESCE(?, fecha_fin_real) WHERE id = ?")
                ->execute([$estado, $fechaInicio, $fechaFin, $id]);
            $msg = ['ok', "Orden actualizada a {$estado}."];
        }
    }
}

$filtroEstado = strtoupper((string) ($_GET['estado'] ?? ''));
$sql = "SELECT o.*, s.nombre AS sede_nombre, u.nombre AS responsable_nombre FROM ordenes_produccion o
    LEFT JOIN sedes s ON s.id = o.sede_id LEFT JOIN usuarios_sistema u ON u.id = o.responsable_usuario_id";
$params = [];
if ($filtroEstado && in_array($filtroEstado, $estadosValidos, true)) { $sql .= " WHERE o.estado = ?"; $params[] = $filtroEstado; }
$sql .= " ORDER BY o.creado_en DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$resumen = $pdo->query("SELECT estado, COUNT(*) c FROM ordenes_produccion GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);

layout_inicio('Producción', 'Producción', '../');
?>
<h1><?= icon('inventory', 'icon-lg') ?> Producción</h1>
<p class="subtitle">Órdenes de producción con estado real, responsable y tiempos de inicio/fin.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <?php foreach ($estadosValidos as $e): ?>
    <div class="card"><div class="num"><?= (int) ($resumen[$e] ?? 0) ?></div><div class="label"><?= e(str_replace('_', ' ', $e)) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="panel">
    <h3>Nueva orden de producción</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="crear">
        <div><label>Producto *</label><input type="text" name="producto" required></div>
        <div><label>Cantidad *</label><input type="number" step="0.01" name="cantidad" required></div>
        <div><label>Unidad</label><input type="text" name="unidad" value="UND"></div>
        <div><label>Sede/planta</label><input type="text" name="sede"></div>
        <div><label>Fecha programada</label><input type="date" name="fecha_programada"></div>
        <div style="grid-column:1/-1;"><label>Observaciones</label><input type="text" name="observaciones"></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Crear orden</button></div>
    </form>
</div>

<div class="panel">
    <h3>Órdenes</h3>
    <form method="get" class="toolbar"><select name="estado" onchange="this.form.requestSubmit()"><option value="">Todas</option><?php foreach ($estadosValidos as $e): ?><option value="<?= $e ?>" <?= $filtroEstado === $e ? 'selected' : '' ?>><?= str_replace('_', ' ', $e) ?></option><?php endforeach; ?></select></form>
    <table style="margin-top:10px;">
        <tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>Sede</th><th>Responsable</th><th>Programada</th><th>Estado</th><th></th></tr>
        <?php foreach ($ordenes as $o): ?>
        <tr>
            <td><strong><?= e($o['codigo']) ?></strong></td>
            <td><?= e($o['producto']) ?></td>
            <td><?= number_format((float) $o['cantidad'], 2, ',', '.') ?> <?= e($o['unidad']) ?></td>
            <td><?= e($o['sede_nombre'] ?: '—') ?></td>
            <td><?= e($o['responsable_nombre'] ?: '—') ?></td>
            <td class="small"><?= e($o['fecha_programada'] ?: '—') ?></td>
            <td><span class="badge <?= $o['estado'] === 'TERMINADA' ? 'badge-activo' : ($o['estado'] === 'CANCELADA' ? 'badge-err' : 'badge-warn') ?>"><?= e(str_replace('_', ' ', $o['estado'])) ?></span></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                    <select name="estado" onchange="this.form.requestSubmit()"><?php foreach ($estadosValidos as $e): ?><option value="<?= $e ?>" <?= $o['estado'] === $e ? 'selected' : '' ?>><?= str_replace('_', ' ', $e) ?></option><?php endforeach; ?></select>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$ordenes): ?><tr><td colspan="8" class="small">Sin órdenes todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
