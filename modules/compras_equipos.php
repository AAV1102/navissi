<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$u = usuario_actual();

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'TI'])) {
    layout_inicio('Compras de Equipos', 'Compras de Equipos', '../');
    echo '<div class="msg-error">No tienes permiso para ver el historial de compras.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $proveedor = limpio($_POST['proveedor'] ?? null);
        $serial = limpio($_POST['equipo_serial'] ?? null);
        if (!$proveedor) {
            $msg = ['error', 'El proveedor es obligatorio.'];
        } else {
            $equipoId = null;
            if ($serial) {
                $stmt = $pdo->prepare("SELECT id FROM inventario WHERE serial = ?");
                $stmt->execute([$serial]);
                $equipoId = $stmt->fetchColumn() ?: null;
            }
            $pdo->prepare("INSERT INTO compras_equipo (equipo_id, equipo_serial, proveedor, numero_factura, fecha_compra, valor, articulo, observaciones, creado_por) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$equipoId, $serial ?: null, $proveedor, limpio($_POST['numero_factura'] ?? null),
                    limpio($_POST['fecha_compra'] ?? null) ?: null, (float) ($_POST['valor'] ?? 0) ?: null,
                    limpio($_POST['articulo'] ?? null), limpio($_POST['observaciones'] ?? null), $u['nombre'] ?? null]);
            $msg = ['ok', 'Compra registrada.'];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM compras_equipo WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, i.marca, i.modelo, i.placa FROM compras_equipo c LEFT JOIN inventario i ON c.equipo_id = i.id WHERE 1=1";
$params = [];
if ($busqueda !== '') {
    $sql .= " AND (c.proveedor LIKE :b OR c.numero_factura LIKE :b OR c.articulo LIKE :b OR c.equipo_serial LIKE :b)";
    $params['b'] = "%{$busqueda}%";
}
$sql .= " ORDER BY c.fecha_compra DESC, c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
$equipos = $pdo->query("SELECT serial, marca, modelo FROM inventario ORDER BY serial")->fetchAll(PDO::FETCH_ASSOC);
$totalInvertido = array_sum(array_column($compras, 'valor'));

layout_inicio('Compras de Equipos', 'Compras de Equipos', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Historial de Compras de Equipos</h1>
<p class="subtitle">De dónde vino cada equipo — proveedor, factura y valor, enlazado al inventario. Total invertido: $<?= number_format($totalInvertido,0,',','.') ?>.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Registrar compra</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <div class="grid-form">
            <div><label>Proveedor *</label><input type="text" name="proveedor" required></div>
            <div><label>N° factura</label><input type="text" name="numero_factura"></div>
            <div><label>Fecha de compra</label><input type="date" name="fecha_compra"></div>
            <div><label>Valor</label><input type="number" step="0.01" name="valor"></div>
            <div><label>Artículo / descripción</label><input type="text" name="articulo" placeholder="Ej. Portátil Dell Latitude 5420"></div>
            <div><label>Equipo (serial, opcional)</label>
                <input type="text" name="equipo_serial" list="lista-eq-compra" placeholder="Si ya está en inventario">
                <datalist id="lista-eq-compra"><?php foreach ($equipos as $eq): ?><option value="<?= e($eq['serial']) ?>"><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?><?php endforeach; ?></datalist>
            </div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Observaciones"></textarea>
        <button type="submit">Registrar</button>
    </form>
</div>

<form class="toolbar" method="get">
    <input type="search" name="q" placeholder="Buscar proveedor, factura, artículo, serial..." value="<?= e($busqueda) ?>" style="min-width:280px">
    <button type="submit"><?= icon('search') ?> Buscar</button>
</form>

<table>
    <tr><th>Proveedor</th><th>Factura</th><th>Artículo</th><th>Equipo</th><th>Fecha</th><th>Valor</th><th></th></tr>
    <?php foreach ($compras as $c): ?>
    <tr>
        <td><?= e($c['proveedor']) ?></td>
        <td class="small"><?= e($c['numero_factura']) ?: '—' ?></td>
        <td><?= e($c['articulo']) ?: '—' ?></td>
        <td class="small"><?= $c['marca'] ? e($c['marca']) . ' ' . e($c['modelo']) : ($c['equipo_serial'] ? e($c['equipo_serial']) : '—') ?></td>
        <td class="small"><?= e($c['fecha_compra']) ?: '—' ?></td>
        <td><?= $c['valor'] ? '$' . number_format((float)$c['valor'],0,',','.') : '—' ?></td>
        <td>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$compras): ?><tr><td colspan="7" class="small">Sin compras registradas.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
