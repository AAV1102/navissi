<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_login('../');
$pdo = db();
$msg = null;
$u = usuario_actual();
$puedeGestionar = tiene_rol(['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO', 'COORDINADOR']);

function comercial_codigo(PDO $pdo): string {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM cotizaciones_comerciales")->fetchColumn() + 1;
    return 'COT-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_cliente') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if ($nombre) {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $pdo->prepare("INSERT INTO clientes_comerciales (nombre, nit, contacto, telefono, email, sede_id, vendedor_usuario_id) VALUES (?,?,?,?,?,?,?)")
                ->execute([$nombre, limpio($_POST['nit'] ?? null), limpio($_POST['contacto'] ?? null), limpio($_POST['telefono'] ?? null),
                    limpio($_POST['email'] ?? null), $sedeId, $u['id'] ?? null]);
            $msg = ['ok', 'Cliente comercial creado.'];
        }
    }

    if ($accion === 'crear_cotizacion') {
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $descripciones = $_POST['item_descripcion'] ?? [];
        $cantidades = $_POST['item_cantidad'] ?? [];
        $valores = $_POST['item_valor'] ?? [];
        if ($clienteId && $descripciones) {
            $codigo = comercial_codigo($pdo);
            $pdo->prepare("INSERT INTO cotizaciones_comerciales (codigo, cliente_id, vendedor_usuario_id, valido_hasta, observaciones) VALUES (?,?,?,?,?)")
                ->execute([$codigo, $clienteId, $u['id'] ?? null, limpio($_POST['valido_hasta'] ?? null), limpio($_POST['observaciones'] ?? null)]);
            $cotId = (int) $pdo->lastInsertId();
            $total = 0;
            foreach ($descripciones as $i => $desc) {
                $desc = limpio($desc);
                if (!$desc) continue;
                $cant = (float) ($cantidades[$i] ?? 1);
                $valor = (float) ($valores[$i] ?? 0);
                $pdo->prepare("INSERT INTO cotizaciones_comerciales_items (cotizacion_id, descripcion, cantidad, valor_unitario) VALUES (?,?,?,?)")
                    ->execute([$cotId, $desc, $cant, $valor]);
                $total += $cant * $valor;
            }
            $pdo->prepare("UPDATE cotizaciones_comerciales SET valor_total = ? WHERE id = ?")->execute([$total, $cotId]);
            $msg = ['ok', "Cotización {$codigo} creada por $" . number_format($total, 0, ',', '.') . "."];
        }
    }

    if ($accion === 'cambiar_estado_cotizacion') {
        $id = (int) ($_POST['id'] ?? 0);
        $estado = strtoupper((string) ($_POST['estado'] ?? ''));
        if (in_array($estado, ['BORRADOR', 'ENVIADA', 'APROBADA', 'RECHAZADA'], true)) {
            $pdo->prepare("UPDATE cotizaciones_comerciales SET estado = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$estado, $id]);
            $msg = ['ok', "Cotización marcada como {$estado}."];
        }
    }
}

