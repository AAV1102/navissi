<?php
require_once __DIR__ . '/config.php';
requiere_login();
if (es_solo_empleado()) {
    // Un usuario sin ningún rol elevado (ni principal ni secundario) siempre va a su
    // panel personal, sin importar si llegó aquí por login o escribiendo la URL a mano.
    header('Location: modules/portal_empleado.php');
    exit;
}
require_once __DIR__ . '/lib/layout.php';
$pdo = db();

// Alcance por área: si el usuario logueado tiene un área asignada, el dashboard
// se recorta solo a esa área automáticamente (mismas consultas, distinto WHERE).
$area = alcance_area();
$condEquipo = $area !== null ? " WHERE area = " . $pdo->quote($area) : '';
$condEmpleado = $area !== null ? " WHERE area = " . $pdo->quote($area) : '';

// El panel NO es el mismo para todos: cada familia de roles ve los widgets
// que le sirven a su trabajo, no una lista genérica de conteos.
$rol = rol_efectivo();
$vistaEjecutiva = in_array($rol, ['DIRECTOR', 'GERENCIA', 'CEO', 'COORDINADOR', 'ANALISTA'], true);
$vistaRRHH = $rol === 'RRHH';
$vistaTecnica = !$vistaEjecutiva && !$vistaRRHH; // SUPER_ADMIN, ADMIN, TI y cualquier rol no contemplado arriba

