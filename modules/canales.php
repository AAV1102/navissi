<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/graph_client.php';
$pdo = db();
$msg = null;
$u = usuario_actual();

// Deliberadamente SIN tiene_rol() para la vista - cualquiera ve sus canales/sitios
// asignados. Solo ADMIN/TI puede asignar cuáles corresponden a cada área/persona.
$puedeAsignar = tiene_rol(['ADMIN', 'TI']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeAsignar) {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'asignar') {
        $pdo->prepare("INSERT INTO enlaces_colaboracion (tipo, nombre, url, area, empleado_documento) VALUES (?,?,?,?,?)")
            ->execute([limpio($_POST['tipo'] ?? null) ?: 'SITIO', limpio($_POST['nombre'] ?? null), limpio($_POST['url'] ?? null),
                limpio($_POST['area'] ?? null) ?: null, limpio($_POST['empleado_documento'] ?? null) ?: null]);
        $msg = ['ok', 'Enlace asignado.'];
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM enlaces_colaboracion WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Enlace eliminado.'];
    }
}

// Catálogo real desde Microsoft 365 (para armar el formulario de asignación) - solo se
// consulta si hay quien pueda asignar, para no gastar llamadas a Graph en cada visita.
$sitios = []; $equipos = []; $canalesPorEquipo = [];
if ($puedeAsignar && ms365_configurado()) {
    try {
        $c = ms365_config();
        $gc = new GraphClient($c['tenant_id'], $c['client_id'], $c['client_secret']);
        $sitios = $gc->listarSitiosSharePoint();
        $equipos = $gc->listarTodosLosEquipos();
    } catch (GraphClientException $e) {
        $msg = ['error', 'No se pudo consultar Microsoft 365: ' . $e->getMessage()];
    }
}

// Lo que ve cada quien: si tiene área asignada (o es un Director/etc.), sus enlaces de
// área; si usuario_ve_todo(), todos; si tiene documento, también lo suyo personal.
$sql = "SELECT * FROM enlaces_colaboracion WHERE 1=1";
$params = [];
if (!usuario_ve_todo()) {
    $condiciones = [];
    if (alcance_area() !== null) { $condiciones[] = "area = :area"; $params['area'] = alcance_area(); }
    if (!empty($u['documento'])) { $condiciones[] = "empleado_documento = :doc"; $params['doc'] = $u['documento']; }
    $condiciones[] = "(area IS NULL AND empleado_documento IS NULL)"; // enlaces generales, visibles para todos
    $sql .= " AND (" . implode(' OR ', $condiciones) . ")";
}
$sql .= " ORDER BY tipo, nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$misEnlaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departamentosCanal = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$empleadosCanal = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Canales', 'Canales', '../');
?>
<h1><?= icon('cloud','icon-lg') ?> Canales y Sitios Compartidos</h1>
<p class="subtitle">Accesos directos a tus canales de Microsoft Teams y sitios de SharePoint - la seguridad real de los archivos la sigue controlando Microsoft 365, esto es solo el directorio de acceso rápido según tu área.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if ($puedeAsignar): ?>
<div class="panel">
    <h3><?= icon('plus') ?> Asignar canal / sitio a un área o persona</h3>
    <form method="post">
        <input type="hidden" name="accion" value="asignar">
        <div class="grid-form">
            <div><label>Tipo</label>
                <select name="tipo">
                    <option value="SITIO">Sitio SharePoint</option>
                    <option value="EQUIPO">Equipo (Teams)</option>
                    <option value="CANAL">Canal específico</option>
                </select>
            </div>
            <div><label>Nombre a mostrar *</label><input type="text" name="nombre" required></div>
            <div style="grid-column:span 2;"><label>URL real (copia el enlace del sitio/canal en Microsoft 365) *</label><input type="url" name="url" required placeholder="https://g10z.sharepoint.com/sites/... o https://teams.cloud.microsoft/l/channel/..."></div>
            <div><label>Área (opcional)</label>
                <select name="area">
                    <option value="">-- ninguna / general para todos --</option>
                    <?php foreach ($departamentosCanal as $d): ?><option><?= e($d) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>O una persona específica (opcional)</label>
                <input type="text" name="empleado_documento" list="lista-emp-canal" placeholder="Documento del empleado">
                <datalist id="lista-emp-canal"><?php foreach ($empleadosCanal as $ec): ?><option value="<?= e($ec['documento']) ?>"><?= e($ec['nombres']) ?><?php endforeach; ?></datalist>
            </div>
        </div>
        <button type="submit">Asignar</button>
    </form>

    <?php if ($sitios || $equipos): ?>
    <details style="margin-top:14px;">
        <summary class="small" style="cursor:pointer;">Ver catálogo real de Microsoft 365 para copiar URLs (<?= count($sitios) ?> sitios, <?= count($equipos) ?> equipos)</summary>
        <div style="max-height:260px;overflow-y:auto;margin-top:10px;">
            <p class="small" style="font-weight:600;">Sitios SharePoint</p>
            <?php foreach ($sitios as $s): if (empty($s['displayName']) && empty($s['name'])) continue; ?>
            <p class="small"><?= e($s['displayName'] ?? $s['name']) ?> — <code style="font-size:11px;"><?= e($s['webUrl'] ?? '') ?></code></p>
            <?php endforeach; ?>
            <p class="small" style="font-weight:600;margin-top:10px;">Equipos (Teams)</p>
            <?php foreach ($equipos as $eq): ?>
            <p class="small"><?= e($eq['displayName']) ?> <span style="color:var(--ink-400);">(id: <?= e($eq['id']) ?>)</span></p>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h3><?= icon('folder') ?> Tus accesos (<?= count($misEnlaces) ?>)</h3>
    <div class="apps-grid">
        <?php foreach ($misEnlaces as $en): ?>
        <div class="app-card">
            <a href="<?= e($en['url']) ?>" target="_blank" rel="noopener" style="text-decoration:none;color:inherit;display:block;">
                <div class="app-card-top">
                    <span class="app-icon" style="background:<?= $en['tipo']==='SITIO' ? '#0078d4' : '#5b5fc7' ?>"><?= icon($en['tipo']==='SITIO' ? 'folder' : 'chat') ?></span>
                </div>
                <span class="app-categoria"><?= e($en['tipo']) ?></span>
                <h3><?= e($en['nombre']) ?></h3>
                <p class="small"><?= $en['area'] ? 'Área: ' . e($en['area']) : ($en['empleado_documento'] ? 'Asignación personal' : 'General - todos') ?></p>
            </a>
            <?php if ($puedeAsignar): ?>
            <form method="post" onsubmit="return confirm('¿Quitar este enlace?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$en['id'] ?>">
                <button type="submit" class="btn-danger" style="margin-top:8px;font-size:11px;padding:4px 10px;">Quitar</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$misEnlaces): ?><p class="small">Todavía no tienes canales o sitios asignados. Pide a TI que te asigne los de tu área.</p><?php endif; ?>
    </div>
</div>
<?php layout_fin(); ?>
