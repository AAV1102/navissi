<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ia_triage.php';
require_once __DIR__ . '/../lib/icons.php';
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

// Detecta automáticamente el/los equipos asignados a este empleado (por nombre,
// igual que en la ficha de empleado), para que el ticket lleve el contexto técnico
// (serial, marca, modelo, SO) SIN que el empleado tenga que saber ni escribir nada
// técnico - solo describe el problema en sus palabras.
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

        $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, sede_id, solicitante, solicitante_contacto, sla_limite, origen, creado_por_documento, equipo_serial)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$titulo, $descripcion, limpio($_POST['categoria'] ?? null) ?: 'SOPORTE',
                $prioridad, $sedeId, $u['nombre'], $u['email'], $slaLimite, 'PORTAL_EMPLEADO', $u['documento'], $equipoSerial]);
        $nuevoId = $pdo->lastInsertId();
        hoja_vida_registrar($pdo, 'EMPLEADO', (string) $u['documento'], 'TICKET_CREADO', $titulo, $u['nombre'], $nuevoId);
        if ($equipoSerial) hoja_vida_registrar($pdo, 'EQUIPO', $equipoSerial, 'TICKET_REPORTADO', $titulo, $u['nombre'], $nuevoId);
        ia_triage_ticket($pdo, $nuevoId);
        $msg = ['ok', 'Tu solicitud fue enviada con la ficha de tu equipo adjunta automáticamente. Si la IA encuentra una solución, la verás aquí abajo en unos segundos.'];
    }
}

$misTickets = [];
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE creado_por_documento = ? OR solicitante_contacto = ? ORDER BY creado_en DESC LIMIT 20");
$stmt->execute([$u['documento'], $u['email']]);
$misTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$desprendibles = [];
if ($u['documento']) {
    $stmt = $pdo->prepare("SELECT * FROM desprendibles WHERE empleado_documento = ? ORDER BY periodo DESC");
    $stmt->execute([$u['documento']]);
    $desprendibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mi Portal - NAVISSI</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="topbar">
    <div class="topbar-row">
        <div class="brand"><span>📦 NAVISSI <span>Mi Portal</span></span></div>
        <div class="topbar-user">
            <span class="small" style="color:#dbe9f7;">Hola, <?= e($u['nombre']) ?></span>
            <a href="../logout.php" class="btn btn-secondary" style="padding:4px 10px;font-size:12px;">Salir</a>
        </div>
    </div>
</div>
<main>
<h1>Mi Portal de Empleado</h1>
<p class="subtitle">Solicita ayuda a TI, descarga tus certificados y consulta tus desprendibles - todo en un solo lugar.</p>
<p><a class="btn btn-secondary" href="mis_accesos.php"><?= icon('key') ?> Ver mis accesos (Siesa, Office 365, OneDrive)</a></p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if ($empleado): ?>
<div class="panel">
    <h3><?= e($empleado['nombres']) ?></h3>
    <p class="small"><?= e($empleado['cargo']) ?> · <?= e($empleado['area']) ?></p>
    <a class="btn" href="certificado_laboral.php?documento=<?= urlencode($empleado['documento']) ?>" target="_blank">📄 Mi certificado laboral</a>
</div>
<?php else: ?>
<div class="msg-error">Tu usuario no está vinculado a un registro de RRHH (falta el número de documento). Pide a RRHH que lo complete en tu usuario para ver tu certificado y desprendibles.</div>
<?php endif; ?>

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
        <textarea name="descripcion" rows="3" style="width:100%;margin-bottom:10px;" placeholder="Cuéntanos qué pasó, con tus palabras..."></textarea>
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
</main>
</body>
</html>
