<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/correo_a_tickets.php';
$pdo = db();
$msg = null;
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar_buzones') {
        guardar_buzones_mesa_ayuda($pdo, limpio($_POST['buzones'] ?? null));
        $msg = ['ok', 'Buzones actualizados.'];
    }
    if ($accion === 'guardar_imap') {
        $anterior = config_imap($pdo) ?: [];
        $claveNueva = $_POST['imap_password'] ?? '';
        guardar_config_imap($pdo, [
            'host' => limpio($_POST['imap_host'] ?? null),
            'puerto' => (int) ($_POST['imap_puerto'] ?? 993),
            'usuario' => limpio($_POST['imap_usuario'] ?? null),
            'password' => $claveNueva !== '' ? $claveNueva : ($anterior['password'] ?? ''),
        ]);
        $msg = ['ok', 'Configuración IMAP guardada.'];
    }
    if ($accion === 'sincronizar') {
        $resultado = sincronizar_correo_a_tickets($pdo);
        $msg = $resultado['errores'] ? ['error', 'Sincronizado con errores - revisa el detalle abajo.'] : ['ok', 'Sincronización completada.'];
    }
}

$buzonesActuales = implode(', ', obtener_buzones($pdo));
$cfgImapActual = config_imap($pdo) ?: ['host' => 'panel.freehosting.com', 'puerto' => 993, 'usuario' => 'mesadeayuda@grupo10z.com.co', 'password' => ''];
$historial = $pdo->query("SELECT ct.*, t.titulo, t.estado FROM correos_a_tickets ct LEFT JOIN tickets t ON ct.ticket_id = t.id ORDER BY ct.id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

layout_inicio('Correo a Tickets', 'Correo → Tickets', '../');
?>
<h1><?= icon('ticket','icon-lg') ?> Correo → Tickets automáticos</h1>
<p class="subtitle">Los correos que lleguen a los buzones configurados se convierten en tickets de Mesa de Ayuda, con triage automático de IA. El correo NO se borra ni se mueve del buzón principal - solo se marca como leído.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>
<?php if ($resultado): ?>
<div class="msg-ok">
    Tickets creados: <?= $resultado['creados'] ?> — ya existían: <?= $resultado['ya_existian'] ?>
    <?php foreach ($resultado['errores'] as $err): ?><br><span style="color:#a12b1f;"><?= e($err) ?></span><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h3><?= icon('cloud') ?> Buzones de Microsoft 365 (mesadeayuda@navissi.com)</h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="guardar_buzones">
        <input type="text" name="buzones" value="<?= e($buzonesActuales) ?>" style="min-width:400px;" placeholder="mesadeayuda@navissi.com, sistemas@navissi.com">
        <button type="submit">Guardar</button>
    </form>
    <p class="small" style="margin-top:10px;">
        Falta un paso en Azure AD para que esto funcione: el permiso de aplicación <code>Mail.Read</code> concedido a esta misma app (Azure AD → tu app → Permisos de API → Microsoft Graph → Aplicación → <code>Mail.Read</code> → conceder consentimiento de administrador). Sin ese permiso, la sincronización de este buzón dará error 403.
    </p>
</div>

<div class="panel">
    <h3><?= icon('key') ?> Buzón de FreeHosting (mesadeayuda@grupo10z.com.co)</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="guardar_imap">
        <div><label>Servidor IMAP</label><input type="text" name="imap_host" value="<?= e($cfgImapActual['host']) ?>"></div>
        <div><label>Puerto (SSL)</label><input type="number" name="imap_puerto" value="<?= (int) $cfgImapActual['puerto'] ?>"></div>
        <div><label>Correo</label><input type="text" name="imap_usuario" value="<?= e($cfgImapActual['usuario']) ?>"></div>
        <div><label>Contraseña</label><input type="password" name="imap_password" placeholder="<?= $cfgImapActual['password'] ? '••••••••' : 'Contraseña del buzón' ?>"></div>
        <div style="align-self:end;"><button type="submit">Guardar</button></div>
    </form>
    <p class="small">Este buzón vive en el hosting de correo de FreeHosting (no en Microsoft 365), así que se lee directo por IMAP - no necesita ningún permiso adicional. La contraseña queda vacía a menos que la escribas de nuevo (por seguridad no se muestra la guardada).</p>
</div>

<div class="panel" style="border-left:4px solid var(--teal-500);">
    <h3><?= icon('zap') ?> Revisar ahora / automatizar</h3>
    <form method="post" style="display:inline;">
        <input type="hidden" name="accion" value="sincronizar">
        <button type="submit">📥 Revisar correos ahora</button>
    </form>
    <p class="small" style="margin-top:12px;">La automatización llama cada 10 minutos a este endpoint mediante HTTPS y envía el secreto en la cabecera <code>X-Navissi-Correo-Token</code>; el token nunca debe incluirse en la URL.</p>
    <pre style="background:#0f1720;color:#d7e3ef;padding:12px;border-radius:8px;overflow-x:auto;font-size:12.5px;"><?= e($base) ?>/api_correo_mesadeayuda.php</pre>
</div>

<div class="panel">
    <h3>Últimos correos convertidos (<?= count($historial) ?>)</h3>
    <table>
        <tr><th>Buzón</th><th>Remitente</th><th>Asunto</th><th>Ticket</th><th>Estado</th><th>Fecha</th></tr>
        <?php foreach ($historial as $h): ?>
        <tr>
            <td><?= e($h['buzon']) ?></td>
            <td><?= e($h['remitente']) ?></td>
            <td><?= e($h['asunto']) ?></td>
            <td><a href="ticket_detalle.php?id=<?= (int)$h['ticket_id'] ?>">#<?= (int)$h['ticket_id'] ?></a></td>
            <td><span class="badge <?= $h['estado']==='CERRADO'?'badge-otro':'badge-activo' ?>"><?= e($h['estado']) ?></span></td>
            <td class="small"><?= e($h['procesado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$historial): ?><tr><td colspan="6" class="small">Aún no se ha sincronizado ningún correo.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
