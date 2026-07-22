<?php
// Explorador de OneDrive/SharePoint/Teams del empleado que inició sesión: su
// propio OneDrive (bajo su correo corporativo), los equipos/canales de Teams
// de los que es miembro (incluye canales de difusión/noticias/informativos
// si su cuenta pertenece a esos equipos), y los sitios de SharePoint del
// tenant. Todo vía Microsoft Graph real (lib/graph_client.php) — sin guardar
// ni pedir ninguna contraseña de Microsoft, usa la app ya autorizada.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/graph_client.php';
$pdo = db();
$u = usuario_actual();

layout_inicio('Mis archivos de Microsoft 365', 'Mis Accesos', '../');
?>
<h1><?= icon('cloud', 'icon-lg') ?> Mis archivos de Microsoft 365</h1>
<p class="subtitle">Tu OneDrive personal, los equipos/canales de Teams a los que perteneces, y los sitios de SharePoint — directo desde Microsoft, sin salir de NAVISSI.</p>

<?php
if (!ms365_configurado()) {
    echo '<div class="msg-error">Microsoft 365 no está configurado todavía en NAVISSI. Pídele a TI que lo configure en <a href="conexiones_microsoft.php">Conexiones Microsoft</a>.</div>';
    layout_fin();
    exit;
}
if (!$u['email']) {
    echo '<div class="msg-error">Tu cuenta de NAVISSI no tiene un correo registrado, así que no se puede saber cuál es tu cuenta de Microsoft 365. Pide a TI/RRHH que te lo agreguen.</div>';
    layout_fin();
    exit;
}

$stmt = $pdo->prepare("SELECT graph_id, correo FROM ms365_usuarios WHERE correo = ? COLLATE NOCASE");
$stmt->execute([$u['email']]);
$ms365 = $stmt->fetch(PDO::FETCH_ASSOC);
// Graph acepta el correo (userPrincipalName) igual que el id real - si todavía
// no se ha sincronizado ese usuario en ms365_usuarios, se intenta igual con
// el correo directamente en vez de bloquear la pantalla.
$idGraph = $ms365['graph_id'] ?? $u['email'];

$cfg = ms365_config();
$gc = new GraphClient($cfg['tenant_id'], $cfg['client_id'], $cfg['client_secret']);

$carpetaOneDrive = trim((string) ($_GET['carpeta'] ?? ''));
$siteId = trim((string) ($_GET['site'] ?? ''));
$carpetaSite = trim((string) ($_GET['ruta'] ?? ''));
?>

<div class="pe-tabs" style="margin-bottom:14px;">
    <button type="button" class="pe-tab-btn activo" data-target="tab-onedrive"><?= icon('folder') ?> Mi OneDrive</button>
    <button type="button" class="pe-tab-btn" data-target="tab-teams"><?= icon('users') ?> Equipos y canales (Teams)</button>
    <button type="button" class="pe-tab-btn" data-target="tab-sharepoint"><?= icon('building') ?> SharePoint</button>
</div>

<div class="pe-panel activo" id="tab-onedrive">
    <div class="panel">
        <h3><?= icon('folder') ?> Mi OneDrive personal <span class="small">(<?= e($u['email']) ?>)</span></h3>
        <?php if ($carpetaOneDrive): ?><p class="small"><a href="mis_archivos_365.php">← Raíz de mi OneDrive</a> / <?= e($carpetaOneDrive) ?></p><?php endif; ?>
        <?php
        try {
            $archivos = $gc->listarOneDriveUsuario($idGraph, $carpetaOneDrive);
            if (!$archivos) {
                echo '<p class="small">Tu OneDrive está vacío, o esta carpeta no tiene contenido.</p>';
            } else {
                echo '<table><tr><th>Nombre</th><th>Tipo</th><th>Tamaño</th><th>Modificado</th><th></th></tr>';
                foreach ($archivos as $item) {
                    $esCarpeta = isset($item['folder']);
                    $nombre = e($item['name'] ?? '');
                    $rutaHija = ($carpetaOneDrive ? $carpetaOneDrive . '/' : '') . ($item['name'] ?? '');
                    $tamano = isset($item['size']) ? number_format($item['size'] / 1024, 0) . ' KB' : '—';
                    $modificado = e($item['lastModifiedDateTime'] ?? '—');
                    echo '<tr><td>' . ($esCarpeta ? icon('folder') . ' <a href="mis_archivos_365.php?carpeta=' . urlencode($rutaHija) . '">' . $nombre . '</a>' : icon('file') . ' ' . $nombre) . '</td>';
                    echo '<td>' . ($esCarpeta ? 'Carpeta' : 'Archivo') . '</td><td>' . ($esCarpeta ? '—' : $tamano) . '</td><td class="small">' . $modificado . '</td>';
                    echo '<td>' . (!$esCarpeta && !empty($item['@microsoft.graph.downloadUrl']) ? '<a href="' . e($item['@microsoft.graph.downloadUrl']) . '" target="_blank">Descargar</a>' : '') . '</td></tr>';
                }
                echo '</table>';
            }
        } catch (GraphClientException $e) {
            echo '<div class="msg-error">No se pudo consultar tu OneDrive: ' . e($e->getMessage()) . '</div>';
        }
        ?>
    </div>
