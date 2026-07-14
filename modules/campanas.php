<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$u = usuario_actual();

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'COORDINADOR'])) {
    layout_inicio('Campañas', 'Calendario de Colecciones', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar campañas.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $nombre = limpio($_POST['nombre'] ?? null);
        if (!$nombre) {
            $msg = ['error', 'El nombre es obligatorio.'];
        } else {
            $datos = [
                'nombre' => $nombre,
                'temporada' => limpio($_POST['temporada'] ?? null),
                'area_responsable' => limpio($_POST['area_responsable'] ?? null),
                'fecha_inicio' => limpio($_POST['fecha_inicio'] ?? null) ?: null,
                'fecha_lanzamiento' => limpio($_POST['fecha_lanzamiento'] ?? null) ?: null,
                'fecha_fin' => limpio($_POST['fecha_fin'] ?? null) ?: null,
                'estado' => limpio($_POST['estado'] ?? null) ?: 'PLANEACION',
                'descripcion' => limpio($_POST['descripcion'] ?? null),
            ];
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $datos['id'] = $id;
                $pdo->prepare("UPDATE campanas_coleccion SET {$set} WHERE id = :id")->execute($datos);
                $msg = ['ok', 'Actualizada.'];
            } else {
                $datos['creado_por'] = $u['nombre'] ?? null;
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO campanas_coleccion ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Campaña creada.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM campanas_coleccion WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM campanas_coleccion WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
$campanas = $pdo->query("SELECT * FROM campanas_coleccion ORDER BY COALESCE(fecha_lanzamiento, fecha_inicio, creado_en) ASC")->fetchAll(PDO::FETCH_ASSOC);
$hoy = date('Y-m-d');

layout_inicio('Campañas', 'Calendario de Colecciones', '../');
?>
<h1><?= icon('dashboard','icon-lg') ?> Calendario de Colecciones y Campañas</h1>
<p class="subtitle">Lanzamientos de temporada coordinados entre diseño de moda, diseño gráfico y comercial.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar' : 'Nueva' ?> campaña</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Temporada</label><input type="text" name="temporada" placeholder="Verano 2026" value="<?= e($editar['temporada'] ?? '') ?>"></div>
            <div><label>Área responsable</label>
                <select name="area_responsable">
                    <?php foreach (['Diseño de Moda','Diseño Gráfico','Comercial','Marketing','Multiple'] as $a): ?><option <?= ($editar['area_responsable'] ?? '')===$a?'selected':'' ?>><?= $a ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['PLANEACION','DISEÑO','PRODUCCION','LANZADA','CERRADA'] as $es): ?><option <?= ($editar['estado'] ?? 'PLANEACION')===$es?'selected':'' ?>><?= $es ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Fecha inicio</label><input type="date" name="fecha_inicio" value="<?= e($editar['fecha_inicio'] ?? '') ?>"></div>
            <div><label>Fecha de lanzamiento</label><input type="date" name="fecha_lanzamiento" value="<?= e($editar['fecha_lanzamiento'] ?? '') ?>"></div>
            <div><label>Fecha fin</label><input type="date" name="fecha_fin" value="<?= e($editar['fecha_fin'] ?? '') ?>"></div>
        </div>
        <textarea name="descripcion" rows="2" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Descripción"><?= e($editar['descripcion'] ?? '') ?></textarea>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Crear' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="campanas.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Campaña</th><th>Temporada</th><th>Área</th><th>Inicio</th><th>Lanzamiento</th><th>Fin</th><th>Estado</th><th></th></tr>
    <?php foreach ($campanas as $c): $vencePronto = $c['fecha_lanzamiento'] && $c['fecha_lanzamiento'] >= $hoy && $c['fecha_lanzamiento'] <= date('Y-m-d', strtotime('+14 days')); ?>
    <tr<?= $vencePronto ? ' style="background:var(--accent-100);"' : '' ?>>
        <td><?= e($c['nombre']) ?></td>
        <td class="small"><?= e($c['temporada']) ?: '—' ?></td>
        <td><?= e($c['area_responsable']) ?: '—' ?></td>
        <td class="small"><?= e($c['fecha_inicio']) ?: '—' ?></td>
        <td class="small"><strong><?= e($c['fecha_lanzamiento']) ?: '—' ?></strong><?= $vencePronto ? ' ⚡' : '' ?></td>
        <td class="small"><?= e($c['fecha_fin']) ?: '—' ?></td>
        <td><span class="badge <?= $c['estado']==='LANZADA'?'badge-activo':'badge-otro' ?>"><?= e($c['estado']) ?></span></td>
        <td>
            <a href="?editar=<?= (int)$c['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$campanas): ?><tr><td colspan="8" class="small">Sin campañas registradas.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
