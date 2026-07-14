<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'RRHH', 'GERENCIA', 'CEO'])) {
    layout_inicio('Constructor de Formularios', 'Constructor de Formularios', '../');
    echo '<div class="msg-error">No tienes permiso para crear formularios.</div>';
    layout_fin();
    exit;
}

$u = usuario_actual();
$tiposCampo = ['texto' => 'Texto corto', 'textarea' => 'Texto largo', 'numero' => 'Número', 'fecha' => 'Fecha', 'select' => 'Lista de opciones', 'checkbox' => 'Sí/No'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $titulo = limpio($_POST['titulo'] ?? null);
        if ($titulo) {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO formularios (titulo, descripcion, area, token_publico, creado_por) VALUES (?,?,?,?,?)")
                ->execute([$titulo, limpio($_POST['descripcion'] ?? null), limpio($_POST['area'] ?? null), $token, $u['nombre'] ?? 'Sistema']);
            $msg = ['ok', 'Formulario creado. Ahora agrégale campos.'];
            $formularioIdNuevo = (int) $pdo->lastInsertId();
        } else {
            $msg = ['error', 'El título es obligatorio.'];
        }
    }

    if ($accion === 'agregar_campo') {
        $formularioId = (int) ($_POST['formulario_id'] ?? 0);
        $etiqueta = limpio($_POST['etiqueta'] ?? null);
        if ($formularioId && $etiqueta) {
            $orden = (int) $pdo->query("SELECT COALESCE(MAX(orden),0)+1 FROM formularios_campos WHERE formulario_id = {$formularioId}")->fetchColumn();
            $pdo->prepare("INSERT INTO formularios_campos (formulario_id, etiqueta, tipo, opciones, requerido, orden) VALUES (?,?,?,?,?,?)")
                ->execute([$formularioId, $etiqueta, limpio($_POST['tipo'] ?? null) ?: 'texto', limpio($_POST['opciones'] ?? null), isset($_POST['requerido']) ? 1 : 0, $orden]);
            $msg = ['ok', 'Campo agregado.'];
        }
    }

    if ($accion === 'eliminar_campo') {
        $pdo->prepare("DELETE FROM formularios_campos WHERE id = ?")->execute([(int) ($_POST['campo_id'] ?? 0)]);
        $msg = ['ok', 'Campo eliminado.'];
    }

    if ($accion === 'activar_desactivar') {
        $formularioId = (int) ($_POST['formulario_id'] ?? 0);
        $pdo->prepare("UPDATE formularios SET activo = 1 - activo WHERE id = ?")->execute([$formularioId]);
        $msg = ['ok', 'Estado del formulario actualizado.'];
    }

    if ($accion === 'eliminar_formulario') {
        $pdo->prepare("DELETE FROM formularios WHERE id = ?")->execute([(int) ($_POST['formulario_id'] ?? 0)]);
        $msg = ['ok', 'Formulario eliminado.'];
    }
}

$verId = (int) ($_GET['ver'] ?? ($formularioIdNuevo ?? 0));

$formularios = $pdo->query("SELECT f.*, (SELECT COUNT(*) FROM formularios_respuestas r WHERE r.formulario_id = f.id) AS respuestas
    FROM formularios f ORDER BY f.creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);

$formularioActivo = null;
$camposActivo = [];
$respuestasActivo = [];
if ($verId) {
    $stmt = $pdo->prepare("SELECT * FROM formularios WHERE id = ?");
    $stmt->execute([$verId]);
    $formularioActivo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($formularioActivo) {
        $stmtC = $pdo->prepare("SELECT * FROM formularios_campos WHERE formulario_id = ? ORDER BY orden ASC");
        $stmtC->execute([$verId]);
        $camposActivo = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        $stmtR = $pdo->prepare("SELECT * FROM formularios_respuestas WHERE formulario_id = ? ORDER BY creado_en DESC LIMIT 100");
        $stmtR->execute([$verId]);
        $respuestasActivo = $stmtR->fetchAll(PDO::FETCH_ASSOC);
    }
}

layout_inicio('Constructor de Formularios', 'Constructor de Formularios', '../');
?>
<h1><?= icon('file','icon-lg') ?> Constructor de Formularios</h1>
<p class="subtitle">Crea formularios propios (encuestas, solicitudes, inscripciones...) sin tocar código, comparte el link público y revisa las respuestas aquí mismo.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Nuevo formulario</h3>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="crear">
        <div><label>Título *</label><input type="text" name="titulo" required></div>
        <div><label>Área (opcional)</label><input type="text" name="area" placeholder="RRHH, TI, Comercial..."></div>
        <div style="grid-column:1/-1;"><label>Descripción</label><input type="text" name="descripcion"></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Crear formulario</button></div>
    </form>
