<?php
// Formulario público de postulación a vacantes - sin necesidad de cuenta en NAVISSI.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/icons.php';
// Arranca la sesión ANTES de imprimir cualquier HTML - csrf_token() la
// necesita para fijar la cookie, y eso falla en silencio si ya se enviaron
// bytes de HTML antes de llamarla (mismo bug encontrado y corregido antes
// en cotizacion_publica.php).
iniciar_sesion_segura();
$pdo = db();
$vacanteId = (int) ($_GET['vacante'] ?? 0);
$enviado = false;
$error = null;

$etapas = ['RECIBIDO' => 'Tu postulación fue recibida', 'ENTREVISTA' => 'En etapa de entrevista', 'PRUEBAS' => 'En etapa de pruebas técnicas', 'ESTUDIO_DOCUMENTOS' => 'En estudio de documentos', 'EXAMEN_MEDICO' => 'En examen médico', 'CONTRATADO' => '¡Felicitaciones, fuiste contratado/a!', 'RECHAZADO' => 'El proceso fue cerrado'];

$stmt = $pdo->prepare("SELECT * FROM vacantes WHERE id = ? AND estado != 'CERRADA'");
$stmt->execute([$vacanteId]);
$vacante = $stmt->fetch(PDO::FETCH_ASSOC);

