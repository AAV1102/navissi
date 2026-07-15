<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_login('../');
$pdo = db();
$msg = null;
$estadosValidos = ['PENDIENTE', 'PROCESANDO', 'ENVIADO', 'ENTREGADO', 'CANCELADO'];

function ecommerce_codigo(PDO $pdo): string {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM pedidos_ecommerce")->fetchColumn() + 1;
    return 'PED-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_pedido') {
        $cliente = limpio($_POST['cliente_nombre'] ?? null);
        $productos = $_POST['item_producto'] ?? [];
        if ($cliente && $productos) {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede_despacho'] ?? null, false);
            $codigo = ecommerce_codigo($pdo);
            $pdo->prepare("INSERT INTO pedidos_ecommerce (codigo, cliente_nombre, cliente_email, cliente_telefono, canal, sede_despacho_id) VALUES (?,?,?,?,?,?)")
                ->execute([$codigo, $cliente, limpio($_POST['cliente_email'] ?? null), limpio($_POST['cliente_telefono'] ?? null),
                    limpio($_POST['canal'] ?? null) ?: 'WEB', $sedeId]);
            $pedidoId = (int) $pdo->lastInsertId();
            $total = 0;
            $cantidades = $_POST['item_cantidad'] ?? [];
            $valores = $_POST['item_valor'] ?? [];
            foreach ($productos as $i => $prod) {
                $prod = limpio($prod);
                if (!$prod) continue;
                $cant = (int) ($cantidades[$i] ?? 1);
                $valor = (float) ($valores[$i] ?? 0);
                $pdo->prepare("INSERT INTO pedidos_ecommerce_items (pedido_id, producto, cantidad, valor_unitario) VALUES (?,?,?,?)")
                    ->execute([$pedidoId, $prod, $cant, $valor]);
                $total += $cant * $valor;
            }
            $pdo->prepare("UPDATE pedidos_ecommerce SET valor_total = ? WHERE id = ?")->execute([$total, $pedidoId]);
            $msg = ['ok', "Pedido {$codigo} registrado por $" . number_format($total, 0, ',', '.') . "."];
        } else {
            $msg = ['error', 'Cliente y al menos un producto son obligatorios.'];
        }
    }

    if ($accion === 'actualizar_estado') {
        $id = (int) ($_POST['id'] ?? 0);
        $estado = strtoupper((string) ($_POST['estado'] ?? ''));
        if (in_array($estado, $estadosValidos, true)) {
            $guia = limpio($_POST['guia_envio'] ?? null);
            $transportadora = limpio($_POST['transportadora'] ?? null);
            $pdo->prepare("UPDATE pedidos_ecommerce SET estado = ?, guia_envio = COALESCE(?, guia_envio), transportadora = COALESCE(?, transportadora), actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$estado, $guia, $transportadora, $id]);
            $msg = ['ok', "Pedido actualizado a {$estado}."];
        }
    }
}

$filtroEstado = strtoupper((string) ($_GET['estado'] ?? ''));
$sql = "SELECT p.*, s.nombre AS sede_nombre FROM pedidos_ecommerce p LEFT JOIN sedes s ON s.id = p.sede_despacho_id";
$params = [];
if ($filtroEstado && in_array($filtroEstado, $estadosValidos, true)) { $sql .= " WHERE p.estado = ?"; $params[] = $filtroEstado; }
$sql .= " ORDER BY p.creado_en DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resumen = $pdo->query("SELECT estado, COUNT(*) c FROM pedidos_ecommerce GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);

layout_inicio('Ecommerce', 'Ecommerce', '../');
?>
<h1><?= icon('store', 'icon-lg') ?> Ecommerce</h1>
<p class="subtitle">Pedidos de tienda online: registro, seguimiento de envío y estado, con despacho ligado a la sede real.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <?php foreach ($estadosValidos as $e): ?>
    <div class="card"><div class="num"><?= (int) ($resumen[$e] ?? 0) ?></div><div class="label"><?= e($e) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="panel">
    <h3>Nuevo pedido</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear_pedido">
        <div class="grid-form">
            <div><label>Cliente *</label><input type="text" name="cliente_nombre" required></div>
            <div><label>Email</label><input type="email" name="cliente_email"></div>
            <div><label>Teléfono</label><input type="text" name="cliente_telefono"></div>
            <div><label>Canal</label><select name="canal"><option>WEB</option><option>WHATSAPP</option><option>MARKETPLACE</option><option>INSTAGRAM</option></select></div>
            <div><label>Sede de despacho</label><input type="text" name="sede_despacho"></div>
        </div>
        <h4 style="margin-top:14px;">Productos</h4>
        <div id="items-pedido">
            <div class="grid-form"><div><label>Producto</label><input type="text" name="item_producto[]"></div><div><label>Cantidad</label><input type="number" name="item_cantidad[]" value="1"></div><div><label>Valor unitario</label><input type="number" step="0.01" name="item_valor[]"></div></div>
        </div>
        <button type="button" onclick="document.getElementById('items-pedido').insertAdjacentHTML('beforeend', document.getElementById('items-pedido').firstElementChild.outerHTML)" class="btn-secondary" style="margin-top:8px;">+ Agregar producto</button>
        <br><button type="submit" style="margin-top:14px;"><?= icon('plus') ?> Registrar pedido</button>
    </form>
</div>

<div class="panel">
    <h3>Pedidos</h3>
    <form method="get" class="toolbar"><select name="estado" onchange="this.form.requestSubmit()"><option value="">Todos</option><?php foreach ($estadosValidos as $e): ?><option <?= $filtroEstado === $e ? 'selected' : '' ?>><?= $e ?></option><?php endforeach; ?></select></form>
    <table style="margin-top:10px;">
        <tr><th>Código</th><th>Cliente</th><th>Canal</th><th>Sede</th><th>Valor</th><th>Estado</th><th>Guía</th><th></th></tr>
        <?php foreach ($pedidos as $p): ?>
        <tr>
            <td><strong><?= e($p['codigo']) ?></strong></td>
            <td><?= e($p['cliente_nombre']) ?></td>
            <td><?= e($p['canal']) ?></td>
            <td><?= e($p['sede_nombre'] ?: '—') ?></td>
            <td>$<?= number_format((float) $p['valor_total'], 0, ',', '.') ?></td>
            <td><span class="badge <?= $p['estado'] === 'ENTREGADO' ? 'badge-activo' : ($p['estado'] === 'CANCELADO' ? 'badge-err' : 'badge-warn') ?>"><?= e($p['estado']) ?></span></td>
            <td class="small"><?= e($p['guia_envio'] ?: '—') ?><?= $p['transportadora'] ? ' (' . e($p['transportadora']) . ')' : '' ?></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="accion" value="actualizar_estado"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <select name="estado" style="width:110px;"><?php foreach ($estadosValidos as $e): ?><option <?= $p['estado'] === $e ? 'selected' : '' ?>><?= $e ?></option><?php endforeach; ?></select>
                    <input type="text" name="guia_envio" placeholder="Guía" style="width:90px;">
                    <button type="submit" style="padding:2px 6px;font-size:11px;">Actualizar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$pedidos): ?><tr><td colspan="8" class="small">Sin pedidos todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
