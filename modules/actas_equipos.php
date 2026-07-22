<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI', 'RRHH'])) {
    layout_inicio('Actas de Equipos', 'Actas de Equipos', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar actas de equipos.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    $empleadoDoc = limpio($_POST['empleado_documento'] ?? null);
    $serial = limpio($_POST['equipo_serial'] ?? null);
    $tipo = limpio($_POST['tipo'] ?? null) ?: 'ENTREGA';
    if ($empleadoDoc && $tipo) {
        $stmtE = $pdo->prepare("SELECT nombres FROM empleados WHERE documento = ?");
        $stmtE->execute([$empleadoDoc]);
        $empleadoNombre = $stmtE->fetchColumn();

        $equipoDescripcion = null;
        if ($serial) {
            $stmtEq = $pdo->prepare("SELECT marca, modelo FROM inventario WHERE serial = ?");
            $stmtEq->execute([$serial]);
            if ($eq = $stmtEq->fetch(PDO::FETCH_ASSOC)) $equipoDescripcion = trim($eq['marca'] . ' ' . $eq['modelo']);
        }

        $pazYSalvo = ($tipo === 'DEVOLUCION' || $tipo === 'BAJA') && !empty($_POST['paz_y_salvo']) ? 1 : 0;
        $autorizaDescuento = ($tipo === 'DEVOLUCION' || $tipo === 'BAJA') && !empty($_POST['autoriza_descuento']) ? 1 : 0;
        $montoDescuento = $autorizaDescuento && is_numeric($_POST['monto_descuento'] ?? null) ? (float) $_POST['monto_descuento'] : null;
        $pdo->prepare("INSERT INTO actas_equipos (tipo, empleado_documento, empleado_nombre, equipo_serial, equipo_descripcion, accesorios, estado_equipo, observaciones, creado_por, paz_y_salvo, autoriza_descuento, monto_descuento) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$tipo, $empleadoDoc, $empleadoNombre, $serial, $equipoDescripcion,
                limpio($_POST['accesorios'] ?? null), limpio($_POST['estado_equipo'] ?? null),
                limpio($_POST['observaciones'] ?? null), $u['nombre'], $pazYSalvo, $autorizaDescuento, $montoDescuento]);
        $msg = ['ok', 'Acta creada. Ahora hay que firmarla (por TI/quien entrega y por el empleado).'];
    }
}

