<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/movimientos_campos.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'firmar') {
    $nombreFirma = limpio($_POST['firma_nombre'] ?? null);
    $docFirma = limpio($_POST['firma_documento'] ?? null);
    if ($nombreFirma) {
        $pdo->prepare("UPDATE movimientos_equipos SET firma_nombre=?, firma_documento=?, firma_fecha=CURRENT_TIMESTAMP, firma_ip=? WHERE id=?")
            ->execute([$nombreFirma, $docFirma, $_SERVER['REMOTE_ADDR'] ?? 'local', $id]);
        $stmt = $pdo->prepare("SELECT inventario_id, tipo FROM movimientos_equipos WHERE id = ?");
        $stmt->execute([$id]);
        $mv = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($mv) {
            $stmtSerial = $pdo->prepare("SELECT serial FROM inventario WHERE id = ?");
            $stmtSerial->execute([$mv['inventario_id']]);
            $serial = $stmtSerial->fetchColumn();
            if ($serial) hoja_vida_registrar($pdo, 'EQUIPO', $serial, 'FORMATO_FIRMADO', "{$mv['tipo']} firmado por {$nombreFirma}", $nombreFirma);
        }
        $msg = ['ok', 'Formato firmado. Queda registrado con nombre, documento, fecha/hora e IP como evidencia.'];
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

    <?php if ($m['firma_nombre']): ?>
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
