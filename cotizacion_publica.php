<?php
// Portal público de cotizaciones: cualquier proveedor externo (sin cuenta en
// NAVISSI) entra por el link con token que le compartió el área que solicitó
// la cotización, sube su propuesta + documentos, y queda registrada bajo esa
// solicitud para que el área responsable la revise.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/icons.php';
// Arranca la sesión ANTES de imprimir cualquier HTML - csrf_token() la necesita
// para fijar la cookie de sesión, y eso falla en silencio si ya se enviaron
// encabezados (bytes de HTML) antes de llamarla.
iniciar_sesion_segura();
$pdo = db();

$token = trim($_GET['t'] ?? $_POST['token'] ?? '');
$stmt = $pdo->prepare("SELECT * FROM cotizaciones_solicitudes WHERE token_publico = ?");
$stmt->execute([$token]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
$valido = $solicitud && $solicitud['estado'] === 'ABIERTA';

$enviado = false;
if ($valido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_requerir();
    $nombre = limpio($_POST['proveedor_nombre'] ?? null);
    if (!$nombre) {
        $error = 'El nombre de la empresa/proveedor es obligatorio.';
    } else {
        $pdo->prepare("INSERT INTO cotizaciones_respuestas (solicitud_id, proveedor_nombre, proveedor_nit, proveedor_contacto, proveedor_email, proveedor_telefono, valor_cotizado, validez_dias, observaciones)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([
                $solicitud['id'], $nombre, limpio($_POST['proveedor_nit'] ?? null), limpio($_POST['proveedor_contacto'] ?? null),
                filter_var($_POST['proveedor_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null, limpio($_POST['proveedor_telefono'] ?? null),
                is_numeric($_POST['valor_cotizado'] ?? null) ? (float) $_POST['valor_cotizado'] : null,
                is_numeric($_POST['validez_dias'] ?? null) ? (int) $_POST['validez_dias'] : null,
                limpio($_POST['observaciones'] ?? null),
            ]);
        $respuestaId = (int) $pdo->lastInsertId();

        $dirAdj = __DIR__ . '/data/cotizaciones_adjuntos';
        if (!is_dir($dirAdj)) mkdir($dirAdj, 0777, true);
        $permitidos = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];
        if (!empty($_FILES['adjuntos']['tmp_name'][0])) {
            foreach ($_FILES['adjuntos']['tmp_name'] as $i => $tmp) {
                if (!$tmp || !is_uploaded_file($tmp)) continue;
                $tamano = (int) ($_FILES['adjuntos']['size'][$i] ?? 0);
                if ($tamano <= 0 || $tamano > 15 * 1024 * 1024) continue;
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
                if (!isset($permitidos[$mime])) continue;
                $rutaGuardada = bin2hex(random_bytes(18)) . '.' . $permitidos[$mime];
                if (move_uploaded_file($tmp, $dirAdj . '/' . $rutaGuardada)) {
                    $pdo->prepare("INSERT INTO cotizaciones_adjuntos (respuesta_id, nombre_archivo, ruta, tipo_mime) VALUES (?,?,?,?)")
                        ->execute([$respuestaId, basename($_FILES['adjuntos']['name'][$i]), $rutaGuardada, $mime]);
                }
            }
        }
        $pdo->prepare("INSERT INTO cotizaciones_comentarios (solicitud_id, respuesta_id, autor, comentario) VALUES (?,?,?,?)")
            ->execute([$solicitud['id'], $respuestaId, $nombre, 'Cotización recibida del proveedor.']);
        $enviado = true;
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cotización - NAVISSI Grupo 10Z</title>
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
</head>
<body style="background:var(--bg);">
<div style="max-width:620px;margin:40px auto;padding:0 16px;">
    <h1><?= icon('dollar','icon-lg') ?> Enviar Cotización</h1>
    <?php if (!$solicitud): ?>
    <div class="msg-error">Este enlace no es válido. Pide a tu contacto en Grupo 10Z SAS que te comparta uno nuevo.</div>
    <?php elseif ($solicitud['estado'] !== 'ABIERTA'): ?>
    <div class="msg-error">Esta solicitud de cotización ya está cerrada y no acepta más propuestas. Contacta a Grupo 10Z SAS si crees que es un error.</div>
    <?php elseif ($enviado): ?>
    <div class="msg-ok">¡Gracias, <?= e($_POST['proveedor_nombre'] ?? '') ?>! Tu cotización para "<strong><?= e($solicitud['titulo']) ?></strong>" quedó registrada. El equipo de Grupo 10Z la revisará y te contactará.</div>
    <?php else: ?>
    <div class="panel">
        <h3><?= e($solicitud['titulo']) ?></h3>
        <?php if ($solicitud['descripcion']): ?><p class="small"><?= nl2br(e($solicitud['descripcion'])) ?></p><?php endif; ?>
        <?php if ($solicitud['fecha_limite']): ?><p class="small">Fecha límite para cotizar: <strong><?= e($solicitud['fecha_limite']) ?></strong></p><?php endif; ?>
        <?php if (!empty($error)): ?><div class="msg-error"><?= e($error) ?></div><?php endif; ?>
    </div>
    <div class="panel">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="grid-form">
                <div style="grid-column:span 2;"><label>Empresa / Proveedor *</label><input type="text" name="proveedor_nombre" required></div>
                <div><label>NIT / Documento</label><input type="text" name="proveedor_nit"></div>
                <div><label>Persona de contacto</label><input type="text" name="proveedor_contacto"></div>
                <div><label>Correo</label><input type="email" name="proveedor_email"></div>
                <div><label>Teléfono</label><input type="text" name="proveedor_telefono"></div>
                <div><label>Valor cotizado (COP)</label><input type="number" step="0.01" name="valor_cotizado"></div>
                <div><label>Validez de la oferta (días)</label><input type="number" name="validez_dias"></div>
            </div>
            <label style="margin-top:10px;display:block;">Observaciones / detalle de la propuesta</label>
            <textarea name="observaciones" rows="4" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;font-family:inherit;margin:6px 0 14px;" placeholder="Describe qué incluye tu cotización, tiempos de entrega, condiciones..."></textarea>
            <label>Adjuntar documentos (cotización en PDF/Excel, fichas técnicas, etc.)</label>
            <input type="file" name="adjuntos[]" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx" style="margin:6px 0 14px;">
            <button type="submit"><?= icon('send') ?> Enviar cotización</button>
        </form>
    </div>
    <?php endif; ?>
    <p class="small" style="margin-top:20px;">NAVISSI · Grupo 10Z SAS</p>
</div>
</body>
</html>
