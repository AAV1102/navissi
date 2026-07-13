<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
requiere_login('../');
$u = usuario_actual();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;
$resultado = null;

$stmt = $pdo->prepare("SELECT e.*, c.titulo AS curso_titulo, c.id AS curso_id FROM examenes e JOIN cursos c ON c.id = e.curso_id WHERE e.id = ?");
$stmt->execute([$id]);
$examen = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$examen) {
    layout_inicio('Examen no encontrado', 'Documentación', '../');
    echo '<div class="msg-error">Ese examen no existe.</div><a class="btn" href="documentacion.php">Volver</a>';
    layout_fin();
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM examen_preguntas WHERE examen_id = ? ORDER BY orden, id");
$stmt->execute([$id]);
$preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($preguntas as &$p) {
    $stmtOp = $pdo->prepare("SELECT * FROM examen_opciones WHERE pregunta_id = ? ORDER BY RANDOM()");
    $stmtOp->execute([$p['id']]);
    $p['opciones'] = $stmtOp->fetchAll(PDO::FETCH_ASSOC);
}
unset($p);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$u['documento']) {
    $msg = ['error', 'Tu usuario no tiene documento vinculado - pide a RRHH que lo complete antes de poder presentar el examen.'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correctas = 0;
    foreach ($preguntas as $p) {
        $opcionElegida = (int) ($_POST['pregunta_' . $p['id']] ?? 0);
        $stmtCorrecta = $pdo->prepare("SELECT id FROM examen_opciones WHERE pregunta_id = ? AND es_correcta = 1");
        $stmtCorrecta->execute([$p['id']]);
        if ($opcionElegida === (int) $stmtCorrecta->fetchColumn()) $correctas++;
    }
    $puntaje = $preguntas ? (int) round(($correctas / count($preguntas)) * 100) : 0;
    $aprobado = $puntaje >= (int) $examen['nota_minima'] ? 1 : 0;
    $pdo->prepare("INSERT INTO examen_resultados (examen_id, empleado_documento, empleado_nombre, puntaje, aprobado) VALUES (?,?,?,?,?)")
        ->execute([$id, $u['documento'], $u['nombre'], $puntaje, $aprobado]);
    hoja_vida_registrar($pdo, 'EMPLEADO', $u['documento'], 'EXAMEN_PRESENTADO', "{$examen['titulo']}: {$puntaje}%", $u['nombre']);
    $resultado = ['puntaje' => $puntaje, 'aprobado' => $aprobado, 'correctas' => $correctas, 'total' => count($preguntas)];
}

layout_inicio($examen['titulo'], 'Documentación', '../');
?>
<p class="small"><a href="curso_detalle.php?id=<?= (int)$examen['curso_id'] ?>">← Volver al curso</a></p>
<h1><?= icon('check','icon-lg') ?> <?= e($examen['titulo']) ?></h1>
<p class="subtitle">Curso: <?= e($examen['curso_titulo']) ?> · Nota mínima para aprobar: <?= (int)$examen['nota_minima'] ?>%</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if ($resultado): ?>
<div class="panel" style="text-align:center;">
    <h2><?= $resultado['puntaje'] ?>%</h2>
    <p><?= $resultado['correctas'] ?> de <?= $resultado['total'] ?> correctas</p>
    <p><span class="badge <?= $resultado['aprobado'] ? 'badge-activo' : 'badge-err' ?>"><?= $resultado['aprobado'] ? '✓ Aprobado' : '✗ No aprobado' ?></span></p>
    <a class="btn" href="curso_detalle.php?id=<?= (int)$examen['curso_id'] ?>">Volver al curso</a>
    <?php if (!$resultado['aprobado']): ?><a class="btn btn-secondary" href="examen_tomar.php?id=<?= $id ?>">Intentar de nuevo</a><?php endif; ?>
</div>
<?php else: ?>
<form method="post">
    <?php foreach ($preguntas as $i => $p): ?>
    <div class="panel">
        <h3><?= $i+1 ?>. <?= e($p['texto']) ?></h3>
        <?php foreach ($p['opciones'] as $o): ?>
        <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin:6px 0;">
            <input type="radio" name="pregunta_<?= (int)$p['id'] ?>" value="<?= (int)$o['id'] ?>" required style="width:18px;height:18px;">
            <?= e($o['texto']) ?>
        </label>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$preguntas): ?><p class="small">Este examen todavía no tiene preguntas.</p><?php else: ?>
    <button type="submit" style="position:sticky;bottom:16px;"><?= icon('check') ?> Enviar respuestas</button>
    <?php endif; ?>
</form>
<?php endif; ?>
<?php layout_fin(); ?>
