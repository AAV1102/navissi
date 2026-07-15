<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ia_triage.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
requiere_login('../');
$u = usuario_actual();
$msg = null;

// Datos del empleado, si su documento está vinculado a RRHH
$empleado = null;
if ($u['documento']) {
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE documento = ?");
    $stmt->execute([$u['documento']]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Control de asistencia: marcar entrada/salida del día de hoy.
$msgAsistencia = null;
$hoyFecha = date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['marcar_entrada', 'marcar_salida'], true) && $u['documento']) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if (($_POST['accion'] ?? '') === 'marcar_entrada') {
        try {
            $pdo->prepare("INSERT INTO asistencia (empleado_documento, empleado_nombre, fecha, hora_entrada, ip_entrada) VALUES (?,?,?,?,?)")
                ->execute([$u['documento'], $u['nombre'], $hoyFecha, date('H:i:s'), $ip]);
            $msgAsistencia = ['ok', 'Entrada marcada a las ' . date('H:i') . '.'];
        } catch (PDOException $e) {
            $msgAsistencia = ['error', 'Ya marcaste entrada hoy.'];
        }
    } else {
        $pdo->prepare("UPDATE asistencia SET hora_salida = ?, ip_salida = ? WHERE empleado_documento = ? AND fecha = ? AND hora_salida IS NULL")
            ->execute([date('H:i:s'), $ip, $u['documento'], $hoyFecha]);
        $msgAsistencia = ['ok', 'Salida marcada a las ' . date('H:i') . '.'];
    }
}
$asistenciaHoy = null;
if ($u['documento']) {
    $stmt = $pdo->prepare("SELECT * FROM asistencia WHERE empleado_documento = ? AND fecha = ?");
    $stmt->execute([$u['documento'], $hoyFecha]);
    $asistenciaHoy = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Detecta automáticamente el/los equipos asignados a este empleado (por nombre,
// igual que en la ficha de empleado), para que el ticket lleve el contexto técnico
// (serial, marca, modelo, SO) SIN que el empleado tenga que saber ni escribir nada
// técnico - solo describe el problema en sus palabras. Se reutiliza también en la
// pestaña de Inventario para que vea su propio equipo asignado.
$misEquipos = [];
if ($empleado) {
    $primerNombre = explode(' ', trim($empleado['nombres']))[0] ?? '';
    if ($primerNombre) {
        $stmt = $pdo->prepare("SELECT * FROM inventario WHERE asignado_a LIKE ?");
        $stmt->execute(['%' . $primerNombre . '%']);
        $misEquipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_ticket') {
    $titulo = limpio($_POST['titulo'] ?? null);
    if ($titulo) {
        $prioridad = limpio($_POST['prioridad'] ?? null) ?: 'MEDIA';
        $horasSla = ['URGENTE' => 4, 'ALTA' => 8, 'MEDIA' => 24, 'BAJA' => 72][$prioridad] ?? 24;
        $slaLimite = gmdate('Y-m-d H:i:s', strtotime("+{$horasSla} hours"));
        $sedeId = $u['sede_id'];
        $descripcion = limpio($_POST['descripcion'] ?? null) ?: '';
        $equipoSerial = limpio($_POST['equipo_serial'] ?? null);

        // Auto-relleno técnico: si el empleado marcó su equipo, se le agrega la ficha
        // técnica a la descripción automáticamente (invisible para él, pero la IA y
        // el técnico la ven completa desde el primer momento).
        if ($equipoSerial) {
            $stmt = $pdo->prepare("SELECT * FROM inventario WHERE serial = ?");
            $stmt->execute([$equipoSerial]);
            $eq = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($eq) {
                $descripcion .= "\n\n[Ficha técnica autodetectada]\nEquipo: {$eq['tipo']} {$eq['marca']} {$eq['modelo']} (serial {$eq['serial']}, placa {$eq['placa']})\nSistema operativo: {$eq['sistema_operativo']}\nProcesador: {$eq['procesador']} · Memoria: {$eq['memoria']} · Almacenamiento: {$eq['almacenamiento']}\nEstado actual en inventario: {$eq['estado']}";
            }
        }

        $stmtAreaU = $pdo->prepare("SELECT area FROM empleados WHERE documento = ?");
        $stmtAreaU->execute([$u['documento']]);
        $areaSolicitanteU = $stmtAreaU->fetchColumn() ?: null;
        $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, sede_id, solicitante, solicitante_contacto, sla_limite, origen, creado_por_documento, equipo_serial, solicitante_area)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$titulo, $descripcion, limpio($_POST['categoria'] ?? null) ?: 'SOPORTE',
                $prioridad, $sedeId, $u['nombre'], $u['email'], $slaLimite, 'PORTAL_EMPLEADO', $u['documento'], $equipoSerial, $areaSolicitanteU]);
        $nuevoId = $pdo->lastInsertId();
        hoja_vida_registrar($pdo, 'EMPLEADO', (string) $u['documento'], 'TICKET_CREADO', $titulo, $u['nombre'], $nuevoId);
        if ($equipoSerial) hoja_vida_registrar($pdo, 'EQUIPO', $equipoSerial, 'TICKET_REPORTADO', $titulo, $u['nombre'], $nuevoId);
        ia_triage_ticket($pdo, $nuevoId);
        $msg = ['ok', 'Tu solicitud fue enviada con la ficha de tu equipo adjunta automáticamente. Si la IA encuentra una solución, la verás aquí abajo en unos segundos.'];
    }
}

