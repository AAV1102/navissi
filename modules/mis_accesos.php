<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();

// Deliberadamente SIN tiene_rol() - cualquier usuario logueado, sin importar rol o
// perfil, puede ver esta página. Lo que cambia es que cada quien solo ve lo suyo,
// vinculado por su propio usuario_id / correo - nunca el de otra persona.

$credenciales = [];
if ($u) {
    $stmt = $pdo->prepare("SELECT c.*, s.nombre AS sede_nombre FROM credenciales c
        LEFT JOIN sedes s ON c.sede_id = s.id
        WHERE c.usuario_id = ? ORDER BY c.sistema");
    $stmt->execute([$u['id']]);
    $credenciales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$cuentaM365 = null;
if ($u && !empty($u['email'])) {
    $stmt = $pdo->prepare("SELECT * FROM ms365_usuarios WHERE correo = ? COLLATE NOCASE LIMIT 1");
    $stmt->execute([$u['email']]);
    $cuentaM365 = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$tenant = null;
$configM365Path = MS365_CONFIG_PATH;
$cfgM365 = leer_config_json($configM365Path);
if ($cfgM365) {
    $tenant = $cfgM365['tenant_dominio'] ?? $cfgM365['tenant'] ?? null;
}

layout_inicio('Mis Accesos', 'Mis Accesos', '../');
?>
<h1><?= icon('key','icon-lg') ?> Mis Accesos</h1>
<p class="subtitle">Tus propias credenciales y accesos — nadie más ve esta página con tu información, ni tú ves la de otros.</p>

<div class="panel">
    <h3><?= icon('cloud') ?> Microsoft 365</h3>
    <?php if ($cuentaM365): ?>
        <p><strong><?= e($cuentaM365['nombre'] ?? $u['nombre']) ?></strong> · <?= e($u['email']) ?></p>
        <p class="small"><?= e($cuentaM365['departamento'] ?? '') ?> <?= e($cuentaM365['cargo'] ?? '') ?></p>
        <p><span class="badge <?= !empty($cuentaM365['cuenta_activa']) ? 'badge-activo' : 'badge-otro' ?>"><?= !empty($cuentaM365['cuenta_activa']) ? 'Cuenta activa' : 'Cuenta inactiva' ?></span></p>
        <div class="toolbar" style="margin-top:14px;">
            <a class="btn" href="https://outlook.office.com/mail/" target="_blank" rel="noopener"><?= icon('chat') ?> Correo (Outlook)</a>
            <a class="btn btn-secondary" href="https://<?= e($tenant ?: 'www') ?>-my.sharepoint.com/personal" target="_blank" rel="noopener"><?= icon('folder') ?> Mi OneDrive</a>
            <a class="btn btn-secondary" href="https://<?= e($tenant ?: 'www') ?>.sharepoint.com" target="_blank" rel="noopener"><?= icon('folder') ?> SharePoint</a>
            <a class="btn btn-secondary" href="conexiones_microsoft.php"><?= icon('external') ?> Ver mis archivos dentro de NAVISSI</a>
        </div>
    <?php else: ?>
        <p class="small">No encontramos una cuenta de Microsoft 365 sincronizada con tu correo (<?= e($u['email']) ?>). Pide a TI que verifique la sincronización en Microsoft 365 → Usuarios.</p>
    <?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('key') ?> Mis credenciales vinculadas</h3>
    <?php if ($credenciales): ?>
    <table>
        <tr><th>Sistema</th><th>Sede</th><th>Usuario</th><th>Contraseña</th><th>Categoría</th></tr>
        <?php foreach ($credenciales as $c): ?>
        <tr>
            <td><?= e($c['sistema']) ?></td>
            <td><?= e($c['sede_nombre']) ?: '—' ?></td>
            <td><?= e($c['usuario']) ?></td>
            <td><code id="credencial-<?= (int)$c['id'] ?>">••••••••</code> <button type="button" class="btn btn-secondary revelar-credencial" data-id="<?= (int)$c['id'] ?>" data-target="credencial-<?= (int)$c['id'] ?>" style="padding:3px 8px;font-size:12px;">Ver</button></td>
            <td><?= e($c['categoria']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p class="small">Todavía no tienes credenciales vinculadas a tu usuario (Siesa, WiFi, etc.). Pide a TI que las vincule desde <em>Credenciales</em> o <em>Siesa</em>, seleccionando tu nombre en "Vincular a usuario".</p>
    <?php endif; ?>
</div>
<?php layout_fin(); ?>
