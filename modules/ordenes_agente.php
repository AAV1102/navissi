<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
requiere_login('');
if (!tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI'])) { http_response_code(403); exit('No autorizado.'); }
$msg = null;

// Catálogo curado de IDs reales de winget (Windows Package Manager) para no
// tener que buscar/escribir el ID exacto cada vez - "Otro" permite cualquier
// ID de winget que no esté en esta lista corta.
$catalogoWinget = [
    'Google.Chrome' => 'Google Chrome',
    'Mozilla.Firefox' => 'Mozilla Firefox',
    '7zip.7zip' => '7-Zip',
    'Adobe.Acrobat.Reader.64-bit' => 'Adobe Acrobat Reader',
    'VideoLAN.VLC' => 'VLC Media Player',
    'Zoom.Zoom' => 'Zoom',
    'Notepad++.Notepad++' => 'Notepad++',
    'Microsoft.Teams' => 'Microsoft Teams',
    'RustDesk.RustDesk' => 'RustDesk',
];

$tiposValidos = ['WINDOWS_UPDATE', 'INSTALLER_URL', 'INSTALL_WINGET', 'UPGRADE_WINGET', 'ACTIVATE_WINDOWS', 'ACTIVATE_OFFICE'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tipo = in_array($_POST['tipo'] ?? '', $tiposValidos, true) ? $_POST['tipo'] : 'WINDOWS_UPDATE';
        $serial = limpio($_POST['serial'] ?? null);
        $params = [];

        if ($tipo === 'INSTALLER_URL') {
            $params = ['url' => trim($_POST['url'] ?? ''), 'argumentos' => trim($_POST['argumentos'] ?? '/quiet')];
            if (!str_starts_with($params['url'], 'https://')) throw new RuntimeException('El instalador debe estar en una URL HTTPS.');
        } elseif ($tipo === 'INSTALL_WINGET') {
            $idWinget = trim($_POST['id_winget_catalogo'] ?? '') === '__otro__' ? trim($_POST['id_winget_manual'] ?? '') : trim($_POST['id_winget_catalogo'] ?? '');
            if (!$idWinget) throw new RuntimeException('Elige un programa del catálogo o escribe el ID de winget.');
            $params = ['id_winget' => $idWinget];
        } elseif ($tipo === 'UPGRADE_WINGET') {
            $idWinget = trim($_POST['id_winget_upgrade'] ?? '');
            if ($idWinget !== '') $params = ['id_winget' => $idWinget];
        } elseif ($tipo === 'ACTIVATE_WINDOWS' || $tipo === 'ACTIVATE_OFFICE') {
            $clave = strtoupper(trim($_POST['clave_licencia'] ?? ''));
            if (!preg_match('/^([A-Z0-9]{5}-){4}[A-Z0-9]{5}$/', $clave)) {
                throw new RuntimeException('La clave debe tener el formato XXXXX-XXXXX-XXXXX-XXXXX-XXXXX.');
            }
            if (!$serial) throw new RuntimeException('Elige un equipo específico para activar licencia (no se puede enviar a "todos").');
            $params = ['clave' => $clave];
        }

        $pdo->prepare('INSERT INTO agente_ordenes(serial_objetivo,tipo,parametros_json,solicitado_por)VALUES(?,?,?,?)')
            ->execute([$serial, $tipo, json_encode($params), usuario_actual()['nombre'] ?? 'Sistema']);
        $msg = ['ok', 'Orden enviada a la cola. Se ejecutará cuando el agente reporte el equipo (máximo 5 minutos).'];
    } catch (Throwable $e) {
        $msg = ['error', $e->getMessage()];
    }
}

