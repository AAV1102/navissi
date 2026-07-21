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
// Consultar y solicitar creación es para toda la empresa: cualquier Director,
// Coordinador, Analista o Empleado puede necesitar verificar/pedir un tercero
// (ej. alguien hizo una compra y necesita la factura electrónica). Requiere
// sesión iniciada (ya la exige layout_inicio más abajo), sin restricción de rol.
// El maestro de Siesa (importar Excel, marcar creado/rechazado) es exclusivo
// de Contabilidad: ADMIN/GERENCIA/CEO (ven todo) o cualquier persona cuya área
// sea Dirección de Contabilidad, sin importar si es Director/Coordinador/Analista.
$puedeGestionar = tiene_rol(['ADMIN', 'GERENCIA', 'CEO'])
    || strcasecmp(trim((string) ($u['area_responsable'] ?? '')), 'Direccion de Contabilidad') === 0;

function terceros_normalizar_codigo(string $v): string {
    // El Excel de Siesa trae el código con espacios de relleno (ancho fijo).
    return trim(preg_replace('/\s+/', '', $v));
}

// ---- Exportar solicitudes pendientes en TXT/CSV/JSON (para subir a Siesa
// manualmente, ya que NAVISSI no tiene conexión directa de escritura a Siesa) ----
if (isset($_GET['exportar'])) {
    requiere_login('../');
    if (!$puedeGestionar) { http_response_code(403); exit('No autorizado.'); }
    $formato = $_GET['exportar'];
    $filtroEstado = in_array($_GET['estado'] ?? '', ['PENDIENTE', 'CREADO_EN_SIESA', 'RECHAZADO'], true) ? $_GET['estado'] : null;
    $sql = "SELECT id, nit_cc, tipo_tercero, tipo_persona, nombre_completo, direccion, actividad_economica, telefono, correo, tipo_proveedor, plazo_pago, estado, creado_en, exportado_en, exportado_por FROM terceros_solicitudes";
    $params = [];
    if ($filtroEstado) { $sql .= " WHERE estado = ?"; $params[] = $filtroEstado; }
    $sql .= " ORDER BY creado_en DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $idsExportados = array_column($filas, 'id');
    // Quita "id" del array antes de escribirlo al archivo - es un detalle interno
    // de NAVISSI, no un dato que Siesa necesite en el import.
    foreach ($filas as &$f) { unset($f['id']); }
    unset($f);
    $nombreArchivo = 'terceros_solicitudes_' . date('Y-m-d');

    // Marca cada solicitud incluida como exportada (con quién y cuándo) y deja
    // un registro en el log de exportaciones - así queda claro qué ya se bajó
    // para subir a Siesa y qué no, sin depender solo del filtro por estado.
    if ($idsExportados) {
        $marcador = $pdo->prepare("UPDATE terceros_solicitudes SET exportado_en = CURRENT_TIMESTAMP, exportado_por = ? WHERE id = ?");
        foreach ($idsExportados as $idExp) { $marcador->execute([$u['nombre'] ?? 'Sistema', $idExp]); }
    }
    $pdo->prepare("INSERT INTO terceros_exportaciones_log (formato, filtro_estado, cantidad, ids_incluidos, exportado_por) VALUES (?,?,?,?,?)")
        ->execute([$formato, $filtroEstado, count($idsExportados), implode(',', $idsExportados), $u['nombre'] ?? 'Sistema']);

    if ($formato === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nombreArchivo}.json\"");
        echo json_encode($filas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nombreArchivo}.csv\"");
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel abra bien los acentos
        if ($filas) fputcsv($out, array_keys($filas[0]), ';');
        foreach ($filas as $f) fputcsv($out, $f, ';');
        fclose($out);
        exit;
    }
    if ($formato === 'txt') {
        // Archivo plano de ancho fijo, separado por | - formato simple para
        // importadores de Siesa que no aceptan CSV/Excel directamente.
        header('Content-Type: text/plain; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$nombreArchivo}.txt\"");
        foreach ($filas as $f) {
            echo implode('|', array_map(fn($v) => str_replace('|', ' ', (string) ($v ?? '')), $f)), "\r\n";
        }
        exit;
    }
    http_response_code(400);
    exit('Formato de exportación no soportado.');
}

