<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['GERENCIA', 'CEO', 'ADMIN', 'COORDINADOR'])) {
    layout_inicio('Pipeline de Ventas', 'Pipeline de Ventas', '../');
    echo '<div class="msg-error">No tienes permiso para ver el pipeline de ventas.</div>';
    layout_fin();
    exit;
}

$ETAPAS = ['PROSPECTO' => 'Prospecto', 'CONTACTADO' => 'Contactado', 'PROPUESTA' => 'Propuesta',
    'NEGOCIACION' => 'Negociación', 'GANADO' => 'Ganado', 'PERDIDO' => 'Perdido'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $titulo = limpio($_POST['titulo'] ?? null);
        if ($titulo) {
            $orden = (int) $pdo->query("SELECT COALESCE(MAX(orden),0)+1 FROM oportunidades WHERE etapa='PROSPECTO'")->fetchColumn();
            $respDoc = limpio($_POST['responsable_documento'] ?? null);
            $respNombre = null;
            if ($respDoc) {
                $stmtR = $pdo->prepare("SELECT nombres FROM empleados WHERE documento = ?");
                $stmtR->execute([$respDoc]);
                $respNombre = $stmtR->fetchColumn();
            }
            $pdo->prepare("INSERT INTO oportunidades (cliente_id, titulo, valor, etapa, responsable_documento, responsable_nombre, fecha_cierre_esperada, notas, orden) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([(int) ($_POST['cliente_id'] ?? 0) ?: null, $titulo, (float) ($_POST['valor'] ?? 0) ?: null,
                    'PROSPECTO', $respDoc, $respNombre, limpio($_POST['fecha_cierre_esperada'] ?? null),
                    limpio($_POST['notas'] ?? null), $orden]);
            $msg = ['ok', 'Oportunidad creada.'];
        }
    } elseif ($accion === 'mover') {
        $pdo->prepare("UPDATE oportunidades SET etapa = ? WHERE id = ?")
            ->execute([limpio($_POST['etapa'] ?? null), (int) ($_POST['id'] ?? 0)]);
        $msg = ['ok', 'Oportunidad movida.'];
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM oportunidades WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
        $msg = ['ok', 'Oportunidad eliminada.'];
    }
}

$oportunidades = $pdo->query("SELECT o.*, c.nombre AS cliente_nombre FROM oportunidades o LEFT JOIN clientes c ON c.id = o.cliente_id ORDER BY o.orden, o.id")->fetchAll(PDO::FETCH_ASSOC);
$porEtapa = array_fill_keys(array_keys($ETAPAS), []);
foreach ($oportunidades as $o) { $porEtapa[$o['etapa']][] = $o; }

$clientes = $pdo->query("SELECT id, nombre FROM clientes WHERE estado='ACTIVO' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$empleadosPipe = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);

$valorTotal = array_sum(array_column(array_filter($oportunidades, fn($o) => !in_array($o['etapa'], ['GANADO','PERDIDO'], true)), 'valor'));
$valorGanado = array_sum(array_column(array_filter($oportunidades, fn($o) => $o['etapa'] === 'GANADO'), 'valor'));

layout_inicio('Pipeline de Ventas', 'Pipeline de Ventas', '../');
?>
<h1><?= icon('dollar','icon-lg') ?> Pipeline de Oportunidades</h1>
<p class="subtitle">Seguimiento de negocios en curso, vinculados a Clientes y Proveedores.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= count($oportunidades) ?></div><div class="label">Oportunidades</div></div>
    <div class="card"><div class="num">$<?= number_format($valorTotal, 0, ',', '.') ?></div><div class="label">En negociación</div></div>
    <div class="card"><div class="num">$<?= number_format($valorGanado, 0, ',', '.') ?></div><div class="label">Ganado</div></div>
</div>

<div class="panel">
    <h3>Nueva oportunidad</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>Título *</label><input type="text" name="titulo" required placeholder="Ej. Renovación de licencias 2026"></div>
            <div><label>Cliente</label>
                <select name="cliente_id">
                    <option value="">-- ninguno --</option>
                    <?php foreach ($clientes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Valor estimado</label><input type="number" step="0.01" name="valor" placeholder="0"></div>
            <div><label>Responsable</label>
                <input type="text" name="responsable_documento" list="lista-emp-pipe" placeholder="Documento (opcional)">
                <datalist id="lista-emp-pipe"><?php foreach ($empleadosPipe as $e): ?><option value="<?= e($e['documento']) ?>"><?= e($e['nombres']) ?><?php endforeach; ?></datalist>
            </div>
            <div><label>Fecha cierre esperada</label><input type="date" name="fecha_cierre_esperada"></div>
        </div>
        <textarea name="notas" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Notas"></textarea>
        <button type="submit">Crear oportunidad</button>
    </form>
</div>

<div class="kanban-board">
    <?php foreach ($ETAPAS as $clave => $etiqueta): ?>
    <div class="kanban-columna">
        <h3><?= e($etiqueta) ?> <span class="small">(<?= count($porEtapa[$clave]) ?>)</span></h3>
        <?php foreach ($porEtapa[$clave] as $o): ?>
        <div class="kanban-tarjeta">
            <div class="kanban-tarjeta-titulo"><?= e($o['titulo']) ?></div>
            <?php if ($o['cliente_nombre']): ?><p class="small"><?= icon('users') ?> <?= e($o['cliente_nombre']) ?></p><?php endif; ?>
            <div class="kanban-tarjeta-meta">
                <?php if ($o['valor']): ?><span class="badge badge-otro">$<?= number_format($o['valor'], 0, ',', '.') ?></span><?php endif; ?>
                <?php if ($o['responsable_nombre']): ?><span class="small"><?= icon('users') ?> <?= e($o['responsable_nombre']) ?></span><?php endif; ?>
                <?php if ($o['fecha_cierre_esperada']): ?><span class="small"><?= icon('bell') ?> <?= e($o['fecha_cierre_esperada']) ?></span><?php endif; ?>
            </div>
            <form method="post" class="kanban-tarjeta-acciones">
                <input type="hidden" name="accion" value="mover">
                <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <select name="etapa" onchange="this.form.requestSubmit()">
                    <?php foreach ($ETAPAS as $k2 => $l2): ?><option value="<?= $k2 ?>" <?= $k2===$clave?'selected':'' ?>><?= e($l2) ?></option><?php endforeach; ?>
                </select>
            </form>
            <form method="post" onsubmit="return confirm('¿Eliminar esta oportunidad?');" style="display:inline;">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <button type="submit" class="link-btn" style="color:var(--err-fg);font-size:11px;"><?= icon('trash') ?></button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (!$porEtapa[$clave]): ?><p class="small">Sin oportunidades.</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php layout_fin(); ?>