$clientes = $pdo->query("SELECT c.*, s.nombre AS sede_nombre FROM clientes_comerciales c LEFT JOIN sedes s ON s.id = c.sede_id ORDER BY c.nombre")->fetchAll(PDO::FETCH_ASSOC);
$cotizaciones = $pdo->query("SELECT co.*, cl.nombre AS cliente_nombre, us.nombre AS vendedor_nombre FROM cotizaciones_comerciales co
    JOIN clientes_comerciales cl ON cl.id = co.cliente_id LEFT JOIN usuarios_sistema us ON us.id = co.vendedor_usuario_id
    ORDER BY co.creado_en DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Comercial', 'Comercial', '../');
?>
<h1><?= icon('dollar', 'icon-lg') ?> Comercial</h1>
<p class="subtitle">Clientes comerciales y cotizaciones, con seguimiento de estado hasta la aprobación.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Nuevo cliente comercial</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="crear_cliente">
        <div><label>Nombre / razón social *</label><input type="text" name="nombre" required></div>
        <div><label>NIT</label><input type="text" name="nit"></div>
        <div><label>Contacto</label><input type="text" name="contacto"></div>
        <div><label>Teléfono</label><input type="text" name="telefono"></div>
        <div><label>Email</label><input type="email" name="email"></div>
        <div><label>Sede/tienda asociada</label><input type="text" name="sede"></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Crear cliente</button></div>
    </form>
    <table style="margin-top:14px;">
        <tr><th>Nombre</th><th>NIT</th><th>Contacto</th><th>Sede</th></tr>
        <?php foreach ($clientes as $c): ?>
        <tr><td><?= e($c['nombre']) ?></td><td><?= e($c['nit'] ?: '—') ?></td><td><?= e($c['contacto'] ?: '—') ?></td><td><?= e($c['sede_nombre'] ?: '—') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$clientes): ?><tr><td colspan="4" class="small">Sin clientes registrados todavía.</td></tr><?php endif; ?>
    </table>
</div>

<div class="panel">
    <h3>Nueva cotización</h3>
    <form method="post" id="form-cotizacion">
        <input type="hidden" name="accion" value="crear_cotizacion">
        <div class="grid-form">
            <div><label>Cliente *</label><select name="cliente_id" required><option value="">Selecciona...</option><?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?></select></div>
            <div><label>Válida hasta</label><input type="date" name="valido_hasta"></div>
            <div style="grid-column:1/-1;"><label>Observaciones</label><input type="text" name="observaciones"></div>
        </div>
        <h4 style="margin-top:14px;">Ítems</h4>
        <div id="items-cotizacion">
            <div class="grid-form"><div><label>Descripción</label><input type="text" name="item_descripcion[]"></div><div><label>Cantidad</label><input type="number" step="0.01" name="item_cantidad[]" value="1"></div><div><label>Valor unitario</label><input type="number" step="0.01" name="item_valor[]"></div></div>
        </div>
        <button type="button" onclick="document.getElementById('items-cotizacion').insertAdjacentHTML('beforeend', document.getElementById('items-cotizacion').firstElementChild.outerHTML)" class="btn-secondary" style="margin-top:8px;">+ Agregar ítem</button>
        <br><button type="submit" style="margin-top:14px;"><?= icon('plus') ?> Crear cotización</button>
    </form>
</div>

<div class="panel">
    <h3>Cotizaciones (<?= count($cotizaciones) ?>)</h3>
    <table>
        <tr><th>Código</th><th>Cliente</th><th>Vendedor</th><th>Valor</th><th>Estado</th><th>Válida hasta</th><th></th></tr>
        <?php foreach ($cotizaciones as $co): ?>
        <tr>
            <td><strong><?= e($co['codigo']) ?></strong></td>
            <td><?= e($co['cliente_nombre']) ?></td>
            <td><?= e($co['vendedor_nombre'] ?: '—') ?></td>
            <td>$<?= number_format((float) $co['valor_total'], 0, ',', '.') ?></td>
            <td><span class="badge <?= $co['estado'] === 'APROBADA' ? 'badge-activo' : ($co['estado'] === 'RECHAZADA' ? 'badge-err' : 'badge-warn') ?>"><?= e($co['estado']) ?></span></td>
            <td class="small"><?= e($co['valido_hasta'] ?: '—') ?></td>
            <td>
                <form method="post" class="inline"><input type="hidden" name="accion" value="cambiar_estado_cotizacion"><input type="hidden" name="id" value="<?= (int) $co['id'] ?>">
                    <select name="estado" onchange="this.form.requestSubmit()">
                        <?php foreach (['BORRADOR', 'ENVIADA', 'APROBADA', 'RECHAZADA'] as $e): ?><option <?= $co['estado'] === $e ? 'selected' : '' ?>><?= $e ?></option><?php endforeach; ?>
                    </select>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$cotizaciones): ?><tr><td colspan="7" class="small">Sin cotizaciones todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
