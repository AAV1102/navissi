<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/movimientos_campos.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    $inventarioId = (int) ($_POST['inventario_id'] ?? 0);
    $tipo = limpio($_POST['tipo'] ?? null);
    if (!$inventarioId || !$tipo) {
        $msg = ['error', 'Selecciona el equipo y el tipo de movimiento.'];
    } else {
        $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);

        // Campos específicos del tipo (Excel FMT_*) -> se guardan como JSON, y la
        // hoja de vida del equipo queda con el detalle completo, no solo el tipo.
        $detalles = [];
        foreach (campos_por_tipo($tipo) as $clave => $etiqueta) {
            $detalles[$etiqueta] = limpio($_POST["campo_{$clave}"] ?? null);
        }

        $pdo->prepare("INSERT INTO movimientos_equipos (inventario_id, tipo, responsable, destinatario, destinatario_documento, sede_id, motivo, observaciones, detalles_json)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$inventarioId, $tipo, limpio($_POST['responsable'] ?? null), limpio($_POST['destinatario'] ?? null),
                limpio($_POST['destinatario_documento'] ?? null), $sedeId, limpio($_POST['motivo'] ?? null),
                limpio($_POST['observaciones'] ?? null), json_encode($detalles, JSON_UNESCAPED_UNICODE)]);

        // Cada tipo refleja su efecto real en el estado del equipo en Inventario.
        $estadoNuevo = match ($tipo) {
            'BAJA' => 'DADO DE BAJA', 'PRESTAMO' => 'PRESTAMO', 'REPOTENCIAMIENTO' => 'EN REPARACION',
            'BODEGA' => 'EN BODEGA', 'DEVOLUCION' => 'EN BODEGA', default => 'ACTIVO',
        };
        if ($tipo === 'ASIGNACION' || $tipo === 'NUEVO') {
            $pdo->prepare("UPDATE inventario SET asignado_a = ?, estado = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([limpio($_POST['destinatario'] ?? null), $estadoNuevo, $inventarioId]);
        } else {
            $pdo->prepare("UPDATE inventario SET estado = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$estadoNuevo, $inventarioId]);
        }

        $nuevoId = $pdo->lastInsertId();
        $stmtSerial = $pdo->prepare("SELECT serial FROM inventario WHERE id = ?");
        $stmtSerial->execute([$inventarioId]);
        $serial = $stmtSerial->fetchColumn();
        if ($serial) {
            $resumenDetalles = implode(' · ', array_filter(array_map(fn($k, $v) => $v ? "{$k}: {$v}" : null, array_keys($detalles), $detalles)));
            hoja_vida_registrar($pdo, 'EQUIPO', $serial, $tipo, trim((limpio($_POST['responsable'] ?? '') ?? '') . ' -> ' . (limpio($_POST['destinatario'] ?? '') ?? '') . '. ' . $resumenDetalles), limpio($_POST['responsable'] ?? null));
        }
        if (!empty($_POST['destinatario_documento'])) {
            hoja_vida_registrar($pdo, 'EMPLEADO', limpio($_POST['destinatario_documento']), $tipo . '_EQUIPO', "Serial {$serial}", limpio($_POST['responsable'] ?? null));
        }
        header("Location: movimiento_detalle.php?id={$nuevoId}");
        exit;
    }
}

$tipoFiltro = trim($_GET['tipo'] ?? '');
$sql = "SELECT m.*, i.serial, i.marca, i.modelo, s.nombre AS sede_nombre
        FROM movimientos_equipos m
        LEFT JOIN inventario i ON m.inventario_id = i.id
        LEFT JOIN sedes s ON m.sede_id = s.id WHERE 1=1";