// Autoservicio RRHH: vacaciones, permisos, horas extra, licencias, incapacidades.
// RRHH solo revisa y aprueba/rechaza - el empleado sube su propio soporte cuando aplica.
$msgRrhh = null;
$tiposRequierenAdjunto = ['INCAPACIDAD', 'LICENCIA'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_solicitud_rrhh') {
    $tipo = limpio($_POST['tipo'] ?? null) ?: 'PERMISO';
    $requiereAdjunto = in_array($tipo, $tiposRequierenAdjunto, true);
    if ($requiereAdjunto && empty($_FILES['adjunto']['tmp_name'])) {
        $msgRrhh = ['error', 'Para ' . strtolower($tipo) . ' es obligatorio adjuntar el soporte (PDF o imagen).'];
    } elseif (!$u['documento']) {
        $msgRrhh = ['error', 'Tu usuario no tiene documento vinculado - pide a RRHH que lo complete antes de poder solicitar esto.'];
    } else {
        $dias = null;
        if (!empty($_POST['fecha_inicio']) && !empty($_POST['fecha_fin'])) {
            $dias = (int) ((strtotime($_POST['fecha_fin']) - strtotime($_POST['fecha_inicio'])) / 86400) + 1;
        }
        $adjuntoRuta = null; $adjuntoNombre = null;
        if (!empty($_FILES['adjunto']['tmp_name'])) {
            $dirAdj = __DIR__ . '/../data/desprendibles';
            if (!is_dir($dirAdj)) mkdir($dirAdj, 0777, true);
            $original = basename($_FILES['adjunto']['name']);
            $seguro = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $original);
            $adjuntoRuta = uniqid() . '_' . $seguro;
            move_uploaded_file($_FILES['adjunto']['tmp_name'], $dirAdj . '/' . $adjuntoRuta);
            $adjuntoNombre = $original;
        }
        $pdo->prepare("INSERT INTO vacaciones_permisos (empleado_documento, empleado_nombre, tipo, fecha_inicio, fecha_fin, dias, motivo, adjunto_ruta, adjunto_nombre)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$u['documento'], $u['nombre'], $tipo, limpio($_POST['fecha_inicio'] ?? null), limpio($_POST['fecha_fin'] ?? null),
                $dias, limpio($_POST['motivo'] ?? null), $adjuntoRuta, $adjuntoNombre]);
        hoja_vida_registrar($pdo, 'EMPLEADO', $u['documento'], 'SOLICITUD_RRHH_CREADA', "{$tipo} - " . ($_POST['motivo'] ?? ''), $u['nombre']);
        $msgRrhh = ['ok', 'Solicitud enviada a Recursos Humanos.'];
    }
}
$misSolicitudesRrhh = [];
if ($u['documento']) {
    $stmt = $pdo->prepare("SELECT * FROM vacaciones_permisos WHERE empleado_documento = ? ORDER BY creado_en DESC LIMIT 15");
    $stmt->execute([$u['documento']]);
    $misSolicitudesRrhh = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$misTickets = [];
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE creado_por_documento = ? OR solicitante_contacto = ? ORDER BY creado_en DESC LIMIT 20");
$stmt->execute([$u['documento'], $u['email']]);
$misTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$desprendibles = [];
$misComprobantesNomina = [];
$misDocumentosRrhh = [];
if ($u['documento']) {
    $stmt = $pdo->prepare("SELECT * FROM desprendibles WHERE empleado_documento = ? ORDER BY periodo DESC");
    $stmt->execute([$u['documento']]);
    $desprendibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT n.id, n.neto_pagar, n.estado, p.nombre AS periodo_nombre
        FROM nominas n JOIN periodos_nomina p ON p.id = n.periodo_id
        WHERE n.empleado_documento = ? ORDER BY p.fecha_inicio DESC LIMIT 6");
    $stmt->execute([$u['documento']]);
    $misComprobantesNomina = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id, tipo, nombre_archivo, estado_firma, creado_en FROM documentos_rrhh WHERE empleado_documento = ? ORDER BY creado_en DESC");
    $stmt->execute([$u['documento']]);
    $misDocumentosRrhh = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Capacitación: cursos visibles para el área del empleado (o generales/todas las
// áreas), con el progreso de lecciones completadas por él mismo.
$misCursos = [];
try {
    $areaEmpleado = $empleado['area'] ?? $u['area_responsable'] ?? null;
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE estado = 'PUBLICADO' AND (area = ? OR area = 'GENERAL' OR area IS NULL OR area = '') ORDER BY creado_en DESC");
    $stmt->execute([$areaEmpleado]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cursos as $c) {
        $stmtL = $pdo->prepare("SELECT COUNT(*) FROM lecciones WHERE curso_id = ?");
        $stmtL->execute([$c['id']]);
        $totalLecciones = (int) $stmtL->fetchColumn();
        $completadas = 0;
        if ($u['documento'] && $totalLecciones > 0) {
            $stmtP = $pdo->prepare("SELECT COUNT(*) FROM progreso_cursos pc JOIN lecciones l ON l.id = pc.leccion_id WHERE l.curso_id = ? AND pc.empleado_documento = ?");
            $stmtP->execute([$c['id'], $u['documento']]);
            $completadas = (int) $stmtP->fetchColumn();
        }
        $c['total_lecciones'] = $totalLecciones;
        $c['completadas'] = $completadas;
        $misCursos[] = $c;
    }
} catch (\Throwable $e) { /* modulo de capacitacion aun no migrado en este entorno */ }

$iniciales = mb_strtoupper(mb_substr($u['nombre'] ?? '?', 0, 1));
layout_inicio('Mi Portal de Empleado', 'Dashboard', '../');
?>
<style>
.pe-tabs{display:flex;gap:4px;overflow-x:auto;border-bottom:2px solid var(--line);margin:18px 0 0;}
.pe-tab-btn{border:none;background:none;padding:10px 16px;font:inherit;font-weight:600;color:var(--ink-500,#5b6472);cursor:pointer;border-radius:8px 8px 0 0;white-space:nowrap;display:flex;align-items:center;gap:6px;}
.pe-tab-btn:hover{background:var(--surface-hover,#f2f4f7);}
.pe-tab-btn.activo{color:var(--navy-900);background:var(--surface-hover,#f2f4f7);box-shadow:inset 0 -2px 0 var(--gold-500);}
.pe-tab-badge{background:var(--teal-500,#0f9d8e);color:#fff;border-radius:20px;font-size:11px;padding:1px 7px;}
.pe-panel{display:none;padding-top:16px;}
.pe-panel.activo{display:block;}
</style>
<div class="panel" style="display:flex;align-items:center;gap:16px;background:linear-gradient(135deg,var(--navy-900),var(--navy-700));border:none;color:#fff;">
    <span class="avatar" style="width:52px;height:52px;font-size:20px;flex-shrink:0;"><?= e($iniciales) ?></span>
    <div>
        <h1 style="color:#fff;margin:0;"><?= e($empleado['nombres'] ?? $u['nombre']) ?></h1>
        <p class="small" style="color:var(--gold-500);margin:4px 0 0;"><?= e($empleado['cargo'] ?? '') ?><?= $empleado && $empleado['area'] ? ' · ' . e($empleado['area']) : '' ?></p>
    </div>
</div>
<p class="subtitle" style="margin-top:14px;">Todo lo tuyo en un solo lugar: mesa de servicio, RRHH, documentos, capacitación e inventario.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel employee-service-entry"><div><span class="page-kicker">NUEVO · AUTOSERVICIO INTERÁREAS</span><h3>Catálogo de Servicios NAVISSI</h3><p class="small">Solicita accesos, equipos, compras o mantenimiento y sigue cada aprobación con responsable y SLA.</p></div><a class="btn" href="catalogo_servicios.php">Abrir catálogo</a></div>

<nav class="pe-tabs" id="pe-tabs">
    <button type="button" class="pe-tab-btn" data-target="pe-mesa"><?= icon('ticket') ?> Mesa de Servicio <span class="pe-tab-badge"><?= count($misTickets) ?></span></button>
    <button type="button" class="pe-tab-btn" data-target="pe-rrhh"><?= icon('briefcase') ?> RRHH y Nómina</button>
    <button type="button" class="pe-tab-btn" data-target="pe-documentos"><?= icon('folder') ?> Documentos <span class="pe-tab-badge"><?= count($misDocumentosRrhh) ?></span></button>
    <button type="button" class="pe-tab-btn" data-target="pe-capacitacion"><?= icon('graduation') ?> Capacitación <span class="pe-tab-badge"><?= count($misCursos) ?></span></button>
    <button type="button" class="pe-tab-btn" data-target="pe-inventario"><?= icon('inventory') ?> Mi Inventario <span class="pe-tab-badge"><?= count($misEquipos) ?></span></button>
</nav>

<div class="pe-panel" id="pe-mesa">
    <div class="panel">
        <h3>Solicitar ayuda a TI</h3>
        <p class="small">No necesitas saber nada técnico - solo cuenta qué te pasa, como se lo dirías a un compañero. Nosotros nos encargamos del resto.</p>
        <form method="post">
            <input type="hidden" name="accion" value="crear_ticket">
            <?php if ($misEquipos): ?>
            <div style="margin-bottom:14px;">
                <label class="small">Tu equipo (detectado automáticamente - no necesitas escribir la marca ni el modelo)</label>
                <?php foreach ($misEquipos as $i => $eq): ?>
                <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;margin-top:6px;cursor:pointer;">
                    <input type="radio" name="equipo_serial" value="<?= e($eq['serial']) ?>" <?= $i===0?'checked':'' ?>>
                    <span><strong><?= e($eq['tipo']) ?> <?= e($eq['marca']) ?> <?= e($eq['modelo']) ?></strong> <span class="small">(placa <?= e($eq['placa']) ?: '—' ?>, <?= e($eq['sistema_operativo']) ?: 'SO no registrado' ?>)</span></span>
                </label>
                <?php endforeach; ?>
                <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;margin-top:6px;cursor:pointer;">
                    <input type="radio" name="equipo_serial" value="">
                    <span class="small">No es sobre un equipo / no aplica</span>
                </label>
            </div>
            <?php else: ?>
            <div class="msg-error" style="margin-bottom:14px;">No encontramos ningún equipo asignado a tu nombre en el inventario - la solicitud igual se envía normal, solo que sin ficha técnica automática.</div>
            <?php endif; ?>
            <div class="grid-form">
                <div style="grid-column:span 2;"><label>¿Qué necesitas? *</label><input type="text" name="titulo" required placeholder="Ej: Mi computador está muy lento"></div>
                <div><label>Categoría</label>
                    <select name="categoria">
                        <?php foreach (['SOPORTE','INVENTARIO','RED/WIFI','SIESA','CORREO/M365','SOLICITUD RRHH','OTRO'] as $c): ?><option><?= $c ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label>¿Qué tan urgente es?</label>
                    <select name="prioridad">
                        <option value="BAJA">Puede esperar</option>
                        <option value="MEDIA" selected>Normal</option>
                        <option value="ALTA">Me está afectando el trabajo</option>
                        <option value="URGENTE">No puedo trabajar</option>
                    </select>
                </div>
            </div>
            <textarea name="descripcion" class="wysiwyg" rows="3" style="width:100%;margin-bottom:10px;" placeholder="Cuéntanos qué pasó, con tus palabras..."></textarea>
            <button type="submit"><?= icon('send') ?> Enviar solicitud</button>
        </form>
    </div>
    <div class="panel">
        <h3>Mis solicitudes (<?= count($misTickets) ?>)</h3>
        <table>
            <tr><th>#</th><th>Título</th><th>Estado</th><th>Respuesta</th><th>Fecha</th></tr>
            <?php foreach ($misTickets as $t):
                $stmtC = $pdo->prepare("SELECT autor, comentario FROM tickets_comentarios WHERE ticket_id = ? AND tipo IN ('IA','COMENTARIO') ORDER BY id DESC LIMIT 1");
                $stmtC->execute([$t['id']]);
                $ultimoComentario = $stmtC->fetch(PDO::FETCH_ASSOC);
            ?>
            <tr>
                <td>#<?= (int)$t['id'] ?></td><td><?= e($t['titulo']) ?></td>
                <td><span class="badge <?= $t['estado']==='CERRADO'?'badge-otro':($t['estado']==='RESUELTO POR IA'?'badge-activo':'') ?>" style="<?= $t['estado']==='RESUELTO POR IA' ? 'background:#d7f5df;color:#1a7a37;' : '' ?>"><?= e($t['estado']) ?></span></td>
                <td class="small" style="max-width:280px;"><?= $ultimoComentario ? '<strong>'.e($ultimoComentario['autor']).':</strong> '.e(mb_substr($ultimoComentario['comentario'],0,150)) : '—' ?></td>
                <td class="small"><?= e($t['creado_en']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$misTickets): ?><tr><td colspan="5" class="small">Sin solicitudes todavía.</td></tr><?php endif; ?>
        </table>
    </div>
</div>

<div class="pe-panel" id="pe-rrhh">
    <?php if ($u['documento']): ?>
    <div class="panel" style="border-left:4px solid var(--teal-500);">
        <h3><?= icon('briefcase') ?> Control de Asistencia - hoy <?= date('d/m/Y') ?></h3>
        <?php if ($msgAsistencia): ?><div class="msg-<?= $msgAsistencia[0] ?>"><?= e($msgAsistencia[1]) ?></div><?php endif; ?>
        <p class="small">
            Entrada: <strong><?= $asistenciaHoy['hora_entrada'] ?? 'sin marcar' ?></strong>
            · Salida: <strong><?= $asistenciaHoy['hora_salida'] ?? 'sin marcar' ?></strong>
        </p>
        <form method="post" class="toolbar" style="margin-bottom:0;">
            <?php if (!$asistenciaHoy): ?>
            <input type="hidden" name="accion" value="marcar_entrada">
            <button type="submit"><?= icon('check') ?> Marcar entrada</button>
            <?php elseif (!$asistenciaHoy['hora_salida']): ?>
            <input type="hidden" name="accion" value="marcar_salida">
            <button type="submit"><?= icon('check') ?> Marcar salida</button>
            <?php else: ?>
            <span class="badge badge-activo"><?= icon('check') ?> Jornada de hoy completa</span>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($empleado): ?>
    <div class="panel">
        <h3><?= e($empleado['nombres']) ?></h3>
        <p class="small"><?= e($empleado['cargo']) ?> · <?= e($empleado['area']) ?></p>
        <div class="toolbar" style="margin-bottom:0;">
            <a class="btn" href="certificado_laboral.php?documento=<?= urlencode($empleado['documento']) ?>" target="_blank">📄 Certificado laboral</a>
            <a class="btn btn-secondary" href="certificado_retiro.php?documento=<?= urlencode($empleado['documento']) ?>" target="_blank">📄 Certificado de retiro</a>
            <a class="btn btn-secondary" href="certificado_aportes.php?documento=<?= urlencode($empleado['documento']) ?>" target="_blank">📄 Certificación de aportes</a>
        </div>
    </div>
    <?php else: ?>
    <div class="msg-error">Tu usuario no está vinculado a un registro de RRHH (falta el número de documento). Pide a RRHH que lo complete en tu usuario para ver tu certificado y desprendibles.</div>
    <?php endif; ?>

    <div class="panel">
        <h3><?= icon('briefcase') ?> Solicitar a Recursos Humanos</h3>
        <p class="small">Vacaciones, permisos, horas extra, licencias o incapacidades - RRHH solo revisa y aprueba, aquí queda todo el trámite.</p>
        <?php if ($msgRrhh): ?><div class="msg-<?= $msgRrhh[0] ?>"><?= e($msgRrhh[1]) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear_solicitud_rrhh">
            <div class="grid-form">
                <div><label>Tipo</label>
                    <select name="tipo" onchange="document.getElementById('aviso-adjunto-obligatorio').hidden = !['INCAPACIDAD','LICENCIA'].includes(this.value); document.getElementById('campo-adjunto-rrhh').required = ['INCAPACIDAD','LICENCIA'].includes(this.value);">
                        <?php foreach (['VACACIONES','PERMISO','HORAS_EXTRA','LICENCIA','INCAPACIDAD'] as $t): ?><option value="<?= $t ?>"><?= ucwords(strtolower(str_replace('_',' ',$t))) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label>Fecha inicio</label><input type="date" name="fecha_inicio"></div>
                <div><label>Fecha fin</label><input type="date" name="fecha_fin"></div>
                <div><label>Soporte / documento <span id="aviso-adjunto-obligatorio" hidden style="color:#b3392c;">(obligatorio)</span></label><input type="file" name="adjunto" id="campo-adjunto-rrhh"></div>
            </div>
            <textarea name="motivo" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin:10px 0;" placeholder="Motivo / observación"></textarea>
            <button type="submit">Enviar solicitud a RRHH</button>
        </form>
    </div>

    <?php if ($misSolicitudesRrhh): ?>
    <div class="panel">
        <h3>Mis solicitudes a RRHH</h3>
        <table>
            <tr><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Soporte</th><th>Estado</th></tr>
            <?php foreach ($misSolicitudesRrhh as $sr): ?>
            <tr>
                <td><?= e($sr['tipo']) ?></td>
                <td><?= e($sr['fecha_inicio']) ?: '—' ?></td>
                <td><?= e($sr['fecha_fin']) ?: '—' ?></td>
                <td><?= $sr['adjunto_ruta'] ? '<a href="descargar_adjunto_rrhh.php?id=' . (int)$sr['id'] . '" target="_blank">Ver archivo</a>' : '—' ?></td>
                <td><span class="badge <?= $sr['estado']==='APROBADO'?'badge-activo':($sr['estado']==='RECHAZADO'?'badge-err':'badge-otro') ?>"><?= e($sr['estado']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <div class="panel">
        <h3>Mis desprendibles de pago (<?= count($desprendibles) ?>)</h3>
        <table>
            <tr><th>Periodo</th><th></th></tr>
            <?php foreach ($desprendibles as $d): ?>
            <tr><td><?= e($d['periodo']) ?></td><td><a href="rrhh_certificados.php?descargar=<?= (int)$d['id'] ?>">Descargar</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$desprendibles): ?><tr><td colspan="2" class="small">Aún no hay desprendibles cargados.</td></tr><?php endif; ?>
        </table>
    </div>

    <div class="panel">
        <h3>Mis comprobantes de nómina (<?= count($misComprobantesNomina) ?>)</h3>
        <table>
            <tr><th>Periodo</th><th>Neto pagado</th><th>Estado</th><th></th></tr>
            <?php foreach ($misComprobantesNomina as $c): ?>
            <tr>
                <td><?= e($c['periodo_nombre']) ?></td>
                <td>$<?= number_format((float)$c['neto_pagar'],0,',','.') ?></td>
                <td><span class="badge <?= $c['estado']==='PAGADA'?'badge-activo':'badge-otro' ?>"><?= e($c['estado']) ?></span></td>
                <td><a href="comprobante_nomina_pdf.php?id=<?= (int)$c['id'] ?>" target="_blank">Descargar PDF</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$misComprobantesNomina): ?><tr><td colspan="4" class="small">Aún no hay nómina liquidada.</td></tr><?php endif; ?>
        </table>
    </div>
</div>

<div class="pe-panel" id="pe-documentos">
    <div class="panel">
        <h3>Mis documentos (contratos, otrosí, cotizaciones...) (<?= count($misDocumentosRrhh) ?>)</h3>
        <table>
            <tr><th>Tipo</th><th>Archivo</th><th>Firma</th><th></th></tr>
            <?php foreach ($misDocumentosRrhh as $d): ?>
            <tr>
                <td><?= e($d['tipo']) ?></td>
                <td><?= e($d['nombre_archivo']) ?></td>
                <td><?= $d['estado_firma'] === 'FIRMADO' ? '<span class="badge badge-activo">FIRMADO</span>' : '<span class="badge badge-otro">Pendiente</span>' ?></td>
                <td><a href="rrhh_documentos.php">Ver / firmar</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$misDocumentosRrhh): ?><tr><td colspan="4" class="small">Aún no tienes documentos cargados por RRHH.</td></tr><?php endif; ?>
        </table>
        <p class="small" style="margin-top:10px;"><a href="mis_documentos.php">Ver mi espacio completo de Documentos y firmas →</a></p>
    </div>
</div>

<div class="pe-panel" id="pe-capacitacion">
    <div class="panel">
        <h3><?= icon('graduation') ?> Mis capacitaciones (<?= count($misCursos) ?>)</h3>
        <p class="small">Cursos disponibles para tu área. Tu progreso se guarda automáticamente al completar cada lección.</p>
        <table>
            <tr><th>Curso</th><th>Área</th><th>Progreso</th><th></th></tr>
            <?php foreach ($misCursos as $c):
                $pct = $c['total_lecciones'] > 0 ? round($c['completadas'] / $c['total_lecciones'] * 100) : 0;
            ?>
            <tr>
                <td><?= e($c['titulo']) ?></td>
                <td><?= e($c['area']) ?: 'General' ?></td>
                <td>
                    <div style="background:var(--line);border-radius:20px;height:8px;width:120px;overflow:hidden;display:inline-block;vertical-align:middle;">
                        <div style="background:var(--teal-500);height:100%;width:<?= $pct ?>%;"></div>
                    </div>
                    <span class="small"><?= $c['completadas'] ?>/<?= $c['total_lecciones'] ?> (<?= $pct ?>%)</span>
                </td>
                <td><a class="btn btn-secondary" href="documentacion.php?curso=<?= (int)$c['id'] ?>">Continuar</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$misCursos): ?><tr><td colspan="4" class="small">Aún no hay cursos publicados para tu área.</td></tr><?php endif; ?>
        </table>
        <p class="small" style="margin-top:10px;"><a href="documentacion.php">Ver toda la Base de Conocimiento / capacitación →</a></p>
    </div>
</div>

<div class="pe-panel" id="pe-inventario">
    <div class="panel">
        <h3><?= icon('inventory') ?> Equipos asignados a mi nombre (<?= count($misEquipos) ?>)</h3>
        <table>
            <tr><th>Tipo</th><th>Marca / Modelo</th><th>Serial</th><th>Placa</th><th>Sistema operativo</th><th>Estado</th></tr>
            <?php foreach ($misEquipos as $eq): ?>
            <tr>
                <td><?= e($eq['tipo']) ?></td>
                <td><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?></td>
                <td><?= e($eq['serial']) ?></td>
                <td><?= e($eq['placa']) ?: '—' ?></td>
                <td><?= e($eq['sistema_operativo']) ?: '—' ?></td>
                <td><span class="badge <?= $eq['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($eq['estado']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$misEquipos): ?><tr><td colspan="6" class="small">No hay equipos asignados a tu nombre en el inventario.</td></tr><?php endif; ?>
        </table>
    </div>
</div>

<script>
(function () {
    var botones = document.querySelectorAll('.pe-tab-btn');
    var paneles = document.querySelectorAll('.pe-panel');
    function activar(id) {
        botones.forEach(function (b) { b.classList.toggle('activo', b.dataset.target === id); });
        paneles.forEach(function (p) { p.classList.toggle('activo', p.id === id); });
        try { sessionStorage.setItem('pe-tab-activa', id); } catch (e) {}
    }
    botones.forEach(function (b) { b.addEventListener('click', function () { activar(b.dataset.target); }); });
    var guardada = null;
    try { guardada = sessionStorage.getItem('pe-tab-activa'); } catch (e) {}
    var valida = guardada && document.getElementById(guardada);
    activar(valida ? guardada : 'pe-mesa');
})();
</script>
<?php layout_fin(); ?>
