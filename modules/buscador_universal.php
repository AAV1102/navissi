<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$q = trim($_GET['q'] ?? '');
$resultados = [];

if ($q !== '' && strlen($q) >= 2) {
    $like = "%{$q}%";

    $stmt = $pdo->prepare("SELECT id, serial, marca, modelo, asignado_a FROM inventario WHERE serial LIKE ? OR placa LIKE ? OR asignado_a LIKE ? OR marca LIKE ? OR modelo LIKE ? LIMIT 10");
    $stmt->execute([$like, $like, $like, $like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $resultados[] = ['tipo' => 'Equipo', 'icono' => 'inventory', 'titulo' => "{$r['marca']} {$r['modelo']} ({$r['serial']})", 'sub' => $r['asignado_a'] ?: 'Sin asignar', 'href' => "equipo_detalle.php?id={$r['id']}"];
    }

    $stmt = $pdo->prepare("SELECT id, nombres, documento, cargo FROM empleados WHERE nombres LIKE ? OR documento LIKE ? OR cargo LIKE ? LIMIT 10");
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $resultados[] = ['tipo' => 'Empleado', 'icono' => 'users', 'titulo' => $r['nombres'], 'sub' => "{$r['documento']} · {$r['cargo']}", 'href' => "empleado_detalle.php?id={$r['id']}"];
    }

    $stmt = $pdo->prepare("SELECT id, titulo, estado, solicitante FROM tickets WHERE titulo LIKE ? OR descripcion LIKE ? OR solicitante LIKE ? ORDER BY id DESC LIMIT 10");
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $resultados[] = ['tipo' => 'Ticket', 'icono' => 'ticket', 'titulo' => "#{$r['id']} {$r['titulo']}", 'sub' => "{$r['estado']} · {$r['solicitante']}", 'href' => "ticket_detalle.php?id={$r['id']}"];
    }

    if (tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI'])) {
        $stmt = $pdo->prepare("SELECT id, sistema, usuario, nombre FROM credenciales WHERE usuario LIKE ? OR nombre LIKE ? OR sistema LIKE ? LIMIT 10");
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Credencial', 'icono' => 'key', 'titulo' => "{$r['sistema']} · {$r['usuario']}", 'sub' => $r['nombre'] ?: '', 'href' => "credenciales.php?editar={$r['id']}"];
        }
    }

    $stmt = $pdo->prepare("SELECT id, nombre FROM sedes WHERE nombre LIKE ? LIMIT 10");
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $resultados[] = ['tipo' => 'Sede', 'icono' => 'building', 'titulo' => $r['nombre'], 'sub' => '', 'href' => "sede_detalle.php?id={$r['id']}"];
    }

    $stmt = $pdo->prepare("SELECT id, proveedor_nombre, tipo FROM contratos WHERE proveedor_nombre LIKE ? OR numero_contrato LIKE ? LIMIT 10");
    $stmt->execute([$like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $resultados[] = ['tipo' => 'Contrato', 'icono' => 'file', 'titulo' => $r['proveedor_nombre'], 'sub' => $r['tipo'], 'href' => "contratos.php?editar={$r['id']}"];
    }
}

layout_inicio('Buscador Universal', 'Buscador Universal', '../');
?>
<h1><?= icon('search','icon-lg') ?> Buscador Universal</h1>
<p class="subtitle">Busca en todo el sistema al mismo tiempo: equipos, empleados, tickets, credenciales, sedes y contratos.</p>

<form method="get" class="toolbar">
    <input type="search" name="q" placeholder="Escribe un nombre, serial, documento, número de ticket..." value="<?= e($q) ?>" style="min-width:360px;font-size:15px;padding:10px 14px;" autofocus>
    <button type="submit"><?= icon('search') ?> Buscar</button>
</form>

<?php if ($q !== ''): ?>
<p class="small"><?= count($resultados) ?> resultado(s) para "<?= e($q) ?>"</p>

<?php if ($resultados): ?>
<div class="apps-grid">
    <?php foreach ($resultados as $r): ?>
    <a class="app-card" href="<?= e($r['href']) ?>" style="flex-direction:row;align-items:center;gap:14px;">
        <span class="app-icon" style="background:var(--accent-600);flex-shrink:0;"><?= icon($r['icono']) ?></span>
        <div style="flex:1;min-width:0;">
            <span class="app-categoria"><?= e($r['tipo']) ?></span>
            <h3 style="font-size:14px;"><?= e($r['titulo']) ?></h3>
            <p style="margin:0;"><?= e($r['sub']) ?></p>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="panel"><p class="small">Sin resultados para "<?= e($q) ?>". Intenta con otra palabra.</p></div>
<?php endif; ?>
<?php endif; ?>
<?php layout_fin(); ?>
