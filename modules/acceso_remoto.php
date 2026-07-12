<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Acceso Remoto', 'Acceso Remoto', '../');
    echo '<div class="msg-error">Solo TI puede usar el control remoto.</div>';
    layout_fin();
    exit;
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT i.*, s.nombre AS sede_nombre FROM inventario i LEFT JOIN sedes s ON i.sede_id = s.id WHERE 1=1";
$params = [];
if ($busqueda !== '') { $sql .= " AND (i.asignado_a LIKE :b OR i.serial LIKE :b OR i.placa LIKE :b OR s.nombre LIKE :b)"; $params['b'] = "%{$busqueda}%"; }
$sql .= " ORDER BY (i.rustdesk_id IS NOT NULL AND i.rustdesk_id != '') DESC, i.actualizado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$conAgente = count(array_filter($equipos, fn($e) => !empty($e['rustdesk_id'])));

layout_inicio('Acceso Remoto', 'Acceso Remoto', '../');
?>
<h1><?= icon('zap','icon-lg') ?> Acceso Remoto</h1>
<p class="subtitle">Control remoto tipo Zoho Assist / AnyDesk usando RustDesk — funciona dentro o fuera de la red de la empresa, sin VPN, apuntando a tu propio servidor.</p>

<div class="stat-cards" style="margin-bottom:18px;">
    <div class="stat-card"><div class="stat-num"><?= count($equipos) ?></div><div class="stat-label">Equipos en inventario</div></div>
    <div class="stat-card"><div class="stat-num" style="color:<?= $conAgente ? 'var(--ok-fg)' : 'inherit' ?>"><?= $conAgente ?></div><div class="stat-label">Con RustDesk registrado (listos para conectar)</div></div>
</div>

<?php
$serverPubKeyPath = __DIR__ . '/../rustdesk-server/id_ed25519.pub';
$clavePublica = file_exists($serverPubKeyPath) ? trim(file_get_contents($serverPubKeyPath)) : null;
$ipServidor = gethostbyname(gethostname());
?>
<div class="panel">
    <h3><?= icon('shield') ?> Tu servidor RustDesk (ya instalado y corriendo)</h3>
    <?php if ($clavePublica): ?>
    <p><span class="badge badge-activo"><?= icon('check') ?> Activo</span> — corriendo en esta misma máquina, portable (carpeta <code>rustdesk-server/</code>), sin Docker.</p>
    <div class="grid-form">
        <div><label>IP del servidor (red local)</label><input type="text" readonly value="<?= e($ipServidor) ?>" onclick="this.select()"></div>
        <div><label>Clave pública (Key)</label><input type="text" readonly value="<?= e($clavePublica) ?>" onclick="this.select()"></div>
    </div>
    <p class="small">Configura estos dos datos en cada cliente RustDesk: Configuración → Red → ID Server = IP de arriba, Relay Server = IP de arriba, Key = clave de arriba. O pásalos directo al agente con <code>-RustDeskServidor "<?= e($ipServidor) ?>"</code>.</p>
    <p class="small"><strong>Para acceso fuera de la red de la empresa</strong> (que fue tu pedido original): en el router de la sede principal, redirige los puertos <code>21115-21119</code> (TCP y UDP) hacia la IP <?= e($ipServidor) ?> de este equipo, y usa la IP pública del router (o un dominio con DNS dinámico si tu IP pública cambia) en vez de la IP local en la configuración de cada cliente.</p>
    <p class="small"><strong>Para que arranque solo</strong> cada vez que se encienda este equipo: clic derecho sobre <code>rustdesk-server\registrar_tarea_programada.ps1</code> → "Ejecutar con PowerShell" → aceptar el permiso de administrador que pedirá Windows (una sola vez). Ahora mismo el servidor está corriendo, pero si este equipo se reinicia antes de hacer eso, hay que abrir <code>iniciar_servidor.ps1</code> otra vez manualmente.</p>
    <?php else: ?>
    <p><span class="badge badge-otro">No detectado</span> — ejecuta <code>rustdesk-server\iniciar_servidor.ps1</code> para arrancarlo.</p>
    <?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('robot') ?> Cómo funciona</h3>
    <ol class="small" style="line-height:1.9;padding-left:18px;">
        <li>El servidor de arriba ya está corriendo — no depende de RustDesk Inc. ni de terceros, es 100% tuyo.</li>
        <li>En cada equipo corre el agente con <code>-InstalarRustDesk -RustDeskServidor "<?= e($ipServidor ?: 'IP-DEL-SERVIDOR') ?>"</code> (una sola vez, puede ir en la Tarea Programada junto al reporte de inventario).</li>
        <li>El agente instala RustDesk, lo apunta a tu servidor, y detecta el ID del equipo — lo guarda aquí automáticamente.</li>
        <li>Para conectarte, haz clic en "Conectar" — se abre la app RustDesk (debes tenerla instalada en tu PC de soporte) directo hacia ese equipo.</li>
    </ol>
</div>

<form class="toolbar" method="get">
    <input type="search" name="q" placeholder="Buscar empleado, serial, placa, sede..." value="<?= e($busqueda) ?>" style="min-width:280px">
    <button type="submit"><?= icon('search') ?> Buscar</button>
</form>

<table>
    <tr><th>Empleado</th><th>Equipo</th><th>Sede</th><th>IP local</th><th>RustDesk ID</th><th>Último reporte</th><th></th></tr>
    <?php foreach ($equipos as $e): ?>
    <tr>
        <td><?= e($e['asignado_a']) ?: '—' ?></td>
        <td><?= e($e['marca']) ?> <?= e($e['modelo']) ?><br><span class="small"><?= e($e['serial']) ?></span></td>
        <td><?= e($e['sede_nombre']) ?></td>
        <td class="small"><?= e($e['ip_local']) ?: '—' ?></td>
        <td>
            <?php if ($e['rustdesk_id']): ?>
                <code><?= e($e['rustdesk_id']) ?></code>
            <?php else: ?>
                <span class="badge badge-otro">Sin agente RustDesk</span>
            <?php endif; ?>
        </td>
        <td class="small"><?= e($e['ultima_conexion_agente']) ?: '—' ?></td>
        <td>
            <?php if ($e['rustdesk_id']): ?>
            <a class="btn" style="padding:5px 12px;font-size:12.5px;" href="rustdesk://<?= e($e['rustdesk_id']) ?>?password=<?= e($e['rustdesk_password']) ?>"><?= icon('zap') ?> Conectar</a>
            <?php elseif ($e['ip_local']): ?>
            <a class="btn btn-secondary" style="padding:5px 12px;font-size:12.5px;" href="ms-rd:subscribe?url=rdp://full%20address=s:<?= e($e['ip_local']) ?>" title="Requiere que el equipo tenga Escritorio Remoto de Windows habilitado"><?= icon('cloud') ?> RDP (<?= e($e['ip_local']) ?>)</a>
            <?php else: ?>
            <span class="small">Sin datos de conexión aún</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$equipos): ?><tr><td colspan="7" class="small">Sin equipos en inventario.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
