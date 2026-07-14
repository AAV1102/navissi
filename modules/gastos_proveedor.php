<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/mailer.php';
requiere_login('../');
$pdo = db();
$msg = null;
$puedeGestionar = tiene_rol(['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO', 'CONTABILIDAD', 'ANALISTA']);

function notificar_aprobador(PDO $pdo, array $gasto, int $usuarioId): void {
    $stmt = $pdo->prepare("SELECT email, nombre FROM usuarios_sistema WHERE id = ? AND activo = 1");
    $stmt->execute([$usuarioId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || !filter_var($u['email'], FILTER_VALIDATE_EMAIL)) return;
    $html = plantilla_correo_html('Tienes un gasto por aprobar',
        "<p>Hola " . e($u['nombre']) . ",</p><p>El proveedor <strong>" . e($gasto['proveedor_nombre']) . "</strong> emitió la factura <strong>" . e($gasto['numero_factura'] ?: '(sin número)') . "</strong> por <strong>$" . number_format((float) $gasto['valor'], 0, ',', '.') . "</strong>, pendiente de tu aprobación antes de que Contabilidad la pase a pago.");
    enviar_correo($u['email'], 'NAVISSI - Gasto pendiente de aprobación', $html, $u['nombre']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'asignar_aprobador' && $puedeGestionar) {
        $proveedor = limpio($_POST['proveedor_nombre'] ?? null);
        $area = limpio($_POST['area'] ?? null);
        $aprobadorId = (int) ($_POST['aprobador_usuario_id'] ?? 0) ?: null;
        if ($proveedor && $area && $aprobadorId) {
            $pdo->prepare("INSERT INTO proveedores_aprobadores (proveedor_nombre, proveedor_nit, area, aprobador_usuario_id) VALUES (?,?,?,?)
                ON CONFLICT(proveedor_nombre, area) DO UPDATE SET aprobador_usuario_id = excluded.aprobador_usuario_id, activo = 1")
                ->execute([$proveedor, limpio($_POST['proveedor_nit'] ?? null), $area, $aprobadorId]);
            $msg = ['ok', "Aprobador asignado para {$proveedor} ({$area})."];
        } else {
            $msg = ['error', 'Proveedor, área y aprobador son obligatorios.'];
        }
    }

    if ($accion === 'registrar_gasto') {
        $proveedor = limpio($_POST['proveedor_nombre'] ?? null);
        if ($proveedor) {
            // Busca si ya hay un aprobador configurado para este proveedor+area; si lo hay,
            // el gasto nace ya con responsable asignado y se le notifica automaticamente.
            $area = limpio($_POST['area'] ?? null);
            $stmt = $pdo->prepare("SELECT aprobador_usuario_id FROM proveedores_aprobadores WHERE proveedor_nombre = ? AND (area = ? OR ? IS NULL) AND activo = 1 LIMIT 1");
            $stmt->execute([$proveedor, $area, $area]);
            $aprobadorId = $stmt->fetchColumn() ?: null;

            $pdo->prepare("INSERT INTO gastos_proveedor (proveedor_nombre, proveedor_nit, numero_factura, area, valor, descripcion, aprobador_usuario_id, creado_por) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$proveedor, limpio($_POST['proveedor_nit'] ?? null), limpio($_POST['numero_factura'] ?? null), $area,
                    trim((string) ($_POST['valor'] ?? '')) === '' ? null : (float) $_POST['valor'], limpio($_POST['descripcion'] ?? null),
                    $aprobadorId, usuario_actual()['nombre'] ?? 'Sistema']);
            $gastoId = (int) $pdo->lastInsertId();

            if ($aprobadorId) {
                $stmtG = $pdo->prepare("SELECT * FROM gastos_proveedor WHERE id = ?");
                $stmtG->execute([$gastoId]);
                notificar_aprobador($pdo, $stmtG->fetch(PDO::FETCH_ASSOC), $aprobadorId);
                $msg = ['ok', "Gasto registrado y notificado automáticamente al aprobador de {$proveedor}."];
            } else {
                $msg = ['ok', "Gasto registrado. Este proveedor no tiene aprobador asignado todavía — asígnalo abajo para que la próxima vez se notifique solo."];
            }
        } else {
            $msg = ['error', 'El proveedor es obligatorio.'];
        }
    }

    if ($accion === 'decidir') {
        $id = (int) ($_POST['id'] ?? 0);
        $decision = strtoupper((string) ($_POST['decision'] ?? ''));
        $stmt = $pdo->prepare("SELECT * FROM gastos_proveedor WHERE id = ?");
        $stmt->execute([$id]);
        $gasto = $stmt->fetch(PDO::FETCH_ASSOC);
        $usuario = usuario_actual();
        $esAprobador = $gasto && $gasto['aprobador_usuario_id'] == ($usuario['id'] ?? 0);
        if ($gasto && ($esAprobador || $puedeGestionar) && in_array($decision, ['APROBADO', 'RECHAZADO'], true)) {
            $pdo->prepare("UPDATE gastos_proveedor SET estado = ?, aprobado_por = ?, aprobado_en = CURRENT_TIMESTAMP, comentario_aprobador = ? WHERE id = ?")
                ->execute([$decision, $usuario['nombre'] ?? 'Sistema', limpio($_POST['comentario'] ?? null), $id]);
            $msg = ['ok', "Gasto marcado como {$decision}."];
        } else {
            $msg = ['error', 'No tienes permiso para decidir sobre este gasto.'];
        }
    }

    if ($accion === 'contabilizar' && $puedeGestionar) {
        $pdo->prepare("UPDATE gastos_proveedor SET contabilizada = 1 WHERE id = ? AND estado = 'APROBADO'")->execute([(int) ($_POST['id'] ?? 0)]);
        $msg = ['ok', 'Gasto marcado como contabilizado.'];
    }
}

$usuario = usuario_actual();
$where = $puedeGestionar ? '1=1' : 'g.aprobador_usuario_id = ' . (int) ($usuario['id'] ?? 0);
$gastos = $pdo->query("SELECT g.*, u.nombre AS aprobador_nombre FROM gastos_proveedor g LEFT JOIN usuarios_sistema u ON u.id = g.aprobador_usuario_id WHERE {$where} ORDER BY g.creado_en DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
$aprobadores = $pdo->query("SELECT pa.*, u.nombre AS aprobador_nombre FROM proveedores_aprobadores pa LEFT JOIN usuarios_sistema u ON u.id = pa.aprobador_usuario_id WHERE pa.activo = 1 ORDER BY pa.proveedor_nombre")->fetchAll(PDO::FETCH_ASSOC);
$usuariosSistema = $pdo->query("SELECT id, nombre, email, area_responsable FROM usuarios_sistema WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Gastos por Proveedor', 'Gastos por Proveedor', '../');
?>
<h1><?= icon('dollar', 'icon-lg') ?> Gastos por Proveedor</h1>
<p class="subtitle">Cuando se identifica un proveedor, se le asigna un aprobador por área. Contabilidad ve el estado de aprobación antes de pasar la factura a pago.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Registrar gasto / factura de proveedor</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="registrar_gasto">
        <div><label>Proveedor *</label><input type="text" name="proveedor_nombre" required></div>
        <div><label>NIT</label><input type="text" name="proveedor_nit"></div>
        <div><label># Factura</label><input type="text" name="numero_factura"></div>
        <div><label>Área</label><input type="text" name="area" placeholder="Tecnología, Logística..."></div>
        <div><label>Valor</label><input type="number" step="0.01" name="valor"></div>
        <div style="grid-column:1/-1;"><label>Descripción</label><input type="text" name="descripcion"></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Registrar</button></div>
    </form>
</div>

<?php if ($puedeGestionar): ?>
<div class="panel">
    <h3>Asignar aprobador por proveedor + área</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="asignar_aprobador">
        <div><label>Proveedor *</label><input type="text" name="proveedor_nombre" required></div>
        <div><label>NIT</label><input type="text" name="proveedor_nit"></div>
        <div><label>Área *</label><input type="text" name="area" required placeholder="Tecnología"></div>
        <div><label>Aprobador *</label>
            <select name="aprobador_usuario_id" required>
                <option value="">Selecciona...</option>
                <?php foreach ($usuariosSistema as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['nombre']) ?> (<?= e($u['email']) ?>)<?= $u['area_responsable'] ? ' — ' . e($u['area_responsable']) : '' ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('check') ?> Asignar</button></div>
    </form>
    <table style="margin-top:14px;">
        <tr><th>Proveedor</th><th>Área</th><th>Aprobador</th></tr>
        <?php foreach ($aprobadores as $a): ?>
        <tr><td><?= e($a['proveedor_nombre']) ?></td><td><?= e($a['area']) ?></td><td><?= e($a['aprobador_nombre'] ?: '—') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$aprobadores): ?><tr><td colspan="3" class="small">Sin proveedores con aprobador asignado todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Gastos <?= $puedeGestionar ? '' : 'que debo aprobar' ?> (<?= count($gastos) ?>)</h3>
    <table>
        <tr><th>Proveedor</th><th>Factura</th><th>Área</th><th>Valor</th><th>Aprobador</th><th>Estado</th><th>Contabilizada</th><th></th></tr>
        <?php foreach ($gastos as $g): ?>
        <tr>
            <td><?= e($g['proveedor_nombre']) ?></td>
            <td><?= e($g['numero_factura'] ?: '—') ?></td>
            <td><?= e($g['area'] ?: '—') ?></td>
            <td><?= $g['valor'] !== null ? '$' . number_format((float) $g['valor'], 0, ',', '.') : '—' ?></td>
            <td><?= e($g['aprobador_nombre'] ?: 'Sin asignar') ?></td>
            <td><span class="badge <?= $g['estado'] === 'APROBADO' ? 'badge-activo' : ($g['estado'] === 'RECHAZADO' ? 'badge-err' : 'badge-warn') ?>"><?= e($g['estado']) ?></span></td>
            <td><?= $g['contabilizada'] ? 'Sí' : 'No' ?></td>
            <td>
                <?php if ($g['estado'] === 'PENDIENTE' && ($g['aprobador_usuario_id'] == ($usuario['id'] ?? 0) || $puedeGestionar)): ?>
                <form method="post" class="inline"><input type="hidden" name="accion" value="decidir"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="decision" value="APROBADO"><button type="submit" style="padding:2px 6px;font-size:11px;">Aprobar</button></form>
                <form method="post" class="inline"><input type="hidden" name="accion" value="decidir"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><input type="hidden" name="decision" value="RECHAZADO"><button type="submit" style="padding:2px 6px;font-size:11px;">Rechazar</button></form>
                <?php endif; ?>
                <?php if ($g['estado'] === 'APROBADO' && !$g['contabilizada'] && $puedeGestionar): ?>
                <form method="post" class="inline"><input type="hidden" name="accion" value="contabilizar"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button type="submit" style="padding:2px 6px;font-size:11px;">Marcar contabilizada</button></form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$gastos): ?><tr><td colspan="8" class="small">Sin gastos registrados todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
