<?php
// Formulario público de postulación a vacantes - sin necesidad de cuenta en NAVISSI.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/icons.php';
$pdo = db();
$vacanteId = (int) ($_GET['vacante'] ?? 0);
$enviado = false;
$error = null;

$stmt = $pdo->prepare("SELECT * FROM vacantes WHERE id = ? AND estado != 'CERRADA'");
$stmt->execute([$vacanteId]);
$vacante = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vacante) {
    $nombre = limpio($_POST['nombre'] ?? null);
    $email = limpio($_POST['email'] ?? null);
    if ($nombre && $email) {
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
            ->execute([$vacante['id'], $nombre, limpio($_POST['documento'] ?? null), $email,
                limpio($_POST['celular'] ?? null), $cvRuta, $cvNombre, 'RECIBIDO']);
        $enviado = true;
    } else {
        $error = 'Completa tu nombre y correo.';
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
    <div class="msg-ok">¡Gracias, <?= e($nombre) ?>! Tu postulación para "<?= e($vacante['titulo']) ?>" fue recibida. Nuestro equipo de Talento Humano la revisará.</div>
    <?php else: ?>
    <div class="panel">
        <h3><?= e($vacante['titulo']) ?> <span class="small">· <?= e($vacante['area']) ?></span></h3>
        <?php if ($vacante['descripcion']): ?><p class="small"><?= nl2br(e($vacante['descripcion'])) ?></p><?php endif; ?>
        <?php if ($vacante['requisitos']): ?><p class="small"><strong>Requisitos:</strong><br><?= nl2br(e($vacante['requisitos'])) ?></p><?php endif; ?>
    </div>
    <?php if ($error): ?><div class="msg-error"><?= e($error) ?></div><?php endif; ?>
    <div class="panel">
        <form method="post" enctype="multipart/form-data">
            <label>Nombre completo *</label><input type="text" name="nombre" required style="margin-bottom:10px;">
            <label>Documento</label><input type="text" name="documento" style="margin-bottom:10px;">
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