$documentosRequeridos = ['RUT' => 'RUT', 'CERTIFICADO_BANCARIO' => 'Certificado bancario', 'CAMARA_COMERCIO' => 'Cámara de Comercio', 'CEDULA' => 'Cédula'];
// "Tipo de tercero" es general para toda la empresa (no solo compras): un
// proveedor, un cliente, o un empleado que necesita quedar como tercero para
// nómina/reembolsos, etc. Cualquier área puede solicitar cualquiera de estos.
$tiposTercero = ['PROVEEDOR', 'CLIENTE', 'EMPLEADO', 'OTRO'];
// Las categorías de proveedor parten de la lista base solicitada (Tela, Insumos,
// Procesos, Confección) y se completan con las clases de proveedor que YA
// existen en el maestro real importado de Siesa (si el maestro aún no se ha
// importado, la lista base sigue apareciendo en vez de quedar vacía), más
// "OTRO" al final para lo que no encaje en ninguna.
$tiposProveedorBase = ['Tela', 'Insumos', 'Procesos', 'Confección'];
$tiposProveedorSiesa = $pdo->query("SELECT DISTINCT desc_clase_proveedor FROM siesa_terceros WHERE desc_clase_proveedor IS NOT NULL AND desc_clase_proveedor != '' ORDER BY desc_clase_proveedor")->fetchAll(PDO::FETCH_COLUMN);
$tiposProveedor = array_values(array_unique(array_merge($tiposProveedorBase, $tiposProveedorSiesa)));
$tiposProveedor[] = 'OTRO';
$plazosPago = ['CONTADO', '1 DÍA', '8 DÍAS', '15 DÍAS', '30 DÍAS', '45 DÍAS', '60 DÍAS', '90 DÍAS', 'OTRO'];

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
        // Cuando el usuario escoge "OTRO" en tipo de persona o plazo de pago, el
        // select viene acompañado de un campo de texto (tipo_persona_otro /
        // plazo_pago_otro) que reemplaza el valor genérico "OTRO" por lo que
        // realmente escribió, para no perder el dato.
        $tipoPersona = limpio($_POST['tipo_persona'] ?? null);
        if ($tipoPersona === 'OTRO' && limpio($_POST['tipo_persona_otro'] ?? null)) {
            $tipoPersona = limpio($_POST['tipo_persona_otro'] ?? null);
        }
        $tipoTercero = limpio($_POST['tipo_tercero'] ?? null) ?: 'PROVEEDOR';
        // La categoría (tipo_proveedor) solo aplica cuando el tercero es un
        // Proveedor - para Cliente/Empleado/Otro el campo se oculta en el
        // formulario, así que aquí se descarta cualquier valor residual.
        $tipoProveedor = $tipoTercero === 'PROVEEDOR' ? limpio($_POST['tipo_proveedor'] ?? null) : null;
        $plazoPago = limpio($_POST['plazo_pago'] ?? null);
        if ($plazoPago === 'OTRO' && limpio($_POST['plazo_pago_otro'] ?? null)) {
            $plazoPago = limpio($_POST['plazo_pago_otro'] ?? null);
        }
        $pdo->prepare("INSERT INTO terceros_solicitudes (nit_cc, tipo_persona, tipo_tercero, nombre_completo, direccion, actividad_economica, telefono, correo, tipo_proveedor, plazo_pago, solicitado_por)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                terceros_normalizar_codigo($nit),
                $tipoPersona,
                $tipoTercero,
                $nombre,
                limpio($_POST['direccion'] ?? null),
                limpio($_POST['actividad_economica'] ?? null),
                limpio($_POST['telefono'] ?? null),
                filter_var($_POST['correo'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
                $tipoProveedor,
                $plazoPago,
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
$exportaciones = $puedeGestionar
    ? $pdo->query("SELECT * FROM terceros_exportaciones_log ORDER BY exportado_en DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC)
    : [];

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
<p class="subtitle">Para toda la empresa: cualquier área verifica si un proveedor, cliente o empleado ya está creado en Siesa por su NIT/CC (ej. antes de una compra, para pedir la factura electrónica) y, si no lo está, solicita su creación. La administración del maestro de Siesa (importar el Excel, marcar creado/rechazado) es exclusiva de Contabilidad.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<nav class="pe-tabs" id="pe-tabs">
    <button type="button" class="pe-tab-btn" data-target="pe-consulta"><?= icon('search') ?> Consultar Tercero</button>
    <button type="button" class="pe-tab-btn" data-target="pe-legalizacion"><?= icon('file') ?> Legalización / Solicitud de Creación</button>
    <button type="button" class="pe-tab-btn" data-target="pe-seguimiento"><?= icon('log') ?> Seguimiento de Solicitudes <?= count($solicitudes) ? '(' . count($solicitudes) . ')' : '' ?></button>
    <?php if ($puedeGestionar): ?>
    <button type="button" class="pe-tab-btn" data-target="pe-importar"><?= icon('upload') ?> Importar maestro (Excel Siesa)</button>
    <button type="button" class="pe-tab-btn" data-target="pe-exportlog"><?= icon('log') ?> Historial de exportaciones</button>
    <?php endif; ?>
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
                <div><label>Tipo de tercero *</label>
                    <select name="tipo_tercero" id="pe-sel-tipo-tercero" required>
                        <?php foreach ($tiposTercero as $tt): ?><option value="<?= e($tt) ?>"><?= e(ucfirst(strtolower($tt))) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label>1. NIT o CC *</label><input type="text" name="nit_cc" value="<?= e($nitConsultado) ?>" required></div>
                <div><label>2. Tipo de persona</label>
                    <select name="tipo_persona" id="pe-sel-tipo-persona">
                        <option value="NATURAL">Natural</option>
                        <option value="JURIDICA">Jurídica</option>
                        <option value="OTRO">Otro</option>
                    </select>
                </div>
                <div id="pe-campo-tipo-persona-otro" style="display:none;"><label>Especifique el tipo de persona</label><input type="text" name="tipo_persona_otro"></div>
                <div style="grid-column:span 2;"><label>3. Nombre completo / Razón social *</label><input type="text" name="nombre_completo" required></div>
                <div style="grid-column:span 2;"><label>4. Dirección</label><input type="text" name="direccion"></div>
                <div><label>5. Actividad económica</label><input type="text" name="actividad_economica"></div>
                <div><label>6. Teléfono</label><input type="text" name="telefono"></div>
                <div><label>7. Correo</label><input type="email" name="correo"></div>
                <div id="pe-campo-tipo-proveedor"><label>8. Categoría del proveedor</label>
                    <select name="tipo_proveedor">
                        <?php foreach ($tiposProveedor as $tp): ?><option><?= e($tp) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label>9. Plazo de pago</label>
                    <select name="plazo_pago" id="pe-sel-plazo-pago">
                        <?php foreach ($plazosPago as $pp): ?><option><?= e($pp) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div id="pe-campo-plazo-otro" style="display:none;"><label>Especifique el plazo de pago</label><input type="text" name="plazo_pago_otro"></div>
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
        <?php if ($puedeGestionar): ?>
        <p class="small">Como NAVISSI no tiene conexión directa de escritura a Siesa, exporta esta lista y súbela allá manualmente:</p>
        <div class="toolbar" style="margin-bottom:14px;">
            <a class="btn btn-secondary" href="?exportar=csv">CSV</a>
            <a class="btn btn-secondary" href="?exportar=txt">TXT (plano, separado por |)</a>
            <a class="btn btn-secondary" href="?exportar=json">JSON</a>
            <a class="btn btn-secondary" href="?exportar=csv&estado=PENDIENTE">CSV — solo pendientes</a>
        </div>
        <?php endif; ?>
        <table>
            <tr><th>Tipo</th><th>NIT/CC</th><th>Nombre</th><th>Tipo proveedor</th><th>Plazo</th><th>Documentos</th><th>Estado</th><th>Exportado</th><th>Solicitado por</th><th>Fecha</th><?php if ($puedeGestionar): ?><th></th><?php endif; ?></tr>
            <?php foreach ($solicitudes as $s): ?>
            <tr>
                <td><?= e($s['tipo_tercero']) ?: 'Proveedor' ?></td>
                <td><?= e($s['nit_cc']) ?></td>
                <td><?= e($s['nombre_completo']) ?></td>
                <td><?= e($s['tipo_proveedor']) ?: '—' ?></td>
                <td><?= e($s['plazo_pago']) ?: '—' ?></td>
                <td><?= (int) $s['n_adjuntos'] ?>/4</td>
                <td><span class="badge <?= $s['estado']==='CREADO_EN_SIESA'?'badge-activo':($s['estado']==='RECHAZADO'?'badge-err':'badge-otro') ?>"><?= e($s['estado']) ?></span></td>
                <td class="small"><?= $s['exportado_en'] ? e($s['exportado_en']) . ' · ' . e($s['exportado_por']) : 'Nunca exportado' ?></td>
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
            <?php if (!$solicitudes): ?><tr><td colspan="11" class="small">Sin solicitudes todavía.</td></tr><?php endif; ?>
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

<div class="pe-panel" id="pe-exportlog">
    <div class="panel">
        <h3><?= icon('log') ?> Historial de exportaciones</h3>
        <p class="small">Cada vez que alguien exporta solicitudes (TXT/CSV/JSON) para subirlas manualmente a Siesa queda un registro aquí, junto con cuáles solicitudes quedaron marcadas como "exportadas".</p>
        <table>
            <tr><th>Fecha</th><th>Formato</th><th>Filtro estado</th><th>Cantidad</th><th>Exportado por</th></tr>
            <?php foreach ($exportaciones as $ex): ?>
            <tr>
                <td class="small"><?= e($ex['exportado_en']) ?></td>
                <td><?= e(strtoupper($ex['formato'])) ?></td>
                <td><?= e($ex['filtro_estado']) ?: 'Todos' ?></td>
                <td><?= (int) $ex['cantidad'] ?></td>
                <td class="small"><?= e($ex['exportado_por']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$exportaciones): ?><tr><td colspan="5" class="small">Sin exportaciones todavía.</td></tr><?php endif; ?>
        </table>
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

    // Categoría de proveedor solo aplica cuando el tercero es Proveedor -
    // para Cliente/Empleado/Otro se oculta (no se envía por si el navegador
    // no soporta 'disabled' bien en submits, se limpia igual en servidor).
    var selTipoTercero = document.getElementById('pe-sel-tipo-tercero');
    var campoTipoProveedor = document.getElementById('pe-campo-tipo-proveedor');
    function actualizarTipoProveedor() {
        var esProveedor = selTipoTercero.value === 'PROVEEDOR';
        campoTipoProveedor.style.display = esProveedor ? '' : 'none';
    }
    if (selTipoTercero && campoTipoProveedor) {
        selTipoTercero.addEventListener('change', actualizarTipoProveedor);
        actualizarTipoProveedor();
    }

    // "Otro" en tipo de persona / plazo de pago habilita un campo de texto
    // para especificar el valor real en vez de quedarse con el genérico "Otro".
    function habilitarOtro(selectId, campoId) {
        var sel = document.getElementById(selectId);
        var campo = document.getElementById(campoId);
        if (!sel || !campo) return;
        function actualizar() { campo.style.display = sel.value === 'OTRO' ? '' : 'none'; }
        sel.addEventListener('change', actualizar);
        actualizar();
    }
    habilitarOtro('pe-sel-tipo-persona', 'pe-campo-tipo-persona-otro');
    habilitarOtro('pe-sel-plazo-pago', 'pe-campo-plazo-otro');
})();
</script>
<?php layout_fin(); ?>
