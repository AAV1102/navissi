<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/totp.php';
require_once __DIR__ . '/../lib/mailer.php';
$pdo = db();
$msg = null;
$u = usuario_actual();

$stmt = $pdo->prepare("SELECT * FROM usuarios_sistema WHERE id = ?");
$stmt->execute([$u['id']]);
$cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'actualizar_perfil') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if (!$nombre) {
            $msg = ['error', 'El nombre no puede quedar vacío.'];
        } else {
            $pdo->prepare("UPDATE usuarios_sistema SET nombre = ?, documento = ? WHERE id = ?")
                ->execute([$nombre, limpio($_POST['documento'] ?? null), $u['id']]);
            $_SESSION['usuario']['nombre'] = $nombre;
            $msg = ['ok', 'Perfil actualizado.'];
            $stmt->execute([$u['id']]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } elseif ($accion === 'iniciar') {
        $secreto = totp_generar_secreto();
        $_SESSION['totp_secreto_pendiente'] = $secreto;
    } elseif ($accion === 'confirmar') {
        $secreto = $_SESSION['totp_secreto_pendiente'] ?? '';
        if ($secreto && totp_verificar($secreto, $_POST['codigo'] ?? '')) {
            $pdo->prepare("UPDATE usuarios_sistema SET totp_secreto = ?, totp_habilitado = 1 WHERE id = ?")->execute([$secreto, $u['id']]);
            unset($_SESSION['totp_secreto_pendiente']);
            hoja_vida_registrar($pdo, 'EMPLEADO', $cuenta['documento'] ?? (string)$u['id'], '2FA_ACTIVADO', 'Verificación en dos pasos activada', $u['nombre']);
            $msg = ['ok', 'Verificación en dos pasos activada correctamente.'];
            $stmt->execute([$u['id']]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $msg = ['error', 'El código ingresado no es válido. Intenta de nuevo.'];
        }
    } elseif ($accion === 'desactivar') {
        $pdo->prepare("UPDATE usuarios_sistema SET totp_secreto = NULL, totp_habilitado = 0 WHERE id = ?")->execute([$u['id']]);
        hoja_vida_registrar($pdo, 'EMPLEADO', $cuenta['documento'] ?? (string)$u['id'], '2FA_DESACTIVADO', 'Verificación en dos pasos desactivada', $u['nombre']);
        $msg = ['ok', 'Verificación en dos pasos desactivada.'];
        $stmt->execute([$u['id']]);
        $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($accion === 'revocar_dispositivo') {
        $pdo->prepare("DELETE FROM dispositivos_confianza WHERE id = ? AND usuario_id = ?")->execute([(int) $_POST['id'], $u['id']]);
        $msg = ['ok', 'Dispositivo revocado. La próxima vez que entres desde ahí, se pedirá el código otra vez.'];
    } elseif ($accion === 'cambiar_clave') {
        $actual = $_POST['clave_actual'] ?? '';
        $nueva = $_POST['clave_nueva'] ?? '';
        $confirmar = $_POST['clave_confirmar'] ?? '';
        if (!password_verify($actual, $cuenta['password_hash'])) {
            $msg = ['error', 'La contraseña actual no es correcta.'];
        } elseif (strlen($nueva) < 8) {
            $msg = ['error', 'La nueva contraseña debe tener al menos 8 caracteres.'];
        } elseif ($nueva !== $confirmar) {
            $msg = ['error', 'La confirmación no coincide con la nueva contraseña.'];
        } else {
            $pdo->prepare("UPDATE usuarios_sistema SET password_hash = ? WHERE id = ?")->execute([password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
            hoja_vida_registrar($pdo, 'EMPLEADO', $cuenta['documento'] ?? (string)$u['id'], 'CONTRASENA_CAMBIADA', 'Cambio de contraseña por el propio usuario', $u['nombre']);
            $msg = ['ok', 'Contraseña actualizada correctamente.'];
        }
    } elseif ($accion === 'enviar_nueva_clave') {
        // No se puede "mostrar" la contraseña actual porque solo se guarda su
        // hash (irreversible) - lo que sí se puede hacer, sin necesidad de
        // conocer la clave vieja, es generar una nueva y enviarla al correo
        // ya registrado del propio usuario (sesión ya autenticada).
        $simbolos = '!@#$%*';
        $nuevaClave = 'Navissi' . random_int(1000, 9999) . $simbolos[random_int(0, strlen($simbolos) - 1)];
        $pdo->prepare("UPDATE usuarios_sistema SET password_hash = ?, password_temporal = 1 WHERE id = ?")
            ->execute([password_hash($nuevaClave, PASSWORD_DEFAULT), $u['id']]);
        $cuerpo = "<p>Hola " . e($cuenta['nombre']) . ",</p>"
            . "<p>Generaste una nueva contraseña desde <strong>Mi Cuenta</strong> porque no recordabas la actual.</p>"
            . "<p style='font-size:20px;font-family:monospace;background:#f4f6f9;padding:12px 16px;border-radius:8px;text-align:center;'>" . e($nuevaClave) . "</p>"
            . "<p>Te pedirá cambiarla la próxima vez que inicies sesión. Si no fuiste tú quien la solicitó, avisa a Sistemas de inmediato.</p>";
        $enviado = enviar_correo($cuenta['email'], 'Tu nueva contraseña de NAVISSI', plantilla_correo_html('Nueva contraseña generada', $cuerpo), $cuenta['nombre']);
        hoja_vida_registrar($pdo, 'EMPLEADO', $cuenta['documento'] ?? (string)$u['id'], 'CONTRASENA_AUTOGENERADA', 'El propio usuario solicitó una nueva contraseña por olvido', $u['nombre']);
        $msg = $enviado
            ? ['ok', 'Te enviamos una nueva contraseña a ' . $cuenta['email'] . '. Úsala para entrar y el sistema te pedirá cambiarla.']
            : ['error', 'No se pudo enviar el correo. Avisa a Sistemas para que te la generen manualmente.'];
    }
}

$dispositivosConfianza = $pdo->prepare("SELECT * FROM dispositivos_confianza WHERE usuario_id = ? ORDER BY ultima_vez DESC");
$dispositivosConfianza->execute([$u['id']]);
$dispositivosConfianza = $dispositivosConfianza->fetchAll(PDO::FETCH_ASSOC);

$secretoPendiente = $_SESSION['totp_secreto_pendiente'] ?? null;
$otpauthUri = $secretoPendiente ? totp_uri_otpauth($secretoPendiente, $cuenta['email']) : null;

layout_inicio('Mi Cuenta', 'Mi Cuenta', '../');
?>
<h1><?= icon('users','icon-lg') ?> Mi Cuenta</h1>
<p class="subtitle"><?= e($cuenta['nombre']) ?> · <?= e($cuenta['email']) ?> · Rol <?= e($cuenta['rol']) ?><?= $cuenta['area_responsable'] ? ' (área: ' . e($cuenta['area_responsable']) . ')' : '' ?></p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('users') ?> Mi perfil</h3>
    <form method="post">
        <input type="hidden" name="accion" value="actualizar_perfil">
        <div class="grid-form">
            <div><label>Nombre</label><input type="text" name="nombre" value="<?= e($cuenta['nombre']) ?>" required></div>
            <div><label>Documento</label><input type="text" name="documento" value="<?= e($cuenta['documento'] ?? '') ?>"></div>
            <div><label>Correo</label><input type="text" value="<?= e($cuenta['email']) ?>" disabled title="El correo no se puede cambiar aquí"></div>
        </div>
        <button type="submit"><?= icon('check') ?> Guardar perfil</button>
    </form>
</div>

<div class="panel">
    <h3><?= icon('key') ?> Cambiar mi contraseña</h3>
    <form method="post">
        <input type="hidden" name="accion" value="cambiar_clave">
        <div class="grid-form">
            <div><label>Contraseña actual</label><input type="password" name="clave_actual" required></div>
            <div><label>Nueva contraseña</label><input type="password" name="clave_nueva" required minlength="8"></div>
            <div><label>Confirmar nueva contraseña</label><input type="password" name="clave_confirmar" required minlength="8"></div>
        </div>
        <button type="submit"><?= icon('check') ?> Actualizar contraseña</button>
    </form>
    <hr style="margin:16px 0;border:none;border-top:1px solid var(--line);">
    <p class="small">¿No recuerdas tu contraseña actual? No es posible mostrarla (nunca se guarda en texto plano), pero podemos generarte una nueva y enviártela a tu correo registrado.</p>
    <form method="post" onsubmit="return confirm('Se generará una contraseña nueva y se enviará a ' + '<?= e($cuenta['email']) ?>' + '. La actual dejará de funcionar. ¿Continuar?');">
        <input type="hidden" name="accion" value="enviar_nueva_clave">
        <button type="submit" class="btn btn-secondary"><?= icon('send') ?> Olvidé mi contraseña - enviarme una nueva</button>
    </form>
</div>

<div class="panel">
<?php if (!empty($cuenta['totp_habilitado'])): ?>
    <p><span class="badge badge-activo"><?= icon('check') ?> Activada</span></p>
    <p class="small">Tu cuenta ya está protegida con verificación en dos pasos. Si cambiaste de teléfono o perdiste acceso a la app, desactívala y vuelve a configurarla.</p>
    <form method="post" onsubmit="return confirm('¿Desactivar la verificación en dos pasos?');">
        <input type="hidden" name="accion" value="desactivar">
        <button type="submit" class="btn-danger"><?= icon('x') ?> Desactivar 2FA</button>
    </form>
<?php elseif ($secretoPendiente): ?>
    <h3>Paso 1: agrega la cuenta en tu app Authenticator</h3>
    <p class="small">Abre Microsoft Authenticator (o Google Authenticator) → Agregar cuenta → Otra cuenta → y escanea este código QR:</p>
    <div style="text-align:center;margin:16px 0;">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode($otpauthUri) ?>" alt="Código QR para Authenticator" width="220" height="220" style="border-radius:8px;border:1px solid var(--line);background:#fff;padding:8px;">
    </div>
    <p class="small">¿No puedes escanear? Ingresa esta clave manualmente en la app:</p>
    <p style="font-size:20px;letter-spacing:2px;font-family:monospace;background:#f3f6fa;padding:12px;border-radius:8px;text-align:center;"><?= e($secretoPendiente) ?></p>
    <p class="small">Cuenta: <?= e($cuenta['email']) ?> · Emisor: NAVISSI Inventario</p>
    <h3 style="margin-top:20px;">Paso 2: confirma con el código de 6 dígitos</h3>
    <form method="post">
        <input type="hidden" name="accion" value="confirmar">
        <input type="text" name="codigo" required inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" style="font-size:20px;letter-spacing:4px;text-align:center;max-width:200px;">
        <button type="submit"><?= icon('check') ?> Confirmar y activar</button>
    </form>
<?php else: ?>
    <p><span class="badge badge-otro">Desactivada</span></p>
    <p class="small">Actualmente tu cuenta solo usa contraseña. Activa la verificación en dos pasos para mayor seguridad.</p>
    <form method="post">
        <input type="hidden" name="accion" value="iniciar">
        <button type="submit"><?= icon('shield') ?> Activar verificación en dos pasos</button>
    </form>
<?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('shield') ?> Dispositivos de confianza (<?= count($dispositivosConfianza) ?>)</h3>
    <p class="small">Equipos donde marcaste "Confiar en este dispositivo" al verificar el código — no te piden 2FA de nuevo ahí por 30 días. Revócalos si perdiste o cambiaste un equipo.</p>
    <table>
        <tr><th>Dispositivo</th><th>Registrado</th><th>Último uso</th><th>Vence</th><th></th></tr>
        <?php foreach ($dispositivosConfianza as $d): ?>
        <tr>
            <td class="small"><?= e($d['nombre_dispositivo']) ?: 'Desconocido' ?></td>
            <td class="small"><?= e($d['creado_en']) ?></td>
            <td class="small"><?= e($d['ultima_vez']) ?></td>
            <td class="small"><?= e($d['expira_en']) ?></td>
            <td>
                <form method="post" class="inline" onsubmit="return confirm('¿Revocar este dispositivo? Volverá a pedir el código 2FA ahí.');">
                    <input type="hidden" name="accion" value="revocar_dispositivo"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Revocar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$dispositivosConfianza): ?><tr><td colspan="5" class="small">Ningún dispositivo marcado como confiable todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