</div>

<div class="panel">
    <h3>Tus formularios</h3>
    <table>
        <tr><th>Título</th><th>Área</th><th>Estado</th><th>Respuestas</th><th>Creado</th><th></th></tr>
        <?php foreach ($formularios as $f): ?>
        <tr>
            <td><?= e($f['titulo']) ?></td>
            <td><?= e($f['area'] ?: '—') ?></td>
            <td><span class="badge <?= $f['activo'] ? 'badge-activo' : '' ?>"><?= $f['activo'] ? 'ACTIVO' : 'PAUSADO' ?></span></td>
            <td><?= (int) $f['respuestas'] ?></td>
            <td class="small"><?= e($f['creado_en']) ?></td>
            <td>
                <a href="?ver=<?= (int) $f['id'] ?>">Administrar</a> ·
                <form method="post" class="inline"><input type="hidden" name="accion" value="activar_desactivar"><input type="hidden" name="formulario_id" value="<?= (int) $f['id'] ?>"><button type="submit" style="padding:2px 6px;font-size:11px;"><?= $f['activo'] ? 'Pausar' : 'Activar' ?></button></form>
                <form method="post" class="inline" onsubmit="return confirm('¿Eliminar este formulario y sus respuestas?');"><input type="hidden" name="accion" value="eliminar_formulario"><input type="hidden" name="formulario_id" value="<?= (int) $f['id'] ?>"><button type="submit" style="padding:2px 6px;font-size:11px;">Eliminar</button></form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$formularios): ?><tr><td colspan="6" class="small">Aún no has creado formularios.</td></tr><?php endif; ?>
    </table>
</div>

<?php if ($formularioActivo): ?>
<div class="panel">
    <h3><?= icon('link') ?> <?= e($formularioActivo['titulo']) ?> — link público</h3>
    <p class="small">Comparte este link con quien deba llenar el formulario (no necesita cuenta en NAVISSI):</p>
    <p><code id="link-publico"><?= e((($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/formulario_ver.php?t=' . $formularioActivo['token_publico']) ?></code></p>

    <h4 style="margin-top:20px;">Agregar campo</h4>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="agregar_campo">
        <input type="hidden" name="formulario_id" value="<?= (int) $formularioActivo['id'] ?>">
        <div><label>Etiqueta *</label><input type="text" name="etiqueta" required></div>
        <div><label>Tipo</label>
            <select name="tipo">
                <?php foreach ($tiposCampo as $valor => $texto): ?><option value="<?= e($valor) ?>"><?= e($texto) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label>Opciones (si es lista, separadas por coma)</label><input type="text" name="opciones" placeholder="Opción 1, Opción 2..."></div>
        <div><label><input type="checkbox" name="requerido"> Obligatorio</label></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('plus') ?> Agregar campo</button></div>
    </form>

    <h4 style="margin-top:20px;">Campos (<?= count($camposActivo) ?>)</h4>
    <table>
        <tr><th>#</th><th>Etiqueta</th><th>Tipo</th><th>Obligatorio</th><th></th></tr>
        <?php foreach ($camposActivo as $c): ?>
        <tr>
            <td><?= (int) $c['orden'] ?></td>
            <td><?= e($c['etiqueta']) ?></td>
            <td><?= e($tiposCampo[$c['tipo']] ?? $c['tipo']) ?></td>
            <td><?= $c['requerido'] ? 'Sí' : 'No' ?></td>
            <td><form method="post" class="inline" onsubmit="return confirm('¿Eliminar este campo?');"><input type="hidden" name="accion" value="eliminar_campo"><input type="hidden" name="campo_id" value="<?= (int) $c['id'] ?>"><button type="submit" style="padding:2px 6px;font-size:11px;">Eliminar</button></form></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$camposActivo): ?><tr><td colspan="5" class="small">Sin campos todavía — agrega al menos uno para que el formulario sea útil.</td></tr><?php endif; ?>
    </table>

    <h4 style="margin-top:20px;">Respuestas (<?= count($respuestasActivo) ?>)</h4>
    <table>
        <tr><th>Enviado por</th><th>Fecha</th><th>Respuestas</th></tr>
        <?php foreach ($respuestasActivo as $r): $datos = json_decode($r['respuestas_json'], true) ?: []; ?>
        <tr>
            <td><?= e($r['enviado_por'] ?: 'Anónimo') ?></td>
            <td class="small"><?= e($r['creado_en']) ?></td>
            <td class="small">
                <?php foreach ($datos as $etiqueta => $valor): ?>
                    <strong><?= e($etiqueta) ?>:</strong> <?= e(is_array($valor) ? implode(', ', $valor) : $valor) ?><br>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$respuestasActivo): ?><tr><td colspan="3" class="small">Sin respuestas todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
