<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $datos = [
            'proveedor' => limpio($_POST['proveedor'] ?? null),
            'tipo' => limpio($_POST['tipo'] ?? null),
            'cantidad' => (int) ($_POST['cantidad'] ?? 0),
            'valor_mes' => (float) ($_POST['valor_mes'] ?? 0),
            'valor_anual' => (float) ($_POST['valor_anual'] ?? 0),
            'observaciones' => limpio($_POST['observaciones'] ?? null),
            'clave_licencia' => limpio($_POST['clave_licencia'] ?? null),
            'fecha_vencimiento' => limpio($_POST['fecha_vencimiento'] ?? null) ?: null,
            'nombre_software' => limpio($_POST['nombre_software'] ?? null),
        ];
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
            $stmt = $pdo->prepare("UPDATE licencias SET {$set} WHERE id = :id");
            $datos['id'] = $id;
            $stmt->execute($datos);
            $msg = ['ok', 'Licencia actualizada.'];
        } else {
            $cols = implode(', ', array_keys($datos));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
            $pdo->prepare("INSERT INTO licencias ({$cols}) VALUES ({$ph})")->execute($datos);
            $msg = ['ok', 'Licencia agregada.'];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM licencias WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Licencia eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM licencias WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$licencias = $pdo->query("SELECT * FROM licencias ORDER BY proveedor")->fetchAll(PDO::FETCH_ASSOC);
$totalMes = array_sum(array_column($licencias, 'valor_mes'));

// Cuántas instalaciones reales del agente coinciden con el nombre de software
// de cada licencia — así se ve de un vistazo si hay mas instalaciones que
// licencias compradas (sobreuso) o licencias sin usar.
$stmtInstalaciones = $pdo->prepare("SELECT COUNT(DISTINCT inventario_id) FROM equipos_software WHERE nombre LIKE ?");
foreach ($licencias as &$l) {
    $l['instalaciones_detectadas'] = null;
    if (!empty($l['nombre_software'])) {
        $stmtInstalaciones->execute(['%' . $l['nombre_software'] . '%']);
        $l['instalaciones_detectadas'] = (int) $stmtInstalaciones->fetchColumn();
    }
}
unset($l);

$hoy = gmdate('Y-m-d');
$en30dias = gmdate('Y-m-d', strtotime('+30 days'));
$vencidas = array_filter($licencias, fn($l) => $l['fecha_vencimiento'] && $l['fecha_vencimiento'] < $hoy);
$porVencer = array_filter($licencias, fn($l) => $l['fecha_vencimiento'] && $l['fecha_vencimiento'] >= $hoy && $l['fecha_vencimiento'] <= $en30dias);
$sobreuso = array_filter($licencias, fn($l) => $l['instalaciones_detectadas'] !== null && $l['instalaciones_detectadas'] > $l['cantidad']);

layout_inicio('Licencias', 'Licencias', '../');
?>
<h1><?= icon('shield','icon-lg') ?> Licencias de software</h1>
<p class="subtitle"><?= count($licencias) ?> registros — costo mensual total: $<?= number_format($totalMes, 0, ',', '.') ?></p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if ($vencidas || $porVencer || $sobreuso): ?>
<div class="msg-error" style="display:flex;flex-direction:column;gap:4px;">
    <strong><?= icon('bell') ?> Licencias que necesitan atención</strong>
    <?php foreach ($vencidas as $l): ?><span class="small">· <?= e($l['proveedor']) ?> <?= e($l['tipo']) ?> — vencida el <?= e($l['fecha_vencimiento']) ?></span><?php endforeach; ?>
    <?php foreach ($porVencer as $l): ?><span class="small">· <?= e($l['proveedor']) ?> <?= e($l['tipo']) ?> — vence el <?= e($l['fecha_vencimiento']) ?></span><?php endforeach; ?>
    <?php foreach ($sobreuso as $l): ?><span class="small">· <?= e($l['proveedor']) ?> <?= e($l['tipo']) ?> — <?= $l['instalaciones_detectadas'] ?> equipos con el software instalado pero solo <?= (int) $l['cantidad'] ?> licencia(s) comprada(s)</span><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar licencia' : 'Agregar licencia' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Proveedor</label><input type="text" name="proveedor" value="<?= e($editar['proveedor'] ?? '') ?>"></div>
            <div><label>Tipo</label><input type="text" name="tipo" value="<?= e($editar['tipo'] ?? '') ?>"></div>
            <div><label>Cantidad comprada (cupos)</label><input type="number" name="cantidad" value="<?= e($editar['cantidad'] ?? 0) ?>"></div>
            <div><label>Clave de licencia</label><input type="text" name="clave_licencia" value="<?= e($editar['clave_licencia'] ?? '') ?>" placeholder="Clave de producto / suscripción"></div>
            <div><label>Vencimiento</label><input type="date" name="fecha_vencimiento" value="<?= e($editar['fecha_vencimiento'] ?? '') ?>"></div>
            <div><label>Nombre del software (para cruzar con lo instalado)</label><input type="text" name="nombre_software" value="<?= e($editar['nombre_software'] ?? '') ?>" placeholder="Ej: Adobe Acrobat, Office"></div>
            <div><label>Valor mensual</label><input type="number" step="0.01" name="valor_mes" value="<?= e($editar['valor_mes'] ?? 0) ?>"></div>
            <div><label>Valor anual</label><input type="number" step="0.01" name="valor_anual" value="<?= e($editar['valor_anual'] ?? 0) ?>"></div>
            <div><label>Observaciones</label><input type="text" name="observaciones" value="<?= e($editar['observaciones'] ?? '') ?>"></div>
        </div>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar licencia' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="licencias.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Proveedor</th><th>Tipo</th><th>Comprado</th><th>Instalado (real)</th><th>Vencimiento</th><th>Valor/mes</th><th>Valor/año</th><th></th></tr>
    <?php foreach ($licencias as $l): ?>
    <tr>
        <td><?= e($l['proveedor']) ?></td>
        <td><?= e($l['tipo']) ?></td>
        <td><?= (int)$l['cantidad'] ?></td>
        <td>
            <?php if ($l['instalaciones_detectadas'] === null): ?><span class="small">Sin cruzar</span>
            <?php else: ?>
                <span class="badge <?= $l['instalaciones_detectadas'] > $l['cantidad'] ? 'badge-err' : 'badge-activo' ?>"><?= $l['instalaciones_detectadas'] ?> equipo(s)</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if (!$l['fecha_vencimiento']): ?>—
            <?php elseif ($l['fecha_vencimiento'] < $hoy): ?><span class="badge badge-err">Vencida <?= e($l['fecha_vencimiento']) ?></span>
            <?php elseif ($l['fecha_vencimiento'] <= $en30dias): ?><span class="badge badge-otro">Vence <?= e($l['fecha_vencimiento']) ?></span>
            <?php else: ?><?= e($l['fecha_vencimiento']) ?><?php endif; ?>
        </td>
        <td>$<?= number_format($l['valor_mes'],0,',','.') ?></td>
        <td>$<?= number_format($l['valor_anual'],0,',','.') ?></td>
        <td>
            <a href="?editar=<?= (int)$l['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$licencias): ?><tr><td colspan="8" class="small">Sin licencias registradas.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