$actas = $pdo->query("SELECT * FROM actas_equipos ORDER BY creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$empleados = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);
$equipos = $pdo->query("SELECT serial, marca, modelo FROM inventario ORDER BY marca")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Actas de Equipos', 'Actas de Equipos', '../');
?>
<h1><?= icon('file','icon-lg') ?> Actas de Entrega / Devolución de Equipos</h1>
<p class="subtitle">Diligenciado y firmado digitalmente por ambas partes — queda como respaldo legal del estado del equipo.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nueva acta</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Tipo *</label>
                <select name="tipo" required>
                    <option value="ENTREGA">Entrega de equipo</option>
                    <option value="DEVOLUCION">Devolución de equipo</option>
                    <option value="PRESTAMO_TEMPORAL">Préstamo temporal</option>
                    <option value="BAJA">Baja / dado de baja</option>
                    <option value="MANTENIMIENTO">Ingreso a mantenimiento/soporte</option>
                    <option value="CAMBIO_REPUESTO">Cambio de repuesto/pieza</option>
                </select>
            </div>
            <div><label>Empleado *</label>
                <input type="text" name="empleado_documento" list="lista-emp-acta" placeholder="Documento" required>
                <datalist id="lista-emp-acta"><?php foreach ($empleados as $e): ?><option value="<?= e($e['documento']) ?>"><?= e($e['nombres']) ?><?php endforeach; ?></datalist>
            </div>
            <div><label>Equipo (serial)</label>
                <input type="text" name="equipo_serial" list="lista-eq-acta" placeholder="Serial (opcional)">
                <datalist id="lista-eq-acta"><?php foreach ($equipos as $eq): ?><option value="<?= e($eq['serial']) ?>"><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?><?php endforeach; ?></datalist>
            </div>
            <div><label>Estado del equipo</label><input type="text" name="estado_equipo" placeholder="Ej. Buen estado, sin daños visibles"></div>
        </div>
        <label>Accesorios entregados</label>
        <input type="text" name="accesorios" style="width:100%;margin-bottom:10px;" placeholder="Cargador, mouse, maletín...">
        <label>Observaciones</label>
        <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;"></textarea>
        <div id="clausula-paz-salvo" hidden style="border:1px dashed var(--line);border-radius:8px;padding:12px;margin-bottom:10px;">
            <p class="small" style="margin-top:0;"><strong>Cláusula de retiro:</strong> si el empleado devuelve todo en buen estado, marca "Paz y salvo". Si falta algo o hay daños, autoriza el descuento correspondiente por nómina o liquidación.</p>
            <label><input type="checkbox" name="paz_y_salvo" value="1" id="chk-paz-salvo"> El empleado queda en paz y salvo (devolvió todo en buen estado)</label><br>
            <label><input type="checkbox" name="autoriza_descuento" value="1" id="chk-autoriza-descuento"> Autoriza el descuento por nómina/liquidación (no está en paz y salvo)</label>
            <div id="campo-monto-descuento" hidden style="margin-top:6px;"><label>Monto a descontar</label><input type="number" name="monto_descuento" step="0.01" placeholder="Valor en pesos"></div>
        </div>
        <button type="submit"><?= icon('check') ?> Crear acta</button>
    </form>
</div>

<table>
    <tr><th>Tipo</th><th>Empleado</th><th>Equipo</th><th>Fecha</th><th>Firmas</th><th></th></tr>
    <?php foreach ($actas as $a): ?>
    <tr>
        <?php
        $tipoEtiquetas = ['ENTREGA' => 'Entrega', 'DEVOLUCION' => 'Devolución', 'PRESTAMO_TEMPORAL' => 'Préstamo temporal',
            'BAJA' => 'Baja', 'MANTENIMIENTO' => 'Mantenimiento', 'CAMBIO_REPUESTO' => 'Cambio de repuesto'];
        $tipoClase = match ($a['tipo']) { 'ENTREGA', 'PRESTAMO_TEMPORAL' => 'badge-activo', 'BAJA' => 'badge-err', 'MANTENIMIENTO', 'CAMBIO_REPUESTO' => 'badge-warn', default => 'badge-otro' };
        ?>
        <td><span class="badge <?= $tipoClase ?>"><?= e($tipoEtiquetas[$a['tipo']] ?? $a['tipo']) ?></span></td>
        <td><?= e($a['empleado_nombre']) ?: e($a['empleado_documento']) ?></td>
        <td><?= e($a['equipo_descripcion']) ?: '—' ?> <?php if ($a['equipo_serial']): ?><br><span class="small"><?= e($a['equipo_serial']) ?></span><?php endif; ?></td>
        <td class="small"><?= e($a['creado_en']) ?></td>
        <td>
            <?= $a['firma_entrega'] ? '<span class="badge badge-activo">Entrega firmada</span>' : '<span class="badge badge-warn">Falta firma entrega</span>' ?><br>
            <?= $a['firma_empleado'] ? '<span class="badge badge-activo">Empleado firmó</span>' : '<span class="badge badge-warn">Falta firma empleado</span>' ?>
            <?php if (in_array($a['tipo'], ['DEVOLUCION', 'BAJA'], true)): ?><br>
                <?php if ($a['paz_y_salvo']): ?><span class="badge badge-activo">Paz y salvo</span>
                <?php elseif ($a['autoriza_descuento']): ?><span class="badge badge-err">Descuento autorizado</span>
                <?php else: ?><span class="badge badge-otro">Sin definir</span><?php endif; ?>
            <?php endif; ?>
        </td>
        <td><a href="acta_equipo_firmar.php?id=<?= (int)$a['id'] ?>">Ver / Firmar</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$actas): ?><tr><td colspan="6" class="small">Sin actas registradas.</td></tr><?php endif; ?>
</table>
<script>
(function () {
    var selTipo = document.querySelector('select[name="tipo"]');
    var clausula = document.getElementById('clausula-paz-salvo');
    var chkPaz = document.getElementById('chk-paz-salvo');
    var chkDescuento = document.getElementById('chk-autoriza-descuento');
    var campoMonto = document.getElementById('campo-monto-descuento');
    function actualizar() {
        clausula.hidden = !['DEVOLUCION', 'BAJA'].includes(selTipo.value);
    }
    selTipo.addEventListener('change', actualizar);
    actualizar();
    chkPaz.addEventListener('change', function () { if (this.checked) chkDescuento.checked = false; campoMonto.hidden = true; });
    chkDescuento.addEventListener('change', function () {
        if (this.checked) chkPaz.checked = false;
        campoMonto.hidden = !this.checked;
    });
})();
</script>
<?php layout_fin(); ?>
