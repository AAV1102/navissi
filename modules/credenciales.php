<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null);
        $datos = [
            'nombre' => limpio($_POST['nombre'] ?? null),
            'sede_id' => $sedeId,
            'sistema' => limpio($_POST['sistema'] ?? null) ?: 'OTRO',
            'usuario' => limpio($_POST['usuario'] ?? null),
            'contrasena' => limpio($_POST['contrasena'] ?? null),
            'categoria' => limpio($_POST['categoria'] ?? null),
            'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVO',
            'origen' => 'Manual - panel Credenciales',
        ];
        if (!$datos['usuario']) {
            $msg = ['error', 'El usuario es obligatorio.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE credenciales SET {$set} WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Credencial actualizada.'];
            } else {
                try {
                    $cols = implode(', ', array_keys($datos));
                    $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                    $pdo->prepare("INSERT INTO credenciales ({$cols}) VALUES ({$ph})")->execute($datos);
                    $msg = ['ok', 'Credencial agregada.'];
                } catch (PDOException $e) {
                    $msg = ['error', 'Ya existe esa credencial (mismo sistema+usuario+sede).'];
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM credenciales WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Credencial eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM credenciales WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$sistemaFiltro = trim($_GET['sistema'] ?? '');
$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, s.nombre AS sede_nombre FROM credenciales c LEFT JOIN sedes s ON c.sede_id = s.id WHERE 1=1";
$params = [];
if ($sistemaFiltro !== '') { $sql .= " AND c.sistema = :sis"; $params['sis'] = $sistemaFiltro; }
if ($busqueda !== '') { $sql .= " AND (c.usuario LIKE :b OR c.nombre LIKE :b OR s.nombre LIKE :b)"; $params['b'] = "%{$busqueda}%"; }
$sql .= " ORDER BY c.sistema, s.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sistemasExistentes = array_column($pdo->query("SELECT DISTINCT sistema FROM credenciales ORDER BY sistema")->fetchAll(PDO::FETCH_ASSOC), 'sistema');
$sistemasSugeridos = ['WIFI', 'CORREO', 'SIESA ERP', 'SIESA POS', 'SERVIDOR', 'DVR', 'SPOTIFY', 'GENERIC TRANSFERS', 'SIESA CUSTOM SUPPORT', 'OFFICE 365', 'VPN', 'ROUTER/MIKROTIK', 'BASE DE DATOS'];
$sistemas = array_unique(array_merge($sistemasExistentes, $sistemasSugeridos));
sort($sistemas);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Credenciales', 'Credenciales', '../');
?>
<h1><?= icon('key','icon-lg') ?> Credenciales (todas)</h1>
<p class="subtitle">Wifi, correos, DVR, servidores, Spotify y cualquier otro sistema con clave — vista completa. Para Siesa POS/ERP puedes usar también el módulo Siesa.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar credencial' : 'Agregar credencial' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre / Descripción</label><input type="text" name="nombre" value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna / general --</option>
                    <?php foreach ($sedes as $s): ?>
                    <option <?= (($editar['sede_id'] ?? null) == $s['id']) ? 'selected' : '' ?> value="<?= e($s['nombre']) ?>"><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Sistema</label><input type="text" name="sistema" list="sistemas" value="<?= e($editar['sistema'] ?? '') ?>" placeholder="WIFI, CORREO, DVR, SERVIDOR...">
                <datalist id="sistemas"><?php foreach ($sistemas as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
            </div>
            <div><label>Usuario / Red *</label><input type="text" name="usuario" required value="<?= e($editar['usuario'] ?? '') ?>"></div>
            <div><label>Contraseña</label><input type="text" name="contrasena" value="<?= e($editar['contrasena'] ?? '') ?>"></div>
            <div><label>Categoría</label><input type="text" name="categoria" value="<?= e($editar['categoria'] ?? '') ?>"></div>
        </div>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="credenciales.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="sistema">
        <option value="">-- todos los sistemas --</option>
        <?php foreach ($sistemas as $s): ?><option <?= $sistemaFiltro === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <input type="search" name="q" placeholder="Buscar usuario, nombre, sede..." value="<?= e($busqueda) ?>" style="min-width:260px">
    <button type="submit">Filtrar</button>
    <?php if ($sistemaFiltro || $busqueda): ?><a class="btn btn-secondary" href="credenciales.php">Limpiar</a><?php endif; ?>
</form>

<table>
    <tr><th>Sistema</th><th>Sede</th><th>Nombre</th><th>Usuario</th><th>Contraseña</th><th>Categoría</th><th></th></tr>
    <?php foreach ($filas as $f): ?>
    <tr>
        <td><?= e($f['sistema']) ?></td>
        <td><?= e($f['sede_nombre']) ?></td>
        <td><?= e($f['nombre']) ?></td>
        <td><?= e($f['usuario']) ?></td>
        <td><?= e($f['contrasena']) ?></td>
        <td><?= e($f['categoria']) ?></td>
        <td>
            <a href="?editar=<?= (int)$f['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php layout_fin(); ?>
