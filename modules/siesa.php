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
            'sistema' => limpio($_POST['sistema'] ?? null) ?: 'SIESA POS',
            'usuario' => limpio($_POST['usuario'] ?? null),
            'contrasena' => limpio($_POST['contrasena'] ?? null),
            'categoria' => limpio($_POST['categoria'] ?? null),
            'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVO',
            'origen' => 'Manual - panel Siesa',
            'usuario_id' => (int) ($_POST['usuario_id'] ?? 0) ?: null,
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

$stmt = $pdo->prepare("
    SELECT c.*, s.nombre AS sede_nombre FROM credenciales c
    LEFT JOIN sedes s ON c.sede_id = s.id
    WHERE c.sistema LIKE 'SIESA%'
    ORDER BY s.nombre, c.sistema
");
$stmt->execute();
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$usuariosSistema = $pdo->query("SELECT id, nombre, email FROM usuarios_sistema WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Siesa', 'Siesa', '../');
?>
<h1><?= icon('key','icon-lg') ?> Usuarios Siesa (POS / ERP)</h1>
<p class="subtitle"><?= count($filas) ?> credenciales de Siesa por sede.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar credencial' : 'Agregar credencial Siesa' ?></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre (responsable)</label><input type="text" name="nombre" value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- seleccionar --</option>
                    <?php foreach ($sedes as $s): ?>
                    <option <?= (($editar['sede_id'] ?? null) == $s['id']) ? 'selected' : '' ?> value="<?= e($s['nombre']) ?>"><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Sistema</label>
                <select name="sistema">
                    <?php foreach (['SIESA POS','SIESA ERP'] as $sis): ?>
                    <option <?= ($editar['sistema'] ?? 'SIESA POS') === $sis ? 'selected' : '' ?>><?= $sis ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Usuario *</label><input type="text" name="usuario" required value="<?= e($editar['usuario'] ?? '') ?>"></div>
            <div><label>Contraseña</label><input type="text" name="contrasena" value="<?= e($editar['contrasena'] ?? '') ?>"></div>
            <div><label>Área / Cargo</label><input type="text" name="categoria" value="<?= e($editar['categoria'] ?? '') ?>"></div>
            <div><label>Vincular a usuario (para "Mis Accesos")</label>
                <select name="usuario_id">
                    <option value="">-- sin vincular --</option>
                    <?php foreach ($usuariosSistema as $us): ?>
                    <option value="<?= (int)$us['id'] ?>" <?= (($editar['usuario_id'] ?? null) == $us['id']) ? 'selected' : '' ?>><?= e($us['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="siesa.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Sede</th><th>Sistema</th><th>Usuario</th><th>Contraseña</th><th>Responsable / Área</th><th></th></tr>
    <?php foreach ($filas as $f): ?>
    <tr>
        <td><?= e($f['sede_nombre']) ?></td>
        <td><?= e($f['sistema']) ?></td>
        <td><?= e($f['usuario']) ?></td>
        <td><?= e($f['contrasena']) ?></td>
        <td><?= e($f['nombre']) ?> <?= e($f['categoria']) ?></td>
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
