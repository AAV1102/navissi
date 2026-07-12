<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Network Discovery', 'Network Discovery', '../');
    echo '<div class="msg-error">Solo TI puede ver el descubrimiento de red.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'vincular') {
    $pdo->prepare("UPDATE dispositivos_red SET inventario_id = ? WHERE id = ?")
        ->execute([(int) $_POST['inventario_id'] ?: null, (int) $_POST['id']]);
    $msg = ['ok', 'Vinculado con el equipo del inventario.'];
}

$totalDispositivos = (int) $pdo->query("SELECT COUNT(*) FROM dispositivos_red")->fetchColumn();
$activos = (int) $pdo->query("SELECT COUNT(*) FROM dispositivos_red WHERE estado='ACTIVO'")->fetchColumn();
$sinVincular = (int) $pdo->query("SELECT COUNT(*) FROM dispositivos_red WHERE inventario_id IS NULL")->fetchColumn();
$ultimoEscaneo = $pdo->query("SELECT MAX(ultima_vez_visto) FROM dispositivos_red")->fetchColumn();

$dispositivos = $pdo->query("SELECT d.*, s.nombre AS sede_nombre, i.marca, i.modelo, i.serial
    FROM dispositivos_red d LEFT JOIN sedes s ON d.sede_id = s.id LEFT JOIN inventario i ON d.inventario_id = i.id
    ORDER BY d.estado = 'ACTIVO' DESC, d.ultima_vez_visto DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
$equipos = $pdo->query("SELECT id, serial, marca, modelo FROM inventario ORDER BY marca")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Network Discovery', 'Network Discovery', '../');
?>
<h1><?= icon('cloud','icon-lg') ?> Network Discovery</h1>
<p class="subtitle">Dispositivos reales detectados en la red local (ping sweep + tabla ARP), reportados por el agente. Identificados por MAC, así que sobreviven a cambios de IP por DHCP.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= $totalDispositivos ?></div><div class="label">Dispositivos detectados históricamente</div></div>
    <div class="card"><div class="num"><?= $activos ?></div><div class="label">Activos en el último escaneo</div></div>
    <div class="card" style="border-left-color:<?= $sinVincular ? '#c98a1f' : '#0d9488' ?>"><div class="num"><?= $sinVincular ?></div><div class="label">Sin vincular a un equipo del inventario</div></div>
</div>

<div class="panel">
    <h3><?= icon('zap') ?> Cómo escanear</h3>
    <p class="small">Corre esto en cualquier PC de la red (no necesita ser admin, tarda ~1 minuto):</p>
    <pre style="background:#0f1720;color:#d7e3ef;padding:12px;border-radius:8px;overflow-x:auto;font-size:12.5px;">powershell -ExecutionPolicy Bypass -File agente_navissi.ps1 -Servidor "http://<?= e($_SERVER['HTTP_HOST']) ?>" -EscanearRed</pre>
    <p class="small">Último escaneo recibido: <?= e($ultimoEscaneo) ?: 'ninguno todavía' ?></p>
</div>

<div class="tabla-toolbar">
    <span class="small">Mostrando <?= count($dispositivos) ?> dispositivo(s)</span>
</div>
<table class="tabla-tickets">
    <thead>
    <tr><th>IP</th><th>MAC</th><th>Hostname</th><th>Sede</th><th>Equipo vinculado</th><th>Última vez visto</th><th>Estado</th></tr>
    </thead>
    <tbody>
    <?php foreach ($dispositivos as $d): ?>
    <tr>
        <td><code><?= e($d['ip']) ?></code></td>
        <td class="small"><?= e($d['mac']) ?></td>
        <td><?= e($d['hostname']) ?: '—' ?></td>
        <td><?= e($d['sede_nombre']) ?: '—' ?></td>
        <td>
            <?php if ($d['inventario_id']): ?>
                <?= e($d['marca']) ?> <?= e($d['modelo']) ?> <span class="small">(<?= e($d['serial']) ?>)</span>
            <?php else: ?>
            <form method="post" class="inline" style="display:flex;gap:4px;">
                <input type="hidden" name="accion" value="vincular">
                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <select name="inventario_id" style="font-size:12px;padding:3px;">
                    <option value="">-- vincular --</option>
                    <?php foreach ($equipos as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['marca']) ?> <?= e($e['modelo']) ?> (<?= e($e['serial']) ?>)</option><?php endforeach; ?>
                </select>
                <button type="submit" style="padding:3px 8px;font-size:11px;">OK</button>
            </form>
            <?php endif; ?>
        </td>
        <td class="small"><?= e($d['ultima_vez_visto']) ?></td>
        <td><span class="badge <?= $d['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($d['estado']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$dispositivos): ?>
    <tr><td colspan="7" style="text-align:center;padding:60px 14px;border-bottom:none;">
        <div style="font-size:44px;opacity:.5;"><?= icon('cloud','icon-lg') ?></div>
        <strong>¿Todo listo para detectar la red?</strong><br>
        <span class="small">Sin escaneos todavía. Corre el comando de arriba desde un equipo de la red.</span>
    </td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php layout_fin(); ?>
