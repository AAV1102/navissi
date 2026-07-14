<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;
$u = usuario_actual();
$puedeAprobar = tiene_rol(['ADMIN', 'GERENCIA', 'CEO']);

if (!tiene_rol(['ADMIN', 'GERENCIA', 'CEO', 'TI'])) {
    layout_inicio('Bajas de Equipos', 'Bajas de Equipos', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar bajas de equipos.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'solicitar') {
        $serial = limpio($_POST['equipo_serial'] ?? null);
        $motivo = limpio($_POST['motivo'] ?? null);
        if (!$serial || !$motivo) {
            $msg = ['error', 'Selecciona el equipo y escribe el motivo.'];
        } else {
            $stmt = $pdo->prepare("SELECT id FROM inventario WHERE serial = ?");
            $stmt->execute([$serial]);
            $equipoId = $stmt->fetchColumn();
            if (!$equipoId) {
                $msg = ['error', 'No existe ningún equipo con ese serial.'];
            } else {
                $pdo->prepare("INSERT INTO inventario_bajas (equipo_id, equipo_serial, tipo_baja, motivo, valor_libros, solicitado_por, observaciones) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$equipoId, $serial, limpio($_POST['tipo_baja'] ?? null) ?: 'OBSOLETO', $motivo,
                        (float) ($_POST['valor_libros'] ?? 0) ?: null, $u['nombre'] ?? null, limpio($_POST['observaciones'] ?? null)]);
                $msg = ['ok', 'Solicitud de baja registrada, queda pendiente de aprobación.'];
            }
        }
    } elseif ($accion === 'aprobar' && $puedeAprobar) {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM inventario_bajas WHERE id = ?");
        $stmt->execute([$id]);
        $baja = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($baja) {
            $pdo->prepare("UPDATE inventario_bajas SET estado = 'APROBADA', aprobado_por = ?, aprobado_en = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$u['nombre'] ?? null, $id]);
            if ($baja['equipo_id']) {
                $pdo->prepare("UPDATE inventario SET estado = 'DADO DE BAJA' WHERE id = ?")->execute([$baja['equipo_id']]);
            }
            $msg = ['ok', 'Baja aprobada. El equipo quedó marcado como DADO DE BAJA en inventario.'];
        }
    } elseif ($accion === 'rechazar' && $puedeAprobar) {
        $pdo->prepare("UPDATE inventario_bajas SET estado = 'RECHAZADA', aprobado_por = ?, aprobado_en = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$u['nombre'] ?? null, (int) $_POST['id']]);
        $msg = ['ok', 'Solicitud de baja rechazada.'];
    } elseif ($accion === 'eliminar' && $puedeAprobar) {
        $pdo->prepare("DELETE FROM inventario_bajas WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$bajas = $pdo->query("SELECT b.*, i.marca, i.modelo, i.placa FROM inventario_bajas b LEFT JOIN inventario i ON b.equipo_id = i.id ORDER BY b.creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$equipos = $pdo->query("SELECT serial, marca, modelo, placa FROM inventario WHERE estado != 'DADO DE BAJA' ORDER BY serial")->fetchAll(PDO::FETCH_ASSOC);
$pendientes = 0; $valorAprobado = 0;
foreach ($bajas as $b) {
    if ($b['estado'] === 'SOLICITADA') $pendientes++;
    if ($b['estado'] === 'APROBADA') $valorAprobado += (float) $b['valor_libros'];
}

layout_inicio('Bajas de Equipos', 'Bajas de Equipos', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Baja Formal de Equipos</h1>
<p class="subtitle">Registro auditable de por qué y cuándo se dio de baja un equipo, con aprobación — <?= $pendientes ?> solicitudes pendientes.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Solicitar baja de un equipo</h3>
    <form method="post">
        <input type="hidden" name="accion" value="solicitar">
        <div class="grid-form">
            <div><label>Equipo (serial) *</label>
                <input type="text" name="equipo_serial" list="lista-eq-baja" required placeholder="Escanea o escribe el serial">
                <datalist id="lista-eq-baja"><?php foreach ($equipos as $eq): ?><option value="<?= e($eq['serial']) ?>"><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?> <?= $eq['placa'] ? '· ' . e($eq['placa']) : '' ?><?php endforeach; ?></datalist>
            </div>
            <div><label>Tipo de baja</label>
                <select name="tipo_baja">
                    <?php foreach (['OBSOLETO','DAÑO_IRREPARABLE','ROBO_PERDIDA','VENTA','DONACION','OTRO'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Valor en libros</label><input type="number" step="0.01" name="valor_libros"></div>
            <div style="grid-column:1/-1;"><label>Motivo *</label><input type="text" name="motivo" required placeholder="Ej. Equipo con más de 6 años, ya no es reparable"></div>
        </div>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Observaciones"></textarea>
        <button type="submit">Solicitar baja</button>
    </form>
</div>

<table>
    <tr><th>Equipo</th><th>Tipo</th><th>Motivo</th><th>Valor libros</th><th>Solicitado por</th><th>Estado</th><th></th></tr>
    <?php foreach ($bajas as $b): ?>
    <tr>
        <td><?= e($b['marca']) ?> <?= e($b['modelo']) ?><br><span class="small"><?= e($b['equipo_serial']) ?></span></td>
        <td><?= e($b['tipo_baja']) ?></td>
        <td class="small"><?= e($b['motivo']) ?></td>
        <td><?= $b['valor_libros'] ? '$' . number_format((float)$b['valor_libros'],0,',','.') : '—' ?></td>
        <td class="small"><?= e($b['solicitado_por']) ?></td>
        <td><span class="badge <?= $b['estado']==='APROBADA'?'badge-activo':'badge-otro' ?>"><?= e($b['estado']) ?></span>
            <?php if ($b['aprobado_por']): ?><div class="small"><?= e($b['aprobado_por']) ?></div><?php endif; ?>
        </td>
        <td>
            <?php if ($puedeAprobar && $b['estado'] === 'SOLICITADA'): ?>
            <form class="inline" method="post"><input type="hidden" name="accion" value="aprobar"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button type="submit" style="padding:4px 10px;font-size:12px;">Aprobar</button></form>
            <form class="inline" method="post"><input type="hidden" name="accion" value="rechazar"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button type="submit" class="btn-secondary" style="padding:4px 10px;font-size:12px;">Rechazar</button></form>
            <?php endif; ?>
            <?php if ($puedeAprobar): ?>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$bajas): ?><tr><td colspan="7" class="small">Sin bajas registradas.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
