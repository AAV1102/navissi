<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

if (!tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI'])) {
    layout_inicio('Diagnóstico del Sistema', 'Diagnóstico del Sistema', '../');
    echo '<div class="msg-error">Solo TI puede ver el diagnóstico del sistema.</div>';
    layout_fin();
    exit;
}

// --- Base de datos: tamaño real, tablas y conteo de filas ---
$tamanoBD = file_exists(DB_PATH) ? round(filesize(DB_PATH) / 1024 / 1024, 2) : 0;
$tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$conteoTablas = [];
foreach ($tablas as $t) {
    try {
        $conteoTablas[$t] = (int) $pdo->query("SELECT COUNT(*) FROM \"{$t}\"")->fetchColumn();
    } catch (Exception $e) {
        $conteoTablas[$t] = -1;
    }
}

// --- Servidor: PHP, extensiones críticas, disco ---
$extensionesRequeridas = ['pdo_sqlite', 'curl', 'mbstring', 'openssl', 'zip'];
$extensiones = [];
foreach ($extensionesRequeridas as $ext) {
    $extensiones[$ext] = extension_loaded($ext);
}
$discoLibre = @disk_free_space(BASE_DIR);
$discoTotal = @disk_total_space(BASE_DIR);

// --- Conectividad de integraciones ---
$rustdeskActivo = file_exists(__DIR__ . '/../rustdesk-server/id_ed25519.pub');
$ms365Configurado = file_exists(MS365_CONFIG_PATH);
$iaConfigurada = file_exists(private_path('ia_config.json'));
$agentesActivos24h = (int) $pdo->query("SELECT COUNT(*) FROM inventario WHERE julianday('now') - julianday(ultima_conexion_agente) < 1")->fetchColumn();

// --- Salud de datos: huerfanos y filas incompletas ---
$equiposSinSede = (int) $pdo->query("SELECT COUNT(*) FROM inventario WHERE sede_id IS NULL")->fetchColumn();
$ticketsSinSla = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE sla_limite IS NULL")->fetchColumn();
$importPendientes = (int) $pdo->query("SELECT COUNT(*) FROM importaciones_log")->fetchColumn();

layout_inicio('Diagnóstico del Sistema', 'Diagnóstico del Sistema', '../');
?>
<h1><?= icon('shield','icon-lg') ?> Diagnóstico del Sistema</h1>
<p class="subtitle">Estado real del servidor, la base de datos y las integraciones — todo medido en el momento, no valores de ejemplo.</p>

<div class="cards">
    <div class="card"><div class="num"><?= $tamanoBD ?> MB</div><div class="label">Tamaño de la base de datos</div></div>
    <div class="card"><div class="num"><?= count($tablas) ?></div><div class="label">Tablas en el esquema</div></div>
    <div class="card"><div class="num"><?= PHP_VERSION ?></div><div class="label">Versión de PHP</div></div>
    <div class="card" style="border-left-color:<?= $agentesActivos24h > 0 ? '#0d9488' : '#c98a1f' ?>"><div class="num"><?= $agentesActivos24h ?></div><div class="label">Equipos con agente activo (24h)</div></div>
</div>

<div class="panel">
    <h3><?= icon('shield') ?> Extensiones PHP requeridas</h3>
    <table>
        <tr><th>Extensión</th><th>Estado</th></tr>
        <?php foreach ($extensiones as $ext => $activa): ?>
        <tr><td><code><?= e($ext) ?></code></td><td><span class="badge <?= $activa?'badge-activo':'badge-err' ?>"><?= $activa ? 'CARGADA' : 'FALTA' ?></span></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="panel">
    <h3><?= icon('cloud') ?> Integraciones</h3>
    <table>
        <tr><th>Integración</th><th>Estado</th></tr>
        <tr><td>Microsoft 365 / Graph API</td><td><span class="badge <?= $ms365Configurado?'badge-activo':'badge-otro' ?>"><?= $ms365Configurado?'CONFIGURADO':'SIN CONFIGURAR' ?></span></td></tr>
        <tr><td>IA (Claude/GPT/Gemini)</td><td><span class="badge <?= $iaConfigurada?'badge-activo':'badge-otro' ?>"><?= $iaConfigurada?'CONFIGURADA':'SIN CONFIGURAR' ?></span></td></tr>
        <tr><td>Servidor RustDesk (acceso remoto propio)</td><td><span class="badge <?= $rustdeskActivo?'badge-activo':'badge-otro' ?>"><?= $rustdeskActivo?'ACTIVO':'SIN CONFIGURAR' ?></span></td></tr>
    </table>
</div>

<div class="panel">
    <h3><?= icon('bell') ?> Salud de los datos</h3>
    <table>
        <tr><th>Chequeo</th><th>Resultado</th></tr>
        <tr><td>Equipos sin sede asignada</td><td><span class="badge <?= $equiposSinSede>0?'badge-warn':'badge-activo' ?>"><?= $equiposSinSede ?></span></td></tr>
        <tr><td>Tickets sin SLA calculado</td><td><span class="badge <?= $ticketsSinSla>0?'badge-warn':'badge-activo' ?>"><?= $ticketsSinSla ?></span></td></tr>
        <tr><td>Filas pendientes de importación</td><td><span class="badge <?= $importPendientes>0?'badge-warn':'badge-activo' ?>"><?= $importPendientes ?></span></td></tr>
        <?php if ($discoTotal): ?>
        <tr><td>Espacio en disco libre</td><td><?= round($discoLibre/1024/1024/1024, 1) ?> GB de <?= round($discoTotal/1024/1024/1024, 1) ?> GB</td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="panel">
    <h3>Filas por tabla</h3>
    <table>
        <tr><th>Tabla</th><th>Filas</th></tr>
        <?php foreach ($conteoTablas as $t => $c): ?>
        <tr><td><code><?= e($t) ?></code></td><td><?= $c ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php layout_fin(); ?>
