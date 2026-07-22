<?php
// Gestión completa del proceso de selección de UN candidato: etapas, citas
// (virtuales con link o presenciales con lugar), documentos de cada etapa
// (entrevista, pruebas técnicas, clínica de ventas, exámenes médicos), y el
// paso final de contratación que crea el registro de empleado con su código
// único, listo para habilitarle el Portal de Autogestión.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['GERENCIA', 'CEO', 'ADMIN', 'RRHH', 'DIRECTOR'])) {
    layout_inicio('Candidato', 'Vacantes', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar candidatos.</div>';
    layout_fin();
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT c.*, v.titulo AS vacante_titulo, v.area AS vacante_area FROM candidatos c JOIN vacantes v ON v.id = c.vacante_id WHERE c.id = ?");
$stmt->execute([$id]);
$candidato = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$candidato) {
    layout_inicio('Candidato no encontrado', 'Vacantes', '../');
    echo '<div class="msg-error">Ese candidato no existe.</div><a class="btn" href="vacantes.php">Volver</a>';
    layout_fin();
    exit;
}

$etapas = ['RECIBIDO' => 'Recibido', 'ENTREVISTA' => 'Entrevista', 'PRUEBAS' => 'Pruebas técnicas', 'ESTUDIO_DOCUMENTOS' => 'Estudio de documentos', 'EXAMEN_MEDICO' => 'Examen médico', 'CONTRATADO' => 'Contratado', 'RECHAZADO' => 'Rechazado'];
$tiposDocumento = ['RESULTADO_ENTREVISTA' => 'Resultado de entrevista', 'PRUEBA_TECNICA' => 'Prueba técnica', 'CLINICA_VENTAS' => 'Clínica de ventas', 'EXAMEN_MEDICO' => 'Examen médico', 'ESTUDIO_SEGURIDAD' => 'Estudio de seguridad/documentos', 'OTRO' => 'Otro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_etapa') {
        $nuevaEtapa = array_key_exists($_POST['estado'] ?? '', $etapas) ? $_POST['estado'] : null;
        if ($nuevaEtapa) {
            $pdo->prepare("UPDATE candidatos SET estado = ?, notas = ? WHERE id = ?")
                ->execute([$nuevaEtapa, limpio($_POST['notas'] ?? $candidato['notas']), $id]);
            $msg = ['ok', 'Etapa actualizada a "' . $etapas[$nuevaEtapa] . '".'];
            $candidato['estado'] = $nuevaEtapa;
        }
    } elseif ($accion === 'agendar_cita') {
        $etapa = array_key_exists($_POST['etapa'] ?? '', $etapas) ? $_POST['etapa'] : 'ENTREVISTA';
        $modalidad = ($_POST['modalidad'] ?? '') === 'PRESENCIAL' ? 'PRESENCIAL' : 'VIRTUAL';
        $fechaHora = trim($_POST['fecha_hora'] ?? '');
        if (!$fechaHora) {
            $msg = ['error', 'Elige fecha y hora de la cita.'];
        } else {
            $pdo->prepare("INSERT INTO candidatos_citas (candidato_id, etapa, fecha_hora, modalidad, link_reunion, lugar, notas, creado_por) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$id, $etapa, $fechaHora, $modalidad,
                    $modalidad === 'VIRTUAL' ? limpio($_POST['link_reunion'] ?? null) : null,
                    $modalidad === 'PRESENCIAL' ? limpio($_POST['lugar'] ?? null) : null,
                    limpio($_POST['notas_cita'] ?? null), $u['nombre'] ?? 'Sistema']);
            $msg = ['ok', 'Cita agendada. El candidato la verá al consultar su proceso.'];
        }
    } elseif ($accion === 'cita_estado') {
        $nuevoEstadoCita = in_array($_POST['estado_cita'] ?? '', ['REALIZADA', 'CANCELADA'], true) ? $_POST['estado_cita'] : null;
        if ($nuevoEstadoCita) {
            $pdo->prepare("UPDATE candidatos_citas SET estado = ? WHERE id = ? AND candidato_id = ?")
                ->execute([$nuevoEstadoCita, (int) ($_POST['cita_id'] ?? 0), $id]);
            $msg = ['ok', 'Cita actualizada.'];
        }
    } elseif ($accion === 'subir_documento') {
        $etapaDoc = array_key_exists($_POST['etapa_doc'] ?? '', $etapas) ? $_POST['etapa_doc'] : $candidato['estado'];
        $tipoDoc = array_key_exists($_POST['tipo_doc'] ?? '', $tiposDocumento) ? $_POST['tipo_doc'] : 'OTRO';
        if (empty($_FILES['documento']['tmp_name']) || !is_uploaded_file($_FILES['documento']['tmp_name'])) {
            $msg = ['error', 'Selecciona un archivo.'];
        } else {
            $tamano = (int) ($_FILES['documento']['size'] ?? 0);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['documento']['tmp_name']) ?: '';
            $permitidos = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
                'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];
            if ($tamano <= 0 || $tamano > 15 * 1024 * 1024 || !isset($permitidos[$mime])) {
                $msg = ['error', 'Archivo inválido: debe ser PDF, Word o imagen, máximo 15MB.'];
            } else {
                $dir = __DIR__ . '/../data/candidatos_documentos';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $rutaGuardada = bin2hex(random_bytes(18)) . '.' . $permitidos[$mime];
                if (move_uploaded_file($_FILES['documento']['tmp_name'], $dir . '/' . $rutaGuardada)) {
                    $pdo->prepare("INSERT INTO candidatos_documentos (candidato_id, etapa, tipo, nombre_archivo, ruta, subido_por) VALUES (?,?,?,?,?,?)")
                        ->execute([$id, $etapaDoc, $tipoDoc, basename($_FILES['documento']['name']), $rutaGuardada, $u['nombre'] ?? 'Sistema']);
                    $msg = ['ok', 'Documento guardado.'];
                }
            }
        }
    } elseif ($accion === 'contratar') {
        if (!$candidato['documento']) {
            $msg = ['error', 'El candidato no tiene número de documento registrado, no se puede crear el empleado.'];
        } else {
            $existente = $pdo->prepare("SELECT id, codigo_empleado FROM empleados WHERE documento = ?");
            $existente->execute([$candidato['documento']]);
            $emp = $existente->fetch(PDO::FETCH_ASSOC);
            if ($emp) {
                $pdo->prepare("UPDATE empleados SET estado = 'ACTIVO', candidato_id = ?, fecha_ingreso = COALESCE(fecha_ingreso, ?) WHERE id = ?")
                    ->execute([$id, gmdate('Y-m-d'), $emp['id']]);
                $codigoNuevo = $emp['codigo_empleado'];
            } else {
                $pdo->prepare("INSERT INTO empleados (documento, nombres, email, estado, fecha_ingreso, candidato_id) VALUES (?,?,?,?,?,?)")
                    ->execute([$candidato['documento'], $candidato['nombre'], $candidato['email'], 'ACTIVO', gmdate('Y-m-d'), $id]);
                $nuevoId = (int) $pdo->lastInsertId();
                $codigoNuevo = 'EMP-' . str_pad((string) $nuevoId, 5, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE empleados SET codigo_empleado = ? WHERE id = ?")->execute([$codigoNuevo, $nuevoId]);
            }
            $pdo->prepare("UPDATE candidatos SET estado = 'CONTRATADO' WHERE id = ?")->execute([$id]);
            hoja_vida_registrar($pdo, 'EMPLEADO', $candidato['documento'], 'CONTRATADO', "Contratado desde el proceso de selección de \"{$candidato['vacante_titulo']}\" (candidato #{$id}). Código: {$codigoNuevo}.", $u['nombre'] ?? 'Sistema');
            $candidato['estado'] = 'CONTRATADO';
            $msg = ['ok', "¡Contratado! Se creó el empleado con código {$codigoNuevo}. Ve a Empleados para completar cargo/área/sede/salario, luego a Documentos RRHH para el proceso de ingreso, y a \"Crear acceso\" para habilitar su Portal de Autogestión."];
        }
    }
    $stmt->execute([$id]);
    $candidato = $stmt->fetch(PDO::FETCH_ASSOC);
}