// Autogestión (para TODOS los roles, incluido el dashboard tecnico): mis
// tickets recientes, para que cualquiera pueda seguir su propia solicitud
// sin ir a buscar a Mesa de Ayuda.
$uActual = usuario_actual();
$misTickets = [];
if (!empty($uActual['nombre'])) {
    $stmt = $pdo->prepare("SELECT id, titulo, estado, prioridad, creado_en FROM tickets WHERE solicitante = ? ORDER BY creado_en DESC LIMIT 5");
    $stmt->execute([$uActual['nombre']]);
    $misTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$solicitudesPendientes = (int) $pdo->query("SELECT COUNT(*) FROM solicitudes_aprobacion WHERE estado='PENDIENTE'")->fetchColumn();
$proximosEventos = $pdo->query("SELECT e.*, s.nombre AS sede_nombre FROM calendario_eventos e LEFT JOIN sedes s ON e.sede_id = s.id
    WHERE fecha_inicio >= datetime('now') ORDER BY fecha_inicio ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
$contratosPorVencer = $pdo->query("SELECT proveedor_nombre, tipo, fecha_fin, julianday(fecha_fin) - julianday('now') AS dias
    FROM contratos WHERE estado='VIGENTE' AND fecha_fin IS NOT NULL AND julianday(fecha_fin) - julianday('now') BETWEEN 0 AND 30
    ORDER BY fecha_fin ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($vistaTecnica) {
    $totalEquipos = $pdo->query("SELECT COUNT(*) FROM inventario{$condEquipo}")->fetchColumn();
    $totalSedes = $pdo->query("SELECT COUNT(*) FROM sedes")->fetchColumn();
    $totalCred = $pdo->query("SELECT COUNT(*) FROM credenciales")->fetchColumn();
    $totalLicencias = $pdo->query("SELECT COALESCE(SUM(cantidad),0) FROM licencias")->fetchColumn();
    $pendientesImport = $pdo->query("SELECT COUNT(*) FROM importaciones_log")->fetchColumn();
    $ticketsAbiertos = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado='ABIERTO'")->fetchColumn();
    $slaVencidos = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('CERRADO','RESUELTO POR IA') AND sla_limite IS NOT NULL AND sla_limite < datetime('now')")->fetchColumn();
    $porSistema = $pdo->query("SELECT sistema, COUNT(*) c FROM credenciales GROUP BY sistema ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
    $porSede = $pdo->query("SELECT s.nombre, COUNT(i.id) c FROM sedes s LEFT JOIN inventario i ON i.sede_id = s.id{$condEquipo} GROUP BY s.id ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);

    // Widget "Estado del ticket" estilo Atera: pastillas con conteos reales
    $ticketsPendientes = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado = 'EN PROCESO'")->fetchColumn();
    $ticketsVenceHoy = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('CERRADO','RESUELTO POR IA') AND sla_limite IS NOT NULL AND date(sla_limite) = date('now')")->fetchColumn();
    $ticketsConRetraso = $slaVencidos;
    $ticketsSinAsignar = $pdo->query("SELECT id, titulo, prioridad, sla_limite FROM tickets WHERE (asignado_a IS NULL OR asignado_a = '') AND estado NOT IN ('CERRADO','RESUELTO POR IA') ORDER BY creado_en DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

    // Tendencia real dia contra dia (se guarda un snapshot diario la primera vez que se abre el dashboard cada dia)
    $tendenciaEquipos = tendencia_metrica($pdo, 'dash_equipos' . ($area ?: ''), (float) $totalEquipos);
    $tendenciaTickets = tendencia_metrica($pdo, 'dash_tickets_abiertos', (float) $ticketsAbiertos);
    $tendenciaCred = tendencia_metrica($pdo, 'dash_credenciales', (float) $totalCred);
} elseif ($vistaRRHH) {
    $totalEmpleados = $pdo->query("SELECT COUNT(*) FROM empleados{$condEmpleado}")->fetchColumn();
    $totalActivos = $pdo->query("SELECT COUNT(*) FROM empleados WHERE estado='ACTIVO'" . ($area !== null ? " AND area = " . $pdo->quote($area) : ''))->fetchColumn();
    $vacacionesPendientes = (int) $pdo->query("SELECT COUNT(*) FROM vacaciones_permisos WHERE estado='PENDIENTE'")->fetchColumn();
    $evaluacionesBorrador = (int) $pdo->query("SELECT COUNT(*) FROM evaluaciones_desempeno WHERE estado='BORRADOR'")->fetchColumn();
    $cumpleMes = $pdo->query("SELECT nombres, fecha_ingreso FROM empleados WHERE estado='ACTIVO'" . ($area !== null ? " AND area = " . $pdo->quote($area) : '') . " ORDER BY nombres LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    $porArea = $pdo->query("SELECT area, COUNT(*) c FROM empleados WHERE estado='ACTIVO' GROUP BY area ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
} else { // vista ejecutiva
    $totalEquipos = $pdo->query("SELECT COUNT(*) FROM inventario{$condEquipo}")->fetchColumn();
    $totalEmpleados = $pdo->query("SELECT COUNT(*) FROM empleados{$condEmpleado}")->fetchColumn();
    $ticketsAbiertos = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado='ABIERTO'")->fetchColumn();
    $valorContratosVigentes = (float) $pdo->query("SELECT COALESCE(SUM(valor),0) FROM contratos WHERE estado='VIGENTE'")->fetchColumn();
    $porSede = $pdo->query("SELECT s.nombre, COUNT(i.id) c FROM sedes s LEFT JOIN inventario i ON i.sede_id = s.id{$condEquipo} GROUP BY s.id ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function badge_prioridad_dashboard($p) {
    $cls = match ($p) { 'URGENTE' => 'badge-err', 'ALTA' => 'badge-warn', default => 'badge-otro' };
    return "<span class=\"badge {$cls}\">" . e($p) . "</span>";
}

layout_inicio('Dashboard', 'Dashboard');
?>
<h1>Panel General</h1>
<p class="subtitle">
    <?php if ($vistaRRHH): ?>Resumen de Talento Humano: empleados, vacaciones y evaluaciones.
    <?php elseif ($vistaEjecutiva): ?>Resumen ejecutivo: equipos, personal, contratos y mesa de ayuda.
    <?php else: ?>Resumen técnico en vivo: inventario, credenciales, tickets y contratos.
    <?php endif; ?>
    <?php if ($area !== null): ?> <span class="badge badge-otro"><?= icon('shield') ?> Vista limitada al área: <?= e($area) ?></span><?php endif; ?>
</p>

<?php if ($vistaTecnica): ?>
<div class="cards">
    <a class="card card-link" href="modules/inventario.php"><div class="num"><?= (int)$totalEquipos ?></div><div class="label">Equipos en inventario</div><?= badge_tendencia($tendenciaEquipos) ?></a>
    <a class="card card-link" href="modules/sedes.php"><div class="num"><?= (int)$totalSedes ?></div><div class="label">Sedes registradas</div></a>
    <a class="card card-link" href="modules/credenciales.php"><div class="num"><?= (int)$totalCred ?></div><div class="label">Credenciales (Siesa, Wifi, Correos)</div><?= badge_tendencia($tendenciaCred) ?></a>
    <a class="card card-link" href="modules/licencias.php"><div class="num"><?= (int)$totalLicencias ?></div><div class="label">Licencias activas</div></a>
    <a class="card card-link" style="border-left-color:#c98a1f" href="modules/mesa_ayuda.php"><div class="num"><?= $ticketsAbiertos ?></div><div class="label">Tickets abiertos</div><?= badge_tendencia($tendenciaTickets) ?></a>
    <a class="card card-link" style="border-left-color:#b3392c" href="modules/mesa_ayuda.php"><div class="num"><?= $slaVencidos ?></div><div class="label">Tickets con SLA vencido</div></a>
    <a class="card card-link" style="border-left-color:#c98a1f" href="modules/aprobaciones.php"><div class="num"><?= $solicitudesPendientes ?></div><div class="label">Solicitudes por aprobar</div></a>
    <a class="card card-link" style="border-left-color:#c98a1f" href="modules/contratos.php"><div class="num"><?= count($contratosPorVencer) ?></div><div class="label">Contratos por vencer (30 días)</div></a>
</div>

<div class="panel-grid-2">
    <div class="panel">
        <h3>Estado del ticket</h3>
        <div class="pill-row">
            <span class="pill-stat"><strong><?= (int)$ticketsAbiertos ?></strong> <span class="badge badge-otro">Abrir</span></span>
            <span class="pill-stat"><strong><?= $ticketsPendientes ?></strong> <span class="badge badge-warn">Pendiente</span></span>
            <span class="pill-stat"><strong><?= $ticketsVenceHoy ?></strong> <span class="badge badge-warn">Vence hoy</span></span>
            <span class="pill-stat"><strong><?= $ticketsConRetraso ?></strong> <span class="badge badge-err">Con retraso</span></span>
        </div>
    </div>
    <div class="panel">
        <h3>Tickets sin asignar</h3>
        <?php if ($ticketsSinAsignar): ?>
        <table class="tabla-tickets">
            <thead><tr><th>Detalles</th><th>Prioridad</th><th>SLA</th></tr></thead>
            <tbody>
            <?php foreach ($ticketsSinAsignar as $t): ?>
            <tr onclick="window.location='modules/ticket_detalle.php?id=<?= (int)$t['id'] ?>'" style="cursor:pointer;">
                <td class="t-title">#<?= (int)$t['id'] ?> — <?= e($t['titulo']) ?></td>
                <td><?= badge_prioridad_dashboard($t['prioridad']) ?></td>
                <td class="small"><?= $t['sla_limite'] ? e($t['sla_limite']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p class="small">No hay tickets sin asignar en este momento.</p><?php endif; ?>
    </div>
</div>

<?php if ($contratosPorVencer): ?>
<div class="panel">
    <h3><?= icon('bell') ?> Contratos por vencer</h3>
    <table>
        <tr><th>Proveedor</th><th>Tipo</th><th>Fecha fin</th><th>Días restantes</th></tr>
        <?php foreach ($contratosPorVencer as $c): ?>
        <tr>
            <td><?= e($c['proveedor_nombre']) ?></td>
            <td><?= e($c['tipo']) ?></td>
            <td><?= e($c['fecha_fin']) ?></td>
            <td><span class="badge badge-warn"><?= (int) round($c['dias']) ?> días</span></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Equipos por sede</h3>
    <table>
        <tr><th>Sede</th><th>Equipos</th></tr>
        <?php foreach ($porSede as $r): if ($r['c'] == 0) continue; ?>
        <tr><td><?= e($r['nombre']) ?></td><td><?= (int)$r['c'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="panel">
    <h3>Credenciales por sistema</h3>
    <table>
        <tr><th>Sistema</th><th>Cantidad</th></tr>
        <?php foreach ($porSistema as $r): ?>
        <tr><td><?= e($r['sistema']) ?></td><td><?= (int)$r['c'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<p class="small">Si el dashboard está vacío, ve a <a href="modules/importar.php">Importar</a> y carga los maestros de TI 2026.</p>

<?php elseif ($vistaRRHH): ?>
<div class="cards">
    <div class="card"><div class="num"><?= (int)$totalEmpleados ?></div><div class="label">Empleados registrados</div></div>
    <div class="card"><div class="num"><?= (int)$totalActivos ?></div><div class="label">Empleados activos</div></div>
    <div class="card" style="border-left-color:#c98a1f"><div class="num"><?= $vacacionesPendientes ?></div><div class="label"><a href="modules/vacaciones.php">Vacaciones/permisos pendientes</a></div></div>
    <div class="card" style="border-left-color:#c98a1f"><div class="num"><?= $evaluacionesBorrador ?></div><div class="label"><a href="modules/evaluaciones.php">Evaluaciones en borrador</a></div></div>
</div>

<div class="panel">
    <h3>Empleados activos por área</h3>
    <table>
        <tr><th>Área</th><th>Empleados</th></tr>
        <?php foreach ($porArea as $r): if (!$r['area']) continue; ?>
        <tr><td><?= e($r['area']) ?></td><td><?= (int)$r['c'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="panel">
    <h3>Accesos rápidos</h3>
    <p><a class="btn" href="modules/rrhh.php"><?= icon('users') ?> Empleados</a>
       <a class="btn btn-secondary" href="modules/nomina.php"><?= icon('dollar') ?> Nómina</a>
       <a class="btn btn-secondary" href="modules/vacaciones.php"><?= icon('briefcase') ?> Vacaciones</a>
       <a class="btn btn-secondary" href="modules/evaluaciones.php"><?= icon('graduation') ?> Evaluaciones</a></p>
</div>

<?php else: ?>
<div class="cards">
    <div class="card"><div class="num"><?= (int)$totalEquipos ?></div><div class="label">Equipos en inventario</div></div>
    <div class="card"><div class="num"><?= (int)$totalEmpleados ?></div><div class="label">Empleados</div></div>
    <div class="card" style="border-left-color:#c98a1f"><div class="num"><?= $ticketsAbiertos ?></div><div class="label"><a href="modules/mesa_ayuda.php">Tickets abiertos</a></div></div>
    <div class="card"><div class="num">$<?= number_format($valorContratosVigentes, 0, ',', '.') ?></div><div class="label">Valor en contratos vigentes</div></div>
    <div class="card" style="border-left-color:#c98a1f"><div class="num"><?= $solicitudesPendientes ?></div><div class="label"><a href="modules/aprobaciones.php">Solicitudes por aprobar</a></div></div>
    <div class="card" style="border-left-color:#c98a1f"><div class="num"><?= count($contratosPorVencer) ?></div><div class="label"><a href="modules/contratos.php">Contratos por vencer (30 días)</a></div></div>
</div>

<div class="panel">
    <h3>Equipos por sede</h3>
    <table>
        <tr><th>Sede</th><th>Equipos</th></tr>
        <?php foreach ($porSede as $r): if ($r['c'] == 0) continue; ?>
        <tr><td><?= e($r['nombre']) ?></td><td><?= (int)$r['c'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="panel">
    <h3><?= icon('ticket') ?> Mesa de Ayuda — autogestión</h3>
    <p class="subtitle" style="margin-bottom:14px;">¿Tienes un problema o una solicitud? Créala aquí mismo, sin salir del panel.</p>
    <form method="post" action="modules/mesa_ayuda.php" style="margin-bottom:18px;">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>¿Qué necesitas?</label><input type="text" name="titulo" required placeholder="Ej: Mi equipo no enciende, necesito acceso a..."></div>
            <div><label>Prioridad</label>
                <select name="prioridad">
                    <option value="BAJA">Baja</option>
                    <option value="MEDIA" selected>Media</option>
                    <option value="ALTA">Alta</option>
                    <option value="URGENTE">Urgente</option>
                </select>
            </div>
        </div>
        <input type="hidden" name="solicitante" value="<?= e($uActual['nombre'] ?? '') ?>">
        <input type="hidden" name="solicitante_contacto" value="<?= e($uActual['email'] ?? '') ?>">
        <button type="submit"><?= icon('send') ?> Crear solicitud</button>
    </form>
    <?php if ($misTickets): ?>
    <h3 style="font-size:13px;">Mis solicitudes recientes</h3>
    <table>
        <tr><th>#</th><th>Título</th><th>Prioridad</th><th>Estado</th><th>Fecha</th></tr>
        <?php foreach ($misTickets as $mt): ?>
        <tr>
            <td><a href="modules/ticket_detalle.php?id=<?= (int)$mt['id'] ?>">#<?= (int)$mt['id'] ?></a></td>
            <td><?= e($mt['titulo']) ?></td>
            <td><span class="badge badge-otro"><?= e($mt['prioridad']) ?></span></td>
            <td><span class="badge <?= in_array($mt['estado'], ['CERRADO','RESUELTO POR IA'], true) ? 'badge-otro' : 'badge-activo' ?>"><?= e($mt['estado']) ?></span></td>
            <td class="small"><?= e($mt['creado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="small">Aún no has creado ninguna solicitud.</p>
    <?php endif; ?>
</div>

<?php if ($proximosEventos): ?>
<div class="panel">
    <h3><?= icon('dashboard') ?> Próximos eventos <a href="modules/calendario.php" class="small" style="float:right;font-weight:400;">Ver calendario completo →</a></h3>
    <table>
        <tr><th>Fecha</th><th>Evento</th><th>Responsable</th><th>Sede</th></tr>
        <?php foreach ($proximosEventos as $ev): ?>
        <tr>
            <td class="small"><?= date('d/m/Y H:i', strtotime($ev['fecha_inicio'])) ?></td>
            <td><?= e($ev['titulo']) ?> <span class="badge badge-otro"><?= e($ev['tipo']) ?></span></td>
            <td><?= e($ev['responsable']) ?: '—' ?></td>
            <td><?= e($ev['sede_nombre']) ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
