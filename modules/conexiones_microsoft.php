<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/graph_client.php';
$pdo = db();

$sitios = null; $equipos = null; $onedrive = null;
$errorSitios = $errorEquipos = $errorOneDrive = null;

if (!ms365_configurado()) {
    layout_inicio('Conexiones Microsoft', 'Microsoft 365', '../');
    echo '<h1>Conexiones Microsoft (OneDrive / SharePoint / Teams)</h1>';
    echo '<div class="msg-error">Primero configura la conexión en el módulo <a href="microsoft365.php">Microsoft 365</a>.</div>';
    layout_fin();
    exit;
}

$c = ms365_config();
$gc = new GraphClient($c['tenant_id'], $c['client_id'], $c['client_secret']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'sharepoint') $sitios = $gc->listarSitiosSharePoint();
        if ($accion === 'teams') $equipos = $gc->listarTodosLosEquipos();
        if ($accion === 'onedrive') {
            $userId = trim($_POST['correo_usuario'] ?? '');
            $onedrive = $gc->listarOneDriveUsuario($userId);
        }
    } catch (GraphClientException $e) {
        if ($accion === 'sharepoint') $errorSitios = $e->getMessage();
        if ($accion === 'teams') $errorEquipos = $e->getMessage();
        if ($accion === 'onedrive') $errorOneDrive = $e->getMessage();
    }
}

layout_inicio('Conexiones Microsoft', 'Microsoft 365', '../');
?>
<p class="small"><a href="microsoft365.php">← Volver a Microsoft 365</a></p>
<h1><?= icon('folder','icon-lg') ?> OneDrive / SharePoint / Teams</h1>
<p class="subtitle">Usa la misma conexión de Azure AD que ya configuraste. Si un botón da error de permisos, hay que agregar ese permiso puntual en Azure (te digo cuál abajo).</p>

<div class="panel">
    <h3>SharePoint — Sitios del tenant</h3>
    <form method="post"><input type="hidden" name="accion" value="sharepoint"><button type="submit">Listar sitios</button></form>
    <?php if ($errorSitios): ?>
        <div class="msg-error" style="margin-top:10px;"><?= e($errorSitios) ?><br><span class="small">Falta el permiso de aplicación <code>Sites.Read.All</code> en Azure AD → esta app → Permisos de API → agregar → Microsoft Graph → Aplicación → conceder consentimiento de administrador.</span></div>
    <?php elseif ($sitios !== null): ?>
        <table style="margin-top:10px;">
            <tr><th>Nombre</th><th>Dirección web</th></tr>
            <?php foreach ($sitios as $s): ?>
            <tr><td><?= e($s['displayName'] ?? $s['name'] ?? '—') ?></td><td><?= e($s['webUrl'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$sitios): ?><tr><td colspan="2" class="small">No se encontraron sitios (o no hay SharePoint activo en el tenant).</td></tr><?php endif; ?>
        </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Microsoft Teams — Equipos del tenant</h3>
    <form method="post"><input type="hidden" name="accion" value="teams"><button type="submit">Listar equipos</button></form>
    <?php if ($errorEquipos): ?>
        <div class="msg-error" style="margin-top:10px;"><?= e($errorEquipos) ?><br><span class="small">Falta el permiso de aplicación <code>Team.ReadBasic.All</code> (o <code>Group.Read.All</code>) en Azure AD → esta app → Permisos de API.</span></div>
    <?php elseif ($equipos !== null): ?>
        <table style="margin-top:10px;">
            <tr><th>Nombre</th><th>Descripción</th></tr>
            <?php foreach ($equipos as $t): ?>
            <tr><td><?= e($t['displayName'] ?? '—') ?></td><td><?= e($t['description'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$equipos): ?><tr><td colspan="2" class="small">No se encontraron equipos de Teams.</td></tr><?php endif; ?>
        </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>OneDrive — Archivos de un usuario</h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="onedrive">
        <input type="text" name="correo_usuario" placeholder="correo@navissi.com" required style="min-width:260px;">
        <button type="submit">Listar archivos</button>
    </form>
    <?php if ($errorOneDrive): ?>
        <div class="msg-error" style="margin-top:10px;"><?= e($errorOneDrive) ?><br><span class="small">Falta el permiso de aplicación <code>Files.Read.All</code> en Azure AD → esta app → Permisos de API, o el usuario no tiene OneDrive aprovisionado.</span></div>
    <?php elseif ($onedrive !== null): ?>
        <table style="margin-top:10px;">
            <tr><th>Nombre</th><th>Tipo</th><th>Modificado</th></tr>
            <?php foreach ($onedrive as $f): ?>
            <tr><td><?= e($f['name'] ?? '—') ?></td><td><?= isset($f['folder']) ? 'Carpeta' : 'Archivo' ?></td><td class="small"><?= e($f['lastModifiedDateTime'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$onedrive): ?><tr><td colspan="3" class="small">Sin archivos en la raíz.</td></tr><?php endif; ?>
        </table>
    <?php endif; ?>
</div>
<?php layout_fin(); ?>
