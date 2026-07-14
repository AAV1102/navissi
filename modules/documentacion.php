<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$puedeGestionarCursos = tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'DIRECTOR', 'RRHH', 'TI', 'COORDINADOR']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_curso' && $puedeGestionarCursos) {
    $titulo = limpio($_POST['titulo'] ?? null);
    $area = limpio($_POST['area'] ?? null);
    if ($titulo && $area) {
        $pdo->prepare("INSERT INTO cursos (area, titulo, descripcion) VALUES (?,?,?)")
            ->execute([$area, $titulo, limpio($_POST['descripcion'] ?? null)]);
        $msg = ['ok', 'Curso creado. Ahora agrégale lecciones.'];
    } else {
        $msg = ['error', 'Área y título son obligatorios.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_curso' && $puedeGestionarCursos) {
    $pdo->prepare("DELETE FROM cursos WHERE id = ?")->execute([(int) $_POST['id']]);
    $msg = ['ok', 'Curso eliminado.'];
}

// Adaptado por área, no globalizado: si el usuario tiene alcance limitado,
// solo ve los cursos de su área (más los de "General", si existen) - no puede
// navegar a las capacitaciones de otras áreas cambiando el filtro a mano.
$areaUsuario = alcance_area();
$areaFiltro = $areaUsuario !== null ? $areaUsuario : trim($_GET['area'] ?? '');
$sql = "SELECT c.*, (SELECT COUNT(*) FROM lecciones l WHERE l.curso_id = c.id) AS n_lecciones
        FROM cursos c WHERE c.estado = 'PUBLICADO'";
$params = [];
if ($areaUsuario !== null) {
    $sql .= " AND (c.area = ? OR c.area = 'General')";
    $params[] = $areaUsuario;
} elseif ($areaFiltro !== '') {
    $sql .= " AND c.area = ?";
    $params[] = $areaFiltro;
}
$sql .= " ORDER BY c.area, c.titulo";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$areas = $areaUsuario !== null ? [$areaUsuario]
    : array_column($pdo->query("SELECT DISTINCT area FROM cursos ORDER BY area")->fetchAll(PDO::FETCH_ASSOC), 'area');

layout_inicio('Documentación', 'Documentación', '../');
?>
<h1><?= icon('graduation','icon-lg') ?> Documentación y Capacitación</h1>
<p class="subtitle">Cursos y guías por área, con seguimiento de qué empleado ya completó cada lección.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if ($puedeGestionarCursos): ?>
<div class="panel">
    <h3>Nuevo curso / guía</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear_curso">
        <div class="grid-form">
            <div><label>Área *</label><input type="text" name="area" list="areas-dl" required placeholder="TI, RRHH, Comercial, Producción..."></div>
            <div style="grid-column: span 2;"><label>Título *</label><input type="text" name="titulo" required></div>
        </div>
        <datalist id="areas-dl"><?php foreach ($areas as $a): ?><option value="<?= e($a) ?>"><?php endforeach; ?></datalist>
        <textarea name="descripcion" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Descripción breve"></textarea>
        <button type="submit">Crear curso</button>
    </form>
</div>
<?php endif; ?>

<?php if ($areaUsuario === null): ?>
<form class="toolbar" method="get">
    <select name="area" onchange="this.form.submit()">
        <option value="">-- todas las áreas --</option>
        <?php foreach ($areas as $a): ?><option <?= $areaFiltro===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
</form>
<?php else: ?>
<p class="small"><?= icon('shield') ?> Viendo solo los cursos de tu área: <strong><?= e($areaUsuario) ?></strong> (y los generales).</p>
<?php endif; ?>

<div class="cards">
    <?php foreach ($cursos as $c): ?>
    <div class="card">
        <div class="label" style="text-transform:uppercase;font-weight:600;color:#1f4e78;"><?= e($c['area']) ?></div>
        <div style="font-size:17px;font-weight:600;margin:6px 0;"><a href="curso_detalle.php?id=<?= (int)$c['id'] ?>"><?= e($c['titulo']) ?></a></div>
        <div class="small"><?= e($c['descripcion']) ?></div>
        <div class="small" style="margin-top:8px;"><?= (int)$c['n_lecciones'] ?> lección(es)</div>
        <a href="curso_detalle.php?id=<?= (int)$c['id'] ?>" class="btn" style="margin-top:10px;font-size:12px;padding:6px 12px;">Abrir curso</a>
        <?php if ($puedeGestionarCursos): ?>
        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este curso y sus lecciones?');">
            <input type="hidden" name="accion" value="eliminar_curso">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn-danger" style="margin-top:10px;font-size:12px;padding:6px 12px;">Eliminar</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$cursos): ?><p class="small">Aún no hay cursos. Crea el primero arriba.</p><?php endif; ?>
</div>
<?php layout_fin(); ?>
