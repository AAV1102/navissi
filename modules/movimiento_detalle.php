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

$stmtTipo = $pdo->prepare("SELECT inventario_id, tipo FROM movimientos_equipos WHERE id = ?");
$stmtTipo->execute([$id]);
$mvActual = $stmtTipo->fetch(PDO::FETCH_ASSOC);
$firmantes = $mvActual ? firmantes_por_tipo($mvActual['tipo']) : ['firma' => 'Firma de aceptación'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'firmar') {
    $nombreFirma = limpio($_POST['firma_nombre'] ?? null);
    $docFirma = limpio($_POST['firma_documento'] ?? null);
    $slot = in_array($_POST['slot'] ?? 'firma', array_keys($firmantes), true) ? $_POST['slot'] : 'firma';
    if ($nombreFirma) {
        $pdo->prepare("UPDATE movimientos_equipos SET {$slot}_nombre=?, {$slot}_documento=?, {$slot}_fecha=CURRENT_TIMESTAMP, {$slot}_ip=? WHERE id=?")
            ->execute([$nombreFirma, $docFirma, $_SERVER['REMOTE_ADDR'] ?? 'local', $id]);
        if ($mvActual) {
            $serial = movimiento_serial($pdo, $mvActual['inventario_id']);
            if ($serial) {
                $rolFirma = $firmantes[$slot] ?? $slot;
                hoja_vida_registrar($pdo, 'EQUIPO', $serial, 'FORMATO_FIRMADO', "{$mvActual['tipo']} firmado por {$nombreFirma} ({$rolFirma})", $nombreFirma);
            }
        }
        $msg = ['ok', 'Firma registrada. Queda con nombre, documento, fecha/hora e IP como evidencia.'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_devuelto') {
    $u = usuario_actual();
    $slotsRequeridos = array_keys($firmantes);
    $stmt = $pdo->prepare("SELECT * FROM movimientos_equipos WHERE id = ?");
    $stmt->execute([$id]);
    $mv = $stmt->fetch(PDO::FETCH_ASSOC);
    $todasFirmadas = $mv && array_reduce($slotsRequeridos, fn($ok, $s) => $ok && !empty($mv["{$s}_nombre"]), true);
    if ($mv && tipo_permite_regreso($mv['tipo']) && $todasFirmadas) {
        $pdo->prepare("UPDATE movimientos_equipos SET devuelto_en = CURRENT_TIMESTAMP, devuelto_por = ? WHERE id = ?")
            ->execute([$u['nombre'] ?? 'Sistema', $id]);
        $pdo->prepare("UPDATE inventario SET estado = 'ACTIVO', actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$mv['inventario_id']]);
        $serial = movimiento_serial($pdo, $mv['inventario_id']);
        if ($serial) hoja_vida_registrar($pdo, 'EQUIPO', $serial, 'REGRESO_EQUIPO', "El equipo regresó (formato #{$id}, {$mv['tipo']}).", $u['nombre'] ?? 'Sistema');
        $msg = ['ok', 'Equipo marcado como recibido de vuelta. Vuelve a estar ACTIVO en Inventario.'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir_documento') {
    $tipoDoc = in_array($_POST['tipo_doc'] ?? '', ['FACTURA', 'COTIZACION', 'OTRO'], true) ? $_POST['tipo_doc'] : 'OTRO';
    if (empty($_FILES['documento']['tmp_name']) || !is_uploaded_file($_FILES['documento']['tmp_name'])) {
        $msg = ['error', 'Selecciona un archivo.'];
    } else {
        $tamano = (int) ($_FILES['documento']['size'] ?? 0);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['documento']['tmp_name']) ?: '';
        $permitidos = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
            'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];
        if ($tamano <= 0 || $tamano > 15 * 1024 * 1024 || !isset($permitidos[$mime])) {
            $msg = ['error', 'Archivo inválido: debe ser PDF, Word, Excel o imagen, máximo 15MB.'];
        } else {
            $dir = __DIR__ . '/../data/movimientos_documentos';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $rutaGuardada = bin2hex(random_bytes(18)) . '.' . $permitidos[$mime];
            if (move_uploaded_file($_FILES['documento']['tmp_name'], $dir . '/' . $rutaGuardada)) {
                $u = usuario_actual();
                $pdo->prepare("INSERT INTO movimientos_documentos (movimiento_id, tipo, nombre_archivo, ruta, subido_por) VALUES (?,?,?,?,?)")
                    ->execute([$id, $tipoDoc, basename($_FILES['documento']['name']), $rutaGuardada, $u['nombre'] ?? 'Sistema']);
                $msg = ['ok', 'Documento adjuntado.'];
            }
        }
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
$stmtDocs = $pdo->prepare("SELECT * FROM movimientos_documentos WHERE movimiento_id = ? ORDER BY id DESC");
$stmtDocs->execute([$id]);
$documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
$etiquetasDoc = ['FACTURA' => 'Factura de compra', 'COTIZACION' => 'Cotización', 'OTRO' => 'Otro documento'];

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

    <h3 style="margin-top:24px;"><?= icon('key') ?> Firma<?= count($firmantes) > 1 ? 's requeridas' : ' electrónica' ?></h3>
    <?php foreach ($firmantes as $slot => $etiqueta): ?>
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

    <?php
    $slotsRequeridos = array_keys($firmantes);
    $todasFirmadas = array_reduce($slotsRequeridos, fn($ok, $s) => $ok && !empty($m["{$s}_nombre"]), true);
    ?>
    <?php if (tipo_permite_regreso($m['tipo'])): ?>
        <?php if ($m['devuelto_en']): ?>
        <div class="msg-ok" style="margin-top:10px;"><?= icon('check') ?> El equipo ya volvió — recibido por <?= e($m['devuelto_por']) ?> el <?= e($m['devuelto_en']) ?>. Vuelve a estar ACTIVO en Inventario.</div>
        <?php elseif ($todasFirmadas): ?>
        <form method="post" class="no-print" style="margin-top:14px;">
            <input type="hidden" name="accion" value="marcar_devuelto">
            <button type="submit"><?= icon('arrow-right') ?> Marcar equipo como recibido de vuelta</button>
        </form>
        <?php else: ?>
        <p class="small" style="margin-top:10px;">El equipo queda con el estado correspondiente en Inventario hasta que se completen todas las firmas y se confirme el regreso.</p>
        <?php endif; ?>
    <?php endif; ?>

    <h3 style="margin-top:24px;"><?= icon('file') ?> Facturas, cotizaciones y otros soportes</h3>
    <?php if ($documentos): ?>
    <ul class="small" style="padding-left:18px;">
        <?php foreach ($documentos as $d): ?>
        <li><a href="descargar_documento_movimiento.php?id=<?= (int)$d['id'] ?>"><?= e($etiquetasDoc[$d['tipo']] ?? $d['tipo']) ?>: <?= e($d['nombre_archivo']) ?></a> — subido por <?= e($d['subido_por']) ?> el <?= e($d['subido_en']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="small">Sin documentos adjuntos.</p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="no-print" style="margin-top:8px;">
        <input type="hidden" name="accion" value="subir_documento">
        <div class="grid-form">
            <div><label>Tipo de documento</label>
                <select name="tipo_doc">
                    <option value="FACTURA">Factura de compra</option>
                    <option value="COTIZACION">Cotización</option>
                    <option value="OTRO">Otro</option>
                </select>
            </div>
            <div><label>Archivo (PDF, Word, Excel o imagen)</label><input type="file" name="documento" required></div>
        </div>
        <button type="submit"><?= icon('plus') ?> Adjuntar documento</button>
    </form>
</div>

<style>
@media print {
    .topbar, .no-print, nav, #ia-chat-launcher, #ia-chat-panel { display: none !important; }
    main { margin: 0; max-width: 100%; }
}
</style>
<?php layout_fin(); ?>
