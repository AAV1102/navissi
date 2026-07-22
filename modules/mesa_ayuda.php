<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/ia_triage.php';
require_once __DIR__ . '/../lib/correo_a_tickets.php';
$pdo = db();
$msg = null;
$dirAdjuntos = tickets_adjuntos_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['bulk_eliminar', 'bulk_asignar', 'bulk_estado'], true)) {
    $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
    if (!$ids) {
        $msg = ['error', 'No seleccionaste ningún ticket.'];
    } else {
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        if ($_POST['accion'] === 'bulk_eliminar') {
            $pdo->prepare("DELETE FROM tickets WHERE id IN ($marcadores)")->execute($ids);
            $msg = ['ok', count($ids) . ' ticket(s) eliminado(s).'];
        } elseif ($_POST['accion'] === 'bulk_asignar') {
            $tecnico = limpio($_POST['tecnico'] ?? null);
            if (!$tecnico) {
                $msg = ['error', 'Escribe el nombre del técnico.'];
            } else {
                $pdo->prepare("UPDATE tickets SET asignado_a = ? WHERE id IN ($marcadores)")->execute(array_merge([$tecnico], $ids));
                $msg = ['ok', count($ids) . " ticket(s) asignado(s) a {$tecnico}."];
            }
        } elseif ($_POST['accion'] === 'bulk_estado') {
            $nuevoEstado = in_array($_POST['nuevo_estado'] ?? '', ['ABIERTO', 'EN PROCESO', 'RESUELTO POR IA', 'CERRADO'], true) ? $_POST['nuevo_estado'] : null;
            if (!$nuevoEstado) {
                $msg = ['error', 'Elige un estado válido.'];
            } else {
                $pdo->prepare("UPDATE tickets SET estado = ? WHERE id IN ($marcadores)")->execute(array_merge([$nuevoEstado], $ids));
                $msg = ['ok', count($ids) . " ticket(s) actualizado(s) a {$nuevoEstado}."];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'revisar_correo') {
    // Refresco manual, obligatorio y visible: el usuario dispara la revision
    // de buzones el mismo desde Mesa de Ayuda (no solo desde un cron externo
    // ni desde una pagina de administracion aparte) y ve de inmediato cuantos
    // tickets nuevos llegaron.
    $resultado = sincronizar_correo_a_tickets($pdo);
    if ($resultado['errores']) {
        $msg = ['error', 'Revisado con errores: ' . implode(' | ', $resultado['errores'])];
    } elseif ($resultado['creados'] > 0) {
        $msg = ['ok', "Llegaron {$resultado['creados']} ticket(s) nuevo(s) desde el correo."];
    } else {
        $msg = ['ok', 'Revisado — no hay correos nuevos desde la última vez.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    $titulo = limpio($_POST['titulo'] ?? null);
    if (!$titulo) {
        $msg = ['error', 'El título es obligatorio.'];
    } else {
        $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null);
        $prioridad = limpio($_POST['prioridad'] ?? null) ?: 'MEDIA';
        $stmtSla = $pdo->prepare("SELECT tiempo_resolucion_horas FROM sla_politicas WHERE prioridad = ? AND activo = 1 ORDER BY id LIMIT 1");
        $stmtSla->execute([$prioridad]);
        $horasSla = (float) ($stmtSla->fetchColumn() ?: 24);
        // gmdate (no date): CURRENT_TIMESTAMP de SQLite siempre es UTC, sin importar la
        // zona horaria de PHP (America/Bogota) - si se mezclan quedan comparaciones de
        // SLA desfasadas 5 horas.
        $slaLimite = gmdate('Y-m-d H:i:s', strtotime("+{$horasSla} hours"));
        $solicitanteNombre = limpio($_POST['solicitante'] ?? null);
        $solicitanteContacto = limpio($_POST['solicitante_contacto'] ?? null);
        $areaSolicitante = area_por_solicitante($pdo, $solicitanteNombre, $solicitanteContacto);
        $stmt = $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, sede_id, solicitante, solicitante_contacto, sla_limite, asignado_a, solicitante_area)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $titulo, limpio_html($_POST['descripcion'] ?? null), limpio($_POST['categoria'] ?? null) ?: 'SOPORTE',
            $prioridad, $sedeId,
            $solicitanteNombre, $solicitanteContacto, $slaLimite,
            limpio($_POST['asignado_a'] ?? null), $areaSolicitante,
        ]);
        $nuevoId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
            ->execute([$nuevoId, $_POST['solicitante'] ?? 'Sistema', 'Ticket creado.', 'SISTEMA']);
        hoja_vida_registrar($pdo, 'TICKET', (string) $nuevoId, 'CREADO', $titulo, $_POST['solicitante'] ?? 'Sistema', $nuevoId);

        // Adjuntos desde la creación (fotos, video corto, PDF, evidencia) - mismas
        // reglas de tamaño/tipo que los adjuntos de comentarios en ticket_detalle.php.
        if (!empty($_FILES['adjuntos']['tmp_name'][0])) {
            $permitidos = [
                'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                'video/mp4' => 'mp4', 'text/plain' => 'txt', 'text/csv' => 'csv',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            ];
            foreach ($_FILES['adjuntos']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $original = basename($_FILES['adjuntos']['name'][$i]);
                $tamano = (int) ($_FILES['adjuntos']['size'][$i] ?? 0);
                if ($tamano <= 0 || $tamano > 25 * 1024 * 1024) continue; // 25MB (video ocupa mas que un PDF)
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
                if (!isset($permitidos[$mime])) continue;
                $rutaGuardada = bin2hex(random_bytes(18)) . '.' . $permitidos[$mime];
                if (move_uploaded_file($tmp, $dirAdjuntos . '/' . $rutaGuardada)) {
                    $pdo->prepare("INSERT INTO tickets_adjuntos (ticket_id, nombre_archivo, ruta, tipo_mime, tamano, subido_por) VALUES (?,?,?,?,?,?)")
                        ->execute([$nuevoId, $original, $rutaGuardada, $mime, $tamano, $solicitanteNombre ?: 'Sistema']);
                }
            }
        }

        $notificacion = plantilla_renderizar($pdo, 'TICKET_CREADO', ['id' => $nuevoId, 'titulo' => $titulo, 'solicitante' => $_POST['solicitante'] ?? 'el solicitante']);
        if ($notificacion) {
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
                ->execute([$nuevoId, 'Notificación automática', "Asunto: {$notificacion['asunto']}\n\n{$notificacion['cuerpo']}", 'SISTEMA']);
        }

        ia_triage_ticket($pdo, $nuevoId);
        header("Location: ticket_detalle.php?id={$nuevoId}");
        exit;
    }
}

$estadoFiltro = trim($_GET['estado'] ?? '');
$prioridadFiltro = trim($_GET['prioridad'] ?? '');
$busqueda = trim($_GET['q'] ?? '');

$sql = "SELECT t.*, s.nombre AS sede_nombre,
        (SELECT COUNT(*) FROM tickets_comentarios c WHERE c.ticket_id = t.id) AS n_comentarios
        FROM tickets t LEFT JOIN sedes s ON t.sede_id = s.id WHERE COALESCE(t.archivado,0)=0";
$params = [];
if ($estadoFiltro !== '') { $sql .= " AND t.estado = :estado"; $params['estado'] = $estadoFiltro; }
if ($prioridadFiltro !== '') { $sql .= " AND t.prioridad = :prioridad"; $params['prioridad'] = $prioridadFiltro; }
if ($busqueda !== '') { $sql .= " AND (t.titulo LIKE :b OR t.solicitante LIKE :b OR s.nombre LIKE :b)"; $params['b'] = "%{$busqueda}%"; }
// Alcance personal: un EMPLEADO sin rol elevado con este módulo habilitado
// individualmente solo ve los tickets que él mismo creó, no los de toda la empresa.
$personalTk = alcance_personal();
if ($personalTk !== null) {
    $sql .= " AND (t.solicitante = :nombre_personal OR t.solicitante_contacto = :correo_personal)";
    $params['nombre_personal'] = $personalTk['nombre'];
    $params['correo_personal'] = $personalTk['email'];
} elseif (alcance_area() !== null) {
    // Un Director (u otro rol con área asignada) solo ve los tickets de gente de su propia área.
    $sql .= " AND t.solicitante_area = :area_director";
    $params['area_director'] = alcance_area();
}
$sql .= " ORDER BY CASE t.estado WHEN 'ABIERTO' THEN 0 WHEN 'EN PROCESO' THEN 1 WHEN 'RESUELTO POR IA' THEN 2 WHEN 'CERRADO' THEN 3 ELSE 4 END,
        CASE t.prioridad WHEN 'URGENTE' THEN 0 WHEN 'ALTA' THEN 1 WHEN 'MEDIA' THEN 2 ELSE 3 END, t.creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($personalTk !== null) {
    $stmtTot = $pdo->prepare("SELECT estado, COUNT(*) c FROM tickets WHERE solicitante = ? OR solicitante_contacto = ? GROUP BY estado");
    $stmtTot->execute([$personalTk['nombre'], $personalTk['email']]);
    $totales = $stmtTot->fetchAll(PDO::FETCH_KEY_PAIR);
    $stmtIa = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE estado = 'RESUELTO POR IA' AND (solicitante = ? OR solicitante_contacto = ?)");
    $stmtIa->execute([$personalTk['nombre'], $personalTk['email']]);
    $resueltosIa = $stmtIa->fetchColumn();
} elseif (alcance_area() !== null) {
    $stmtTot = $pdo->prepare("SELECT estado, COUNT(*) c FROM tickets WHERE solicitante_area = ? GROUP BY estado");
    $stmtTot->execute([alcance_area()]);
    $totales = $stmtTot->fetchAll(PDO::FETCH_KEY_PAIR);
    $stmtIa = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE estado = 'RESUELTO POR IA' AND solicitante_area = ?");
    $stmtIa->execute([alcance_area()]);
    $resueltosIa = $stmtIa->fetchColumn();
} else {
    $totales = $pdo->query("SELECT estado, COUNT(*) c FROM tickets GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
    $resueltosIa = $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado = 'RESUELTO POR IA'")->fetchColumn();
}
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

function icono_canal($origen) {
    return match ($origen) {
        'WHATSAPP' => icon('chat'), 'CORREO' => icon('file'), 'PORTAL_EMPLEADO' => icon('users'),
        default => icon('ticket'),
    };
}
function badge_prioridad($p) {
    $cls = match ($p) { 'URGENTE' => 'badge-err', 'ALTA' => 'badge-warn', default => 'badge-otro' };
    return "<span class=\"badge {$cls}\">" . e($p) . "</span>";
}
function badge_estado($e) {
    $cls = match ($e) { 'CERRADO' => 'badge-otro', 'RESUELTO POR IA' => 'badge-activo', 'EN PROCESO' => 'badge-warn', default => 'badge-err' };
    return "<span class=\"badge {$cls}\">" . e($e) . "</span>";
}

$categoriasDisponibles = $pdo->query("SELECT nombre FROM categorias_tickets WHERE activa = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
if (!$categoriasDisponibles) $categoriasDisponibles = ['SOPORTE'];
$tecnicos = $pdo->query("SELECT nombre FROM usuarios_sistema WHERE rol IN ('SUPER_ADMIN','ADMIN','TI') AND activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

layout_inicio('Mesa de Ayuda', 'Mesa de Ayuda', '../');
?>
<div style="display:flex; justify-content:space-between; align-items:start; gap:14px; flex-wrap:wrap;">
    <div>
        <h1><?= icon('ticket', 'icon-lg') ?> <?= editable('mesa_ayuda.titulo', 'Tickets') ?></h1>
        <p class="subtitle"><?= editable('mesa_ayuda.subtitulo', 'Tickets de tiendas y oficina hacia TI - correo, WhatsApp, portal y creación manual, todo en un solo lugar.') ?></p>
    </div>
    <form method="post">
        <input type="hidden" name="accion" value="revisar_correo">
        <button type="submit" class="btn-secondary"><?= icon('zap') ?> Revisar correos ahora</button>
    </form>
</div>

<div class="cards">
    <div class="card card-err"><div class="num"><?= (int)($totales['ABIERTO'] ?? 0) ?></div><div class="label"><?= icon('bell') ?> Abiertos</div></div>
    <div class="card card-warn"><div class="num"><?= (int)($totales['EN PROCESO'] ?? 0) ?></div><div class="label"><?= icon('zap') ?> En proceso</div></div>
    <div class="card card-ok"><div class="num"><?= (int)$resueltosIa ?></div><div class="label"><?= icon('robot') ?> Resueltos por IA</div></div>
    <div class="card"><div class="num"><?= (int)($totales['CERRADO'] ?? 0) ?></div><div class="label"><?= icon('check') ?> Cerrados</div></div>
</div>

<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= icon('x') ?> <?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel" id="nuevo-ticket">
    <h3><?= icon('plus') ?> Nuevo ticket</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="crear">
        <div class="form-2col">
            <div>
                <p class="small" style="text-transform:uppercase;letter-spacing:.02em;font-weight:600;color:var(--ink-500);">Creación del ticket</p>
                <label>Ubicación</label>
                <select name="sede">
                    <option value="">-- ninguna / oficina --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
                <label>Solicitante</label>
                <input type="text" name="solicitante" placeholder="Nombre de quien reporta">
                <label>Contacto (celular/correo)</label>
                <input type="text" name="solicitante_contacto">
                <label>Título del ticket *</label>
                <input type="text" name="titulo" required placeholder="Agrega un breve resumen del ticket" value="<?= e($_GET['titulo'] ?? '') ?>">
                <label>Descripción</label>
                <textarea name="descripcion" class="wysiwyg" rows="6" style="width:100%;" placeholder="Introduce los detalles del ticket aquí"><?= e($_GET['descripcion'] ?? '') ?></textarea>
                <label style="margin-top:10px;">Adjuntar archivos (fotos, video, PDF, evidencia)</label>
                <input type="file" name="adjuntos[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,application/pdf,.docx,.xlsx,.csv,.txt">
            </div>
            <div>
                <p class="small" style="text-transform:uppercase;letter-spacing:.02em;font-weight:600;color:var(--ink-500);">Asignación y tipo</p>
                <label>Asignar técnico</label>
                <select name="asignado_a">
                    <option value="">Sin asignar</option>
                    <?php foreach ($tecnicos as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
                </select>
                <label>Categoría</label>
                <select name="categoria">
                    <?php foreach ($categoriasDisponibles as $c): ?>
                    <option><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="small" style="text-transform:uppercase;letter-spacing:.02em;font-weight:600;color:var(--ink-500);margin-top:16px;">Propiedades del ticket</p>
                <label>Prioridad</label>
                <select name="prioridad">
                    <?php foreach (['BAJA','MEDIA','ALTA','URGENTE'] as $p): ?>
                    <option <?= $p==='MEDIA'?'selected':'' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" style="margin-top:14px;"><?= icon('plus') ?> Crear ticket</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="estado">
        <option value="">Todos los estados</option>
        <?php foreach (['ABIERTO','EN PROCESO','RESUELTO POR IA','CERRADO'] as $es): ?>
        <option <?= $estadoFiltro===$es?'selected':'' ?>><?= $es ?></option>
        <?php endforeach; ?>
    </select>
    <select name="prioridad">
        <option value="">Toda prioridad</option>
        <?php foreach (['BAJA','MEDIA','ALTA','URGENTE'] as $p): ?>
        <option <?= $prioridadFiltro===$p?'selected':'' ?>><?= $p ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" placeholder="Buscar título, solicitante, sede..." value="<?= e($busqueda) ?>" style="min-width:240px">
    <button type="submit"><?= icon('search') ?> Filtrar</button>
    <?php if ($estadoFiltro || $prioridadFiltro || $busqueda): ?><a class="btn btn-secondary" href="mesa_ayuda.php">Limpiar</a><?php endif; ?>
</form>

<form method="post" id="form-bulk-tickets">
<input type="hidden" name="accion" id="bulk-accion" value="">
<div class="tabla-toolbar">
    <label class="small chk-todos"><input type="checkbox" id="chk-todos-tickets"> Seleccionar todo</label>
    <span class="tabla-toolbar-acciones small">
        <button type="button" class="link-btn" id="btn-bulk-eliminar" disabled><?= icon('trash') ?> Eliminar</button>
        <button type="button" class="link-btn" id="btn-bulk-asignar" disabled><?= icon('users') ?> Asignar ticket</button>
        <button type="button" class="link-btn" id="btn-bulk-estado" disabled><?= icon('check') ?> Establecer estado</button>
    </span>
    <span class="small" style="margin-left:auto;">Mostrando <?= count($tickets) ?> de <?= count($tickets) ?> tickets</span>
</div>

<table class="tabla-tickets">
    <thead>
        <tr>
            <th style="width:30px;"></th>
            <th>Detalles</th>
            <th>SLA</th>
            <th>Técnico asignado</th>
            <th>Prioridad</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tickets as $t):
        $slaVencido = $t['sla_limite'] && !in_array($t['estado'], ['CERRADO', 'RESUELTO POR IA'], true) && strtotime($t['sla_limite']) < time();
        $iniciales = $t['asignado_a'] ? strtoupper(mb_substr($t['asignado_a'], 0, 1)) : '?';
    ?>
        <tr onclick="window.location='ticket_detalle.php?id=<?= (int)$t['id'] ?>'" style="cursor:pointer;">
            <td onclick="event.stopPropagation()"><input type="checkbox" class="chk-ticket" name="ids[]" value="<?= (int) $t['id'] ?>"></td>
            <td>
                <div style="display:flex; gap:10px; align-items:flex-start;">
                    <span class="avatar-sq"><?= e($iniciales) ?></span>
                    <div>
                        <div class="t-title"><?= icono_canal($t['origen'] ?? '') ?> #<?= (int)$t['id'] ?> — <?= e($t['titulo']) ?></div>
                        <div class="small">
                            <?= e($t['solicitante']) ?: 'Sin solicitante' ?> ·
                            <?= icon('building') ?> <?= e($t['sede_nombre']) ?: 'Sin sede' ?> ·
                            Creado <?= e($t['creado_en']) ?> ·
                            <?= (int)$t['n_comentarios'] ?> mensajes
                        </div>
                    </div>
                </div>
            </td>
            <td><?php if ($slaVencido): ?><span class="badge badge-err"><?= icon('bell') ?> Vencido</span><?php elseif (in_array($t['estado'], ['CERRADO','RESUELTO POR IA'], true)): ?><span class="small">No SLA</span><?php else: ?><span class="badge badge-activo">En plazo</span><?php endif; ?></td>
            <td><span class="avatar-mini"><?= e($iniciales) ?></span> <?= e($t['asignado_a']) ?: 'Sin asignar' ?></td>
            <td><?= badge_prioridad($t['prioridad']) ?></td>
            <td><?= badge_estado($t['estado']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$tickets): ?><tr><td colspan="6" style="padding:30px;text-align:center;" class="small">No hay tickets con ese filtro.</td></tr><?php endif; ?>
    </tbody>
</table>
</form>
<script>
(function () {
    var chkTodos = document.getElementById('chk-todos-tickets');
    var chks = document.querySelectorAll('.chk-ticket');
    var btns = [document.getElementById('btn-bulk-eliminar'), document.getElementById('btn-bulk-asignar'), document.getElementById('btn-bulk-estado')];
    var form = document.getElementById('form-bulk-tickets');
    var accionInput = document.getElementById('bulk-accion');

    function actualizarBotones() {
        var haySeleccion = Array.from(chks).some(function (c) { return c.checked; });
        btns.forEach(function (b) { b.disabled = !haySeleccion; });
    }
    chkTodos?.addEventListener('change', function () {
        chks.forEach(function (c) { c.checked = this.checked; }.bind(this));
        actualizarBotones();
    });
    chks.forEach(function (c) { c.addEventListener('change', actualizarBotones); });

    document.getElementById('btn-bulk-eliminar')?.addEventListener('click', function () {
        if (!confirm('¿Eliminar los tickets seleccionados? Esta acción no se puede deshacer.')) return;
        accionInput.value = 'bulk_eliminar';
        form.submit();
    });
    document.getElementById('btn-bulk-asignar')?.addEventListener('click', function () {
        var tecnico = prompt('¿A quién asignar los tickets seleccionados?');
        if (!tecnico) return;
        var campo = document.createElement('input');
        campo.type = 'hidden'; campo.name = 'tecnico'; campo.value = tecnico;
        form.appendChild(campo);
        accionInput.value = 'bulk_asignar';
        form.submit();
    });
    document.getElementById('btn-bulk-estado')?.addEventListener('click', function () {
        var estado = prompt('Nuevo estado (ABIERTO, EN PROCESO, RESUELTO POR IA, CERRADO):');
        if (!estado) return;
        var campo = document.createElement('input');
        campo.type = 'hidden'; campo.name = 'nuevo_estado'; campo.value = estado.toUpperCase();
        form.appendChild(campo);
        accionInput.value = 'bulk_estado';
        form.submit();
    });
})();
</script>
<?php layout_fin(); ?>
