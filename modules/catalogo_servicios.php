<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/gobierno_operativo.php';
requiere_login('../');
$pdo = db();
$usuario = usuario_actual();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'solicitar') {
            $monto = trim((string)($_POST['monto'] ?? '')) === '' ? null : (float)$_POST['monto'];
            $solicitud = solicitud_crear($pdo, $usuario, (int)$_POST['catalogo_id'], (string)$_POST['descripcion'], $monto, (string)($_POST['prioridad'] ?? 'NORMAL'), (int)($_POST['sede_id'] ?? 0) ?: null);
            header('Location: solicitud_detalle.php?id=' . (int)$solicitud['id'] . '&creada=1');
            exit;
        }
        if (!tiene_rol(['SUPER_ADMIN','ADMIN'])) throw new RuntimeException('Solo Administración puede modificar el catálogo.');
        if ($accion === 'guardar_servicio') {
            $id = (int)($_POST['id'] ?? 0);
            $codigo = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)$_POST['codigo']));
            $datos = [
                $codigo, limpio($_POST['nombre'] ?? null), limpio($_POST['descripcion'] ?? null), limpio($_POST['categoria'] ?? null) ?: 'OPERACION',
                limpio($_POST['area_responsable'] ?? null), in_array($_POST['nivel_aprobacion'] ?? '', ['DIRECTOR','GERENCIA'], true) ? $_POST['nivel_aprobacion'] : 'DIRECTOR',
                !empty($_POST['requiere_monto']) ? 1 : 0, trim((string)($_POST['monto_escalamiento'] ?? '')) === '' ? null : (float)$_POST['monto_escalamiento'],
                min(720, max(1, (int)($_POST['sla_horas'] ?? 24))), !empty($_POST['crea_ticket']) ? 1 : 0,
                limpio($_POST['categoria_ticket'] ?? null), limpio($_POST['prioridad_ticket'] ?? null) ?: 'MEDIA', (int)($_POST['orden'] ?? 0),
            ];
            if (!$codigo || !$datos[1] || !$datos[4]) throw new InvalidArgumentException('Código, nombre y área son obligatorios.');
            if ($id) {
                $datos[] = $id;
                $pdo->prepare('UPDATE catalogo_servicios SET codigo=?,nombre=?,descripcion=?,categoria=?,area_responsable=?,nivel_aprobacion=?,requiere_monto=?,monto_escalamiento=?,sla_horas=?,crea_ticket=?,categoria_ticket=?,prioridad_ticket=?,orden=?,actualizado_en=CURRENT_TIMESTAMP WHERE id=?')->execute($datos);
            } else {
                $pdo->prepare('INSERT INTO catalogo_servicios(codigo,nombre,descripcion,categoria,area_responsable,nivel_aprobacion,requiere_monto,monto_escalamiento,sla_horas,crea_ticket,categoria_ticket,prioridad_ticket,orden) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($datos);
            }
            $msg = ['ok', 'Servicio guardado.'];
        } elseif ($accion === 'toggle') {
            $pdo->prepare('UPDATE catalogo_servicios SET activo=CASE activo WHEN 1 THEN 0 ELSE 1 END,actualizado_en=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$_POST['id']]);
            $msg = ['ok', 'Disponibilidad actualizada.'];
        }
    } catch (Throwable $e) {
        $msg = ['error', $e instanceof PDOException ? 'No se pudo guardar; verifica que el código no esté repetido.' : $e->getMessage()];
    }
}

