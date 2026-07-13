<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;
$u = usuario_actual();
$dirCursos = __DIR__ . '/../data/documentos/cursos';
if (!is_dir($dirCursos)) mkdir($dirCursos, 0777, true);

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
            $archivoRuta = null; $archivoNombre = null;
            $tipoLeccion = limpio($_POST['tipo'] ?? null) ?: 'TEXTO';
            if (!empty($_FILES['archivo']['tmp_name'])) {
                $original = basename($_FILES['archivo']['name']);
                $seguro = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $original);
                $archivoRuta = uniqid() . '_' . $seguro;
                move_uploaded_file($_FILES['archivo']['tmp_name'], $dirCursos . '/' . $archivoRuta);
                $archivoNombre = $original;
            }
            $pdo->prepare("INSERT INTO lecciones (curso_id, titulo, contenido, orden, tipo, archivo_ruta, archivo_nombre) VALUES (?,?,?,?,?,?,?)")
                ->execute([$id, $titulo, limpio($_POST['contenido'] ?? null), $orden, $tipoLeccion, $archivoRuta, $archivoNombre]);
            $msg = ['ok', 'Lección agregada.'];
        }
    }
    if ($accion === 'eliminar_leccion') {
        $pdo->prepare("DELETE FROM lecciones WHERE id = ? AND curso_id = ?")->execute([(int) $_POST['leccion_id'], $id]);
        $msg = ['ok', 'Lección eliminada.'];
    }
    if ($accion === 'marcar_completada' && $u && $u['documento']) {
        $leccionId = (int) $_POST['leccion_id'];
        try {
            $pdo->prepare("INSERT INTO progreso_cursos (leccion_id, empleado_documento, empleado_nombre) VALUES (?,?,?)")
                ->execute([$leccionId, $u['documento'], $u['nombre']]);
            $msg = ['ok', '¡Lección marcada como completada!'];
        } catch (PDOException $e) {
            $msg = ['ok', 'Ya tenías esta lección marcada como completada.'];
        }
    }
    // Base simple de examen: preguntas de opción múltiple en formato de texto,
    // una por línea: "Pregunta | Respuesta correcta | Opción incorrecta | Opción incorrecta..."
    if ($accion === 'crear_examen') {
        $tituloExamen = limpio($_POST['titulo_examen'] ?? null) ?: 'Examen del curso';
        $notaMinima = (int) ($_POST['nota_minima'] ?? 60);
        $pdo->prepare("INSERT INTO examenes (curso_id, titulo, nota_minima) VALUES (?,?,?)")->execute([$id, $tituloExamen, $notaMinima]);
        $examenId = $pdo->lastInsertId();
        $lineas = preg_split('/\r\n|\r|\n/', trim($_POST['preguntas'] ?? ''));
        $orden = 0;
        foreach ($lineas as $linea) {
            $partes = array_map('trim', explode('|', $linea));
            if (count($partes) < 2) continue;
            $pdo->prepare("INSERT INTO examen_preguntas (examen_id, texto, orden) VALUES (?,?,?)")->execute([$examenId, $partes[0], $orden++]);
            $preguntaId = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO examen_opciones (pregunta_id, texto, es_correcta) VALUES (?,?,1)")->execute([$preguntaId, $partes[1]]);
            for ($i = 2; $i < count($partes); $i++) {
                if ($partes[$i] === '') continue;
                $pdo->prepare("INSERT INTO examen_opciones (pregunta_id, texto, es_correcta) VALUES (?,?,0)")->execute([$preguntaId, $partes[$i]]);
            }
        }
        $msg = ['ok', 'Examen creado con ' . $orden . ' pregunta(s).'];
    }
    if ($accion === 'eliminar_examen') {
        $pdo->prepare("DELETE FROM examenes WHERE id = ? AND curso_id = ?")->execute([(int) $_POST['examen_id'], $id]);
        $msg = ['ok', 'Examen eliminado.'];
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
$miCompletado = [];
if ($u && $u['documento']) {
    $stmt = $pdo->prepare("SELECT leccion_id FROM progreso_cursos WHERE empleado_documento = ?");
    $stmt->execute([$u['documento']]);
    $miCompletado = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$examen = null;
$stmtEx = $pdo->prepare("SELECT * FROM examenes WHERE curso_id = ? LIMIT 1");
$stmtEx->execute([$id]);
$examen = $stmtEx->fetch(PDO::FETCH_ASSOC);
if ($examen) {
    $examen['n_preguntas'] = (int) $pdo->query("SELECT COUNT(*) FROM examen_preguntas WHERE examen_id = " . (int)$examen['id'])->fetchColumn();
}

layout_inicio($curso['titulo'], 'Documentación', '../');
?>
<p class="small"><a href="documentacion.php">← Volver a Documentación</a></p>
<h1><?= icon('graduation','icon-lg') ?> <?= e($curso['titulo']) ?></h1>
<p class="subtitle"><?= e($curso['area']) ?> · <?= e($curso['descripcion']) ?></p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Agregar lección</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="agregar_leccion">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>Título *</label><input type="text" name="titulo" required></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <option value="TEXTO">Texto / guía</option>
                    <option value="VIDEO">Video</option>
                    <option value="PDF">PDF / documento</option>
                </select>
            </div>
        </div>
        <textarea name="contenido" rows="6" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Contenido de la lección (texto, pasos, enlaces...)"></textarea>
        <label class="small">Archivo (video o PDF, si el tipo lo requiere)</label><br>
        <input type="file" name="archivo" style="margin-bottom:10px;">
        <br><button type="submit">Agregar lección</button>
    </form>
</div>

<?php foreach ($lecciones as $i => $l): $yaCompletada = in_array($l['id'], $miCompletado, true); ?>
<div class="panel">
    <h3><?= $i+1 ?>. <?= e($l['titulo']) ?> <?php if (!empty($l['tipo']) && $l['tipo'] !== 'TEXTO'): ?><span class="badge badge-otro"><?= e($l['tipo']) ?></span><?php endif; ?>
        <span class="small" style="float:right;"><?= $progresoPorLeccion[$l['id']] ?> completada(s)</span>
    </h3>
    <p style="white-space:pre-wrap;"><?= nl2br(e($l['contenido'])) ?></p>
    <?php if (!empty($l['archivo_ruta'])): ?>
        <?php if ($l['tipo'] === 'VIDEO'): ?>
        <video controls style="max-width:100%;border-radius:8px;margin:10px 0;"><source src="descargar_leccion.php?id=<?= (int)$l['id'] ?>"></video>
        <?php else: ?>
        <p><a class="btn btn-secondary" href="descargar_leccion.php?id=<?= (int)$l['id'] ?>" target="_blank">📄 <?= e($l['archivo_nombre']) ?></a></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($u && $u['documento']): ?>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="marcar_completada">
        <input type="hidden" name="leccion_id" value="<?= (int)$l['id'] ?>">
        <?php if ($yaCompletada): ?>
        <span class="badge badge-activo"><?= icon('check') ?> Ya la completaste</span>
        <?php else: ?>
        <button type="submit">Marcar como completada</button>
        <?php endif; ?>
    </form>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('¿Eliminar esta lección?');">
        <input type="hidden" name="accion" value="eliminar_leccion">
        <input type="hidden" name="leccion_id" value="<?= (int)$l['id'] ?>">
        <button type="submit" class="btn-danger" style="font-size:12px;padding:5px 12px;">Eliminar lección</button>
    </form>
</div>
<?php endforeach; ?>
<?php if (!$lecciones): ?><p class="small">Este curso todavía no tiene lecciones.</p><?php endif; ?>

<div class="panel" style="border-left:4px solid var(--accent-600);">
    <h3><?= icon('check') ?> Examen del curso</h3>
    <?php if ($examen): ?>
        <p><strong><?= e($examen['titulo']) ?></strong> · <?= $examen['n_preguntas'] ?> pregunta(s) · nota mínima <?= (int)$examen['nota_minima'] ?>%</p>
        <a class="btn" href="examen_tomar.php?id=<?= (int)$examen['id'] ?>">Presentar examen</a>
        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este examen y sus resultados?');">
            <input type="hidden" name="accion" value="eliminar_examen">
            <input type="hidden" name="examen_id" value="<?= (int)$examen['id'] ?>">
            <button type="submit" class="btn-danger" style="margin-left:6px;">Eliminar examen</button>
        </form>
    <?php else: ?>
        <p class="small">Base simple de examen de opción múltiple - una pregunta por línea, así: <code>Pregunta | Respuesta correcta | Opción incorrecta 1 | Opción incorrecta 2</code></p>
        <form method="post">
            <input type="hidden" name="accion" value="crear_examen">
            <div class="grid-form">
                <div style="grid-column:span 2;"><label>Título del examen</label><input type="text" name="titulo_examen" placeholder="Examen final del curso"></div>
                <div><label>Nota mínima para aprobar (%)</label><input type="number" name="nota_minima" value="60" min="1" max="100"></div>
            </div>
            <textarea name="preguntas" rows="6" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin:10px 0;" placeholder="¿Cuál es la contraseña que nunca debes compartir? | La de tu usuario | Tu número de cédula | El nombre de tu jefe"></textarea>
            <button type="submit">Crear examen</button>
        </form>
    <?php endif; ?>
</div>
<?php layout_fin(); ?>
