<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null);
        // Asignado a: vinculo real por documento (selector), no texto libre — asi
        // Inventario y Talento Humano quedan sincronizados de verdad.
        $documentoAsignado = limpio($_POST['asignado_documento'] ?? null);
        $nombreAsignado = null;
        if ($documentoAsignado) {
            $stmtEmp = $pdo->prepare("SELECT nombres FROM empleados WHERE documento = ?");
            $stmtEmp->execute([$documentoAsignado]);
            $nombreAsignado = $stmtEmp->fetchColumn() ?: null;
        }
        $datos = [
            'serial' => limpio($_POST['serial'] ?? null),
            'placa' => limpio($_POST['placa'] ?? null),
            'asignado_a' => $nombreAsignado ?: limpio($_POST['asignado_a_manual'] ?? null),
            'asignado_documento' => $documentoAsignado,
            'sede_id' => $sedeId,
            'area' => limpio($_POST['area'] ?? null),
            'cargo' => limpio($_POST['cargo'] ?? null),
            'tipo' => limpio($_POST['tipo'] ?? null),
            'marca' => limpio($_POST['marca'] ?? null),
            'modelo' => limpio($_POST['modelo'] ?? null),
            'sistema_operativo' => limpio($_POST['sistema_operativo'] ?? null),
            'procesador' => limpio($_POST['procesador'] ?? null),
            'memoria' => limpio($_POST['memoria'] ?? null),
            'almacenamiento' => limpio($_POST['almacenamiento'] ?? null),
            'estado' => limpio($_POST['estado'] ?? null) ?: 'ACTIVO',
        ];
        if (!$datos['serial']) {
            $msg = ['error', 'El serial es obligatorio.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE inventario SET {$set}, actualizado_en = CURRENT_TIMESTAMP WHERE id = :id");
                $datos['id'] = $id;
                $stmt->execute($datos);
                $msg = ['ok', 'Equipo actualizado.'];
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                try {
                    $stmt = $pdo->prepare("INSERT INTO inventario ({$cols}) VALUES ({$ph})");
                    $stmt->execute($datos);
                    $msg = ['ok', 'Equipo agregado.'];
                } catch (PDOException $e) {
                    $msg = ['error', 'Ya existe un equipo con ese serial.'];
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM inventario WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Equipo eliminado.'];
    } elseif ($accion === 'guardar_campos') {
        $idCampos = (int) ($_POST['id'] ?? 0);
        foreach ($_POST['campo'] ?? [] as $campoId => $valor) {
            $pdo->prepare("INSERT INTO campos_personalizados_valor (campo_id, entidad_id, valor) VALUES (?,?,?)
                ON CONFLICT(campo_id, entidad_id) DO UPDATE SET valor = excluded.valor")
                ->execute([(int) $campoId, $idCampos, limpio($valor)]);
        }
        $msg = ['ok', 'Campos personalizados guardados.'];
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM inventario WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$busqueda = trim($_GET['q'] ?? '');
$vista = strtoupper(trim((string)($_GET['vista'] ?? 'TODOS')));
$vistas = [
    'TODOS'=>['Todos',[]],
    'COMPUTADORES'=>['Computadores',['PORTATIL','ESCRITORIO','ALL IN ONE','POS','TABLET']],
    'IMPRESORAS'=>['Impresoras',['IMPRESORA']],
    'SERVIDORES'=>['Servidores',['SERVIDOR']],
    'REDES'=>['Redes y puntos de acceso',['PUNTO DE ACCESO','ACCESS POINT','ROUTER','SWITCH','RED']],
    'PERIFERICOS'=>['Monitores y periféricos',['MONITOR','CAMARA','OTRO']],
];
if(!isset($vistas[$vista]))$vista='TODOS';
$sql = "SELECT i.*, s.nombre AS sede_nombre FROM inventario i LEFT JOIN sedes s ON i.sede_id = s.id WHERE 1=1";
$params = [];
if($vistas[$vista][1]){$ph=[];foreach($vistas[$vista][1] as $n=>$tipoVista){$k='tipo_v'.$n;$ph[]=':'.$k;$params[$k]=$tipoVista;}$sql.=' AND upper(i.tipo) IN ('.implode(',',$ph).')';}
if ($busqueda !== '') {
    $sql .= " AND (i.serial LIKE :b OR i.placa LIKE :b OR i.asignado_a LIKE :b OR i.asignado_documento LIKE :b OR i.marca LIKE :b OR i.modelo LIKE :b OR s.nombre LIKE :b)";
    $params['b'] = "%{$busqueda}%";
}
// Alcance por área: si el usuario tiene un área asignada (ver Usuarios y roles),
// solo ve los equipos de esa área — automático, sin que tenga que filtrar nada.
if (alcance_area() !== null) {
    $sql .= " AND i.area = :area";
    $params['area'] = alcance_area();
}
// Alcance personal: un EMPLEADO sin rol elevado que tenga este módulo habilitado
// individualmente solo ve el/los equipo(s) asignados a él, no el inventario completo.
$personal = alcance_personal();
if ($personal !== null) {
    $sql .= " AND i.asignado_documento = :doc_personal";
    $params['doc_personal'] = $personal['documento'];
}
$sql .= " ORDER BY i.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$empleadosLista = $pdo->query("SELECT documento, nombres, area, cargo FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);

// Campos especificos por tipo de equipo: los campos GLOBALES (asignado, sede,
// serial, placa, modelo, tipo, area, cargo) siempre se muestran; el resto
// cambia segun el tipo (un monitor no tiene procesador ni almacenamiento).
$camposPorTipo = [
    'PORTATIL' => ['marca','sistema_operativo','procesador','memoria','almacenamiento'],
    'ESCRITORIO' => ['marca','sistema_operativo','procesador','memoria','almacenamiento'],
    'ALL IN ONE' => ['marca','sistema_operativo','procesador','memoria','almacenamiento'],
    'SERVIDOR' => ['marca','sistema_operativo','procesador','memoria','almacenamiento'],
    'POS' => ['marca','sistema_operativo','procesador','memoria','almacenamiento'],
    'MONITOR' => ['marca'],
    'IMPRESORA' => ['marca'],
    'PUNTO DE ACCESO' => ['marca'],
    'ROUTER' => ['marca'],
    'SWITCH' => ['marca'],
    'RED' => ['marca'],
    'CAMARA' => ['marca'],
    'TABLET' => ['marca','sistema_operativo','almacenamiento'],
    'OTRO' => ['marca','sistema_operativo','procesador','memoria','almacenamiento'],
];
$tiposDisponibles = array_keys($camposPorTipo);

// Campos personalizados (definidos sin tocar código desde Seguridad -> Campos Personalizados).
$camposDefInv = $pdo->query("SELECT * FROM campos_personalizados_def WHERE entidad = 'inventario' ORDER BY nombre_campo")->fetchAll(PDO::FETCH_ASSOC);
$camposValoresInv = [];
if ($editar && $camposDefInv) {
    $stmt = $pdo->prepare("SELECT campo_id, valor FROM campos_personalizados_valor WHERE entidad_id = ?");
    $stmt->execute([$editar['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) $camposValoresInv[$v['campo_id']] = $v['valor'];
}

layout_inicio('Inventario', 'Inventario', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Inventario de equipos</h1>
<p class="subtitle"><?= count($equipos) ?> equipos <?= $busqueda ? 'encontrados para "' . e($busqueda) . '"' : 'en total' ?>.</p>

<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="toolbar" style="margin-bottom:14px"><button type="button" id="btn-abrir-form" class="btn"><?= icon('plus') ?> Agregar activo</button><a class="btn btn-secondary" href="agente_inventario.php"><?=icon('zap')?> Instalar agente</a><a class="btn btn-secondary" href="acceso_remoto.php"><?=icon('remote')?> Acceso remoto</a></div>

<div class="panel-grid-4" style="margin-bottom:16px">
<?php foreach ([['COMPUTADORES','Computadores','inventory','inventario.php?vista=COMPUTADORES'],['IMPRESORAS','Impresoras','file','impresoras.php'],['SERVIDORES','Servidores','cloud','inventario.php?vista=SERVIDORES'],['REDES','Access point / redes','cloud','inventario.php?vista=REDES']] as $tarjeta): $cnt=0; if($tarjeta[0]==='IMPRESORAS'){$cnt=(int)$pdo->query("SELECT COUNT(*) FROM impresoras")->fetchColumn();}else{$tipos=$vistas[$tarjeta[0]][1];$ph=implode(',',array_fill(0,count($tipos),'?'));$st=$pdo->prepare("SELECT COUNT(*) FROM inventario WHERE upper(tipo) IN ($ph)");$st->execute($tipos);$cnt=(int)$st->fetchColumn();} ?><a class="panel" href="<?=e($tarjeta[3])?>" style="text-decoration:none;display:block;border-top:3px solid var(--teal-500)"><div class="small"><?=icon($tarjeta[2])?> <?=e($tarjeta[1])?></div><strong style="font-size:24px;display:block;margin-top:6px"><?=$cnt?></strong><span class="small">Ver y gestionar →</span></a><?php endforeach; ?>
</div>

<nav class="tabs-atera" aria-label="Tipos de inventario" style="margin-bottom:16px">
<?php foreach($vistas as $claveVista=>$datosVista):?><a class="tab-atera <?=$vista===$claveVista?'activo':''?>" href="?vista=<?=e($claveVista)?><?= $busqueda?'&q='.urlencode($busqueda):'' ?>"><?=e($datosVista[0])?></a><?php endforeach;?>
</nav>

<div class="panel" id="panel-form-equipo" data-form-manual="1" <?= $editar ? '' : 'hidden' ?> style="margin-top:14px;">
    <h3><?= $editar ? 'Editar equipo' : 'Agregar equipo' ?></h3>
    <form method="post" id="form-equipo">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <div class="grid-form">
            <div><label>Serial *</label><input type="text" name="serial" required value="<?= e($editar['serial'] ?? '') ?>"></div>
            <div><label>Placa</label><input type="text" name="placa" value="<?= e($editar['placa'] ?? '') ?>"></div>
            <div>
                <label>Asignado a (empleado real de Talento Humano)</label>
                <input type="text" id="buscar-empleado" list="lista-empleados-inv" placeholder="Escribe un nombre o documento..."
                    value="<?= e($editar['asignado_a'] ?? '') ?>" autocomplete="off">
                <datalist id="lista-empleados-inv">
                    <?php foreach ($empleadosLista as $emp): ?>
                    <option data-documento="<?= e($emp['documento']) ?>" data-area="<?= e($emp['area']) ?>" data-cargo="<?= e($emp['cargo']) ?>" value="<?= e($emp['nombres']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="asignado_documento" id="asignado_documento" value="<?= e($editar['asignado_documento'] ?? '') ?>">
                <input type="hidden" name="asignado_a_manual" id="asignado_a_manual" value="<?= e($editar['asignado_a'] ?? '') ?>">
                <p class="small" id="aviso-sin-vincular" <?= !empty($editar['asignado_documento']) ? 'hidden' : '' ?>>
                    <?= icon('bell') ?> Si no aparece en la lista, el equipo queda "asignado" solo como texto — no se vinculará con la ficha real del empleado en Talento Humano.
                </p>
            </div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- seleccionar --</option>
                    <?php foreach ($sedes as $s): ?>
                    <option <?= (($editar['sede_id'] ?? null) == $s['id']) ? 'selected' : '' ?> value="<?= e($s['nombre']) ?>"><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Área</label><input type="text" name="area" id="campo-area" value="<?= e($editar['area'] ?? '') ?>"></div>
            <div><label>Cargo</label><input type="text" name="cargo" id="campo-cargo" value="<?= e($editar['cargo'] ?? '') ?>"></div>
            <div><label>Tipo *</label>
                <select name="tipo" id="campo-tipo" required>
                    <?php foreach ($tiposDisponibles as $t): ?>
                    <option <?= ($editar['tipo'] ?? 'PORTATIL') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Modelo</label><input type="text" name="modelo" value="<?= e($editar['modelo'] ?? '') ?>"></div>
            <div><label>Estado</label>
                <select name="estado">
                    <?php foreach (['ACTIVO','DISPONIBLE','EN REPARACION','DADO DE BAJA','PRESTAMO','EN BODEGA'] as $es): ?>
                    <option <?= ($editar['estado'] ?? 'ACTIVO') === $es ? 'selected' : '' ?>><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid-form" id="campos-por-tipo">
            <div data-campo="marca"><label>Marca</label><input type="text" name="marca" value="<?= e($editar['marca'] ?? '') ?>"></div>
            <div data-campo="sistema_operativo"><label>Sistema operativo</label><input type="text" name="sistema_operativo" value="<?= e($editar['sistema_operativo'] ?? '') ?>"></div>
            <div data-campo="procesador"><label>Procesador</label><input type="text" name="procesador" value="<?= e($editar['procesador'] ?? '') ?>"></div>
            <div data-campo="memoria"><label>Memoria</label><input type="text" name="memoria" value="<?= e($editar['memoria'] ?? '') ?>"></div>
            <div data-campo="almacenamiento"><label>Almacenamiento</label><input type="text" name="almacenamiento" value="<?= e($editar['almacenamiento'] ?? '') ?>"></div>
        </div>

        <button type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar equipo' ?></button>
        <a class="btn btn-secondary" href="inventario.php" id="btn-cancelar-form">Cancelar</a>
    </form>
</div>

<?php if ($editar && $camposDefInv): ?>
<div class="panel">
    <h3><?= icon('inventory') ?> Campos personalizados <a href="campos_personalizados.php" class="small" style="float:right;font-weight:400;">Agregar/quitar campos →</a></h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar_campos">
        <input type="hidden" name="id" value="<?= (int) $editar['id'] ?>">
        <?php foreach ($camposDefInv as $cd): $valorActual = $camposValoresInv[$cd['id']] ?? ''; ?>
        <label class="small"><?= e($cd['nombre_campo']) ?></label>
        <?php if ($cd['tipo'] === 'LISTA' && $cd['opciones']): ?>
        <select name="campo[<?= (int)$cd['id'] ?>]" style="width:100%;margin-bottom:10px;">
            <option value="">-- sin definir --</option>
            <?php foreach (array_map('trim', explode(',', $cd['opciones'])) as $op): ?>
            <option <?= $valorActual===$op?'selected':'' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="<?= $cd['tipo']==='FECHA'?'date':($cd['tipo']==='NUMERO'?'number':'text') ?>" name="campo[<?= (int)$cd['id'] ?>]" value="<?= e($valorActual) ?>" style="width:100%;margin-bottom:10px;">
        <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit"><?= icon('check') ?> Guardar campos</button>
    </form>
</div>
<?php endif; ?>

<script>
(function () {
    var CAMPOS_POR_TIPO = <?= json_encode($camposPorTipo) ?>;
    var btnAbrir = document.getElementById('btn-abrir-form');
    var panel = document.getElementById('panel-form-equipo');
    btnAbrir.addEventListener('click', function () { panel.hidden = false; panel.scrollIntoView({behavior:'smooth'}); });

    var tipoSelect = document.getElementById('campo-tipo');
    function actualizarCamposPorTipo() {
        var visibles = CAMPOS_POR_TIPO[tipoSelect.value] || [];
        document.querySelectorAll('#campos-por-tipo [data-campo]').forEach(function (div) {
            var campo = div.getAttribute('data-campo');
            div.style.display = visibles.indexOf(campo) !== -1 ? '' : 'none';
        });
    }
    tipoSelect.addEventListener('change', actualizarCamposPorTipo);
    actualizarCamposPorTipo();

    var buscarEmpleado = document.getElementById('buscar-empleado');
    var docHidden = document.getElementById('asignado_documento');
    var manualHidden = document.getElementById('asignado_a_manual');
    var aviso = document.getElementById('aviso-sin-vincular');
    var areaCampo = document.getElementById('campo-area');
    var cargoCampo = document.getElementById('campo-cargo');
    buscarEmpleado.addEventListener('input', function () {
        var opciones = document.querySelectorAll('#lista-empleados-inv option');
        var encontrada = null;
        opciones.forEach(function (op) { if (op.value === buscarEmpleado.value) encontrada = op; });
        if (encontrada) {
            docHidden.value = encontrada.getAttribute('data-documento');
            manualHidden.value = '';
            aviso.hidden = true;
            if (!areaCampo.value) areaCampo.value = encontrada.getAttribute('data-area') || '';
            if (!cargoCampo.value) cargoCampo.value = encontrada.getAttribute('data-cargo') || '';
        } else {
            docHidden.value = '';
            manualHidden.value = buscarEmpleado.value;
            aviso.hidden = buscarEmpleado.value === '';
        }
    });
})();
</script>

<form class="toolbar" method="get">
    <input type="hidden" name="vista" value="<?=e($vista)?>">
    <input type="search" name="q" placeholder="Buscar por serial, placa, usuario, marca, sede..." value="<?= e($busqueda) ?>" style="min-width:320px">
    <button type="submit">Buscar</button>
    <?php if ($busqueda): ?><a class="btn btn-secondary" href="inventario.php?vista=<?=e($vista)?>">Limpiar</a><?php endif; ?>
</form>

<div class="tabla-toolbar">
    <label class="small chk-todos"><input type="checkbox" id="chk-todos-eq"> Seleccionar todo</label>
    <span class="tabla-toolbar-acciones small">Selecciona un activo para abrir su ficha, editarlo o eliminarlo.</span>
    <span class="small" style="margin-left:auto;">Mostrando <?= count($equipos) ?> de <?= count($equipos) ?> dispositivos</span>
</div>
<table class="tabla-tickets">
    <thead>
    <tr>
        <th style="width:30px;"></th>
        <th>Dispositivo</th><th>Disponibilidad</th><th>Tipo</th><th>Sitio</th><th>Asignado a</th><th>Marca / Modelo</th><th>Estado</th><th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($equipos as $eq):
        $inicialesEq = mb_strtoupper(mb_substr($eq['tipo'] ?: 'E', 0, 1));
        $enLinea = $eq['estado'] === 'ACTIVO';
    ?>
    <tr>
        <td onclick="event.stopPropagation()"><input type="checkbox" class="chk-eq"></td>
        <td>
            <a href="equipo_detalle.php?id=<?= (int)$eq['id'] ?>" style="display:flex; gap:10px; align-items:center; text-decoration:none; color:inherit;">
                <span class="avatar-sq"><?= e($inicialesEq) ?></span>
                <div><strong><?= e($eq['serial']) ?></strong><br><span class="small"><?= e($eq['placa']) ?: 'Sin placa' ?></span></div>
            </a>
        </td>
        <td><span class="badge <?= $enLinea ? 'badge-activo' : 'badge-otro' ?>"><?= $enLinea ? '● En línea' : '○ Sin conexión' ?></span></td>
        <td><?= e($eq['tipo']) ?></td>
        <td><?= e($eq['sede_nombre']) ?: '—' ?></td>
        <td><?= e($eq['asignado_a']) ?: '—' ?></td>
        <td><?= e($eq['marca']) ?> <?= e($eq['modelo']) ?></td>
        <td><span class="badge <?= $eq['estado']==='ACTIVO'?'badge-activo':'badge-otro' ?>"><?= e($eq['estado']) ?></span></td>
        <td onclick="event.stopPropagation()">
            <a href="?editar=<?= (int)$eq['id'] ?>" class="small">Editar</a>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar este equipo?');" style="display:inline;">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$eq['id'] ?>">
                <button type="submit" class="link-btn" style="color:var(--err-fg);"><?= icon('trash') ?></button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$equipos): ?>
    <tr><td colspan="9" style="text-align:center;padding:60px 14px;border-bottom:none;">
        <div style="font-size:44px;opacity:.5;"><?= icon('inventory','icon-lg') ?></div>
        <strong>Lamentablemente, no tiene dispositivos</strong><br>
        <span class="small">Agrega uno con el formulario de arriba o corre el agente de inventario.</span>
    </td></tr>
    <?php endif; ?>
    </tbody>
</table>
<script>
document.getElementById('chk-todos-eq')?.addEventListener('change', function () {
    document.querySelectorAll('.chk-eq').forEach(c => c.checked = this.checked);
});
</script>
<?php layout_fin(); ?>
