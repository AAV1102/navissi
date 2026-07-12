<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Campos Personalizados', 'Campos Personalizados', '../');
    echo '<div class="msg-error">Solo TI puede definir campos personalizados.</div>';
    layout_fin();
    exit;
}

$entidades = ['inventario' => 'Inventario (equipos)', 'empleados' => 'Empleados', 'tickets' => 'Tickets'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $nombre = limpio($_POST['nombre_campo'] ?? null);
        if (!$nombre) {
            $msg = ['error', 'El nombre del campo es obligatorio.'];
        } else {
            try {
                $pdo->prepare("INSERT INTO campos_personalizados_def (entidad, nombre_campo, tipo, opciones) VALUES (?,?,?,?)")
                    ->execute([$_POST['entidad'] ?? 'inventario', $nombre, $_POST['tipo'] ?? 'TEXTO', limpio($_POST['opciones'] ?? null)]);
                $msg = ['ok', 'Campo personalizado creado.'];
            } catch (PDOException $e) {
                $msg = ['error', 'Ya existe un campo con ese nombre para esa entidad.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM campos_personalizados_def WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$campos = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM campos_personalizados_valor v WHERE v.campo_id = d.id AND v.valor IS NOT NULL AND v.valor != '') AS con_valor
    FROM campos_personalizados_def d ORDER BY entidad, nombre_campo")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Campos Personalizados', 'Campos Personalizados', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Campos Personalizados</h1>
<p class="subtitle">Agrega campos propios a Inventario, Empleados o Tickets sin tocar código — aparecen automáticamente en la ficha de cada uno.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nuevo campo</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Aplica a</label>
                <select name="entidad">
                    <?php foreach ($entidades as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Nombre del campo *</label><input type="text" name="nombre_campo" required placeholder="Ej. Número de póliza"></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <option value="TEXTO">Texto</option>
                    <option value="NUMERO">Número</option>
                    <option value="FECHA">Fecha</option>
                    <option value="LISTA">Lista de opciones</option>
                </select>
            </div>
            <div><label>Opciones (si es lista, separadas por coma)</label><input type="text" name="opciones" placeholder="Opción 1, Opción 2..."></div>
        </div>
        <button type="submit"><?= icon('check') ?> Crear campo</button>
    </form>
</div>

<table>
    <tr><th>Entidad</th><th>Campo</th><th>Tipo</th><th>Registros con valor</th><th></th></tr>
    <?php foreach ($campos as $c): ?>
    <tr>
        <td><span class="badge badge-otro"><?= e($entidades[$c['entidad']] ?? $c['entidad']) ?></span></td>
        <td><strong><?= e($c['nombre_campo']) ?></strong></td>
        <td><?= e($c['tipo']) ?><?= $c['opciones'] ? ' (' . e($c['opciones']) . ')' : '' ?></td>
        <td><?= (int)$c['con_valor'] ?></td>
        <td>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar este campo? Se perderán los valores guardados.');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$campos): ?><tr><td colspan="5" class="small">Sin campos personalizados todavía.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
