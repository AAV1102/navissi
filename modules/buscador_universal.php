<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$q = trim($_GET['q'] ?? '');
$tipoFiltro = trim($_GET['tipo'] ?? '');
$resultados = [];
$area = alcance_area();

$tiposDisponibles = ['Equipo', 'Empleado', 'Ticket', 'Credencial', 'Sede', 'Contrato', 'Documento RRHH', 'Curso'];

if ($q !== '' && strlen($q) >= 2) {
    $like = "%{$q}%";

    if ($tipoFiltro === '' || $tipoFiltro === 'Equipo') {
        $sql = "SELECT id, serial, marca, modelo, asignado_a FROM inventario WHERE (serial LIKE ? OR placa LIKE ? OR asignado_a LIKE ? OR marca LIKE ? OR modelo LIKE ?)";
        $params = [$like, $like, $like, $like, $like];
        if ($area !== null) { $sql .= " AND area = ?"; $params[] = $area; }
        $sql .= " LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Equipo', 'icono' => 'inventory', 'titulo' => "{$r['marca']} {$r['modelo']} ({$r['serial']})", 'sub' => $r['asignado_a'] ?: 'Sin asignar', 'href' => "equipo_detalle.php?id={$r['id']}"];
        }
    }

    if ($tipoFiltro === '' || $tipoFiltro === 'Empleado') {
        $sql = "SELECT id, nombres, documento, cargo FROM empleados WHERE (nombres LIKE ? OR documento LIKE ? OR cargo LIKE ?)";
        $params = [$like, $like, $like];
        if ($area !== null) { $sql .= " AND area = ?"; $params[] = $area; }
        $sql .= " LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Empleado', 'icono' => 'users', 'titulo' => $r['nombres'], 'sub' => "{$r['documento']} · {$r['cargo']}", 'href' => "empleado_detalle.php?id={$r['id']}"];
        }
    }

    if ($tipoFiltro === '' || $tipoFiltro === 'Ticket') {
        $sql = "SELECT id, titulo, estado, solicitante FROM tickets WHERE (titulo LIKE ? OR descripcion LIKE ? OR solicitante LIKE ?)";
        $params = [$like, $like, $like];
        if ($area !== null) { $sql .= " AND solicitante_area = ?"; $params[] = $area; }
        $sql .= " ORDER BY id DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Ticket', 'icono' => 'ticket', 'titulo' => "#{$r['id']} {$r['titulo']}", 'sub' => "{$r['estado']} · {$r['solicitante']}", 'href' => "ticket_detalle.php?id={$r['id']}"];
        }
    }

    if (($tipoFiltro === '' || $tipoFiltro === 'Credencial') && tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI'])) {
        $stmt = $pdo->prepare("SELECT id, sistema, usuario, nombre FROM credenciales WHERE usuario LIKE ? OR nombre LIKE ? OR sistema LIKE ? LIMIT 10");
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Credencial', 'icono' => 'key', 'titulo' => "{$r['sistema']} · {$r['usuario']}", 'sub' => $r['nombre'] ?: '', 'href' => "credenciales.php?editar={$r['id']}"];
        }
    }

    if ($tipoFiltro === '' || $tipoFiltro === 'Sede') {
        $stmt = $pdo->prepare("SELECT id, nombre FROM sedes WHERE nombre LIKE ? LIMIT 10");
        $stmt->execute([$like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Sede', 'icono' => 'building', 'titulo' => $r['nombre'], 'sub' => '', 'href' => "sede_detalle.php?id={$r['id']}"];
        }
    }

    if ($tipoFiltro === '' || $tipoFiltro === 'Contrato') {
        $stmt = $pdo->prepare("SELECT id, proveedor_nombre, tipo FROM contratos WHERE proveedor_nombre LIKE ? OR numero_contrato LIKE ? LIMIT 10");
        $stmt->execute([$like, $like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Contrato', 'icono' => 'file', 'titulo' => $r['proveedor_nombre'], 'sub' => $r['tipo'], 'href' => "contratos.php?editar={$r['id']}"];
        }
    }

    if (($tipoFiltro === '' || $tipoFiltro === 'Documento RRHH') && tiene_rol(['SUPER_ADMIN', 'ADMIN', 'GERENCIA', 'CEO', 'DIRECTOR', 'RRHH'])) {
        $stmt = $pdo->prepare("SELECT id, tipo, nombre_archivo, empleado_documento FROM documentos_rrhh WHERE nombre_archivo LIKE ? OR empleado_documento LIKE ? OR tipo LIKE ? LIMIT 10");
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Documento RRHH', 'icono' => 'file', 'titulo' => $r['nombre_archivo'], 'sub' => "{$r['tipo']} · {$r['empleado_documento']}", 'href' => "rrhh_documentos.php?documento=" . urlencode($r['empleado_documento'])];
        }
    }

    if ($tipoFiltro === '' || $tipoFiltro === 'Curso') {
        $sql = "SELECT id, titulo, area FROM cursos WHERE (titulo LIKE ? OR descripcion LIKE ?)";
        $params = [$like, $like];
        if ($area !== null) { $sql .= " AND (area = ? OR area = 'General')"; $params[] = $area; }
        $sql .= " LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resultados[] = ['tipo' => 'Curso', 'icono' => 'graduation', 'titulo' => $r['titulo'], 'sub' => $r['area'], 'href' => "curso_detalle.php?id={$r['id']}"];
        }
    }
}

layout_inicio('Buscador Universal', 'Buscador Universal', '../');
?>
<h1><?= icon('search','icon-lg') ?> Buscador Universal</h1>
<p class="subtitle">Busca en todo el sistema al mismo tiempo: equipos, empleados, tickets, credenciales, sedes, contratos, documentos y cursos<?= $area !== null ? ' — limitado a tu área: ' . e($area) : '' ?>.</p>

<form method="get" class="toolbar">
    <input type="search" name="q" placeholder="Escribe un nombre, serial, documento, número de ticket..." value="<?= e($q) ?>" style="min-width:320px;font-size:15px;padding:10px 14px;" autofocus>
    <select name="tipo" style="font-size:14px;">
        <option value="">-- todo --</option>
        <?php foreach ($tiposDisponibles as $t): ?><option <?= $tipoFiltro===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
    </select>
    <button type="submit"><?= icon('search') ?> Buscar</button>
</form>

<?php if ($q !== ''): ?>
<p class="small"><?= count($resultados) ?> resultado(s) para "<?= e($q) ?>"<?= $tipoFiltro ? ' en ' . e($tipoFiltro) : '' ?></p>

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