// Consultar el estado de mi proceso: cualquiera con su documento puede ver en
// qué etapa va y sus próximas citas, sin necesitar escribir por correo o
// WhatsApp para preguntar "¿cómo va mi proceso?".
$documentoConsulta = trim((string) ($_GET['documento'] ?? ''));
$miCandidatura = null;
$misCitas = [];
if ($vacante && $documentoConsulta !== '') {
    $stmtCand = $pdo->prepare("SELECT * FROM candidatos WHERE vacante_id = ? AND documento = ? ORDER BY creado_en DESC LIMIT 1");
    $stmtCand->execute([$vacante['id'], $documentoConsulta]);
    $miCandidatura = $stmtCand->fetch(PDO::FETCH_ASSOC);
    if ($miCandidatura) {
        $stmtCitas = $pdo->prepare("SELECT * FROM candidatos_citas WHERE candidato_id = ? ORDER BY fecha_hora ASC");
        $stmtCitas->execute([$miCandidatura['id']]);
        $misCitas = $stmtCitas->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vacante) {
    csrf_requerir();
    $nombre = limpio($_POST['nombre'] ?? null);
    $email = limpio($_POST['email'] ?? null);
    $documento = limpio($_POST['documento'] ?? null);
    if (!$nombre || !$email || !$documento) {
        $error = 'Completa tu nombre, documento y correo (el documento lo necesitas después para consultar el estado de tu proceso).';
    } else {
        $cvRuta = null;
        $cvNombre = null;
        if (!empty($_FILES['cv']['tmp_name'])) {
            $dir = __DIR__ . '/data/candidatos_cv';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $original = basename($_FILES['cv']['name']);
            $seguro = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $original);
            $cvRuta = uniqid() . '_' . $seguro;
            move_uploaded_file($_FILES['cv']['tmp_name'], $dir . '/' . $cvRuta);
            $cvNombre = $original;
        }
        $pdo->prepare("INSERT INTO candidatos (vacante_id, nombre, documento, email, celular, cv_ruta, cv_nombre, estado) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$vacante['id'], $nombre, $documento, $email,
                limpio($_POST['celular'] ?? null), $cvRuta, $cvNombre, 'RECIBIDO']);
        $enviado = true;
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Postúlate - NAVISSI Grupo 10Z</title>
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
</head>
<body style="background:var(--bg);">
<div style="max-width:560px;margin:40px auto;padding:0 16px;">
    <h1><?= icon('briefcase','icon-lg') ?> Trabaja con nosotros</h1>
    <?php if (!$vacante): ?>
    <div class="msg-error">Esta vacante no existe o ya fue cerrada.</div>
    <?php elseif ($enviado): ?>
    <div class="msg-ok">¡Gracias, <?= e($nombre) ?>! Tu postulación para "<strong><?= e($vacante['titulo']) ?></strong>" fue recibida. Nuestro equipo de Talento Humano la revisará.</div>
    <p class="small">Guarda este link y tu documento — puedes volver cuando quieras a consultar en qué va tu proceso, sin necesidad de escribirnos:</p>
    <p><a href="postular.php?vacante=<?= (int) $vacanteId ?>&documento=<?= urlencode($documento) ?>">Ver el estado de mi proceso</a></p>
    <?php else: ?>

    <div class="panel">
        <h3><?= icon('search') ?> ¿Ya postulaste? Consulta tu proceso</h3>
        <form method="get" class="toolbar">
            <input type="hidden" name="vacante" value="<?= (int) $vacanteId ?>">
            <input type="text" name="documento" value="<?= e($documentoConsulta) ?>" placeholder="Tu número de documento" style="min-width:220px">
            <button type="submit">Consultar</button>
        </form>
        <?php if ($documentoConsulta !== ''): ?>
            <?php if (!$miCandidatura): ?>
            <p class="small" style="margin-top:10px;">No encontramos ninguna postulación con ese documento para esta vacante.</p>
            <?php else: ?>
            <div style="margin-top:12px;padding:12px;border:1px solid var(--line);border-radius:8px;">
                <span class="badge <?= $miCandidatura['estado']==='CONTRATADO'?'badge-activo':($miCandidatura['estado']==='RECHAZADO'?'badge-err':'badge-otro') ?>"><?= e($etapas[$miCandidatura['estado']] ?? $miCandidatura['estado']) ?></span>
                <?php
                $proximaCita = null;
                foreach ($misCitas as $c) { if ($c['estado'] === 'PENDIENTE') { $proximaCita = $c; break; } }
                ?>
                <?php if ($proximaCita): ?>
                <p class="small" style="margin-top:10px;"><strong>Tu próxima cita:</strong> <?= e($proximaCita['fecha_hora']) ?> ·
                    <?php if ($proximaCita['modalidad'] === 'VIRTUAL'): ?>
                    Virtual<?php if ($proximaCita['link_reunion']): ?> — <a href="<?= e($proximaCita['link_reunion']) ?>" target="_blank">Unirme a la reunión</a><?php else: ?> (el link se compartirá pronto)<?php endif; ?>
                    <?php else: ?>
                    Presencial<?php if ($proximaCita['lugar']): ?> — <?= e($proximaCita['lugar']) ?><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($proximaCita['notas']): ?><br><em><?= e($proximaCita['notas']) ?></em><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3><?= e($vacante['titulo']) ?> <span class="small">· <?= e($vacante['area']) ?></span></h3>
        <?php if ($vacante['descripcion']): ?><p class="small"><?= nl2br(e($vacante['descripcion'])) ?></p><?php endif; ?>
        <?php if ($vacante['requisitos']): ?><p class="small"><strong>Requisitos:</strong><br><?= nl2br(e($vacante['requisitos'])) ?></p><?php endif; ?>
    </div>
    <?php if ($error): ?><div class="msg-error"><?= e($error) ?></div><?php endif; ?>
    <div class="panel">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label>Nombre completo *</label><input type="text" name="nombre" required style="margin-bottom:10px;">
            <label>Documento *</label><input type="text" name="documento" required style="margin-bottom:10px;" placeholder="Lo necesitas para consultar tu proceso después">
            <label>Correo *</label><input type="email" name="email" required style="margin-bottom:10px;">
            <label>Celular</label><input type="text" name="celular" style="margin-bottom:10px;">
            <label>Hoja de vida (PDF/Word)</label><input type="file" name="cv" accept=".pdf,.doc,.docx" style="margin-bottom:10px;">
            <button type="submit"><?= icon('send') ?> Enviar postulación</button>
        </form>
    </div>
    <?php endif; ?>
    <p class="small" style="margin-top:20px;">NAVISSI · Grupo 10Z SAS</p>
</div>
</body>
</html>