$citas = $pdo->prepare("SELECT * FROM candidatos_citas WHERE candidato_id = ? ORDER BY fecha_hora DESC");
$citas->execute([$id]);
$citas = $citas->fetchAll(PDO::FETCH_ASSOC);

$documentos = $pdo->prepare("SELECT * FROM candidatos_documentos WHERE candidato_id = ? ORDER BY creado_en DESC");
$documentos->execute([$id]);
$documentos = $documentos->fetchAll(PDO::FETCH_ASSOC);

$empleadoVinculado = null;
if ($candidato['documento']) {
    $stmtEmp = $pdo->prepare("SELECT codigo_empleado FROM empleados WHERE documento = ?");
    $stmtEmp->execute([$candidato['documento']]);
    $empleadoVinculado = $stmtEmp->fetchColumn() ?: null;
}

layout_inicio($candidato['nombre'], 'Vacantes', '../');
?>
<p class="small"><a href="vacantes.php">← Volver a Vacantes</a></p>
<h1><?= icon('users', 'icon-lg') ?> <?= e($candidato['nombre']) ?></h1>
<p class="subtitle">Aplicó a "<?= e($candidato['vacante_titulo']) ?>" (<?= e($candidato['vacante_area']) ?>) · <?= e($candidato['documento']) ?: 'Sin documento' ?></p>
<?php if ($msg): ?><div class="msg-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('sliders') ?> Etapa actual del proceso</h3>
    <p>Estado: <span class="badge <?= $candidato['estado']==='CONTRATADO'?'badge-activo':($candidato['estado']==='RECHAZADO'?'badge-err':'badge-otro') ?>" style="font-size:13px;"><?= e($etapas[$candidato['estado']] ?? $candidato['estado']) ?></span></p>
    <?php if (!in_array($candidato['estado'], ['CONTRATADO', 'RECHAZADO'], true)): ?>
    <form method="post" class="toolbar" style="margin-top:8px;">
        <input type="hidden" name="accion" value="cambiar_etapa">
        <select name="estado">
            <?php foreach ($etapas as $val => $etiqueta): ?><option value="<?= e($val) ?>" <?= $candidato['estado']===$val?'selected':'' ?>><?= e($etiqueta) ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="notas" value="<?= e($candidato['notas'] ?? '') ?>" placeholder="Notas del proceso" style="min-width:260px">
        <button type="submit"><?= icon('check') ?> Actualizar etapa</button>
    </form>
    <?php if ($candidato['documento']): ?>
    <form method="post" style="margin-top:10px;" onsubmit="return confirm('¿Contratar a <?= e(addslashes($candidato['nombre'])) ?>? Esto crea su registro de empleado con código único.');">
        <input type="hidden" name="accion" value="contratar">
        <button type="submit"><?= icon('check') ?> Marcar como contratado</button>
    </form>
    <?php endif; ?>
    <?php else: ?>
    <p class="small">Proceso cerrado.<?php if ($empleadoVinculado): ?> Empleado: <strong><?= e($empleadoVinculado) ?></strong> — <a href="expediente_empleado.php?codigo=<?= urlencode($empleadoVinculado) ?>">Ver expediente</a> · <a href="rrhh.php">Ir a Empleados</a> para completar cargo/área/sede/salario.<?php endif; ?></p>
    <?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('log') ?> Citas (entrevistas, pruebas, exámenes)</h3>
    <?php if (!in_array($candidato['estado'], ['CONTRATADO', 'RECHAZADO'], true)): ?>
    <form method="post" class="grid-form" style="margin-bottom:14px;">
        <input type="hidden" name="accion" value="agendar_cita">
        <div><label>Etapa de la cita</label>
            <select name="etapa">
                <?php foreach (['ENTREVISTA', 'PRUEBAS', 'EXAMEN_MEDICO'] as $val): ?><option value="<?= e($val) ?>"><?= e($etapas[$val]) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label>Fecha y hora</label><input type="datetime-local" name="fecha_hora" required></div>
        <div><label>Modalidad</label>
            <select name="modalidad" id="sel-modalidad">
                <option value="VIRTUAL">Virtual</option>
                <option value="PRESENCIAL">Presencial</option>
            </select>
        </div>
        <div id="campo-link"><label>Link de la reunión</label><input type="url" name="link_reunion" placeholder="https://meet.google.com/..."></div>
        <div id="campo-lugar" hidden><label>Lugar</label><input type="text" name="lugar" placeholder="Oficina, dirección..."></div>
        <div style="grid-column:span 2;"><label>Notas para el candidato</label><input type="text" name="notas_cita" placeholder="Ej: traer cédula original"></div>
        <div style="grid-column:span 2;"><button type="submit"><?= icon('plus') ?> Agendar cita</button></div>
    </form>
    <script>
    document.getElementById('sel-modalidad').addEventListener('change', function () {
        document.getElementById('campo-link').hidden = this.value !== 'VIRTUAL';
        document.getElementById('campo-lugar').hidden = this.value !== 'PRESENCIAL';
    });
    </script>
    <?php endif; ?>
    <table>
        <tr><th>Etapa</th><th>Fecha y hora</th><th>Modalidad</th><th>Detalle</th><th>Estado</th><th></th></tr>
        <?php foreach ($citas as $c): ?>
        <tr>
            <td><?= e($etapas[$c['etapa']] ?? $c['etapa']) ?></td>
            <td><?= e($c['fecha_hora']) ?></td>
            <td><?= e($c['modalidad']) ?></td>
            <td class="small"><?= $c['modalidad']==='VIRTUAL' ? ($c['link_reunion'] ? '<a href="'.e($c['link_reunion']).'" target="_blank">Unirse</a>' : '—') : (e($c['lugar']) ?: '—') ?></td>
            <td><span class="badge <?= $c['estado']==='REALIZADA'?'badge-activo':($c['estado']==='CANCELADA'?'badge-err':'badge-otro') ?>"><?= e($c['estado']) ?></span></td>
            <td>
                <?php if ($c['estado'] === 'PENDIENTE'): ?>
                <form method="post" class="inline"><input type="hidden" name="accion" value="cita_estado"><input type="hidden" name="cita_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="estado_cita" value="REALIZADA"><button type="submit" style="padding:3px 8px;font-size:11px;">Marcar realizada</button></form>
                <form method="post" class="inline"><input type="hidden" name="accion" value="cita_estado"><input type="hidden" name="cita_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="estado_cita" value="CANCELADA"><button type="submit" class="btn-danger" style="padding:3px 8px;font-size:11px;">Cancelar</button></form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$citas): ?><tr><td colspan="6" class="small">Sin citas agendadas.</td></tr><?php endif; ?>
    </table>
