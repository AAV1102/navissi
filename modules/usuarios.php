<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/mailer.php';
$pdo = db();
$msg = null;

function generar_password_temporal(): string {
    $palabras = ['Navissi', 'Grupo10z', 'Acceso', 'Portal', 'Ingreso', 'Bienvenido'];
    return $palabras[array_rand($palabras)] . random_int(100, 999) . '!';
}

function enviar_credenciales(PDO $pdo, array $u, string $claveTemporal): bool {
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $html = plantilla_correo_html(
        'Tu cuenta de NAVISSI está lista',
        "<p>Hola " . e($u['nombre'] ?: $u['email']) . ",</p>
        <p>Se creó/actualizó tu acceso a <strong>NAVISSI Inventario</strong> con estas credenciales temporales:</p>
        <table style=\"width:100%;background:#f4f6f9;border-radius:8px;padding:4px;margin:14px 0;\">
            <tr><td style=\"padding:8px 12px;\"><strong>Correo:</strong></td><td style=\"padding:8px 12px;\">" . e($u['email']) . "</td></tr>
            <tr><td style=\"padding:8px 12px;\"><strong>Contraseña temporal:</strong></td><td style=\"padding:8px 12px;font-family:monospace;font-size:15px;\">" . e($claveTemporal) . "</td></tr>
            <tr><td style=\"padding:8px 12px;\"><strong>Perfil:</strong></td><td style=\"padding:8px 12px;\">" . e($u['rol']) . "</td></tr>
        </table>
        <p>Por seguridad, al ingresar por primera vez el sistema te pedirá crear tu propia contraseña.</p>",
        'Ingresar a NAVISSI',
        "{$base}/login.php"
    );
    return enviar_correo($u['email'], 'Tu cuenta de NAVISSI está lista', $html, $u['nombre']);
}

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
        $rolNuevo = $_POST['rol'] ?? 'EMPLEADO';
        $rolSecundario = limpio($_POST['rol_secundario'] ?? null);
        if ($rolSecundario === $rolNuevo) $rolSecundario = null;
        $generarTemporal = !empty($_POST['generar_temporal']);
        $clave = $generarTemporal ? generar_password_temporal() : ($_POST['password'] ?? '');
        if ($rolNuevo === 'SUPER_ADMIN' && !usuario_ve_todo()) {
            $msg = ['error', 'Solo un SUPER_ADMIN puede crear otra cuenta SUPER_ADMIN.'];
        } elseif (!$email || strlen($clave) < 6) {
            $msg = ['error', 'Correo válido y contraseña de al menos 6 caracteres son obligatorios.'];
        } else {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            try {
                $pdo->prepare("INSERT INTO usuarios_sistema (nombre, email, documento, password_hash, rol, rol_secundario, sede_id, area_responsable, password_temporal) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([limpio($_POST['nombre'] ?? null), $email, limpio($_POST['documento'] ?? null),
                        password_hash($clave, PASSWORD_DEFAULT), $rolNuevo, $rolSecundario, $sedeId, limpio($_POST['area_responsable'] ?? null),
                        $generarTemporal ? 1 : 0]);
                $msg = ['ok', 'Usuario creado.'];
                if ($generarTemporal) {
                    $nuevoId = $pdo->lastInsertId();
                    $creado = ['nombre' => limpio($_POST['nombre'] ?? null), 'email' => $email, 'rol' => $rolNuevo];
                    if (enviar_credenciales($pdo, $creado, $clave) !== false) {
                        $msg = ['ok', "Usuario creado. Contraseña temporal enviada a {$email}."];
                    }
                }
            } catch (PDOException $e) {
                $msg = ['error', 'Ya existe un usuario con ese correo.'];
            }
        }
    } elseif ($accion === 'cambiar_area') {
        if (usuario_ve_todo()) {
            $pdo->prepare("UPDATE usuarios_sistema SET area_responsable = ? WHERE id = ?")->execute([limpio($_POST['area_responsable'] ?? null), (int) $_POST['id']]);
            $msg = ['ok', 'Área actualizada.'];
        }
    } elseif ($accion === 'cambiar_email') {
        $nuevoEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
        $idObjetivo = (int) ($_POST['id'] ?? 0);
        if (!usuario_ve_todo() || !filter_var($nuevoEmail, FILTER_VALIDATE_EMAIL)) {
            $msg = ['error', 'Correo no válido o permisos insuficientes.'];
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE usuarios_sistema SET email = ? WHERE id = ? AND activo = 1');
                $stmt->execute([$nuevoEmail, $idObjetivo]);
                $msg = ['ok', 'Correo actualizado. La próxima entrada usará el nuevo correo.'];
            } catch (PDOException $e) {
                $msg = ['error', 'Ese correo ya está registrado.'];
            }
        }
    } elseif ($accion === 'cambiar_rol_secundario') {
        $rolSec = limpio($_POST['rol_secundario'] ?? null);
        $pdo->prepare("UPDATE usuarios_sistema SET rol_secundario = ? WHERE id = ?")->execute([$rolSec ?: null, (int) $_POST['id']]);
        $msg = ['ok', 'Perfil adicional actualizado.'];
    } elseif ($accion === 'cambiar_estado') {
        $pdo->prepare("UPDATE usuarios_sistema SET activo = ? WHERE id = ?")->execute([(int) $_POST['activo'], (int) $_POST['id']]);
        $msg = ['ok', 'Actualizado.'];
    } elseif ($accion === 'resetear_clave') {
        $nueva = limpio($_POST['nueva_clave'] ?? null);
        if ($nueva && strlen($nueva) >= 6) {
            $pdo->prepare("UPDATE usuarios_sistema SET password_hash = ? WHERE id = ?")->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $_POST['id']]);
            $msg = ['ok', 'Contraseña actualizada.'];
        }
    } elseif ($accion === 'enviar_temporal') {
        $stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE id = ?");
        $stmt->execute([(int) $_POST['id']]);
        $objetivo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($objetivo) {
            $claveTemporal = generar_password_temporal();
            $pdo->prepare("UPDATE usuarios_sistema SET password_hash = ?, password_temporal = 1 WHERE id = ?")
                ->execute([password_hash($claveTemporal, PASSWORD_DEFAULT), $objetivo['id']]);
            enviar_credenciales($pdo, $objetivo, $claveTemporal);
            $msg = ['ok', "Contraseña temporal generada y enviada a {$objetivo['email']}."];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM usuarios_sistema WHERE id = ? AND email != 'admin@navissi.com'")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    } elseif ($accion === 'sincronizar_areas' && usuario_ve_todo()) {
        // Rellena el área de los usuarios que tienen documento (vinculados a un
        // empleado real de RRHH) pero se quedaron sin área asignada - toma el
        // área real del empleado en vez de dejarlo "sin límite" por descuido.
        $stmt = $pdo->query("SELECT u.id, e.area FROM usuarios_sistema u
            JOIN empleados e ON e.documento = u.documento
            WHERE (u.area_responsable IS NULL OR u.area_responsable = '') AND e.area IS NOT NULL AND e.area != '' AND u.rol != 'SUPER_ADMIN'");
        $actualizados = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $pdo->prepare("UPDATE usuarios_sistema SET area_responsable = ? WHERE id = ?")->execute([$fila['area'], $fila['id']]);
            $actualizados++;
        }
        $msg = ['ok', "{$actualizados} usuario(s) actualizados con el área de su registro de RRHH."];
    }
}