</div>

<div class="pe-panel" id="tab-teams">
    <div class="panel">
        <h3><?= icon('users') ?> Mis equipos y canales</h3>
        <p class="small">Incluye los canales de difusión, noticias y avances de Gestión Humana si tu cuenta ya pertenece a esos equipos en Microsoft Teams.</p>
        <?php
        try {
            $equipos = $gc->listarEquiposDeUsuario($idGraph);
            if (!$equipos) {
                echo '<p class="small">No perteneces a ningún equipo de Microsoft Teams todavía (o tu administrador de TI aún no te agregó a los canales informativos/de difusión de la empresa).</p>';
            }
            foreach ($equipos as $eq) {
                echo '<div style="margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--line);">';
                echo '<strong>' . e($eq['displayName'] ?? '') . '</strong>';
                if (!empty($eq['description'])) echo ' <span class="small">— ' . e($eq['description']) . '</span>';
                try {
                    $canales = $gc->listarCanalesEquipo($eq['id']);
                    if ($canales) {
                        echo '<ul style="margin:6px 0 0 20px;">';
                        foreach ($canales as $c) {
                            echo '<li>' . icon('chat') . ' ' . e($c['displayName'] ?? '') . ($c['membershipType'] === 'private' ? ' <span class="badge badge-otro">Privado</span>' : '') . '</li>';
                        }
                        echo '</ul>';
                    }
                } catch (GraphClientException $e) {
                    echo '<p class="small">No se pudieron leer los canales de este equipo.</p>';
                }
                echo '</div>';
            }
        } catch (GraphClientException $e) {
            echo '<div class="msg-error">No se pudieron consultar tus equipos de Teams: ' . e($e->getMessage()) . '</div>';
        }
        ?>
    </div>
</div>

<div class="pe-panel" id="tab-sharepoint">
    <div class="panel">
        <h3><?= icon('building') ?> Sitios de SharePoint</h3>
        <?php if ($siteId): ?><p class="small"><a href="mis_archivos_365.php#sharepoint">← Todos los sitios</a></p><?php endif; ?>
        <?php
        try {
            if (!$siteId) {
                $sitios = $gc->listarSitiosSharePoint();
                if (!$sitios) {
                    echo '<p class="small">No se encontraron sitios de SharePoint en el tenant.</p>';
                } else {
                    echo '<table><tr><th>Sitio</th><th>Descripción</th><th></th></tr>';
                    foreach ($sitios as $s) {
                        echo '<tr><td>' . icon('building') . ' ' . e($s['displayName'] ?? $s['name'] ?? '') . '</td><td class="small">' . e($s['description'] ?? '—') . '</td>';
                        echo '<td><a href="mis_archivos_365.php?site=' . urlencode($s['id']) . '#sharepoint">Explorar →</a> · <a href="' . e($s['webUrl'] ?? '#') . '" target="_blank">Abrir en SharePoint</a></td></tr>';
                    }
                    echo '</table>';
                }
            } else {
                if ($carpetaSite) echo '<p class="small">' . e($carpetaSite) . '</p>';
                $archivosSite = $gc->listarArchivosSitio($siteId, $carpetaSite);
                if (!$archivosSite) {
                    echo '<p class="small">Esta carpeta está vacía.</p>';
                } else {
                    echo '<table><tr><th>Nombre</th><th>Tipo</th><th></th></tr>';
                    foreach ($archivosSite as $item) {
                        $esCarpeta = isset($item['folder']);
                        $rutaHija = ($carpetaSite ? $carpetaSite . '/' : '') . ($item['name'] ?? '');
                        echo '<tr><td>' . ($esCarpeta ? icon('folder') . ' <a href="mis_archivos_365.php?site=' . urlencode($siteId) . '&ruta=' . urlencode($rutaHija) . '#sharepoint">' . e($item['name']) . '</a>' : icon('file') . ' ' . e($item['name'])) . '</td>';
                        echo '<td>' . ($esCarpeta ? 'Carpeta' : 'Archivo') . '</td>';
                        echo '<td>' . (!$esCarpeta && !empty($item['@microsoft.graph.downloadUrl']) ? '<a href="' . e($item['@microsoft.graph.downloadUrl']) . '" target="_blank">Descargar</a>' : '') . '</td></tr>';
                    }
                    echo '</table>';
                }
            }
        } catch (GraphClientException $e) {
            echo '<div class="msg-error">No se pudo consultar SharePoint: ' . e($e->getMessage()) . '</div>';
        }
        ?>
    </div>
</div>

<script>
(function () {
    var botones = document.querySelectorAll('.pe-tab-btn');
    var paneles = document.querySelectorAll('.pe-panel');
    function activar(id) {
        botones.forEach(function (b) { b.classList.toggle('activo', b.dataset.target === id); });
        paneles.forEach(function (p) { p.classList.toggle('activo', p.id === id); });
    }
    botones.forEach(function (b) { b.addEventListener('click', function () { activar(b.dataset.target); }); });
    <?php if ($siteId): ?>activar('tab-sharepoint');<?php endif; ?>
})();
</script>
<?php layout_fin(); ?>
