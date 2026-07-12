<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$tipos = ['RENTING' => 'Renting de equipos', 'MANTENIMIENTO' => 'Mantenimiento', 'SOPORTE' => 'Soporte técnico', 'LICENCIA' => 'Licenciamiento', 'SEGURO' => 'Seguro / Póliza', 'INTERNET' => 'Internet / Telecom', 'OTRO' => 'Otro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $proveedor = limpio($_POST['proveedor_nombre'] ?? null);
        if (!$proveedor) {
            $msg = ['error', 'El proveedor es obligatorio.'];
        } else {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $datos = [
                'proveedor_nombre' => $proveedor, 'tipo' => limpio($_POST['tipo'] ?? null) ?: 'OTRO',
                'numero_contrato' => limpio($_POST['numero_contrato'] ?? null), 'descripcion' => limpio($_POST['descripcion'] ?? null),
                'fecha_inicio' => limpio($_POST['fecha_inicio'] ?? null), 'fecha_fin' => limpio($_POST['fecha_fin'] ?? null),
                'valor' => $_POST['valor'] !== '' ? (float) $_POST['valor'] : null,
                'periodicidad_pago' => limpio($_POST['periodicidad_pago'] ?? null), 'responsable' => limpio($_POST['responsable'] ?? null),
                'sede_id' => $sedeId, 'estado' => limpio($_POST['estado'] ?? null) ?: 'VIGENTE',
                'observaciones' => limpio($_POST['observaciones'] ?? null),
            ];
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE contratos SET {$set} WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Contrato actualizado.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO contratos ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Contrato registrado.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM contratos WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, s.nombre AS sede_nombre FROM contratos c LEFT JOIN sedes s ON c.sede_id = s.id WHERE 1=1";
$params = [];
if ($busqueda !== '') { $sql .= " AND (c.proveedor_nombre LIKE :b OR c.numero_contrato LIKE :b OR c.descripcion LIKE :b)"; $params['b'] = "%{$busqueda}%"; }
$sql .= " ORDER BY c.fecha_fin IS NULL, c.fecha_fin ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$hoy = new DateTime();
$proximosVencer = 0;
foreach ($contratos as $c) {
    if ($c['estado'] === 'VIGENTE' && $c['fecha_fin']) {
        $dias = (int) $hoy->diff(new DateTime($c['fecha_fin']))->format('%r%a');
        if ($dias >= 0 && $dias <= 30) $proximosVencer++;
    }
}

layout_inicio('Contratos y Proveedores', 'Contratos', '../');
?>
<h1><?= icon('file','icon-lg') ?> Contratos y Proveedores</h1>
<p class="subtitle">Renting, mantenimiento, soporte, licenciamiento, seguros e internet — con alerta de vencimiento.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="stat-cards" style="margin-bottom:18px;">
    <div class="stat-card"><div class="stat-num"><?= count($contratos) ?></div><div class="stat-label">Contratos registrados</div></div>
    <div class="stat-card"><div class="stat-num" style="color:<?= $proximosVencer ? 'var(--warn-fg)' : 'inherit' ?>"><?= $proximosVencer ?></div><div class="stat-label">Vencen en los próximos 30 días</div></div>
</div>

<div class="panel">
    <h3><?= $editar ? 'Editar contrato' : 'Nuevo contrato' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Proveedor *</label><input type="text" name="proveedor_nombre" required value="<?= e($editar['proveedor_nombre'] ?? '') ?>"></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach ($tipos as $val => $label): ?><option value="<?= $val ?>" <?= ($editar['tipo'] ?? '')===$val?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Número de contrato</label><input type="text" name="numero_contrato" value="<?= e($editar['numero_contrato'] ?? '') ?>"></div>
            <div><label>Fecha inicio</label><input type="date" name="fecha_inicio" value="<?= e($editar['fecha_inicio'] ?? '') ?>"></div>
            <div><label>Fecha fin</label><input type="date" name="fecha_fin" value="<?= e($editar['fecha_fin'] ?? '') ?>"></div>
            <div><label>Valor</label><input type="number" step="0.01" name="valor" value="<?= e($editar['valor'] ?? '') ?>"></div>
            <div><label>Periodicidad de pago</label>
                <select name="periodicidad_pago">
                    <?php foreach (['MENSUAL','TRIMESTRAL','SEMESTRAL','ANUAL','UNICO'] as $p): ?>
                    <option <?= ($editar['periodicidad_pago'] ?? '')===$p?'selected':'' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Responsable</label><input type="text" name="responsable" value="<?= e($editar['responsable'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option <?= (($editar['sede_id'] ?? null)==$s['id'])?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['VIGENTE','VENCIDO','CANCELADO','EN_RENOVACION'] as $es): ?>
                    <option <?= ($editar['estado'] ?? 'VIGENTE')===$es?'selected':'' ?>><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <label>Descripción</label>
        <textarea name="descripcion" rows="2" style="width:100%;"><?= e($editar['descripcion'] ?? '') ?></textarea>
        <label>Observaciones</label>
        <textarea name="observaciones" rows="2" style="width:100%;"><?= e($editar['observaciones'] ?? '') ?></textarea>
        <button type="submit" style="margin-top:10px;"><?= $editar ? 'Guardar cambios' : 'Registrar contrato' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="contratos.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<form class="toolbar" method="get">
    <input type="search" name="q" placeholder="Buscar proveedor, número, descripción..." value="<?= e($busqueda) ?>" style="min-width:280px">
    <button type="submit"><?= icon('search') ?> Buscar</button>
</form>

<table>
    <tr><th>Proveedor</th><th>Tipo</th><th>N° contrato</th><th>Vigencia</th><th>Valor</th><th>Responsable</th><th>Estado</th><th></th></tr>
    <?php foreach ($contratos as $c):
        $dias = $c['fecha_fin'] ? (int) $hoy->diff(new DateTime($c['fecha_fin']))->format('%r%a') : null;
        $porVencer = $c['estado'] === 'VIGENTE' && $dias !== null && $dias >= 0 && $dias <= 30;
    ?>
    <tr>
        <td><?= e($c['proveedor_nombre']) ?></td>
        <td><?= e($tipos[$c['tipo']] ?? $c['tipo']) ?></td>
        <td><?= e($c['numero_contrato']) ?></td>
        <td><?= e($c['fecha_inicio']) ?> → <?= e($c['fecha_fin']) ?: '—' ?>
            <?php if ($porVencer): ?><br><span class="badge badge-warn"><?= icon('bell') ?> Vence en <?= $dias ?> días</span><?php endif; ?>
        </td>
        <td><?= $c['valor'] ? '$' . number_format((float)$c['valor'],0,',','.') : '—' ?></td>
        <td><?= e($c['responsable']) ?></td>
        <td><span class="badge <?= $c['estado']==='VIGENTE'?'badge-activo':($c['estado']==='VENCIDO'?'badge-err':'badge-otro') ?>"><?= e($c['estado']) ?></span></td>
        <td>
            <a href="?editar=<?= (int)$c['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$contratos): ?><tr><td colspan="8" class="small">Sin contratos registrados.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
