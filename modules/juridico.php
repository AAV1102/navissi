<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO'])) {
    layout_inicio('Jurídico', 'Jurídico', '../');
    echo '<div class="msg-error">No tienes permiso para ver el módulo Jurídico.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $titulo = limpio($_POST['titulo'] ?? null);
        if ($titulo) {
            $pdo->prepare("INSERT INTO casos_juridicos (titulo, tipo, contraparte, responsable, fecha_apertura, descripcion, creado_por) VALUES (?,?,?,?,?,?,?)")
                ->execute([$titulo, limpio($_POST['tipo'] ?? null), limpio($_POST['contraparte'] ?? null),
                    limpio($_POST['responsable'] ?? null), limpio($_POST['fecha_apertura'] ?? null) ?: date('Y-m-d'),
                    limpio($_POST['descripcion'] ?? null), $u['nombre']]);
            $msg = ['ok', 'Caso jurídico creado.'];
        }
    } elseif ($accion === 'actualizar') {
        $id = (int) $_POST['id'];
        $estado = limpio($_POST['estado'] ?? null);
        $cierre = $estado === 'CERRADO' ? "CURRENT_TIMESTAMP" : "NULL";
        $pdo->prepare("UPDATE casos_juridicos SET estado = ?, fecha_cierre = {$cierre} WHERE id = ?")->execute([$estado, $id]);
        $msg = ['ok', 'Caso actualizado.'];
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM casos_juridicos WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$filtroEstado = trim($_GET['estado'] ?? '');
$sql = "SELECT * FROM casos_juridicos" . ($filtroEstado ? " WHERE estado = ?" : "") . " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($filtroEstado ? [$filtroEstado] : []);
$casos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$abiertos = (int) $pdo->query("SELECT COUNT(*) FROM casos_juridicos WHERE estado != 'CERRADO'")->fetchColumn();

layout_inicio('Jurídico', 'Jurídico', '../');
?>
<h1><?= icon('file','icon-lg') ?> Gestión Jurídica</h1>
<p class="subtitle">Casos legales, contractuales y litigiosos de la empresa.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= count($casos) ?></div><div class="label">Total (filtro actual)</div></div>
    <div class="card" style="border-left-color:#c98a1f"><div class="num"><?= $abiertos ?></div><div class="label">Abiertos</div></div>
</div>

<div class="panel">
    <h3><?= icon('plus') ?> Nuevo caso</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>Título *</label><input type="text" name="titulo" required></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach (['CONTRACTUAL','LABORAL','CIVIL','COMERCIAL','PROPIEDAD_INTELECTUAL','OTRO'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Contraparte</label><input type="text" name="contraparte"></div>
            <div><label>Responsable</label><input type="text" name="responsable"></div>
            <div><label>Fecha apertura</label><input type="date" name="fecha_apertura" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <textarea name="descripcion" rows="3" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Descripción del caso"></textarea>
        <button type="submit">Crear caso</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="estado" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <?php foreach (['ABIERTO','EN_PROCESO','CERRADO'] as $e): ?><option value="<?= $e ?>" <?= $filtroEstado===$e?'selected':'' ?>><?= $e ?></option><?php endforeach; ?>
    </select>
</form>

<?php foreach ($casos as $c): ?>
<div class="panel">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <div>
            <strong><?= e($c['titulo']) ?></strong> <span class="small">· <?= e($c['tipo']) ?></span>
            <br><span class="small">Contraparte: <?= e($c['contraparte']) ?: '—' ?> · Responsable: <?= e($c['responsable']) ?: '—' ?> · Abierto: <?= e($c['fecha_apertura']) ?></span>
        </div>
        <span class="badge <?= $c['estado']==='CERRADO'?'badge-activo':'badge-warn' ?>"><?= e($c['estado']) ?></span>
    </div>
    <p style="margin:10px 0;"><?= nl2br(e($c['descripcion'])) ?></p>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="actualizar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <select name="estado" onchange="this.form.requestSubmit()">
            <?php foreach (['ABIERTO','EN_PROCESO','CERRADO'] as $e): ?><option value="<?= $e ?>" <?= $c['estado']===$e?'selected':'' ?>><?= $e ?></option><?php endforeach; ?>
        </select>
        <button type="submit">Guardar estado</button>
    </form>
    <form method="post" class="inline" onsubmit="return confirm('¿Eliminar este caso?');">
        <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
    </form>
</div>
<?php endforeach; ?>
<?php if (!$casos): ?><p class="small">No hay casos registrados.</p><?php endif; ?>
<?php layout_fin(); ?>
