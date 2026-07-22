<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/movimientos_campos.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;

function movimiento_serial(PDO $pdo, int $inventarioId): ?string {
    $stmt = $pdo->prepare("SELECT serial FROM inventario WHERE id = ?");
    $stmt->execute([$inventarioId]);
    return $stmt->fetchColumn() ?: null;
}

$etiquetasFirmaProveedor = [
    'firma' => 'Directora de Gestión Humana',
    'firma2' => 'Responsable TI (entrega el equipo)',
    'firma3' => 'Proveedor (quien retira el equipo)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'firmar') {
    $nombreFirma = limpio($_POST['firma_nombre'] ?? null);
    $docFirma = limpio($_POST['firma_documento'] ?? null);
    $slot = in_array($_POST['slot'] ?? 'firma', ['firma', 'firma2', 'firma3'], true) ? $_POST['slot'] : 'firma';
    if ($nombreFirma) {
        $pdo->prepare("UPDATE movimientos_equipos SET {$slot}_nombre=?, {$slot}_documento=?, {$slot}_fecha=CURRENT_TIMESTAMP, {$slot}_ip=? WHERE id=?")
            ->execute([$nombreFirma, $docFirma, $_SERVER['REMOTE_ADDR'] ?? 'local', $id]);
        $stmt = $pdo->prepare("SELECT inventario_id, tipo FROM movimientos_equipos WHERE id = ?");
        $stmt->execute([$id]);
        $mv = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($mv) {
            $serial = movimiento_serial($pdo, $mv['inventario_id']);
            if ($serial) {
                $rolFirma = $mv['tipo'] === 'SALIDA_PROVEEDOR' ? ($etiquetasFirmaProveedor[$slot] ?? $slot) : null;
                hoja_vida_registrar($pdo, 'EQUIPO', $serial, 'FORMATO_FIRMADO', trim("{$mv['tipo']} firmado por {$nombreFirma}" . ($rolFirma ? " ({$rolFirma})" : '')), $nombreFirma);
            }
        }
        $msg = ['ok', 'Firma registrada. Queda con nombre, documento, fecha/hora e IP como evidencia.'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_devuelto') {
    $u = usuario_actual();
    $stmt = $pdo->prepare("SELECT inventario_id, tipo, firma_nombre, firma2_nombre, firma3_nombre FROM movimientos_equipos WHERE id = ?");
    $stmt->execute([$id]);
    $mv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mv && $mv['tipo'] === 'SALIDA_PROVEEDOR' && $mv['firma_nombre'] && $mv['firma2_nombre'] && $mv['firma3_nombre']) {
        $pdo->prepare("UPDATE movimientos_equipos SET devuelto_en = CURRENT_TIMESTAMP, devuelto_por = ? WHERE id = ?")
            ->execute([$u['nombre'] ?? 'Sistema', $id]);
        $pdo->prepare("UPDATE inventario SET estado = 'ACTIVO', actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$mv['inventario_id']]);
        $serial = movimiento_serial($pdo, $mv['inventario_id']);
        if ($serial) hoja_vida_registrar($pdo, 'EQUIPO', $serial, 'REGRESO_PROVEEDOR', "El equipo volvió del proveedor (formato #{$id}).", $u['nombre'] ?? 'Sistema');
        $msg = ['ok', 'Equipo marcado como recibido de vuelta del proveedor. Vuelve a estar ACTIVO en Inventario.'];
    }
}

$stmt = $pdo->prepare("SELECT m.*, i.serial, i.placa, i.marca, i.modelo, i.tipo AS equipo_tipo, i.sistema_operativo, i.procesador, i.memoria, i.almacenamiento, s.nombre AS sede_nombre
    FROM movimientos_equipos m LEFT JOIN inventario i ON m.inventario_id = i.id LEFT JOIN sedes s ON m.sede_id = s.id
    WHERE m.id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$m) {
    layout_inicio('No encontrado', 'Movimientos', '../');
    echo '<div class="msg-error">Movimiento no encontrado.</div><a class="btn" href="movimientos.php">Volver</a>';
    layout_fin();
    exit;
}

$detalles = json_decode($m['detalles_json'] ?? '[]', true) ?: [];

layout_inicio('Movimiento #' . $id, 'Movimientos', '../');
?>
<p class="small no-print"><a href="movimientos.php"><?= icon('arrow-right') ?> Volver a Movimientos</a> ·
    <button onclick="window.print()" class="btn btn-secondary" style="padding:5px 12px;"><?= icon('file') ?> Imprimir / Guardar PDF</button></p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel" style="max-width:760px;margin:0 auto;">
    <h2 style="text-align:center;"><?= e(titulo_formato($m['tipo'])) ?></h2>
    <p style="text-align:center;" class="small">Grupo 10Z SAS / Navissi — Formato N° <?= (int)$m['id'] ?> — <?= e($m['fecha']) ?></p>
    <hr>
    <table class="deftable">
        <tr><th>Serial</th><td><?= e($m['serial']) ?></td><th>Placa</th><td><?= e($m['placa']) ?></td></tr>
        <tr><th>Tipo de equipo</th><td><?= e($m['equipo_tipo']) ?></td><th>Marca/Modelo</th><td><?= e($m['marca']) ?> <?= e($m['modelo']) ?></td></tr>
        <tr><th>Sistema operativo</th><td><?= e($m['sistema_operativo']) ?: '—' ?></td><th>Procesador</th><td><?= e($m['procesador']) ?: '—' ?></td></tr>
        <tr><th>Sede</th><td colspan="3"><?= e($m['sede_nombre']) ?: '—' ?></td></tr>
        <tr><th>Responsable (TI)</th><td colspan="3"><?= e($m['responsable']) ?: '—' ?></td></tr>
        <tr><th>Destinatario / recibe</th><td colspan="3"><?= e($m['destinatario']) ?: '—' ?> <?= $m['destinatario_documento'] ? '(doc. '.e($m['destinatario_documento']).')' : '' ?></td></tr>
        <?php foreach ($detalles as $etiqueta => $valor): if (!$valor) continue; ?>
        <tr><th><?= e($etiqueta) ?></th><td colspan="3"><?= nl2br(e($valor)) ?></td></tr>
        <?php endforeach; ?>
        <tr><th>Observaciones</th><td colspan="3"><?= nl2br(e($m['observaciones'])) ?: '—' ?></td></tr>
    </table>

    <?php if ($m['tipo'] === 'SALIDA_PROVEEDOR'): ?>
    <h3 style="margin-top:24px;"><?= icon('key') ?> Firmas requeridas (Gestión Humana, TI y proveedor)</h3>
    <?php foreach ($etiquetasFirmaProveedor as $slot => $etiqueta): ?>
    <div class="panel" style="margin-bottom:10px;">
        <strong><?= e($etiqueta) ?></strong>
        <?php if ($m["{$slot}_nombre"]): ?>
        <div class="msg-ok" style="margin-top:8px;">
            <?= icon('check') ?> Firmado por <strong><?= e($m["{$slot}_nombre"]) ?></strong>
            <?= $m["{$slot}_documento"] ? '(doc. '.e($m["{$slot}_documento"]).')' : '' ?>
            el <?= e($m["{$slot}_fecha"]) ?> desde IP <?= e($m["{$slot}_ip"]) ?>.
        </div>
        <?php else: ?>
        <form method="post" class="no-print" style="margin-top:8px;" onsubmit="return confirm('Al firmar, el nombre, documento, fecha/hora e IP quedan registrados como evidencia. ¿Continuar?');">
            <input type="hidden" name="accion" value="firmar">
            <input type="hidden" name="slot" value="<?= e($slot) ?>">
            <div class="grid-form">
                <div><label>Nombre completo *</label><input type="text" name="firma_nombre" required></div>
                <div><label>Documento</label><input type="text" name="firma_documento"></div>
            </div>
            <button type="submit"><?= icon('check') ?> Firmar como <?= e($etiqueta) ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($m['devuelto_en']): ?>
    <div class="msg-ok" style="margin-top:10px;"><?= icon('check') ?> El equipo ya volvió del proveedor — recibido por <?= e($m['devuelto_por']) ?> el <?= e($m['devuelto_en']) ?>. Vuelve a estar ACTIVO en Inventario.</div>
    <?php elseif ($m['firma_nombre'] && $m['firma2_nombre'] && $m['firma3_nombre']): ?>
    <form method="post" class="no-print" style="margin-top:14px;">
        <input type="hidden" name="accion" value="marcar_devuelto">
        <button type="submit"><?= icon('arrow-right') ?> Marcar equipo como recibido de vuelta del proveedor</button>
    </form>
    <?php else: ?>
    <p class="small" style="margin-top:10px;">El equipo queda con estado <strong>EN REPARACIÓN</strong> hasta que se firmen las 3 firmas y se confirme el regreso.</p>
    <?php endif; ?>
    <?php elseif ($m['firma_nombre']): ?>
    <div class="msg-ok" style="margin-top:20px;">
        <?= icon('check') ?> Firmado electrónicamente por <strong><?= e($m['firma_nombre']) ?></strong>
        <?= $m['firma_documento'] ? '(doc. '.e($m['firma_documento']).')' : '' ?>
        el <?= e($m['firma_fecha']) ?> desde IP <?= e($m['firma_ip']) ?>.
    </div>
    <?php else: ?>
    <form method="post" class="no-print" style="margin-top:30px;border-top:1px solid var(--line);padding-top:16px;" onsubmit="return confirm('Al firmar, tu nombre, documento, fecha/hora e IP quedan registrados como evidencia de aceptación de este formato. ¿Continuar?');">
        <input type="hidden" name="accion" value="firmar">
        <h3><?= icon('key') ?> Firma electrónica</h3>
        <div class="grid-form">
            <div><label>Nombre completo de quien firma *</label><input type="text" name="firma_nombre" required></div>
            <div><label>Documento</label><input type="text" name="firma_documento"></div>
        </div>
        <button type="submit"><?= icon('check') ?> Firmar y aceptar este formato</button>
    </form>
    <div style="display:flex;justify-content:space-between;margin-top:40px;" class="print-only-if-unsigned">
        <div style="text-align:center;width:45%;"><div style="border-top:1px solid #333;padding-top:6px;">Firma responsable TI</div></div>
        <div style="text-align:center;width:45%;"><div style="border-top:1px solid #333;padding-top:6px;">Firma quien recibe / autoriza</div></div>
    </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .topbar, .no-print, nav, #ia-chat-launcher, #ia-chat-panel { display: none !important; }
    main { margin: 0; max-width: 100%; }
}
</style>
<?php layout_fin(); ?>