$servicios = catalogo_servicios_listar($pdo, !tiene_rol(['SUPER_ADMIN','ADMIN']));
$categorias = array_values(array_unique(array_column(array_filter($servicios, fn($s) => (int)$s['activo']), 'categoria')));
$sedes = $pdo->query("SELECT id,nombre FROM sedes WHERE estado='ACTIVO' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$departamentos = $pdo->query("SELECT nombre FROM departamentos WHERE estado='ACTIVO' ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$servicioSeleccionado = (int)($_GET['servicio'] ?? 0);
$stmt = $pdo->prepare('SELECT s.*,c.nombre servicio_nombre FROM solicitudes_aprobacion s LEFT JOIN catalogo_servicios c ON c.id=s.catalogo_id WHERE (s.solicitante_documento IS NOT NULL AND s.solicitante_documento=?) OR (s.solicitante_documento IS NULL AND s.solicitante_nombre=?) ORDER BY s.id DESC LIMIT 12');
$stmt->execute([$usuario['documento'] ?? '', $usuario['nombre']]);
$mias = $stmt->fetchAll(PDO::FETCH_ASSOC);
layout_inicio('Catálogo de Servicios', 'Catálogo de Servicios', '../');
?>
<div class="page-kicker">AUTOSERVICIO · OPERACIÓN INTERÁREAS</div>
<h1><?= icon('inventory','icon-lg') ?> Catálogo de Servicios</h1>
<p class="subtitle">Un punto único para pedir accesos, equipos, compras, mantenimientos y aprobaciones con responsable y SLA definidos.</p>
<?php if ($msg): ?><div class="msg-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="service-catalog-grid">
<?php foreach ($servicios as $servicio): if (!(int)$servicio['activo'] && !tiene_rol(['SUPER_ADMIN','ADMIN'])) continue; ?>
    <article class="service-card <?= !(int)$servicio['activo'] ? 'is-off' : '' ?>">
        <div class="service-card-meta"><span><?= e($servicio['categoria']) ?></span><span><?= (int)$servicio['sla_horas'] ?> h</span></div>
        <h3><?= e($servicio['nombre']) ?></h3><p><?= e($servicio['descripcion']) ?></p>
        <div class="service-card-foot"><small><?= e($servicio['area_responsable']) ?></small><?php if ((int)$servicio['activo']): ?><a class="btn" href="?servicio=<?= (int)$servicio['id'] ?>#solicitar">Solicitar</a><?php else: ?><span class="badge badge-otro">INACTIVO</span><?php endif; ?></div>
    </article>
<?php endforeach; ?>
</div>

<div class="panel" id="solicitar"><h3><?= icon('send') ?> Crear solicitud</h3>
<form method="post"><input type="hidden" name="accion" value="solicitar"><div class="grid-form">
    <div><label>Servicio</label><select name="catalogo_id" required><option value="">Selecciona</option><?php foreach ($servicios as $s): if (!(int)$s['activo']) continue; ?><option value="<?= (int)$s['id'] ?>" <?= $servicioSeleccionado===(int)$s['id']?'selected':'' ?>><?= e($s['nombre']) ?> · <?= e($s['area_responsable']) ?></option><?php endforeach; ?></select></div>
    <div><label>Sede / tienda</label><select name="sede_id"><option value="">No aplica</option><?php foreach ($sedes as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($usuario['sede_id']??0)===(int)$s['id']?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?></select></div>
    <div><label>Monto estimado</label><input type="number" name="monto" min="0" step="1000" placeholder="Solo si aplica"></div>
    <div><label>Prioridad</label><select name="prioridad"><option>BAJA</option><option selected>NORMAL</option><option>ALTA</option><option>URGENTE</option></select></div>
</div><label>Necesidad y resultado esperado</label><textarea name="descripcion" rows="4" required placeholder="Describe qué necesitas, para cuándo y qué impacto tiene en la operación."></textarea><button type="submit" style="margin-top:12px"><?= icon('send') ?> Enviar a aprobación</button></form></div>

<div class="panel"><h3>Mis solicitudes</h3><table><thead><tr><th>Código</th><th>Servicio</th><th>Área</th><th>Estado</th><th>SLA</th></tr></thead><tbody><?php foreach ($mias as $s): ?><tr onclick="location.href='solicitud_detalle.php?id=<?= (int)$s['id'] ?>'" style="cursor:pointer"><td><strong><?= e($s['codigo'] ?: '#'.$s['id']) ?></strong></td><td><?= e($s['servicio_nombre'] ?: $s['tipo']) ?></td><td><?= e($s['area_responsable']) ?></td><td><span class="badge <?= $s['estado']==='APROBADA'?'badge-activo':($s['estado']==='RECHAZADA'?'badge-err':'badge-warn') ?>"><?= e($s['estado']) ?></span></td><td><?= e(solicitud_sla($s)) ?></td></tr><?php endforeach; ?><?php if (!$mias): ?><tr><td colspan="5" class="small">Todavía no has creado solicitudes.</td></tr><?php endif; ?></tbody></table></div>

<?php if (tiene_rol(['SUPER_ADMIN','ADMIN'])): ?><details class="panel"><summary><strong>Administrar catálogo</strong></summary><form method="post" style="margin-top:18px"><input type="hidden" name="accion" value="guardar_servicio"><div class="grid-form"><div><label>Código</label><input name="codigo" required placeholder="SERVICIO-01"></div><div><label>Nombre</label><input name="nombre" required></div><div><label>Categoría</label><input name="categoria" required></div><div><label>Área responsable</label><select name="area_responsable" required><?php foreach ($departamentos as $d): ?><option><?= e($d) ?></option><?php endforeach; ?></select></div><div><label>Nivel inicial</label><select name="nivel_aprobacion"><option>DIRECTOR</option><option>GERENCIA</option></select></div><div><label>SLA horas</label><input type="number" name="sla_horas" value="24"></div><div><label>Monto para escalar a Gerencia</label><input type="number" name="monto_escalamiento" min="0"></div><div><label>Orden</label><input type="number" name="orden" value="100"></div><div><label><input type="checkbox" name="requiere_monto"> Requiere monto</label></div><div><label><input type="checkbox" name="crea_ticket"> Crear ticket al aprobar</label></div><div><label>Categoría del ticket</label><input name="categoria_ticket"></div><div><label>Prioridad del ticket</label><select name="prioridad_ticket"><option>MEDIA</option><option>ALTA</option><option>URGENTE</option></select></div></div><label>Descripción</label><textarea name="descripcion" rows="2"></textarea><button>Agregar servicio</button></form>
<table style="margin-top:20px"><thead><tr><th>Código</th><th>Servicio</th><th>Área</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach ($servicios as $s): ?><tr><td><?= e($s['codigo']) ?></td><td><?= e($s['nombre']) ?></td><td><?= e($s['area_responsable']) ?></td><td><?= (int)$s['activo']?'ACTIVO':'INACTIVO' ?></td><td><form method="post"><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="link-btn"><?= (int)$s['activo']?'Desactivar':'Activar' ?></button></form></td></tr><?php endforeach; ?></tbody></table></details><?php endif; ?>
<?php layout_fin(); ?>
