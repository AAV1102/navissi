<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;
$u = usuario_actual();

$stmt = $pdo->prepare("SELECT * FROM tableros WHERE id = ?");
$stmt->execute([$id]);
$tablero = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tablero) {
    layout_inicio('Tablero no encontrado', 'Proyectos', '../');
    echo '<div class="msg-error">Ese tablero no existe.</div><a class="btn" href="proyectos.php">Volver</a>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_tarea') {
        $titulo = limpio($_POST['titulo'] ?? null);
        $columnaId = (int) ($_POST['columna_id'] ?? 0);
        if ($titulo && $columnaId) {
            $orden = (int) $pdo->query("SELECT COALESCE(MAX(orden),0)+1 FROM tablero_tareas WHERE columna_id = {$columnaId}")->fetchColumn();
            $respDoc = limpio($_POST['responsable_documento'] ?? null);
            $respNombre = null;
            if ($respDoc) {
                $stmtR = $pdo->prepare("SELECT nombres FROM empleados WHERE documento = ?");
                $stmtR->execute([$respDoc]);
                $respNombre = $stmtR->fetchColumn();
            }
            $pdo->prepare("INSERT INTO tablero_tareas (tablero_id, columna_id, titulo, descripcion, responsable_documento, responsable_nombre, prioridad, fecha_vencimiento, orden) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$id, $columnaId, $titulo, limpio($_POST['descripcion'] ?? null), $respDoc, $respNombre,
                    limpio($_POST['prioridad'] ?? null) ?: 'NORMAL', limpio($_POST['fecha_vencimiento'] ?? null), $orden]);
            $msg = ['ok', 'Tarea creada.'];
        }
    } elseif ($accion === 'mover_tarea') {
        $pdo->prepare("UPDATE tablero_tareas SET columna_id = ? WHERE id = ? AND tablero_id = ?")
            ->execute([(int) $_POST['columna_id'], (int) $_POST['tarea_id'], $id]);
        $msg = ['ok', 'Tarea movida.'];
    } elseif ($accion === 'eliminar_tarea') {
        $pdo->prepare("DELETE FROM tablero_tareas WHERE id = ? AND tablero_id = ?")->execute([(int) $_POST['id'], $id]);
        $msg = ['ok', 'Tarea eliminada.'];
    } elseif ($accion === 'crear_columna') {
        $nombreCol = limpio($_POST['nombre_columna'] ?? null);
        if ($nombreCol) {
            $orden = (int) $pdo->query("SELECT COALESCE(MAX(orden),0)+1 FROM tablero_columnas WHERE tablero_id = {$id}")->fetchColumn();
            $pdo->prepare("INSERT INTO tablero_columnas (tablero_id, nombre, orden) VALUES (?,?,?)")->execute([$id, $nombreCol, $orden]);
            $msg = ['ok', 'Columna agregada.'];
        }
    }
}

$columnas = $pdo->prepare("SELECT * FROM tablero_columnas WHERE tablero_id = ? ORDER BY orden, id");
$columnas->execute([$id]);
$columnas = $columnas->fetchAll(PDO::FETCH_ASSOC);

$tareasPorColumna = [];
foreach ($columnas as $c) {
    $stmtT = $pdo->prepare("SELECT * FROM tablero_tareas WHERE columna_id = ? ORDER BY orden, id");
    $stmtT->execute([$c['id']]);
    $tareasPorColumna[$c['id']] = $stmtT->fetchAll(PDO::FETCH_ASSOC);
}

$empleadosProy = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio($tablero['nombre'], 'Proyectos', '../');
?>
<p class="small"><a href="proyectos.php">← Volver a Proyectos</a></p>
<h1><?= icon('dashboard','icon-lg') ?> <?= e($tablero['nombre']) ?></h1>
<p class="subtitle"><?= e($tablero['area']) ?: 'General' ?> · <?= e($tablero['descripcion']) ?></p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Nueva tarea</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear_tarea">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>Título *</label><input type="text" name="titulo" required></div>
            <div><label>Columna</label>
                <select name="columna_id" required>
                    <?php foreach ($columnas as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Responsable</label>
                <input type="text" name="responsable_documento" list="lista-emp-tarea" placeholder="Documento (opcional)">
                <datalist id="lista-emp-tarea"><?php foreach ($empleadosProy as $e): ?><option value="<?= e($e['documento']) ?>"><?= e($e['nombres']) ?><?php endforeach; ?></datalist>
            </div>
            <div><label>Prioridad</label>
                <select name="prioridad">
                    <?php foreach (['BAJA','NORMAL','ALTA','URGENTE'] as $p): ?><option <?= $p==='NORMAL'?'selected':'' ?>><?= $p ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Fecha límite</label><input type="date" name="fecha_vencimiento"></div>
        </div>
        <textarea name="descripcion" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Detalles de la tarea"></textarea>
        <button type="submit">Crear tarea</button>
    </form>
</div>

<div class="kanban-board">
    <?php foreach ($columnas as $col): ?>
    <div class="kanban-columna">
        <h3><?= e($col['nombre']) ?> <span class="small">(<?= count($tareasPorColumna[$col['id']]) ?>)</span></h3>
        <?php foreach ($tareasPorColumna[$col['id']] as $tarea): ?>
        <div class="kanban-tarjeta">
            <div class="kanban-tarjeta-titulo"><?= e($tarea['titulo']) ?></div>
            <?php if ($tarea['descripcion']): ?><p class="small"><?= nl2br(e($tarea['descripcion'])) ?></p><?php endif; ?>
            <div class="kanban-tarjeta-meta">
                <span class="badge <?= $tarea['prioridad']==='URGENTE'?'badge-err':($tarea['prioridad']==='ALTA'?'badge-warn':'badge-otro') ?>"><?= e($tarea['prioridad']) ?></span>
                <?php if ($tarea['responsable_nombre']): ?><span class="small"><?= icon('users') ?> <?= e($tarea['responsable_nombre']) ?></span><?php endif; ?>
                <?php if ($tarea['fecha_vencimiento']): ?><span class="small"><?= icon('bell') ?> <?= e($tarea['fecha_vencimiento']) ?></span><?php endif; ?>
            </div>
            <form method="post" class="kanban-tarjeta-acciones">
                <input type="hidden" name="accion" value="mover_tarea">
                <input type="hidden" name="tarea_id" value="<?= (int)$tarea['id'] ?>">
                <select name="columna_id" onchange="this.form.requestSubmit()">
                    <?php foreach ($columnas as $c2): ?><option value="<?= (int)$c2['id'] ?>" <?= $c2['id']==$col['id']?'selected':'' ?>><?= e($c2['nombre']) ?></option><?php endforeach; ?>
                </select>
            </form>
            <form method="post" onsubmit="return confirm('¿Eliminar esta tarea?');" style="display:inline;">
                <input type="hidden" name="accion" value="eliminar_tarea"><input type="hidden" name="id" value="<?= (int)$tarea['id'] ?>">
                <button type="submit" class="link-btn" style="color:var(--err-fg);font-size:11px;"><?= icon('trash') ?></button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (!$tareasPorColumna[$col['id']]): ?><p class="small">Sin tareas.</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <div class="kanban-columna kanban-columna-nueva">
        <form method="post">
            <input type="hidden" name="accion" value="crear_columna">
            <input type="text" name="nombre_columna" placeholder="+ Nueva columna" style="width:100%;">
        </form>
    </div>
</div>
<?php layout_fin(); ?>