$ordenes = $pdo->query('SELECT * FROM agente_ordenes ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$equipos = $pdo->query("SELECT serial,asignado_a,marca,modelo FROM inventario WHERE estado='ACTIVO' ORDER BY serial")->fetchAll(PDO::FETCH_ASSOC);
layout_inicio('Órdenes del agente', 'Órdenes del agente', '../');
?>
<h1><?= icon('zap', 'icon-lg') ?> Órdenes para agentes</h1>
<p class="subtitle">Instalar, actualizar, desinstalar y activar software vía winget/Windows Update. El agente devuelve el resultado al panel (máximo 5 minutos).</p>
<?php if ($msg): ?><div class="msg-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <form method="post">
        <label>Equipo (vacío = todos)</label>
        <select name="serial">
            <option value="">Todos los equipos</option>
            <?php foreach ($equipos as $e): ?><option value="<?= e($e['serial']) ?>" <?= ($_GET['serial'] ?? '') === $e['serial'] ? 'selected' : '' ?>><?= e($e['serial'] . ' · ' . $e['asignado_a'] . ' · ' . $e['marca'] . ' ' . $e['modelo']) ?></option><?php endforeach; ?>
        </select>

        <label>Acción</label>
        <select name="tipo" id="tipo">
            <option value="WINDOWS_UPDATE">Buscar e instalar actualizaciones de Windows</option>
            <option value="INSTALL_WINGET">Instalar un programa (winget)</option>
            <option value="UPGRADE_WINGET">Actualizar programas (winget)</option>
            <option value="INSTALLER_URL">Ejecutar instalador aprobado (URL HTTPS)</option>
            <option value="ACTIVATE_WINDOWS">Activar licencia de Windows</option>
            <option value="ACTIVATE_OFFICE">Activar licencia de Office</option>
        </select>

        <div id="box-winget-install" hidden>
            <label>Programa</label>
            <select name="id_winget_catalogo" id="sel-winget-catalogo">
                <?php foreach ($catalogoWinget as $id => $nombre): ?><option value="<?= e($id) ?>"><?= e($nombre) ?></option><?php endforeach; ?>
                <option value="__otro__">Otro (escribir ID de winget)…</option>
            </select>
            <div id="box-winget-manual" hidden>
                <label>ID de winget</label>
                <input type="text" name="id_winget_manual" placeholder="Ej: Slack.Slack (búscalo con: winget search nombre)">
            </div>
        </div>

        <div id="box-winget-upgrade" hidden>
            <label>ID de winget a actualizar (vacío = actualizar todos los programas del equipo)</label>
            <input type="text" name="id_winget_upgrade" placeholder="Ej: Google.Chrome — déjalo vacío para actualizar todo">
        </div>

        <div id="box-url" hidden>
            <label>URL HTTPS del instalador</label>
            <input type="url" name="url">
            <label>Argumentos silenciosos</label>
            <input name="argumentos" value="/quiet">
        </div>

        <div id="box-licencia" hidden>
            <label>Clave de producto</label>
            <input type="text" name="clave_licencia" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" style="text-transform:uppercase;">
            <p class="small">Debes elegir un equipo específico arriba (no se puede enviar a "todos" — cada licencia se activa en un solo equipo).</p>
        </div>

        <button style="margin-top:10px;">Crear orden</button>
    </form>
</div>

<div class="panel">
    <h3>Historial de órdenes</h3>
    <table>
        <tr><th>ID</th><th>Equipo</th><th>Tipo</th><th>Estado</th><th>Resultado</th><th>Fecha</th></tr>
        <?php foreach ($ordenes as $o): ?>
        <tr>
            <td>#<?= (int) $o['id'] ?></td>
            <td><?= e($o['serial_objetivo'] ?: 'Todos') ?></td>
            <td><?= e($o['tipo']) ?></td>
            <td><span class="badge <?= $o['estado']==='COMPLETADA'?'badge-activo':($o['estado']==='FALLIDA'?'badge-err':'badge-otro') ?>"><?= e($o['estado']) ?></span></td>
            <td class="small"><?= e($o['resultado'] ?: $o['error']) ?></td>
            <td class="small"><?= e($o['creado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$ordenes): ?><tr><td colspan="6" class="small">Sin órdenes enviadas todavía.</td></tr><?php endif; ?>
    </table>
</div>

<script>
(function () {
    var selTipo = document.getElementById('tipo');
    var cajas = {
        WINDOWS_UPDATE: [],
        INSTALL_WINGET: ['box-winget-install'],
        UPGRADE_WINGET: ['box-winget-upgrade'],
        INSTALLER_URL: ['box-url'],
        ACTIVATE_WINDOWS: ['box-licencia'],
        ACTIVATE_OFFICE: ['box-licencia'],
    };
    var todas = ['box-winget-install', 'box-winget-upgrade', 'box-url', 'box-licencia'];
    function actualizar() {
        var visibles = cajas[selTipo.value] || [];
        todas.forEach(function (id) { document.getElementById(id).hidden = visibles.indexOf(id) === -1; });
    }
    selTipo.addEventListener('change', actualizar);
    actualizar();

    var selCatalogo = document.getElementById('sel-winget-catalogo');
    var cajaManual = document.getElementById('box-winget-manual');
    function actualizarManual() { cajaManual.hidden = selCatalogo.value !== '__otro__'; }
    selCatalogo.addEventListener('change', actualizarManual);
    actualizarManual();
})();
</script>
<?php layout_fin(); ?>