$departamentosDisponibles = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$sinAreaConDocumento = (int) $pdo->query("SELECT COUNT(*) FROM usuarios_sistema u
    JOIN empleados e ON e.documento = u.documento
    WHERE (u.area_responsable IS NULL OR u.area_responsable = '') AND e.area IS NOT NULL AND e.area != '' AND u.rol != 'SUPER_ADMIN'")->fetchColumn();

$usuarios = $pdo->query("SELECT u.*, s.nombre AS sede_nombre FROM usuarios_sistema u LEFT JOIN sedes s ON u.sede_id = s.id ORDER BY u.rol, u.nombre")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Usuarios', 'Usuarios y roles', '../');
?>
<h1><?= icon('users','icon-lg') ?> Usuarios y Roles</h1>
<p class="subtitle">Controla quién entra al software y qué puede ver. <strong>SUPER_ADMIN, DIRECTOR, GERENCIA y CEO</strong> ven todo sin excepción. Los demás roles, si tienen un <strong>área</strong> asignada, quedan limitados a esa área; sin área, ven todo dentro de lo que su rol permite.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>
<?php if ($sinAreaConDocumento > 0 && usuario_ve_todo()): ?>
<div class="msg-error" style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">
    <span><?= icon('bell') ?> <?= $sinAreaConDocumento ?> usuario(s) vinculados a un empleado de RRHH no tienen área asignada — quedan viendo más de lo que deberían.</span>
    <form method="post"><input type="hidden" name="accion" value="sincronizar_areas"><button type="submit"><?= icon('check') ?> Sincronizar desde RRHH</button></form>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Nuevo usuario</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Nombre</label><input type="text" name="nombre"></div>
            <div><label>Correo *</label><input type="email" name="email" required></div>
            <div><label>Documento (para vincular con RRHH)</label><input type="text" name="documento"></div>
            <div><label>Contraseña * <span class="small">(se ignora si generas una temporal abajo)</span></label><input type="text" name="password" placeholder="Mínimo 6 caracteres"></div>
            <div><label>Rol principal</label>
                <select name="rol">
                    <?php foreach (ROLES_DISPONIBLES as $r): ?>
                    <option <?= ($r === 'SUPER_ADMIN' && !usuario_ve_todo()) ? 'disabled' : '' ?> <?= $r === 'EMPLEADO' ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Perfil adicional (opcional)</label>
                <select name="rol_secundario">
                    <option value="">-- ninguno --</option>
                    <?php foreach (ROLES_DISPONIBLES as $r): if ($r === 'SUPER_ADMIN') continue; ?>
                    <option><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="small">Ej: un usuario con rol principal ADMIN puede además tener el perfil EMPLEADO, y ve los módulos de ambos.</p>
            </div>
            <div><label>Área de alcance (opcional)</label>
                <select name="area_responsable">
                    <option value="">-- sin límite (ve todo lo que su rol permite) --</option>
                    <?php foreach ($departamentosDisponibles as $dep): ?><option><?= e($dep) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <label class="small" style="display:flex;align-items:center;gap:8px;margin:6px 0 14px;">
            <input type="checkbox" name="generar_temporal" value="1" checked>
            Generar contraseña temporal y enviarla por correo (el usuario deberá cambiarla al ingresar)
        </label>
        <button type="submit"><?= icon('plus') ?> Crear usuario</button>
    </form>
</div>

<table>
    <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Perfil adicional</th><th>Área</th><th>Sede</th><th>Estado</th><th></th></tr>
    <?php foreach ($usuarios as $u): ?>
    <tr>
        <td><?= e($u['nombre']) ?></td>
        <td>
            <?= e($u['email']) ?><br>
            <?php if (usuario_ve_todo()): ?>
            <form method="post" class="inline" style="margin-top:4px;">
                <input type="hidden" name="accion" value="cambiar_email"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="email" name="email" value="<?= e($u['email']) ?>" required style="width:190px;font-size:12px;">
                <button type="submit" style="padding:4px 8px;font-size:11px;">Guardar correo</button>
            </form>
            <?php endif; ?>
        </td>
        <td><span class="badge <?= $u['rol']==='SUPER_ADMIN'?'badge-activo':'badge-otro' ?>"><?= e($u['rol']) ?></span> <?php if (!empty($u['password_temporal'])): ?><span class="badge badge-warn" title="El usuario todavía no ha creado su contraseña definitiva">temporal</span><?php endif; ?></td>
        <td>
            <form method="post" class="inline">
                <input type="hidden" name="accion" value="cambiar_rol_secundario"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <select name="rol_secundario" onchange="this.form.requestSubmit()" style="font-size:12px;">
                    <option value="">-- ninguno --</option>
                    <?php foreach (ROLES_DISPONIBLES as $r): if ($r === 'SUPER_ADMIN' || $r === $u['rol']) continue; ?>
                    <option <?= ($u['rol_secundario'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </td>
        <td>
            <?php if (usuario_ve_todo() && $u['rol'] !== 'SUPER_ADMIN'): ?>
            <form method="post" class="inline">
                <input type="hidden" name="accion" value="cambiar_area"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <select name="area_responsable" onchange="this.form.requestSubmit()" style="font-size:12px;">
                    <option value="">-- sin límite --</option>
                    <?php foreach ($departamentosDisponibles as $dep): ?><option <?= ($u['area_responsable'] ?? '') === $dep ? 'selected' : '' ?>><?= e($dep) ?></option><?php endforeach; ?>
                </select>
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
            <form method="post" class="inline" onsubmit="return confirm('¿Generar una contraseña temporal nueva y enviarla a ' + '<?= e($u['email']) ?>' + '?');" style="display:inline;">
                <input type="hidden" name="accion" value="enviar_temporal"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" style="padding:4px 8px;font-size:11px;"><?= icon('send') ?> Enviar clave temporal</button>
            </form><br>
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
