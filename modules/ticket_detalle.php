<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'comentar') {
        $comentario = limpio($_POST['comentario'] ?? null);
        if ($comentario) {
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario) VALUES (?,?,?)")
                ->execute([$id, limpio($_POST['autor'] ?? null) ?: 'TI', $comentario]);
            $pdo->prepare("UPDATE tickets SET actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            if (!empty($_POST['respuesta_rapida_id'])) {
                $pdo->prepare("UPDATE respuestas_rapidas SET usos = usos + 1 WHERE id = ?")->execute([(int) $_POST['respuesta_rapida_id']]);
            }
            $msg = ['ok', 'Comentario agregado.'];
        }
    }

    if ($accion === 'cambiar_estado') {
        $nuevoEstado = $_POST['estado'] ?? '';
        $cierre = $nuevoEstado === 'CERRADO' ? ", cerrado_en = CURRENT_TIMESTAMP" : "";
        $pdo->prepare("UPDATE tickets SET estado = ?, actualizado_en = CURRENT_TIMESTAMP{$cierre} WHERE id = ?")
            ->execute([$nuevoEstado, $id]);
        $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
            ->execute([$id, 'Sistema', "Estado cambiado a {$nuevoEstado}.", 'SISTEMA']);
        hoja_vida_registrar($pdo, 'TICKET', (string) $id, 'CAMBIO_ESTADO', $nuevoEstado, usuario_actual()['nombre'] ?? 'Sistema', $id);
        $msg = ['ok', "Estado actualizado a {$nuevoEstado}."];
    }

    if ($accion === 'asignar') {
        $asignado = limpio($_POST['asignado_a'] ?? null);
        $pdo->prepare("UPDATE tickets SET asignado_a = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$asignado, $id]);
        $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
            ->execute([$id, 'Sistema', "Asignado a {$asignado}.", 'SISTEMA']);
        $msg = ['ok', 'Ticket asignado.'];
    }

    if ($accion === 'guardar_campos') {
        foreach ($_POST['campo'] ?? [] as $campoId => $valor) {
            $pdo->prepare("INSERT INTO campos_personalizados_valor (campo_id, entidad_id, valor) VALUES (?,?,?)
                ON CONFLICT(campo_id, entidad_id) DO UPDATE SET valor = excluded.valor")
                ->execute([(int) $campoId, $id, limpio($valor)]);
        }
        $msg = ['ok', 'Campos personalizados guardados.'];
    }
}

$stmt = $pdo->prepare("SELECT t.*, s.nombre AS sede_nombre FROM tickets t LEFT JOIN sedes s ON t.sede_id = s.id WHERE t.id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    layout_inicio('Ticket no encontrado', 'Mesa de Ayuda', '../');
    echo '<div class="msg-error">' . icon('x') . ' Ese ticket no existe.</div><a class="btn" href="mesa_ayuda.php">Volver</a>';
    layout_fin();
    exit;
}

$camposDef = $pdo->query("SELECT * FROM campos_personalizados_def WHERE entidad = 'tickets' ORDER BY nombre_campo")->fetchAll(PDO::FETCH_ASSOC);
$camposValores = [];
if ($camposDef) {
    $stmt = $pdo->prepare("SELECT campo_id, valor FROM campos_personalizados_valor WHERE entidad_id = ?");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) $camposValores[$v['campo_id']] = $v['valor'];
}

