<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_login('../');
$pdo = db();
$msg = null;
$estadosValidos = ['PLANEADA', 'ACTIVA', 'PAUSADA', 'FINALIZADA'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if ($nombre) {
            $u = usuario_actual();
            $pdo->prepare("INSERT INTO campanas_marketing (nombre, canal, objetivo, presupuesto, fecha_inicio, fecha_fin, responsable_usuario_id) VALUES (?,?,?,?,?,?,?)")
                ->execute([$nombre, limpio($_POST['canal'] ?? null), limpio($_POST['objetivo'] ?? null),
                    trim((string) ($_POST['presupuesto'] ?? '')) === '' ? null : (float) $_POST['presupuesto'],
                    limpio($_POST['fecha_inicio'] ?? null), limpio($_POST['fecha_fin'] ?? null), $u['id'] ?? null]);
            $msg = ['ok', 'Campaña creada.'];
        }
    }

    if ($accion === 'actualizar_resultados') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE campanas_marketing SET alcance = ?, conversiones = ?, inversion_real = ?, estado = ? WHERE id = ?")
            ->execute([
                trim((string) ($_POST['alcance'] ?? '')) === '' ? null : (int) $_POST['alcance'],
                trim((string) ($_POST['conversiones'] ?? '')) === '' ? null : (int) $_POST['conversiones'],
                trim((string) ($_POST['inversion_real'] ?? '')) === '' ? null : (float) $_POST['inversion_real'],
                strtoupper((string) ($_POST['estado'] ?? 'PLANEADA')),
                $id,
            ]);
        $msg = ['ok', 'Resultados actualizados.'];
    }
}

$campanas = $pdo->query("SELECT c.*, u.nombre AS responsable_nombre FROM campanas_marketing c LEFT JOIN usuarios_sistema u ON u.id = c.responsable_usuario_id ORDER BY c.creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalPresupuesto = array_sum(array_column($campanas, 'presupuesto'));
$totalInvertido = array_sum(array_column($campanas, 'inversion_real'));

layout_inicio('Marketing', 'Marketing', '../');
?>
<h1><?= icon('megaphone', 'icon-lg') ?> Marketing</h1>
<p class="subtitle">Campañas de marketing con presupuesto, resultados reales y estado — separado del calendario de colecciones de producto.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= count($campanas) ?></div><div class="label">Campañas totales</div></div>
    <div class="card"><div class="num">$<?= number_format($totalPresupuesto, 0, ',', '.') ?></div><div class="label">Presupuesto asignado</div></div>
    <div class="card"><div class="num">$<?= number_format($totalInvertido, 0, ',', '.') ?></div><div class="label">Invertido real</div></div>
</div>

<div class="panel">
    <h3>Nueva campaña</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="crear">
        <div><label>Nombre *</label><input type="text" name="nombre" required></div>
        <div><label>Canal</label><select name="canal"><option>Redes sociales</option><option>Google Ads</option><option>Email</option><option>WhatsApp</option><option>Influencers</option><option>Punto de venta</option><option>Otro</option></select></div>
        <div><label>Objetivo</label><input type="text" name="objetivo" placeholder="Tráfico, ventas, reconocimiento..."></div>
        <div><label>Presupuesto</label><input type="number" step="0.01" name="presupuesto"></div>
        <div><label>Fecha inicio</label><input type="date" name="fecha_inicio"></div>
        <div><label>Fecha fin</label><input type="date" name="fecha_fin"></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Crear campaña</button></div>
    </form>
</div>

<div class="panel">
    <h3>Campañas (<?= count($campanas) ?>)</h3>
    <table>
        <tr><th>Nombre</th><th>Canal</th><th>Responsable</th><th>Presupuesto</th><th>Invertido</th><th>Alcance</th><th>Conversiones</th><th>Estado</th><th></th></tr>
        <?php foreach ($campanas as $c): ?>
        <tr>
            <td><strong><?= e($c['nombre']) ?></strong><br><span class="small"><?= e($c['fecha_inicio'] ?: '') ?><?= $c['fecha_fin'] ? ' → ' . e($c['fecha_fin']) : '' ?></span></td>
            <td><?= e($c['canal'] ?: '—') ?></td>
            <td><?= e($c['responsable_nombre'] ?: '—') ?></td>
            <td><?= $c['presupuesto'] !== null ? '$' . number_format((float) $c['presupuesto'], 0, ',', '.') : '—' ?></td>
            <td><?= $c['inversion_real'] !== null ? '$' . number_format((float) $c['inversion_real'], 0, ',', '.') : '—' ?></td>
            <td><?= $c['alcance'] !== null ? number_format((int) $c['alcance'], 0, ',', '.') : '—' ?></td>
            <td><?= $c['conversiones'] !== null ? (int) $c['conversiones'] : '—' ?></td>
            <td><span class="badge <?= $c['estado'] === 'ACTIVA' ? 'badge-activo' : ($c['estado'] === 'FINALIZADA' ? 'badge-otro' : 'badge-warn') ?>"><?= e($c['estado']) ?></span></td>
            <td>
                <form method="post" class="inline" style="display:flex;gap:4px;flex-wrap:wrap;">
                    <input type="hidden" name="accion" value="actualizar_resultados"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <input type="number" name="alcance" placeholder="Alcance" style="width:80px;" value="<?= e($c['alcance']) ?>">
                    <input type="number" name="conversiones" placeholder="Conv." style="width:60px;" value="<?= e($c['conversiones']) ?>">
                    <input type="number" step="0.01" name="inversion_real" placeholder="Invertido" style="width:90px;" value="<?= e($c['inversion_real']) ?>">
                    <select name="estado" style="width:100px;"><?php foreach ($estadosValidos as $e): ?><option <?= $c['estado'] === $e ? 'selected' : '' ?>><?= $e ?></option><?php endforeach; ?></select>
                    <button type="submit" style="padding:2px 6px;font-size:11px;">Guardar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$campanas): ?><tr><td colspan="9" class="small">Sin campañas todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
