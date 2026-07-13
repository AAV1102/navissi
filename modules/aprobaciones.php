<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/mailer.php';
$pdo = db();
$msg = null;
$u = usuario_actual();

$tipos = ['COMPRA' => 'Compra / Gasto', 'CAMBIO_EQUIPO' => 'Cambio de equipo', 'PERMISO' => 'Permiso / Ausencia', 'ACCESO' => 'Acceso a sistema', 'OTRO' => 'Otro'];

/** Correos de todos los usuarios con rol GERENCIA/CEO, para copiarlos en lo que les corresponde aprobar. */
function correos_gerencia_ceo(PDO $pdo): array {
    return $pdo->query("SELECT email FROM usuarios_sistema WHERE rol IN ('GERENCIA','CEO') AND activo = 1 AND email IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
}

function notificar_solicitud(PDO $pdo, array $destinatarios, string $asunto, string $cuerpo): void {
    foreach (array_unique(array_filter($destinatarios)) as $correo) {
        enviar_correo($correo, $asunto, plantilla_correo_html($asunto, $cuerpo));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $doc = limpio($_POST['solicitante_documento'] ?? null);
        $desc = limpio($_POST['descripcion'] ?? null);
        if (!$desc) {
            $msg = ['error', 'La descripción es obligatoria.'];
        } else {
            $nombre = $u['nombre'];
            $area = limpio($_POST['area_responsable'] ?? null);
            if ($doc) {
                $stmt = $pdo->prepare("SELECT nombres, area FROM empleados WHERE documento = ?");
                $stmt->execute([$doc]);
                $emp = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($emp) { $nombre = $emp['nombres']; $area = $area ?: $emp['area']; }
            }
            // Si quien envía es Director (o rol elevado), su propia solicitud va directo a Gerencia/CEO
            // (punto 3). Si es un empleado normal, primero pasa por el Director de su área (punto 4).
            $rolCreador = rol_efectivo();
            $nivelInicial = in_array($rolCreador, ['DIRECTOR', 'GERENCIA', 'CEO', 'ADMIN', 'SUPER_ADMIN'], true) ? 'GERENCIA' : 'DIRECTOR';

            $pdo->prepare("INSERT INTO solicitudes_aprobacion (tipo, solicitante_documento, solicitante_nombre, area_responsable, descripcion, monto, prioridad, estado, nivel_actual)
                VALUES (?,?,?,?,?,?,?,'PENDIENTE',?)")
                ->execute([limpio($_POST['tipo'] ?? null) ?: 'OTRO', $doc, $nombre, $area,
                    $desc, $_POST['monto'] !== '' ? (float) $_POST['monto'] : null, limpio($_POST['prioridad'] ?? null) ?: 'NORMAL', $nivelInicial]);
            $id = $pdo->lastInsertId();
            hoja_vida_registrar($pdo, 'EMPLEADO', $doc ?: $u['documento'] ?: (string)$u['id'], 'SOLICITUD_CREADA', "Solicitud #{$id}: {$desc}", $u['nombre']);

            // Notificación: al Director de esa área si es nivel DIRECTOR, o a Gerencia/CEO si ya nace en ese nivel.
            // Gerencia y CEO siempre reciben copia (punto 8), aunque no sean quienes deban aprobar todavía.
            $destinatarios = correos_gerencia_ceo($pdo);
            if ($nivelInicial === 'DIRECTOR' && $area) {
                $stmtDir = $pdo->prepare("SELECT email FROM usuarios_sistema WHERE rol = 'DIRECTOR' AND area_responsable = ? AND activo = 1");
                $stmtDir->execute([$area]);
                $destinatarios = array_merge($destinatarios, $stmtDir->fetchAll(PDO::FETCH_COLUMN));
            }
            notificar_solicitud($pdo, $destinatarios, "Nueva solicitud #{$id} para aprobar",
                "<p><strong>{$nombre}</strong> envió una solicitud de tipo <strong>" . e($tipos[$_POST['tipo'] ?? ''] ?? 'Otro') . "</strong>:</p><p>" . e($desc) . "</p>");
            $msg = ['ok', 'Solicitud enviada para aprobación.'];
        }
    } elseif ($accion === 'resolver') {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_aprobacion WHERE id = ?");
        $stmt->execute([$id]);
        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
        $puedeResolver = tiene_rol(['ADMIN', 'COORDINADOR', 'RRHH'])
            || ($sol && rol_efectivo() === 'DIRECTOR' && $sol['nivel_actual'] === 'DIRECTOR' && $sol['area_responsable'] === alcance_area())
            || ($sol && in_array(rol_efectivo(), ['GERENCIA', 'CEO'], true) && $sol['nivel_actual'] === 'GERENCIA');
        if ($sol && $puedeResolver) {
            $estado = $_POST['estado'] === 'APROBADA' ? 'APROBADA' : 'RECHAZADA';
            $pdo->prepare("UPDATE solicitudes_aprobacion SET estado=?, aprobador=?, comentario_aprobador=?, resuelto_en=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$estado, $u['nombre'], limpio($_POST['comentario'] ?? null), $id]);
            hoja_vida_registrar($pdo, 'EMPLEADO', $sol['solicitante_documento'] ?: (string)$id, 'SOLICITUD_' . $estado, "Solicitud #{$id}: {$sol['descripcion']}", $u['nombre']);
            notificar_solicitud($pdo, correos_gerencia_ceo($pdo), "Solicitud #{$id} {$estado}",
                "<p>La solicitud de <strong>" . e($sol['solicitante_nombre']) . "</strong> fue marcada como <strong>{$estado}</strong> por " . e($u['nombre']) . ".</p>");
            $msg = ['ok', "Solicitud #{$id} marcada como {$estado}."];
        }
    } elseif ($accion === 'escalar' && rol_efectivo() === 'DIRECTOR') {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_aprobacion WHERE id = ?");
        $stmt->execute([$id]);
        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sol && $sol['nivel_actual'] === 'DIRECTOR' && $sol['area_responsable'] === alcance_area()) {
            $motivo = limpio($_POST['motivo_escalamiento'] ?? null);
            $pdo->prepare("UPDATE solicitudes_aprobacion SET nivel_actual='GERENCIA', escalado_por=?, escalado_en=CURRENT_TIMESTAMP, escalado_motivo=? WHERE id=?")
                ->execute([$u['nombre'], $motivo, $id]);
            hoja_vida_registrar($pdo, 'EMPLEADO', $sol['solicitante_documento'] ?: (string)$id, 'SOLICITUD_ESCALADA', "Solicitud #{$id} escalada a Gerencia/CEO: {$motivo}", $u['nombre']);
            notificar_solicitud($pdo, correos_gerencia_ceo($pdo), "Solicitud #{$id} escalada para tu aprobación",
                "<p><strong>" . e($u['nombre']) . "</strong> escaló esta solicitud de <strong>" . e($sol['solicitante_nombre']) . "</strong> para que la revises tú:</p><p>" . e($sol['descripcion']) . "</p>" . ($motivo ? "<p><em>Motivo del escalamiento: " . e($motivo) . "</em></p>" : ''));
            $msg = ['ok', "Solicitud #{$id} escalada a Gerencia/CEO."];
        }
    }
}

$filtroEstado = $_GET['estado'] ?? 'PENDIENTE';
$sql = "SELECT * FROM solicitudes_aprobacion WHERE 1=1";
$params = [];
if ($filtroEstado !== 'TODAS') { $sql .= " AND estado = ?"; $params[] = $filtroEstado; }
if (rol_efectivo() === 'DIRECTOR' && alcance_area() !== null) {
    // Un Director solo ve las solicitudes de su propia área (las suyas propias van directo a Gerencia/CEO).
    $sql .= " AND area_responsable = ?";
    $params[] = alcance_area();
}
// Alcance personal: un EMPLEADO sin rol elevado solo ve sus propias solicitudes, no las de aprobar de otros.
$personalApr = alcance_personal();
if ($personalApr !== null) {
    $sql .= " AND solicitante_documento = ?";
    $params[] = $personalApr['documento'];
}
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$empleados = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);
$pendientes = (int) $pdo->query("SELECT COUNT(*) FROM solicitudes_aprobacion WHERE estado='PENDIENTE'")->fetchColumn();

layout_inicio('Solicitudes y Aprobaciones', 'Solicitudes y Aprobaciones', '../');
?>
<h1><?= icon('check','icon-lg') ?> Solicitudes y Aprobaciones</h1>
<p class="subtitle">Flujo genérico de aprobación: compras, cambios de equipo, permisos, accesos, etc. — con trazabilidad en la hoja de vida.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="stat-cards" style="margin-bottom:18px;">
    <div class="stat-card"><div class="stat-num"><?= $pendientes ?></div><div class="stat-label">Pendientes por aprobar</div></div>
</div>

<div class="panel">
    <h3><?= icon('plus') ?> Nueva solicitud</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach ($tipos as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Solicitante</label>
                <input type="text" name="solicitante_documento" list="lista-emp" placeholder="Documento del empleado (opcional)">
                <datalist id="lista-emp">
                    <?php foreach ($empleados as $emp): ?><option value="<?= e($emp['documento']) ?>"><?= e($emp['nombres']) ?></option><?php endforeach; ?>
                </datalist>
            </div>
            <div><label>Área responsable</label><input type="text" name="area_responsable" placeholder="TI, RRHH, Compras..."></div>
            <div><label>Monto (si aplica)</label><input type="number" step="0.01" name="monto"></div>
            <div><label>Prioridad</label>
                <select name="prioridad">
                    <option value="BAJA">Baja</option>
                    <option value="NORMAL" selected>Normal</option>
                    <option value="ALTA">Alta</option>
                    <option value="URGENTE">Urgente</option>
                </select>
            </div>
        </div>
        <label>Descripción *</label>
        <textarea name="descripcion" rows="2" style="width:100%;" required></textarea>
        <button type="submit" style="margin-top:10px;"><?= icon('send') ?> Enviar solicitud</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="estado" onchange="this.form.submit()">
        <option value="PENDIENTE" <?= $filtroEstado==='PENDIENTE'?'selected':'' ?>>Pendientes</option>
        <option value="APROBADA" <?= $filtroEstado==='APROBADA'?'selected':'' ?>>Aprobadas</option>
        <option value="RECHAZADA" <?= $filtroEstado==='RECHAZADA'?'selected':'' ?>>Rechazadas</option>
        <option value="TODAS" <?= $filtroEstado==='TODAS'?'selected':'' ?>>Todas</option>
    </select>
</form>

<table>
    <tr><th>#</th><th>Tipo</th><th>Solicitante</th><th>Descripción</th><th>Prioridad</th><th>Nivel</th><th>Estado</th><th>Fecha</th><th></th></tr>
    <?php foreach ($solicitudes as $s):
        $rolActual = rol_efectivo();
        $puedeResolverFila = tiene_rol(['ADMIN', 'COORDINADOR', 'RRHH'])
            || ($rolActual === 'DIRECTOR' && $s['nivel_actual'] === 'DIRECTOR' && $s['area_responsable'] === alcance_area())
            || (in_array($rolActual, ['GERENCIA', 'CEO'], true) && $s['nivel_actual'] === 'GERENCIA');
        $puedeEscalarFila = $rolActual === 'DIRECTOR' && $s['nivel_actual'] === 'DIRECTOR' && $s['area_responsable'] === alcance_area();
    ?>
    <tr>
        <td>#<?= (int)$s['id'] ?></td>
        <td><?= e($tipos[$s['tipo']] ?? $s['tipo']) ?></td>
        <td><?= e($s['solicitante_nombre']) ?: '—' ?></td>
        <td><?= e($s['descripcion']) ?><?php if ($s['monto']): ?><br><span class="small">$<?= number_format((float)$s['monto'],0,',','.') ?></span><?php endif; ?>
            <?php if ($s['escalado_por']): ?><br><span class="small">↑ Escalada por <?= e($s['escalado_por']) ?><?= $s['escalado_motivo'] ? ': ' . e($s['escalado_motivo']) : '' ?></span><?php endif; ?>
        </td>
        <td><span class="badge <?= $s['prioridad']==='URGENTE'?'badge-err':'badge-otro' ?>"><?= e($s['prioridad']) ?></span></td>
        <td><span class="badge badge-otro"><?= $s['estado'] === 'PENDIENTE' ? e($s['nivel_actual'] ?: 'DIRECTOR') : '—' ?></span></td>
        <td><span class="badge <?= $s['estado']==='APROBADA'?'badge-activo':($s['estado']==='RECHAZADA'?'badge-err':'badge-otro') ?>"><?= e($s['estado']) ?></span></td>
        <td class="small"><?= e($s['creado_en']) ?></td>
        <td>
            <?php if ($s['estado'] === 'PENDIENTE' && $puedeResolverFila): ?>
            <form class="inline" method="post" style="display:flex;gap:4px;flex-wrap:wrap;">
                <input type="hidden" name="accion" value="resolver">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <input type="hidden" name="comentario" value="">
                <button type="submit" name="estado" value="APROBADA" style="padding:4px 10px;font-size:12px;"><?= icon('check') ?> Aprobar</button>
                <button type="submit" name="estado" value="RECHAZADA" class="btn-danger" style="padding:4px 10px;font-size:12px;"><?= icon('x') ?> Rechazar</button>
            </form>
            <?php elseif ($s['estado'] === 'PENDIENTE' && $puedeEscalarFila === false && rol_efectivo() === 'DIRECTOR'): ?>
            <span class="small">no es de tu área</span>
            <?php elseif ($s['estado'] !== 'PENDIENTE'): ?>
            <span class="small">por <?= e($s['aprobador']) ?></span>
            <?php else: ?>
            <span class="small">esperando a <?= e($s['nivel_actual'] ?: 'DIRECTOR') ?></span>
            <?php endif; ?>
            <?php if ($puedeEscalarFila): ?>
            <form class="inline" method="post" onsubmit="var m = prompt('Motivo del escalamiento (opcional):'); if (m === null) return false; this.motivo_escalamiento.value = m;">
                <input type="hidden" name="accion" value="escalar">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <input type="hidden" name="motivo_escalamiento" value="">
                <button type="submit" class="btn-secondary" style="padding:4px 10px;font-size:12px;margin-top:4px;"><?= icon('arrow-right') ?> Escalar a Gerencia/CEO</button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$solicitudes): ?><tr><td colspan="9" class="small">Sin solicitudes en este estado.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
