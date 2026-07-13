<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$u = usuario_actual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_tablero') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if ($nombre) {
            $pdo->prepare("INSERT INTO tableros (nombre, area, descripcion, creado_por) VALUES (?,?,?,?)")
                ->execute([$nombre, limpio($_POST['area'] ?? null), limpio($_POST['descripcion'] ?? null), $u['nombre']]);
            $tableroId = $pdo->lastInsertId();
            // Columnas por defecto - cualquiera puede renombrarlas o agregar más después.
            foreach (['Por hacer', 'En progreso', 'Hecho'] as $i => $col) {
                $pdo->prepare("INSERT INTO tablero_columnas (tablero_id, nombre, orden) VALUES (?,?,?)")->execute([$tableroId, $col, $i]);
            }
            $msg = ['ok', 'Tablero creado.'];
        }
    } elseif ($accion === 'eliminar_tablero') {
        $pdo->prepare("DELETE FROM tableros WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Tablero eliminado.'];
    }
}

$areaFiltro = trim($_GET['area'] ?? '');
$sql = "SELECT t.*, (SELECT COUNT(*) FROM tablero_tareas tt WHERE tt.tablero_id = t.id) AS n_tareas FROM tableros t WHERE 1=1";
$params = [];
if ($areaFiltro !== '') { $sql .= " AND t.area = ?"; $params[] = $areaFiltro; }
if (alcance_area() !== null) { $sql .= " AND (t.area = ? OR t.area IS NULL)"; $params[] = alcance_area(); }
$sql .= " ORDER BY t.creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tableros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departamentosProy = $pdo->query("SELECT nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

layout_inicio('Proyectos', 'Proyectos', '../');
?>
<h1><?= icon('dashboard','icon-lg') ?> Tableros de Proyectos</h1>
<p class="subtitle">Organiza tareas por proyecto o área con tableros tipo kanban - arrastra las tarjetas entre columnas.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nuevo tablero</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear_tablero">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required placeholder="Ej: Apertura tienda Manizales"></div>
            <div><label>Área (opcional)</label>
                <select name="area">
                    <option value="">-- general / multi-área --</option>
                    <?php foreach ($departamentosProy as $d): ?><option><?= e($d) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <textarea name="descripcion" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Descripción breve"></textarea>
        <button type="submit">Crear tablero</button>
    </form>
</div>

<div class="cards">
    <?php foreach ($tableros as $t): ?>
    <a class="card card-link" href="tablero_detalle.php?id=<?= (int)$t['id'] ?>">
        <div class="num"><?= (int)$t['n_tareas'] ?></div>
        <div class="label"><?= e($t['nombre']) ?><?= $t['area'] ? ' · ' . e($t['area']) : '' ?></div>
    </a>
    <?php endforeach; ?>
    <?php if (!$tableros): ?><p class="small">Aún no hay tableros. Crea el primero arriba.</p><?php endif; ?>
</div>
<?php layout_fin(); ?>
