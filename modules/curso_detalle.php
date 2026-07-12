<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;

$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$id]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$curso) {
    layout_inicio('Curso no encontrado', 'Documentación', '../');
    echo '<div class="msg-error">Ese curso no existe.</div><a class="btn" href="documentacion.php">Volver</a>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'agregar_leccion') {
        $titulo = limpio($_POST['titulo'] ?? null);
        if ($titulo) {
            $orden = (int) $pdo->query("SELECT COALESCE(MAX(orden),0)+1 FROM lecciones WHERE curso_id = {$id}")->fetchColumn();
            $pdo->prepare("INSERT INTO lecciones (curso_id, titulo, contenido, orden) VALUES (?,?,?,?)")
                ->execute([$id, $titulo, limpio($_POST['contenido'] ?? null), $orden]);
            $msg = ['ok', 'Lección agregada.'];
        }
    }
    if ($accion === 'eliminar_leccion') {
        $pdo->prepare("DELETE FROM lecciones WHERE id = ? AND curso_id = ?")->execute([(int) $_POST['leccion_id'], $id]);
        $msg = ['ok', 'Lección eliminada.'];
    }
    if ($accion === 'marcar_completada') {
        $doc = limpio($_POST['documento'] ?? null);
        $nombre = limpio($_POST['nombre'] ?? null);
        $leccionId = (int) $_POST['leccion_id'];
        if ($doc) {
            try {
                $pdo->prepare("INSERT INTO progreso_cursos (leccion_id, empleado_documento, empleado_nombre) VALUES (?,?,?)")
                    ->execute([$leccionId, $doc, $nombre]);
                $msg = ['ok', '¡Lección marcada como completada!'];
            } catch (PDOException $e) {
                $msg = ['ok', 'Ya tenías esta lección marcada como completada.'];
            }
        } else {
            $msg = ['error', 'Escribe tu número de documento para registrar el avance.'];
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM lecciones WHERE curso_id = ? ORDER BY orden, id");
$stmt->execute([$id]);
$lecciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$progresoPorLeccion = [];
foreach ($lecciones as $l) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM progreso_cursos WHERE leccion_id = ?");
    $stmt->execute([$l['id']]);
    $progresoPorLeccion[$l['id']] = (int) $stmt->fetchColumn();
}

layout_inicio($curso['titulo'], 'Documentación', '../');
?>
<p class="small"><a href="documentacion.php">← Volver a Documentación</a></p>
<h1><?= icon('graduation','icon-lg') ?> <?= e($curso['titulo']) ?></h1>
<p class="subtitle"><?= e($curso['area']) ?> · <?= e($curso['descripcion']) ?></p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Agregar lección</h3>
    <form method="post">
        <input type="hidden" name="accion" value="agregar_leccion">
        <div class="grid-form">
            <div style="grid-column:span 3;"><label>Título *</label><input type="text" name="titulo" required></div>
        </div>
        <textarea name="contenido" rows="6" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Contenido de la lección (texto, pasos, enlaces...)"></textarea>
        <button type="submit">Agregar lección</button>
    </form>
</div>

<?php foreach ($lecciones as $i => $l): ?>
<div class="panel">
    <h3><?= $i+1 ?>. <?= e($l['titulo']) ?>
        <span class="small" style="float:right;"><?= $progresoPorLeccion[$l['id']] ?> completada(s)</span>
    </h3>
    <p style="white-space:pre-wrap;"><?= nl2br(e($l['contenido'])) ?></p>

    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="marcar_completada">
        <input type="hidden" name="leccion_id" value="<?= (int)$l['id'] ?>">
        <input type="text" name="documento" placeholder="Tu documento (cédula)" required>
        <input type="text" name="nombre" placeholder="Tu nombre">
        <button type="submit">Marcar como completada</button>
    </form>
    <form method="post" onsubmit="return confirm('¿Eliminar esta lección?');">
        <input type="hidden" name="accion" value="eliminar_leccion">
        <input type="hidden" name="leccion_id" value="<?= (int)$l['id'] ?>">
        <button type="submit" class="btn-danger" style="font-size:12px;padding:5px 12px;">Eliminar lección</button>
    </form>
</div>
<?php endforeach; ?>
<?php if (!$lecciones): ?><p class="small">Este curso todavía no tiene lecciones.</p><?php endif; ?>
<?php layout_fin(); ?>