$params = [];
if ($tipoFiltro !== '') { $sql .= " AND m.tipo = ?"; $params[] = $tipoFiltro; }
// Alcance personal: un EMPLEADO sin rol elevado solo ve movimientos de sus propios equipos.
$personalMov = alcance_personal();
if ($personalMov !== null) {
    $sql .= " AND (m.destinatario_documento = ? OR i.asignado_documento = ?)";
    $params[] = $personalMov['documento'];
    $params[] = $personalMov['documento'];
}
$sql .= " ORDER BY m.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$equipos = $pdo->query("SELECT id, serial, placa, marca, modelo, tipo, sistema_operativo, procesador, memoria, almacenamiento, estado, fuente FROM inventario ORDER BY serial")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$empleados = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Movimientos de equipos', 'Movimientos', '../');
?>
<h1><?= icon('arrow-right','icon-lg') ?> Movimientos de Equipos</h1>
<p class="subtitle">Nuevo, asignación, préstamo, devolución, repotenciamiento, renting, bodega y baja - un formato digital por tipo, con firma electrónica, vinculado a Inventario, Hoja de Vida y Empleados.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nuevo movimiento</h3>
    <form method="post" id="form-movimiento">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Tipo de movimiento *</label>
                <select name="tipo" id="sel-tipo" required onchange="mostrarCampos()">
                    <?php foreach (tipos_movimiento() as $t => $label): ?>
                    <option value="<?= e($t) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column:span 2;"><label>Equipo (serial) *</label>
                <select name="inventario_id" id="sel-equipo" required onchange="mostrarFicha()">
                    <option value="">-- selecciona --</option>
                    <?php foreach ($equipos as $eq): ?>
                    <option value="<?= (int)$eq['id'] ?>"
                        data-serial="<?= e($eq['serial']) ?>" data-tipo="<?= e($eq['tipo']) ?>" data-marca="<?= e($eq['marca']) ?>"
                        data-modelo="<?= e($eq['modelo']) ?>" data-so="<?= e($eq['sistema_operativo']) ?>" data-cpu="<?= e($eq['procesador']) ?>"
                        data-ram="<?= e($eq['memoria']) ?>" data-disco="<?= e($eq['almacenamiento']) ?>" data-estado="<?= e($eq['estado']) ?>"
                        data-fuente="<?= e($eq['fuente']) ?>">
                        <?= e($eq['serial']) ?> — <?= e($eq['marca']) ?> <?= e($eq['modelo']) ?> (placa <?= e($eq['placa']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="ficha-tecnica-viva" class="msg-ok" style="display:none;"></div>

        <div class="grid-form">
            <div><label>Responsable / entrega TI</label><input type="text" name="responsable"></div>
            <div><label>Destinatario / recibe</label><input type="text" name="destinatario" list="lista-empleados" id="inp-destinatario" onchange="autocompletarDocumento()"></div>
            <input type="hidden" name="destinatario_documento" id="inp-destinatario-doc">
            <datalist id="lista-empleados">
                <?php foreach ($empleados as $emp): ?>
                <option value="<?= e($emp['nombres']) ?>" data-doc="<?= e($emp['documento']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- ninguna --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="campos-dinamicos"></div>

        <label class="small">Observaciones generales</label>
        <textarea name="observaciones" id="txt-observaciones" rows="3" style="width:100%;margin-bottom:8px;" placeholder="Escribe tus notas rápidas aquí, aunque sea desordenado - la IA las redacta profesional por ti."></textarea>
        <button type="button" class="btn-secondary" onclick="sugerirIA()" style="margin-bottom:14px;"><?= icon('robot') ?> Redactar con IA</button>
        <span id="ia-sugerir-estado" class="small"></span>
        <br>
        <button type="submit"><?= icon('plus') ?> Generar formato</button>
    </form>
</div>

<form class="toolbar" method="get">
    <select name="tipo" onchange="this.form.submit()">
        <option value="">Todos los tipos</option>
        <?php foreach (tipos_movimiento() as $t => $label): ?>
        <option value="<?= e($t) ?>" <?= $tipoFiltro===$t?'selected':'' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<table>
    <tr><th>#</th><th>Tipo</th><th>Equipo</th><th>Responsable</th><th>Destinatario</th><th>Sede</th><th>Firma</th><th>Fecha</th><th></th></tr>
    <?php foreach ($movimientos as $m): ?>
    <tr>
        <td>#<?= (int)$m['id'] ?></td>
        <td><span class="badge badge-otro"><?= e(tipos_movimiento()[$m['tipo']] ?? $m['tipo']) ?></span></td>
        <td><?= e($m['serial']) ?></td>
        <td><?= e($m['responsable']) ?></td>
        <td><?= e($m['destinatario']) ?></td>
        <td><?= e($m['sede_nombre']) ?></td>
        <td><?= $m['firma_nombre'] ? '<span class="badge badge-activo">'.icon('check').' Firmado</span>' : '<span class="badge badge-warn">Pendiente</span>' ?></td>
        <td class="small"><?= e($m['fecha']) ?></td>
        <td><a href="movimiento_detalle.php?id=<?= (int)$m['id'] ?>">Ver formato</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$movimientos): ?><tr><td colspan="9" class="small">No hay movimientos registrados.</td></tr><?php endif; ?>
</table>

<script>
var CAMPOS_POR_TIPO = <?php
    $todos = [];
    foreach (tipos_movimiento() as $t => $label) $todos[$t] = campos_por_tipo($t);
    echo json_encode($todos, JSON_UNESCAPED_UNICODE);
?>;

function mostrarCampos() {
    var tipo = document.getElementById('sel-tipo').value;
    var cont = document.getElementById('campos-dinamicos');
    var campos = CAMPOS_POR_TIPO[tipo] || {};
    var html = '';
    if (Object.keys(campos).length) {
        html += '<div class="grid-form">';
        for (var clave in campos) {
            html += '<div><label>' + campos[clave] + '</label><input type="text" name="campo_' + clave + '"></div>';
        }
        html += '</div>';
    }
    cont.innerHTML = html;
}

function mostrarFicha() {
    var sel = document.getElementById('sel-equipo');
    var opt = sel.options[sel.selectedIndex];
    var ficha = document.getElementById('ficha-tecnica-viva');
    if (!opt.value) { ficha.style.display = 'none'; return; }
    ficha.style.display = 'block';
    ficha.innerHTML = '<strong>Ficha técnica en vivo (del inventario / agente local):</strong><br>' +
        opt.dataset.tipo + ' ' + opt.dataset.marca + ' ' + opt.dataset.modelo + ' · SO: ' + (opt.dataset.so || '—') +
        ' · CPU: ' + (opt.dataset.cpu || '—') + ' · RAM: ' + (opt.dataset.ram || '—') + ' · Disco: ' + (opt.dataset.disco || '—') +
        ' · Estado actual: ' + opt.dataset.estado + (opt.dataset.fuente ? ' · Fuente del dato: ' + opt.dataset.fuente : '');
}

function autocompletarDocumento() {
    var val = document.getElementById('inp-destinatario').value;
    var opciones = document.querySelectorAll('#lista-empleados option');
    var doc = '';
    opciones.forEach(function (o) { if (o.value === val) doc = o.dataset.doc; });
    document.getElementById('inp-destinatario-doc').value = doc;
}

function sugerirIA() {
    var estado = document.getElementById('ia-sugerir-estado');
    var textarea = document.getElementById('txt-observaciones');
    var sel = document.getElementById('sel-equipo');
    var serial = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].dataset.serial : '';
    var tipo = document.getElementById('sel-tipo').value;
    if (!textarea.value.trim()) { estado.textContent = 'Escribe unas notas rápidas primero.'; return; }
    estado.textContent = 'Redactando...';
    fetch('../api_ia_sugerir.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ serial: serial, tipo: tipo, borrador: textarea.value })
    }).then(r => r.json()).then(data => {
        if (data.sugerencia) { textarea.value = data.sugerencia; estado.textContent = 'Redactado por IA - revísalo antes de guardar.'; }
        else { estado.textContent = data.error || 'No se pudo redactar.'; }
    }).catch(() => { estado.textContent = 'Error de conexión.'; });
}

mostrarCampos();
</script>
<?php layout_fin(); ?>
