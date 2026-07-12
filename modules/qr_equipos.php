<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT i.*, s.nombre AS sede_nombre FROM inventario i LEFT JOIN sedes s ON i.sede_id = s.id WHERE 1=1";
$params = [];
if ($busqueda !== '') { $sql .= " AND (i.asignado_a LIKE :b OR i.serial LIKE :b OR i.placa LIKE :b OR s.nombre LIKE :b)"; $params['b'] = "%{$busqueda}%"; }
$sql .= " ORDER BY i.actualizado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];

layout_inicio('Códigos QR de Equipos', 'Códigos QR de Equipos', '../');
?>
<style>
@media print {
    .topbar, .toolbar, .no-print, #ia-chat-launcher, #ia-chat-panel { display: none !important; }
    .qr-grid { break-inside: avoid; }
}
.qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-top:16px; }
.qr-card { border: 1px solid var(--line); border-radius: 10px; padding: 14px; text-align: center; background: #fff; }
.qr-card img { width: 140px; height: 140px; }
.qr-card .titulo { font-weight: 700; margin-top: 8px; font-size: 13px; }
.qr-card .sub { font-size: 11.5px; color: var(--ink-500); }
</style>
<h1><?= icon('inventory','icon-lg') ?> Códigos QR de Equipos</h1>
<p class="subtitle no-print">Etiqueta imprimible por equipo — un solo QR fijo para pegar en cada PC de por vida. Al escanearlo siempre abre la ficha de ESE equipo con la información al momento: ficha técnica, movimientos, tickets de mesa de ayuda y hoja de vida completa, todo leído en vivo de la base de datos (nunca es una foto estática).</p>

<form class="toolbar no-print" method="get">
    <input type="search" name="q" placeholder="Buscar empleado, serial, placa, sede..." value="<?= e($busqueda) ?>" style="min-width:280px">
    <button type="submit"><?= icon('search') ?> Buscar</button>
    <button type="button" onclick="window.print()"><?= icon('file') ?> Imprimir etiquetas</button>
</form>

<div class="qr-grid">
    <?php foreach ($equipos as $e):
        $contenido = "{$baseUrl}/modules/equipo_detalle.php?id=" . (int) $e['id'];
        $qrSrc = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($contenido);
    ?>
    <div class="qr-card">
        <img src="<?= e($qrSrc) ?>" alt="QR <?= e($e['serial']) ?>" loading="lazy">
        <div class="titulo"><?= e($e['marca']) ?> <?= e($e['modelo']) ?></div>
        <div class="sub">Serial: <?= e($e['serial']) ?: '—' ?></div>
        <div class="sub">Placa: <?= e($e['placa']) ?: '—' ?></div>
        <div class="sub"><?= e($e['asignado_a']) ?: 'Sin asignar' ?> · <?= e($e['sede_nombre']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$equipos): ?><p class="small">Sin equipos que coincidan.</p><?php endif; ?>
</div>
<?php layout_fin(); ?>