</div>

<div class="panel">
    <h3><?= icon('folder') ?> Documentos del proceso (entrevistas, pruebas técnicas, clínica de ventas, exámenes médicos)</h3>
    <?php if (!in_array($candidato['estado'], ['CONTRATADO', 'RECHAZADO'], true)): ?>
    <form method="post" enctype="multipart/form-data" class="toolbar" style="margin-bottom:14px;">
        <input type="hidden" name="accion" value="subir_documento">
        <select name="etapa_doc">
            <?php foreach ($etapas as $val => $etiqueta): if (in_array($val, ['CONTRATADO','RECHAZADO'], true)) continue; ?><option value="<?= e($val) ?>" <?= $candidato['estado']===$val?'selected':'' ?>><?= e($etiqueta) ?></option><?php endforeach; ?>
        </select>
        <select name="tipo_doc">
            <?php foreach ($tiposDocumento as $val => $etiqueta): ?><option value="<?= e($val) ?>"><?= e($etiqueta) ?></option><?php endforeach; ?>
        </select>
        <input type="file" name="documento" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
        <button type="submit"><?= icon('upload') ?> Subir</button>
    </form>
    <?php endif; ?>
    <table>
        <tr><th>Etapa</th><th>Tipo</th><th>Archivo</th><th>Subido por</th><th>Fecha</th></tr>
        <?php foreach ($documentos as $d): ?>
        <tr>
            <td><?= e($etapas[$d['etapa']] ?? $d['etapa']) ?></td>
            <td><?= e($tiposDocumento[$d['tipo']] ?? $d['tipo']) ?></td>
            <td><a href="descargar_documento_candidato.php?id=<?= (int)$d['id'] ?>" target="_blank"><?= icon('file') ?> <?= e($d['nombre_archivo']) ?></a></td>
            <td class="small"><?= e($d['subido_por']) ?></td>
            <td class="small"><?= e($d['creado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$documentos): ?><tr><td colspan="5" class="small">Sin documentos cargados.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
