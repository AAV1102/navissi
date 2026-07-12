<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/ia_triage.php';
$pdo = db();
$msg = null;

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
        $stmt = $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, sede_id, solicitante, solicitante_contacto, sla_limite, asignado_a)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $titulo, limpio($_POST['descripcion'] ?? null), limpio($_POST['categoria'] ?? null) ?: 'SOPORTE',
            $prioridad, $sedeId,
            limpio($_POST['solicitante'] ?? null), limpio($_POST['solicitante_contacto'] ?? null), $slaLimite,
            limpio($_POST['asignado_a'] ?? null),
        ]);
        $nuevoId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
            ->execute([$nuevoId, $_POST['solicitante'] ?? 'Sistema', 'Ticket creado.', 'SISTEMA']);
        hoja_vida_registrar($pdo, 'TICKET', (string) $nuevoId, 'CREADO', $titulo, $_POST['solicitante'] ?? 'Sistema', $nuevoId);

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
        FROM tickets t LEFT JOIN sedes s ON t.sede_id = s.id WHERE 1=1";
$params = [];
if ($estadoFiltro !== '') { $sql .= " AND t.estado = :estado"; $params['estado'] = $estadoFiltro; }
if ($prioridadFiltro !== '') { $sql .= " AND t.prioridad = :prioridad"; $params['prioridad'] = $prioridadFiltro; }
if ($busqueda !== '') { $sql .= " AND (t.titulo LIKE :b OR t.solicitante LIKE :b OR s.nombre LIKE :b)"; $params['b'] = "%{$busqueda}%"; }
$sql .= " ORDER BY CASE t.estado WHEN 'ABIERTO' THEN 0 WHEN 'EN PROCESO' THEN 1 WHEN 'RESUELTO POR IA' THEN 2 WHEN 'CERRADO' THEN 3 ELSE 4 END,
        CASE t.prioridad WHEN 'URGENTE' THEN 0 WHEN 'ALTA' THEN 1 WHEN 'MEDIA' THEN 2 ELSE 3 END, t.creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totales = $pdo->query("SELECT estado, COUNT(*) c FROM tickets GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$resueltosIa = $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado = 'RESUELTO POR IA'")->fetchColumn();
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
        <h1><?= icon('ticket', 'icon-lg') ?> Tickets</h1>
        <p class="subtitle">Tickets de tiendas y oficina hacia TI - correo, WhatsApp, portal y creación manual, todo en un solo lugar.</p>
    </div>
</div>

<div class="cards">
    <div class="card card-err"><div class="num"><?= (int)($totales['ABIERTO'] ?? 0) ?></div><div class="label"><?= icon('bell') ?> Abiertos</div></div>
    <div class="card card-warn"><div class="num"><?= (int)($totales['EN PROCESO'] ?? 0) ?></div><div class="label"><?= icon('zap') ?> En proceso</div></div>
    <div class="card card-ok"><div class="num"><?= (int)$resueltosIa ?></div><div class="label"><?= icon('robot') ?> Resueltos por IA</div></div>
    <div class="card"><div class="num"><?= (int)($totales['CERRADO'] ?? 0) ?></div><div class="label"><?= icon('check') ?> Cerrados</div></div>
</div>

<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= icon('x') ?> <?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nuevo ticket</h3>
    <form method="post">
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
                <input type="text" name="titulo" required placeholder="Agrega un breve resumen del ticket">
                <label>Descripción</label>
                <textarea name="descripcion" rows="6" style="width:100%;" placeholder="Introduce los detalles del ticket aquí"></textarea>
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

<div class="tabla-toolbar">
    <label class="small chk-todos"><input type="checkbox" id="chk-todos-tickets"> Seleccionar todo</label>
    <span class="tabla-toolbar-acciones small">
        <button type="button" class="link-btn" disabled><?= icon('trash') ?> Eliminar</button>
        <button type="button" class="link-btn" disabled><?= icon('users') ?> Asignar ticket</button>
        <button type="button" class="link-btn" disabled><?= icon('check') ?> Establecer estado</button>
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
            <th>Estado de actividad</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tickets as $t):
        $slaVencido = $t['sla_limite'] && !in_array($t['estado'], ['CERRADO', 'RESUELTO POR IA'], true) && strtotime($t['sla_limite']) < time();
        $iniciales = $t['asignado_a'] ? strtoupper(mb_substr($t['asignado_a'], 0, 1)) : '?';
    ?>
        <tr onclick="window.location='ticket_detalle.php?id=<?= (int)$t['id'] ?>'" style="cursor:pointer;">
            <td onclick="event.stopPropagation()"><input type="checkbox" class="chk-ticket"></td>
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
            <td><span class="link-estado"><?= e($t['estado']) ?> <?= icon('chevron-down') ?></span></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$tickets): ?><tr><td colspan="7" style="padding:30px;text-align:center;" class="small">No hay tickets con ese filtro.</td></tr><?php endif; ?>
    </tbody>
</table>
<script>
document.getElementById('chk-todos-tickets')?.addEventListener('change', function () {
    document.querySelectorAll('.chk-ticket').forEach(c => c.checked = this.checked);
});
</script>
<?php layout_fin(); ?>
