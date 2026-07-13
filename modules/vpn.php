<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('VPN', 'VPN', '../');
    echo '<div class="msg-error">Solo TI puede gestionar conexiones VPN.</div>';
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
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $datos = ['nombre' => $nombre, 'tipo' => limpio($_POST['tipo'] ?? null), 'servidor' => limpio($_POST['servidor'] ?? null),
                'usuario' => limpio($_POST['usuario'] ?? null), 'sede_id' => $sedeId,
                'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVA', 'observaciones' => limpio($_POST['observaciones'] ?? null)];
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $datos['id'] = $id;
                $pdo->prepare("UPDATE vpn_conexiones SET {$set} WHERE id = :id")->execute($datos);
                $msg = ['ok', 'Actualizada.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO vpn_conexiones ({$cols}) VALUES ({$ph})")->execute($datos);
                $msg = ['ok', 'Conexión VPN agregada.'];
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM vpn_conexiones WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM vpn_conexiones WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}
$conexiones = $pdo->query("SELECT v.*, s.nombre AS sede_nombre FROM vpn_conexiones v LEFT JOIN sedes s ON v.sede_id = s.id ORDER BY v.nombre")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('VPN', 'VPN', '../');
?>
<h1><?= icon('shield','icon-lg') ?> Gestión de VPN</h1>
<p class="subtitle">Registro de conexiones VPN por sede/servidor (site-to-site, acceso remoto, etc.).</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= $editar ? 'Editar' : 'Agregar' ?> conexión VPN</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
            <div><label>Tipo</label>
                <select name="tipo">
                    <?php foreach (['SITE_TO_SITE','ACCESO_REMOTO','CLIENTE_A_SITIO','OTRO'] as $t): ?><option <?= ($editar['tipo'] ?? '')===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Servidor/IP</label><input type="text" name="servidor" value="<?= e($editar['servidor'] ?? '') ?>"></div>
            <div><label>Usuario</label><input type="text" name="usuario" value="<?= e($editar['usuario'] ?? '') ?>"></div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option <?= (($editar['sede_id'] ?? null)==$s['id'])?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['ACTIVA','INACTIVA','EN REVISION'] as $es): ?><option <?= ($editar['estado'] ?? 'ACTIVA')===$es?'selected':'' ?>><?= $es ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;"><?= e($editar['observaciones'] ?? '') ?></textarea>
        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar' ?></button>
        <?php if ($editar): ?><a class="btn btn-secondary" href="vpn.php">Cancelar</a><?php endif; ?>
    </form>
</div>

<table>
    <tr><th>Nombre</th><th>Tipo</th><th>Servidor</th><th>Sede</th><th>Estado</th><th></th></tr>
    <?php foreach ($conexiones as $v): ?>
    <tr>
        <td><?= e($v['nombre']) ?></td>
        <td><?= e($v['tipo']) ?: '—' ?></td>
        <td class="small"><?= e($v['servidor']) ?: '—' ?></td>
        <td><?= e($v['sede_nombre']) ?: '—' ?></td>
        <td><span class="badge <?= $v['estado']==='ACTIVA'?'badge-activo':'badge-otro' ?>"><?= e($v['estado']) ?></span></td>
        <td>
            <a href="?editar=<?= (int)$v['id'] ?>">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$conexiones): ?><tr><td colspan="6" class="small">Sin conexiones VPN registradas.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
