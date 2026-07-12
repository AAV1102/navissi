<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN'])) {
    layout_inicio('Usuarios', 'Usuarios y roles', '../');
    echo '<div class="msg-error">Solo un administrador puede gestionar usuarios.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $email = trim($_POST['email'] ?? '');
        $clave = $_POST['password'] ?? '';
        $rolNuevo = $_POST['rol'] ?? 'EMPLEADO';
        if ($rolNuevo === 'SUPER_ADMIN' && !usuario_ve_todo()) {
            $msg = ['error', 'Solo un SUPER_ADMIN puede crear otra cuenta SUPER_ADMIN.'];
        } elseif (!$email || strlen($clave) < 6) {
            $msg = ['error', 'Correo válido y contraseña de al menos 6 caracteres son obligatorios.'];
        } else {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            try {
                $pdo->prepare("INSERT INTO usuarios_sistema (nombre, email, documento, password_hash, rol, sede_id, area_responsable) VALUES (?,?,?,?,?,?,?)")
                    ->execute([limpio($_POST['nombre'] ?? null), $email, limpio($_POST['documento'] ?? null),
                        password_hash($clave, PASSWORD_DEFAULT), $rolNuevo, $sedeId, limpio($_POST['area_responsable'] ?? null)]);
                $msg = ['ok', 'Usuario creado.'];
            } catch (PDOException $e) {
                $msg = ['error', 'Ya existe un usuario con ese correo.'];
            }
        }
    } elseif ($accion === 'cambiar_area') {
        if (usuario_ve_todo()) {
            $pdo->prepare("UPDATE usuarios_sistema SET area_responsable = ? WHERE id = ?")->execute([limpio($_POST['area_responsable'] ?? null), (int) $_POST['id']]);
            $msg = ['ok', 'Área actualizada.'];
        }
    } elseif ($accion === 'cambiar_estado') {
        $pdo->prepare("UPDATE usuarios_sistema SET activo = ? WHERE id = ?")->execute([(int) $_POST['activo'], (int) $_POST['id']]);
        $msg = ['ok', 'Actualizado.'];
    } elseif ($accion === 'resetear_clave') {
        $nueva = limpio($_POST['nueva_clave'] ?? null);
        if ($nueva && strlen($nueva) >= 6) {
            $pdo->prepare("UPDATE usuarios_sistema SET password_hash = ? WHERE id = ?")->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $_POST['id']]);
            $msg = ['ok', 'Contraseña actualizada.'];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM usuarios_sistema WHERE id = ? AND email != 'admin@navissi.com'")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$usuarios = $pdo->query("SELECT u.*, s.nombre AS sede_nombre FROM usuarios_sistema u LEFT JOIN sedes s ON u.sede_id = s.id ORDER BY u.rol, u.nombre")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Usuarios', 'Usuarios y roles', '../');
?>
<h1><?= icon('users','icon-lg') ?> Usuarios y Roles</h1>
<p class="subtitle">Controla quién entra al software y qué puede ver. <strong>SUPER_ADMIN</strong> ve todo sin excepción. Los demás roles, si tienen un <strong>área</strong> asignada, quedan limitados a esa área; sin área, ven todo dentro de lo que su rol permite.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Nuevo usuario</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Nombre</label><input type="text" name="nombre"></div>
            <div><label>Correo *</label><input type="email" name="email" required></div>
            <div><label>Documento (para vincular con RRHH)</label><input type="text" name="documento"></div>
            <div><label>Contraseña *</label><input type="text" name="password" required placeholder="Mínimo 6 caracteres"></div>
            <div><label>Rol</label>
                <select name="rol">
                    <?php foreach (ROLES_DISPONIBLES as $r): ?>
                    <option <?= ($r === 'SUPER_ADMIN' && !usuario_ve_todo()) ? 'disabled' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Área de alcance (opcional)</label><input type="text" name="area_responsable" placeholder="Ej. Logística, Tiendas Bogotá..."></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit">Crear usuario</button>
    </form>
</div>

<table>
    <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Área</th><th>Sede</th><th>Estado</th><th></th></tr>
    <?php foreach ($usuarios as $u): ?>
    <tr>
        <td><?= e($u['nombre']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge <?= $u['rol']==='SUPER_ADMIN'?'badge-activo':'badge-otro' ?>"><?= e($u['rol']) ?></span></td>
        <td>
            <?php if (usuario_ve_todo() && $u['rol'] !== 'SUPER_ADMIN'): ?>
            <form method="post" class="inline">
                <input type="hidden" name="accion" value="cambiar_area"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="text" name="area_responsable" value="<?= e($u['area_responsable'] ?? '') ?>" placeholder="Sin límite" style="width:120px;font-size:12px;">
                <button type="submit" style="padding:4px 8px;font-size:11px;">Guardar</button>
            </form>
            <?php else: ?>
                <?= e($u['area_responsable']) ?: '<span class="small">Sin límite</span>' ?>
            <?php endif; ?>
        </td>
        <td><?= e($u['sede_nombre']) ?></td>
        <td>
            <form method="post" class="inline">
                <input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" name="activo" value="<?= $u['activo'] ? 0 : 1 ?>" class="badge <?= $u['activo']?'badge-activo':'badge-otro' ?>" style="border:none;cursor:pointer;">
                    <?= $u['activo'] ? 'ACTIVO' : 'INACTIVO' ?>
                </button>
            </form>
        </td>
        <td>
            <a href="usuario_modulos.php?id=<?= (int)$u['id'] ?>" style="display:inline-block;margin-bottom:4px;">Módulos individuales</a><br>
            <form method="post" class="inline" onsubmit="return prompt('Nueva contraseña (mín. 6 caracteres):') !== null;" style="display:inline;">
                <input type="hidden" name="accion" value="resetear_clave"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="text" name="nueva_clave" placeholder="Nueva clave" style="width:110px;font-size:12px;">
                <button type="submit" style="padding:4px 8px;font-size:11px;">Cambiar</button>
            </form>
            <?php if ($u['email'] !== 'admin@navissi.com'): ?>
            <form method="post" class="inline" onsubmit="return confirm('¿Eliminar usuario?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 8px;font-size:11px;">Eliminar</button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php layout_fin(); ?>
