<?php
// Formulario público de PQRS - sin necesidad de cuenta en NAVISSI.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/icons.php';
$pdo = db();
$enviado = false;
$folio = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpio($_POST['nombre'] ?? null);
    $descripcion = limpio($_POST['descripcion'] ?? null);
    if ($nombre && $descripcion) {
        $pdo->prepare("INSERT INTO pqrs (tipo, cliente_nombre, cliente_documento, cliente_contacto, canal, descripcion, estado) VALUES (?,?,?,?,?,?,?)")
            ->execute([
                limpio($_POST['tipo'] ?? null) ?: 'PETICION', $nombre, limpio($_POST['documento'] ?? null),
                limpio($_POST['contacto'] ?? null), 'WEB', $descripcion, 'RECIBIDA',
            ]);
        $folio = $pdo->lastInsertId();
        $enviado = true;
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PQRS - NAVISSI Grupo 10Z</title>
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: time() ?>">
</head>
<body style="background:var(--bg);">
<div style="max-width:560px;margin:40px auto;padding:0 16px;">
    <h1><?= icon('chat','icon-lg') ?> Peticiones, Quejas, Reclamos y Sugerencias</h1>
    <p class="subtitle">Cuéntanos qué pasó — te responderemos por el medio de contacto que nos dejes.</p>
    <?php if ($enviado): ?>
    <div class="msg-ok">¡Gracias! Tu <?= e(strtolower($_POST['tipo'] ?? 'solicitud')) ?> quedó registrada con el número <strong>#<?= (int)$folio ?></strong>. Guárdalo para hacer seguimiento.</div>
    <?php else: ?>
    <div class="panel">
        <form method="post">
            <label>Tipo *</label>
            <select name="tipo" required style="margin-bottom:10px;">
                <?php foreach (['PETICION'=>'Petición','QUEJA'=>'Queja','RECLAMO'=>'Reclamo','SUGERENCIA'=>'Sugerencia'] as $v=>$l): ?>
                <option value="<?= $v ?>"><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <label>Nombre completo *</label><input type="text" name="nombre" required style="margin-bottom:10px;">
            <label>Documento</label><input type="text" name="documento" style="margin-bottom:10px;">
            <label>Correo o celular de contacto</label><input type="text" name="contacto" style="margin-bottom:10px;">
            <label>Cuéntanos qué pasó *</label>
            <textarea name="descripcion" rows="5" required style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;"></textarea>
            <button type="submit"><?= icon('send') ?> Enviar</button>
        </form>
    </div>
    <?php endif; ?>
    <p class="small" style="margin-top:20px;">NAVISSI · Grupo 10Z SAS</p>
</div>
</body>
</html>
