<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if (!$nombre) {
            $msg = ['error', 'El nombre es obligatorio.'];
        } else {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $datos = [
                'nombre' => $nombre, 'nit_cedula' => limpio($_POST['nit_cedula'] ?? null),
                'tipo' => limpio($_POST['tipo'] ?? null) ?: 'CLIENTE', 'telefono' => limpio($_POST['telefono'] ?? null),
                'email' => limpio($_POST['email'] ?? null), 'sede_id' => $sedeId,
                'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVO', 'observaciones' => limpio($_POST['observaciones'] ?? null),
            ];
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE clientes SET {$set} WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Actualizado.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO clientes ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Agregado.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, s.nombre AS sede_nombre FROM clientes c LEFT JOIN sedes s ON c.sede_id = s.id WHERE 1=1";
$params = [];
if ($busqueda !== '') { $sql .= " AND (c.nombre LIKE :b OR c.nit_cedula LIKE :b OR c.email LIKE :b)"; $params['b'] = "%{$busqueda}%"; }
$sql .= " ORDER BY c.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('CRM', 'CRM', '../');
?>
<h1><?= icon('users','icon-lg') ?> CRM — Clientes y Proveedores</h1>
<p class="subtitle"><?= count($clientes) ?> registros.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar' : 'Agregar' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>NIT / Cédula</label><input type="text" name="nit_cedula" value="<?= e($editar['nit_cedula'] ?? '') ?>"></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach (['CLIENTE','PROVEEDOR','ALIADO'] as $t): ?>
                    <option <?= ($editar['tipo'] ?? 'CLIENTE') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Teléfono</label><input type="text" name="telefono" value="<?= e($editar['telefono'] ?? '') ?>"></div>
            <div><label>Email</label><input type="text" name="email" value="<?= e($editar['email'] ?? '') ?>"></div>
            <div><label>Sede relacionada</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option <?= (($editar['sede_id'] ?? null)==$s['id'])?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['ACTIVO','INACTIVO'] as $es): ?>
                    <option <?= ($editar['estado'] ?? 'ACTIVO') === $es ? 'selected' : '' ?>><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;"><?= e($editar['observaciones'] ?? '') ?></textarea>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="crm.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<form class="toolbar" method="get">
    <input type="search" name="q" placeholder="Buscar nombre, NIT, correo..." value="<?= e($busqueda) ?>" style="min-width:280px">
    <button type="submit">Buscar</button>
</form>

<table>
    <tr><th>Nombre</th><th>Tipo</th><th>NIT/Cédula</th><th>Teléfono</th><th>Email</th><th>Sede</th><th>Estado</th><th></th></tr>
    <?php foreach ($clientes as $c): ?>
    <tr>
        <td><?= e($c['nombre']) ?></td>
        <td><?= e($c['tipo']) ?></td>
        <td><?= e($c['nit_cedula']) ?></td>
        <td><?= e($c['telefono']) ?></td>
        <td><?= e($c['email']) ?></td>
        <td><?= e($c['sede_nombre']) ?></td>
        <td><span class="badge <?= $c['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($c['estado']) ?></span></td>
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
    <?php if (!$clientes): ?><tr><td colspan="8" class="small">Sin registros.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