$stmt = $pdo->prepare("SELECT * FROM tickets_comentarios WHERE ticket_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$respuestasRapidas = $pdo->query("SELECT * FROM respuestas_rapidas ORDER BY usos DESC, titulo")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio("Ticket #{$id}", 'Mesa de Ayuda', '../');
?>
<p class="small"><a href="mesa_ayuda.php"><?= icon('arrow-right', 'icon') ?> Volver a Mesa de Ayuda</a></p>
<h1><?= icon('ticket','icon-lg') ?> #<?= (int)$ticket['id'] ?> — <?= e($ticket['titulo']) ?>
    <span class="badge <?= $ticket['estado']==='CERRADO'?'badge-otro':($ticket['estado']==='RESUELTO POR IA'?'badge-activo':'badge-err') ?>"><?= e($ticket['estado']) ?></span>
</h1>
<p class="subtitle"><?= e($ticket['categoria']) ?> · Prioridad <?= e($ticket['prioridad']) ?> · Sede: <?= e($ticket['sede_nombre']) ?: '—' ?> · Origen: <?= e($ticket['origen'] ?? 'MANUAL') ?></p>

<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= icon('check') ?> <?= e($msg[1]) ?></div><?php endif; ?>

<div class="helpdesk-layout">
    <div>
        <div class="panel">
            <h3><?= icon('sliders') ?> Datos del ticket</h3>
            <table class="deftable">
                <tr><th>Solicitante</th><td><?= e($ticket['solicitante']) ?: '—' ?></td></tr>
                <tr><th>Contacto</th><td><?= e($ticket['solicitante_contacto']) ?: '—' ?></td></tr>
                <tr><th>Creado</th><td class="small"><?= e($ticket['creado_en']) ?></td></tr>
                <tr><th>Última actividad</th><td class="small"><?= e($ticket['actualizado_en']) ?></td></tr>
                <tr><th>SLA límite</th><td class="small"><?= e($ticket['sla_limite']) ?: '—' ?></td></tr>
            </table>
        </div>
        <?php if ($camposDef): ?>
        <div class="panel">
            <h3><?= icon('ticket') ?> Campos personalizados</h3>
            <form method="post">
                <input type="hidden" name="accion" value="guardar_campos">
                <?php foreach ($camposDef as $cd): $valorActual = $camposValores[$cd['id']] ?? ''; ?>
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
                <button type="submit" style="width:100%;"><?= icon('check') ?> Guardar</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="panel">
            <h3><?= icon('users') ?> Acciones</h3>
            <form method="post" style="margin-bottom:14px;">
                <input type="hidden" name="accion" value="cambiar_estado">
                <label class="small">Estado</label>
                <select name="estado" onchange="this.form.submit()" style="width:100%;">
                    <?php foreach (['ABIERTO','EN PROCESO','RESUELTO POR IA','CERRADO'] as $es): ?>
                    <option <?= $ticket['estado']===$es?'selected':'' ?>><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form method="post">
                <input type="hidden" name="accion" value="asignar">
                <label class="small">Asignar a</label>
                <input type="text" name="asignado_a" value="<?= e($ticket['asignado_a']) ?>" placeholder="Nombre del técnico" style="width:100%;margin-bottom:8px;">
                <button type="submit" style="width:100%;justify-content:center;"><?= icon('check') ?> Asignar</button>
            </form>
        </div>
    </div>

    <div class="panel" style="display:flex;flex-direction:column;">
        <h3><?= icon('chat') ?> Conversación (<?= count($comentarios) ?>)</h3>
        <div style="max-height:460px;overflow-y:auto;padding:6px 2px;">
            <?php foreach ($comentarios as $c):
                $tipo = $c['tipo'] ?? 'COMENTARIO';
                $claseBubble = $tipo === 'SISTEMA' ? 'system' : ($tipo === 'IA' ? 'ia' : ($c['autor'] === $ticket['solicitante'] ? 'theirs' : 'mine'));
            ?>
            <div class="conv-bubble <?= $claseBubble ?>">
                <?php if ($tipo !== 'SISTEMA'): ?><div class="conv-meta"><?= $tipo === 'IA' ? icon('robot') . ' Agente IA' : e($c['autor']) ?></div><?php endif; ?>
                <?= nl2br(e($c['comentario'])) ?>
                <div class="small" style="margin-top:5px;opacity:.7;"><?= e($c['creado_en']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="post" style="margin-top:14px;border-top:1px solid var(--line);padding-top:14px;" id="form-respuesta">
            <input type="hidden" name="accion" value="comentar">
            <input type="hidden" name="respuesta_rapida_id" id="respuesta_rapida_id" value="">
            <div class="grid-form">
                <div><label>Tu nombre</label><input type="text" name="autor" placeholder="TI / técnico"></div>
                <?php if ($respuestasRapidas): ?>
                <div><label><?= icon('zap') ?> Respuesta rápida</label>
                    <select id="selector-respuesta-rapida">
                        <option value="">-- ninguna, escribir manual --</option>
                        <?php foreach ($respuestasRapidas as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" data-texto="<?= e($r['texto']) ?>"><?= e($r['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <textarea name="comentario" id="textarea-comentario" rows="3" style="width:100%;margin-bottom:10px;" placeholder="Escribe una actualización o respuesta..."></textarea>
            <button type="submit"><?= icon('send') ?> Responder</button>
        </form>
    </div>
</div>
<script>
var selRespuesta = document.getElementById('selector-respuesta-rapida');
if (selRespuesta) {
    selRespuesta.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        document.getElementById('textarea-comentario').value = opt.dataset.texto || '';
        document.getElementById('respuesta_rapida_id').value = this.value;
    });
}
</script>
<?php layout_fin(); ?>
