<?php
/**
 * Consulta de Terceros + Legalización de Facturas (creación de terceros nuevos).
 * El maestro real de terceros vive en Siesa; aquí se mantiene una caché local
 * (tabla siesa_terceros) que se recarga importando el Excel que exporta Siesa
 * (columna "Código" = NIT/CC). Si el tercero no existe todavía, el usuario
 * pide su creación con los datos + documentos de soporte, y queda en una cola
 * (terceros_solicitudes) para que Contabilidad lo cree en Siesa y marque el
 * estado aquí.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/xlsx_reader.php';
$pdo = db();
$u = usuario_actual();
$msg = null;
$puedeGestionar = tiene_rol(['ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO']);

function terceros_normalizar_codigo(string $v): string {
    // El Excel de Siesa trae el código con espacios de relleno (ancho fijo).
    return trim(preg_replace('/\s+/', '', $v));
}

$documentosRequeridos = ['RUT' => 'RUT', 'CERTIFICADO_BANCARIO' => 'Certificado bancario', 'CAMARA_COMERCIO' => 'Cámara de Comercio', 'CEDULA' => 'Cédula'];
$tiposProveedor = ['TELA', 'INSUMOS', 'PROCESOS', 'CONFECCIÓN'];
$plazosPago = ['CONTADO', '10 DÍAS', '30 DÍAS', '60 DÍAS', '90 DÍAS'];

// ---- Importar/actualizar el maestro de terceros desde el Excel de Siesa ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'importar_terceros' && $puedeGestionar) {
    if (empty($_FILES['archivo']['tmp_name'])) {
        $msg = ['error', 'Selecciona el archivo Excel de terceros.'];
    } else {
        try {
            $hojas = xlsx_read_all_sheets($_FILES['archivo']['tmp_name']);
            $filas = xlsx_rows_to_assoc(reset($hojas));
            $upsert = $pdo->prepare("INSERT INTO siesa_terceros (codigo, razon_social, clase_proveedor, desc_clase_proveedor, condicion_pago, desc_condicion_pago, estado, actualizado_en)
                VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)
                ON CONFLICT(codigo) DO UPDATE SET razon_social=excluded.razon_social, clase_proveedor=excluded.clase_proveedor,
                    desc_clase_proveedor=excluded.desc_clase_proveedor, condicion_pago=excluded.condicion_pago,
                    desc_condicion_pago=excluded.desc_condicion_pago, estado=excluded.estado, actualizado_en=CURRENT_TIMESTAMP");
            $importados = 0;
            foreach ($filas as $f) {
                $codigo = terceros_normalizar_codigo((string) ($f['Código'] ?? $f['Codigo'] ?? ''));
                if ($codigo === '') continue;
                $upsert->execute([
                    $codigo,
                    trim((string) ($f['Razón social'] ?? $f['Razon social'] ?? '')),
                    trim((string) ($f['Clase de proveedor'] ?? '')),
                    trim((string) ($f['Desc. clase de proveedor'] ?? '')),
                    trim((string) ($f['Condicion de pago'] ?? $f['Condición de pago'] ?? '')),
                    trim((string) ($f['Desc. condicion de pago'] ?? $f['Desc. condición de pago'] ?? '')),
                    trim((string) ($f['Estado'] ?? '')),
                ]);
                $importados++;
            }
            $msg = ['ok', "Maestro de terceros actualizado: {$importados} registros importados/actualizados."];
        } catch (Throwable $e) {
            $msg = ['error', 'No se pudo leer el archivo: ' . $e->getMessage()];
        }
    }
}

// ---- Consulta de un tercero por NIT/CC ----
$resultadoConsulta = null;
$nitConsultado = trim((string) ($_GET['nit'] ?? $_POST['nit_consulta'] ?? ''));
if ($nitConsultado !== '') {
    $codigoBuscado = terceros_normalizar_codigo($nitConsultado);
    $stmt = $pdo->prepare("SELECT * FROM siesa_terceros WHERE codigo = ?");
    $stmt->execute([$codigoBuscado]);
    $tercero = $stmt->fetch(PDO::FETCH_ASSOC);
    $resultadoConsulta = ['nit' => $codigoBuscado, 'tercero' => $tercero];
}

// ---- Nueva solicitud de creación/legalización ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_solicitud') {
    $nit = limpio($_POST['nit_cc'] ?? null);
    $nombre = limpio($_POST['nombre_completo'] ?? null);
    if (!$nit || !$nombre) {
        $msg = ['error', 'El NIT/CC y el nombre completo son obligatorios.'];
    } else {
        $pdo->prepare("INSERT INTO terceros_solicitudes (nit_cc, tipo_persona, nombre_completo, direccion, actividad_economica, telefono, correo, tipo_proveedor, plazo_pago, solicitado_por)
            VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                terceros_normalizar_codigo($nit),
                limpio($_POST['tipo_persona'] ?? null),
                $nombre,
                limpio($_POST['direccion'] ?? null),
                limpio($_POST['actividad_economica'] ?? null),
                limpio($_POST['telefono'] ?? null),
                filter_var($_POST['correo'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
                limpio($_POST['tipo_proveedor'] ?? null),
                limpio($_POST['plazo_pago'] ?? null),
                $u['nombre'] ?? 'Sistema',
            ]);
        $solicitudId = (int) $pdo->lastInsertId();

        $dirAdj = __DIR__ . '/../data/terceros_solicitudes';
        if (!is_dir($dirAdj)) mkdir($dirAdj, 0777, true);
        $permitidos = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        foreach ($documentosRequeridos as $clave => $etiqueta) {
            $campo = 'doc_' . strtolower($clave);
            if (empty($_FILES[$campo]['tmp_name'])) continue;
            $tamano = (int) ($_FILES[$campo]['size'] ?? 0);
            if ($tamano <= 0 || $tamano > 10 * 1024 * 1024) continue;
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$campo]['tmp_name']) ?: '';
            if (!isset($permitidos[$mime])) continue;
            $rutaGuardada = bin2hex(random_bytes(18)) . '.' . $permitidos[$mime];
            if (move_uploaded_file($_FILES[$campo]['tmp_name'], $dirAdj . '/' . $rutaGuardada)) {
                $pdo->prepare("INSERT INTO terceros_solicitudes_adjuntos (solicitud_id, tipo_documento, nombre_archivo, ruta) VALUES (?,?,?,?)")
                    ->execute([$solicitudId, $clave, basename($_FILES[$campo]['name']), $rutaGuardada]);
            }
        }
        hoja_vida_registrar($pdo, 'TERCERO', $nit, 'SOLICITUD_CREACION', "Solicitud de creación de tercero: {$nombre}", $u['nombre'] ?? 'Sistema', $solicitudId);
        $msg = ['ok', "Solicitud enviada. Contabilidad la revisará y creará el tercero en Siesa. Puedes hacer seguimiento en la tabla de abajo con el NIT {$nit}."];
    }
}

// ---- Gestión de solicitudes (marcar creado/rechazado en Siesa) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'gestionar_solicitud' && $puedeGestionar) {
    $nuevoEstado = in_array($_POST['nuevo_estado'] ?? '', ['CREADO_EN_SIESA', 'RECHAZADO'], true) ? $_POST['nuevo_estado'] : null;
    if ($nuevoEstado) {
        $pdo->prepare("UPDATE terceros_solicitudes SET estado = ?, gestionado_por = ?, gestionado_en = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$nuevoEstado, $u['nombre'] ?? 'Sistema', (int) $_POST['solicitud_id']]);
        $msg = ['ok', 'Solicitud actualizada.'];
    }
}

$totalTerceros = (int) $pdo->query("SELECT COUNT(*) FROM siesa_terceros")->fetchColumn();
$ultimaImportacion = $pdo->query("SELECT MAX(actualizado_en) FROM siesa_terceros")->fetchColumn();
$misSolicitudes = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM terceros_solicitudes_adjuntos a WHERE a.solicitud_id = s.id) AS n_adjuntos
    FROM terceros_solicitudes s WHERE " . ($puedeGestionar ? '1=1' : 's.solicitado_por = ?') . " ORDER BY s.creado_en DESC LIMIT 100");
$puedeGestionar ? $misSolicitudes->execute() : $misSolicitudes->execute([$u['nombre'] ?? '']);
$solicitudes = $misSolicitudes->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Consulta de Terceros', 'Consulta de Terceros', '../');
?>
<style>
.pe-tabs{display:flex;gap:4px;overflow-x:auto;border-bottom:2px solid var(--line);margin:18px 0 0;}
.pe-tab-btn{border:none;background:none;padding:10px 16px;font:inherit;font-weight:600;color:var(--ink-500,#5b6472);cursor:pointer;border-radius:8px 8px 0 0;white-space:nowrap;}
.pe-tab-btn:hover{background:var(--surface-hover,#f2f4f7);}
.pe-tab-btn.activo{color:var(--navy-900);background:var(--surface-hover,#f2f4f7);box-shadow:inset 0 -2px 0 var(--gold-500);}
.pe-panel{display:none;padding-top:16px;}
.pe-panel.activo{display:block;}
</style>
<h1><?= icon('users','icon-lg') ?> Consulta de Terceros y Legalización de Facturas</h1>
<p class="subtitle">Verifica si un proveedor/tercero ya está creado en Siesa por su NIT o cédula; si no lo está, solicita su creación con los documentos de soporte.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<nav class="pe-tabs" id="pe-tabs">
    <button type="button" class="pe-tab-btn" data-target="pe-consulta"><?= icon('search') ?> Consultar Tercero</button>
    <button type="button" class="pe-tab-btn" data-target="pe-legalizacion"><?= icon('file') ?> Legalización / Solicitud de Creación</button>
    <button type="button" class="pe-tab-btn" data-target="pe-seguimiento"><?= icon('log') ?> Seguimiento de Solicitudes <?= count($solicitudes) ? '(' . count($solicitudes) . ')' : '' ?></button>
    <?php if ($puedeGestionar): ?><button type="button" class="pe-tab-btn" data-target="pe-importar"><?= icon('upload') ?> Importar maestro (Excel Siesa)</button><?php endif; ?>
</nav>

<div class="pe-panel" id="pe-consulta">
    <div class="panel">
        <h3>Buscar por NIT o Cédula</h3>
        <form method="get" class="toolbar">
            <input type="text" name="nit" value="<?= e($nitConsultado) ?>" placeholder="Ej: 900542009" style="min-width:260px" required>
            <button type="submit"><?= icon('search') ?> Consultar en Siesa</button>
        </form>
        <?php if ($resultadoConsulta): ?>
            <?php if ($resultadoConsulta['tercero']): $t = $resultadoConsulta['tercero']; ?>
            <div class="msg-ok" style="margin-top:14px;">
                <strong>✓ Sí está creado en Siesa.</strong><br>
                Código: <code><?= e($t['codigo']) ?></code> · Razón social: <strong><?= e($t['razon_social']) ?></strong><br>
                <?= e($t['desc_clase_proveedor']) ?: 'Sin clase de proveedor' ?> · Condición de pago: <?= e($t['desc_condicion_pago']) ?: '—' ?> ·
                Estado: <span class="badge <?= strtolower($t['estado'] ?? '') === 'activo' ? 'badge-activo' : 'badge-otro' ?>"><?= e($t['estado']) ?></span>
            </div>
            <?php else: ?>
            <div class="msg-error" style="margin-top:14px;">
                <strong>✗ NO está creado en Siesa</strong> (NIT/CC <?= e($resultadoConsulta['nit']) ?>).<br>
                Ve a la pestaña <strong>Legalización / Solicitud de Creación</strong> para pedir que lo creen.
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <p class="small" style="margin-top:12px;">
            Maestro cargado: <strong><?= number_format($totalTerceros) ?></strong> terceros
            <?= $ultimaImportacion ? '· última actualización: ' . e($ultimaImportacion) : '· aún no se ha importado el Excel de Siesa' ?>.
        </p>
    </div>
</div>

<div class="pe-panel" id="pe-legalizacion">
    <div class="panel">
        <h3>Solicitar creación de un tercero nuevo</h3>
        <p class="small">Úsalo cuando la consulta te diga que el tercero NO está creado en Siesa. Contabilidad revisa y lo crea allá; aquí queda el trámite y los documentos.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear_solicitud">
            <div class="grid-form">
                <div><label>1. NIT o CC *</label><input type="text" name="nit_cc" value="<?= e($nitConsultado) ?>" required></div>
                <div><label>2. Tipo de persona</label>
                    <select name="tipo_persona">
                        <option value="NATURAL">Natural</option>
                        <option value="JURIDICA">Jurídica</option>
                    </select>
                </div>
                <div style="grid-column:span 2;"><label>3. Nombre completo / Razón social *</label><input type="text" name="nombre_completo" required></div>
                <div style="grid-column:span 2;"><label>4. Dirección</label><input type="text" name="direccion"></div>
                <div><label>5. Actividad económica</label><input type="text" name="actividad_economica"></div>
                <div><label>6. Teléfono</label><input type="text" name="telefono"></div>
                <div><label>7. Correo</label><input type="email" name="correo"></div>
                <div><label>8. Tipo de proveedor</label>
                    <select name="tipo_proveedor">
                        <?php foreach ($tiposProveedor as $tp): ?><option><?= e($tp) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label>9. Plazo de pago</label>
                    <select name="plazo_pago">
                        <?php foreach ($plazosPago as $pp): ?><option><?= e($pp) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <h4 style="margin-top:18px;">10. Adjuntar documentos</h4>
            <div class="grid-form">
                <?php foreach ($documentosRequeridos as $clave => $etiqueta): ?>
                <div><label><?= e($etiqueta) ?></label><input type="file" name="doc_<?= strtolower($clave) ?>" accept="application/pdf,image/jpeg,image/png"></div>
                <?php endforeach; ?>
            </div>
            <button type="submit" style="margin-top:14px;"><?= icon('send') ?> Enviar solicitud a Contabilidad</button>
        </form>
    </div>
</div>

<div class="pe-panel" id="pe-seguimiento">
    <div class="panel">
        <h3>Solicitudes <?= $puedeGestionar ? 'de creación de terceros' : 'que he enviado' ?></h3>
        <table>
            <tr><th>NIT/CC</th><th>Nombre</th><th>Tipo proveedor</th><th>Plazo</th><th>Documentos</th><th>Estado</th><th>Solicitado por</th><th>Fecha</th><?php if ($puedeGestionar): ?><th></th><?php endif; ?></tr>
            <?php foreach ($solicitudes as $s): ?>
            <tr>
                <td><?= e($s['nit_cc']) ?></td>
                <td><?= e($s['nombre_completo']) ?></td>
                <td><?= e($s['tipo_proveedor']) ?: '—' ?></td>
                <td><?= e($s['plazo_pago']) ?: '—' ?></td>
                <td><?= (int) $s['n_adjuntos'] ?>/4</td>
                <td><span class="badge <?= $s['estado']==='CREADO_EN_SIESA'?'badge-activo':($s['estado']==='RECHAZADO'?'badge-err':'badge-otro') ?>"><?= e($s['estado']) ?></span></td>
                <td class="small"><?= e($s['solicitado_por']) ?></td>
                <td class="small"><?= e($s['creado_en']) ?></td>
                <?php if ($puedeGestionar): ?>
                <td>
                    <?php if ($s['estado'] === 'PENDIENTE'): ?>
                    <form method="post" class="inline"><input type="hidden" name="accion" value="gestionar_solicitud"><input type="hidden" name="solicitud_id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="nuevo_estado" value="CREADO_EN_SIESA"><button type="submit" style="padding:3px 8px;font-size:11px;">Ya creado en Siesa</button></form>
                    <form method="post" class="inline"><input type="hidden" name="accion" value="gestionar_solicitud"><input type="hidden" name="solicitud_id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="nuevo_estado" value="RECHAZADO"><button type="submit" class="btn-danger" style="padding:3px 8px;font-size:11px;">Rechazar</button></form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (!$solicitudes): ?><tr><td colspan="9" class="small">Sin solicitudes todavía.</td></tr><?php endif; ?>
        </table>
    </div>
</div>

<?php if ($puedeGestionar): ?>
<div class="pe-panel" id="pe-importar">
    <div class="panel">
        <h3><?= icon('upload') ?> Importar/actualizar el maestro de terceros</h3>
        <p class="small">Sube el Excel que exporta Siesa (con la columna <strong>Código</strong> = NIT/CC). Se puede repetir cuando Siesa tenga terceros nuevos - actualiza sin duplicar.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="importar_terceros">
            <input type="file" name="archivo" accept=".xlsx" required>
            <button type="submit" style="margin-top:10px;"><?= icon('upload') ?> Importar</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var botones = document.querySelectorAll('.pe-tab-btn');
    var paneles = document.querySelectorAll('.pe-panel');
    function activar(id) {
        botones.forEach(function (b) { b.classList.toggle('activo', b.dataset.target === id); });
        paneles.forEach(function (p) { p.classList.toggle('activo', p.id === id); });
    }
    botones.forEach(function (b) { b.addEventListener('click', function () { activar(b.dataset.target); }); });
    activar('<?= $resultadoConsulta && !$resultadoConsulta['tercero'] ? 'pe-legalizacion' : 'pe-consulta' ?>');
})();
</script>
<?php layout_fin(); ?>
