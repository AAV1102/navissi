<?php
// Portal de autogestión de proveedores: acceso por enlace con token, sin necesidad
// de cuenta en NAVISSI. El enlace se genera desde Contratos y Proveedores y expira
// en 30 días o al usarse (token de un solo uso extendido: se puede reutilizar hasta
// que expire, para permitir varias subidas del mismo proveedor).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/icons.php';
$pdo = db();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$stmt = $pdo->prepare("SELECT t.*, c.proveedor_nombre, c.numero_contrato, c.tipo FROM proveedores_portal_tokens t
    JOIN contratos c ON c.id = t.contrato_id WHERE t.token = ?");
$stmt->execute([$token]);
$acceso = $stmt->fetch(PDO::FETCH_ASSOC);
$valido = $acceso && (!$acceso['expira_en'] || $acceso['expira_en'] >= date('Y-m-d H:i:s'));

$enviado = false;
if ($valido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_requerir();
    $tipo = limpio($_POST['tipo'] ?? null) ?: 'FACTURA';
    $descripcion = limpio($_POST['descripcion'] ?? null);
    $archivoRuta = null; $archivoNombre = null;
    if (!empty($_FILES['archivo']['tmp_name']) && is_uploaded_file($_FILES['archivo']['tmp_name'])) {
        $dirLocal = __DIR__ . '/data/proveedores_uploads';
        if (!is_dir($dirLocal)) mkdir($dirLocal, 0777, true);
        $original = basename($_FILES['archivo']['name']);
        $seguro = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $original);
        $archivoNombre = $seguro;
        $archivoRuta = 'proveedores_uploads/' . uniqid() . '_' . $seguro;
        move_uploaded_file($_FILES['archivo']['tmp_name'], __DIR__ . '/data/' . $archivoRuta);
    }
    $pdo->prepare("INSERT INTO proveedores_actualizaciones (contrato_id, tipo, descripcion, archivo_ruta, archivo_nombre) VALUES (?,?,?,?,?)")
        ->execute([$acceso['contrato_id'], $tipo, $descripcion, $archivoRuta, $archivoNombre]);
    $pdo->prepare("UPDATE proveedores_portal_tokens SET usado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$acceso['id']]);
    $enviado = true;
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Portal de Proveedores - NAVISSI Grupo 10Z</title>
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
</head>
<body style="background:var(--bg);">
<div style="max-width:560px;margin:40px auto;padding:0 16px;">
    <h1><?= icon('upload','icon-lg') ?> Portal de Proveedores</h1>
    <?php if (!$valido): ?>
    <div class="msg-error">Este enlace no es válido o ya expiró. Pide a tu contacto en Grupo 10Z SAS que genere uno nuevo desde Contratos y Proveedores.</div>
    <?php else: ?>
    <p class="subtitle">Hola <strong><?= e($acceso['proveedor_nombre']) ?></strong> — sube facturas o actualiza información de tu contrato <?= $acceso['numero_contrato'] ? '#' . e($acceso['numero_contrato']) : '' ?> sin necesitar cuenta.</p>
    <?php if ($enviado): ?>
    <div class="msg-ok">¡Gracias! Tu actualización quedó registrada y será revisada por el equipo de Grupo 10Z.</div>
    <p><a href="portal_proveedor.php?token=<?= e($token) ?>">Enviar otra actualización</a></p>
    <?php else: ?>
    <div class="panel">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>Tipo de actualización *</label>
            <select name="tipo" required style="margin-bottom:10px;">
                <?php foreach (['FACTURA'=>'Factura','COTIZACION'=>'Cotización','ACTUALIZACION_PRECIOS'=>'Actualización de precios','SOPORTE_TECNICO'=>'Documento de soporte técnico','OTRO'=>'Otro'] as $v=>$l): ?>
                <option value="<?= $v ?>"><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <label>Descripción</label>
            <textarea name="descripcion" rows="3" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Ej. Factura de mantenimiento de mayo, cotización de renovación..."></textarea>
            <label>Archivo (PDF, imagen, Excel)</label>
            <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx" style="margin-bottom:14px;">
            <button type="submit"><?= icon('send') ?> Enviar</button>
        </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <p class="small" style="margin-top:20px;">NAVISSI · Grupo 10Z SAS</p>
</div>
</body>
</html>
