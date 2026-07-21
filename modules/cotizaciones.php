<?php
/**
 * Cotizaciones a Proveedores: cualquier área publica una solicitud de
 * cotización con un link público (sin login) para que proveedores externos
 * suban su propuesta + documentos. El área responsable revisa, aprueba o
 * rechaza cada respuesta, y queda un histórico tipo bitácora (gestión
 * documental y avances) por cada solicitud.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;
$miArea = alcance_area();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_solicitud') {
    $titulo = limpio($_POST['titulo'] ?? null);
    $area = limpio($_POST['area_responsable'] ?? null) ?: $miArea;
    if (!$titulo || !$area) {
        $msg = ['error', 'El título y el área responsable son obligatorios.'];
    } else {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("INSERT INTO cotizaciones_solicitudes (titulo, area_responsable, descripcion, fecha_limite, token_publico, creado_por, responsable_asignado)
            VALUES (?,?,?,?,?,?,?)")
            ->execute([$titulo, $area, limpio($_POST['descripcion'] ?? null), limpio($_POST['fecha_limite'] ?? null) ?: null, $token, $u['nombre'] ?? 'Sistema', $u['nombre'] ?? null]);
        $nuevaId = (int) $pdo->lastInsertId();
        $msg = ['ok', 'Solicitud de cotización creada. Comparte el link público con los proveedores.'];
        $verIdNuevo = $nuevaId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado_solicitud') {
    $nuevo = in_array($_POST['nuevo_estado'] ?? '', ['ABIERTA', 'CERRADA'], true) ? $_POST['nuevo_estado'] : null;
    if ($nuevo) {
        $pdo->prepare("UPDATE cotizaciones_solicitudes SET estado = ? WHERE id = ?")->execute([$nuevo, (int) $_POST['solicitud_id']]);
        $msg = ['ok', 'Estado de la solicitud actualizado.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado_respuesta') {
    $nuevo = in_array($_POST['nuevo_estado'] ?? '', ['EN_REVISION', 'APROBADA', 'RECHAZADA'], true) ? $_POST['nuevo_estado'] : null;
    if ($nuevo) {
        $pdo->prepare("UPDATE cotizaciones_respuestas SET estado = ? WHERE id = ?")->execute([$nuevo, (int) $_POST['respuesta_id']]);
        $pdo->prepare("INSERT INTO cotizaciones_comentarios (solicitud_id, respuesta_id, autor, comentario) VALUES (?,?,?,?)")
            ->execute([(int) $_POST['solicitud_id'], (int) $_POST['respuesta_id'], $u['nombre'] ?? 'Sistema', "Cambió el estado de la cotización a {$nuevo}."]);
        $msg = ['ok', 'Estado de la cotización actualizado.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar_comentario') {
    $comentario = limpio_html($_POST['comentario'] ?? null);
    if ($comentario) {
        $pdo->prepare("INSERT INTO cotizaciones_comentarios (solicitud_id, respuesta_id, autor, comentario) VALUES (?,?,?,?)")
            ->execute([(int) $_POST['solicitud_id'], $_POST['respuesta_id'] ?: null, $u['nombre'] ?? 'Sistema', $comentario]);
        $msg = ['ok', 'Comentario agregado.'];
    }
}

// Alcance: un Director/Coordinador con área asignada solo ve las solicitudes de su propia área.
$sqlLista = "SELECT s.*, (SELECT COUNT(*) FROM cotizaciones_respuestas r WHERE r.solicitud_id = s.id) AS n_respuestas FROM cotizaciones_solicitudes s";
$paramsLista = [];
if ($miArea !== null) { $sqlLista .= " WHERE s.area_responsable = ?"; $paramsLista[] = $miArea; }
$sqlLista .= " ORDER BY s.creado_en DESC";
$stmt = $pdo->prepare($sqlLista);
$stmt->execute($paramsLista);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$verId = (int) ($_GET['ver'] ?? ($verIdNuevo ?? 0));
$solicitudActiva = null; $respuestas = []; $comentarios = [];
if ($verId) {
    $stmt = $pdo->prepare("SELECT * FROM cotizaciones_solicitudes WHERE id = ?");
    $stmt->execute([$verId]);
    $solicitudActiva = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($solicitudActiva && ($miArea === null || $solicitudActiva['area_responsable'] === $miArea)) {
        $stmtR = $pdo->prepare("SELECT r.*, (SELECT COUNT(*) FROM cotizaciones_adjuntos a WHERE a.respuesta_id = r.id) AS n_adjuntos FROM cotizaciones_respuestas r WHERE r.solicitud_id = ? ORDER BY r.creado_en DESC");
        $stmtR->execute([$verId]);
        $respuestas = $stmtR->fetchAll(PDO::FETCH_ASSOC);
        $stmtC = $pdo->prepare("SELECT * FROM cotizaciones_comentarios WHERE solicitud_id = ? ORDER BY creado_en ASC");
        $stmtC->execute([$verId]);
        $comentarios = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $solicitudActiva = null;
    }
}

$linkPublicoBase = rtrim(navissi_url_publica(), '/') . '/cotizacion_publica.php?t=';

layout_inicio('Cotizaciones a Proveedores', 'Cotizaciones a Proveedores', '../');
?>
<h1><?= icon('dollar','icon-lg') ?> Cotizaciones a Proveedores</h1>
<p class="subtitle">Publica una solicitud de cotización, comparte el link público con los proveedores (sin que necesiten cuenta), y revisa sus propuestas con todo el flujo y gestión documental en un solo lugar.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if (!$verId || !$solicitudActiva): ?>
<div class="panel">
    <h3>Nueva solicitud de cotización</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="crear_solicitud">
        <div style="grid-column:span 2;"><label>Título *</label><input type="text" name="titulo" required placeholder="Ej: Cotización de herramientas tecnológicas TI"></div>
        <div><label>Área responsable *</label>
            <?php if ($miArea): ?>
            <input type="text" value="<?= e($miArea) ?>" disabled>
            <input type="hidden" name="area_responsable" value="<?= e($miArea) ?>">
            <?php else: ?>
            <input type="text" name="area_responsable" required placeholder="Ej: Direccion de Tecnologia">
            <?php endif; ?>
        </div>
        <div><label>Fecha límite para cotizar</label><input type="date" name="fecha_limite"></div>
        <div style="grid-column:1/-1;"><label>Descripción / qué se necesita</label><textarea name="descripcion" class="wysiwyg" rows="3" style="width:100%;"></textarea></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Crear y generar link público</button></div>
    </form>
</div>

<div class="panel">
    <h3>Solicitudes de cotización <?= $miArea ? '— ' . e($miArea) : '(todas las áreas)' ?></h3>
    <table>
        <tr><th>Título</th><th>Área</th><th>Fecha límite</th><th>Cotizaciones recibidas</th><th>Estado</th><th></th></tr>
        <?php foreach ($solicitudes as $s): ?>
        <tr>
            <td><?= e($s['titulo']) ?></td>
            <td><?= e($s['area_responsable']) ?></td>
            <td><?= e($s['fecha_limite']) ?: '—' ?></td>
            <td><?= (int) $s['n_respuestas'] ?></td>
            <td><span class="badge <?= $s['estado']==='ABIERTA'?'badge-activo':'badge-otro' ?>"><?= e($s['estado']) ?></span></td>
            <td><a href="?ver=<?= (int) $s['id'] ?>">Ver / gestionar</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$solicitudes): ?><tr><td colspan="6" class="small">Aún no has creado ninguna solicitud de cotización.</td></tr><?php endif; ?>
    </table>
</div>
<?php else: ?>

<div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:start;gap:14px;flex-wrap:wrap;">
        <div>
            <h3><?= e($solicitudActiva['titulo']) ?></h3>
            <p class="small">Área: <strong><?= e($solicitudActiva['area_responsable']) ?></strong>
                <?= $solicitudActiva['fecha_limite'] ? ' · Fecha límite: ' . e($solicitudActiva['fecha_limite']) : '' ?>
                · Responsable: <?= e($solicitudActiva['responsable_asignado']) ?: '—' ?></p>
            <?php if ($solicitudActiva['descripcion']): ?><p><?= $solicitudActiva['descripcion'] ?></p><?php endif; ?>
        </div>
        <span class="badge <?= $solicitudActiva['estado']==='ABIERTA'?'badge-activo':'badge-otro' ?>"><?= e($solicitudActiva['estado']) ?></span>
    </div>
    <p class="small" style="margin-top:10px;"><strong>Link público para proveedores:</strong><br>
        <code id="link-cotizacion"><?= e($linkPublicoBase . $solicitudActiva['token_publico']) ?></code>
    </p>
    <form method="post" class="toolbar" style="margin-top:10px;">
        <input type="hidden" name="accion" value="cambiar_estado_solicitud">
        <input type="hidden" name="solicitud_id" value="<?= (int) $solicitudActiva['id'] ?>">
        <?php if ($solicitudActiva['estado'] === 'ABIERTA'): ?>
        <input type="hidden" name="nuevo_estado" value="CERRADA">
        <button type="submit" class="btn-danger">Cerrar solicitud (dejar de recibir cotizaciones)</button>
        <?php else: ?>
        <input type="hidden" name="nuevo_estado" value="ABIERTA">
        <button type="submit">Reabrir solicitud</button>
        <?php endif; ?>
    </form>
    <p style="margin-top:10px;"><a href="cotizaciones.php">← Volver al listado</a></p>
</div>

<div class="panel">
    <h3>Cotizaciones recibidas (<?= count($respuestas) ?>)</h3>
    <?php foreach ($respuestas as $r):
        $stmtAdj = $pdo->prepare("SELECT * FROM cotizaciones_adjuntos WHERE respuesta_id = ?");
        $stmtAdj->execute([$r['id']]);
        $adjuntosR = $stmtAdj->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="ficha" style="margin-bottom:12px;padding:14px 16px;border:1px solid var(--line);border-radius:8px;">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <div>
                <strong><?= e($r['proveedor_nombre']) ?></strong> <?= $r['proveedor_nit'] ? '· NIT ' . e($r['proveedor_nit']) : '' ?><br>
                <span class="small"><?= e($r['proveedor_contacto']) ?: '' ?> <?= $r['proveedor_email'] ? '· ' . e($r['proveedor_email']) : '' ?> <?= $r['proveedor_telefono'] ? '· ' . e($r['proveedor_telefono']) : '' ?></span>
            </div>
            <span class="badge <?= $r['estado']==='APROBADA'?'badge-activo':($r['estado']==='RECHAZADA'?'badge-err':'badge-otro') ?>"><?= e($r['estado']) ?></span>
        </div>
        <p style="margin:8px 0;">
            <?= $r['valor_cotizado'] ? '<strong>$' . number_format((float) $r['valor_cotizado'], 0, ',', '.') . ' COP</strong>' : 'Sin valor indicado' ?>
            <?= $r['validez_dias'] ? ' · Válida ' . (int) $r['validez_dias'] . ' días' : '' ?>
        </p>
        <?php if ($r['observaciones']): ?><p class="small"><?= nl2br(e($r['observaciones'])) ?></p><?php endif; ?>
        <?php if ($adjuntosR): ?>
        <p class="small"><strong>Documentos:</strong>
            <?php foreach ($adjuntosR as $a): ?>
            <a href="descargar_adjunto_cotizacion.php?id=<?= (int) $a['id'] ?>" target="_blank"><?= e($a['nombre_archivo']) ?></a><?php if ($a !== end($adjuntosR)): ?>, <?php endif; ?>
            <?php endforeach; ?>
        </p>
        <?php endif; ?>
        <p class="small">Recibida: <?= e($r['creado_en']) ?></p>
        <?php if (!in_array($r['estado'], ['APROBADA','RECHAZADA'], true)): ?>
        <form method="post" class="toolbar" style="margin-top:6px;margin-bottom:0;">
            <input type="hidden" name="accion" value="cambiar_estado_respuesta">
            <input type="hidden" name="solicitud_id" value="<?= (int) $solicitudActiva['id'] ?>">
            <input type="hidden" name="respuesta_id" value="<?= (int) $r['id'] ?>">
            <?php if ($r['estado'] === 'RECIBIDA'): ?>
            <button type="submit" name="nuevo_estado" value="EN_REVISION">Marcar en revisión</button>
            <?php endif; ?>
            <button type="submit" name="nuevo_estado" value="APROBADA">Aprobar</button>
            <button type="submit" name="nuevo_estado" value="RECHAZADA" class="btn-danger">Rechazar</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$respuestas): ?><p class="small">Aún no ha llegado ninguna cotización. Comparte el link público de arriba con los proveedores.</p><?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('log') ?> Gestión documental y avances (bitácora)</h3>
    <div style="max-height:320px;overflow-y:auto;margin-bottom:14px;">
        <?php foreach ($comentarios as $c): ?>
        <div style="padding:8px 0;border-bottom:1px solid var(--line);">
            <strong><?= e($c['autor']) ?></strong> <span class="small"><?= e($c['creado_en']) ?></span><br>
            <?= $c['comentario'] ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$comentarios): ?><p class="small">Sin movimientos todavía.</p><?php endif; ?>
    </div>
    <form method="post">
        <input type="hidden" name="accion" value="agregar_comentario">
        <input type="hidden" name="solicitud_id" value="<?= (int) $solicitudActiva['id'] ?>">
        <textarea name="comentario" class="wysiwyg" rows="2" style="width:100%;" placeholder="Registra un avance, decisión o nota interna..."></textarea>
        <button type="submit" style="margin-top:8px;"><?= icon('send') ?> Agregar</button>
    </form>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
