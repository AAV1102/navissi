<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/mailer.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$msg = null;
$dirAdjuntos = tickets_adjuntos_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'comentar') {
        $comentario = limpio_html($_POST['comentario'] ?? null);
        $visibleCliente = isset($_POST['visible_cliente']) ? 1 : 0;
        if ($comentario || !empty($_FILES['adjuntos']['tmp_name'][0])) {
            $autor = limpio($_POST['autor'] ?? null) ?: 'TI';
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, visible_cliente) VALUES (?,?,?,?)")
                ->execute([$id, $autor, $comentario ?: '(archivo adjunto)', $visibleCliente]);
            $comentarioId = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE tickets SET actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            if (!empty($_POST['respuesta_rapida_id'])) {
                $pdo->prepare("UPDATE respuestas_rapidas SET usos = usos + 1 WHERE id = ?")->execute([(int) $_POST['respuesta_rapida_id']]);
            }

            if (!empty($_FILES['adjuntos']['tmp_name'][0])) {
                foreach ($_FILES['adjuntos']['tmp_name'] as $i => $tmp) {
                    if (!$tmp) continue;
                    $original = basename($_FILES['adjuntos']['name'][$i]);
                    $tamano=(int)($_FILES['adjuntos']['size'][$i]??0);if($tamano<=0||$tamano>10*1024*1024)continue;$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'application/octet-stream';$permitidos=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','text/plain'=>'txt','text/csv'=>'csv','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx'];if(!isset($permitidos[$mime]))continue;$rutaGuardada=bin2hex(random_bytes(18)).'.'.$permitidos[$mime];
                    if (move_uploaded_file($tmp, $dirAdjuntos . '/' . $rutaGuardada)) {
                        $pdo->prepare("INSERT INTO tickets_adjuntos (ticket_id, comentario_id, nombre_archivo, ruta, tipo_mime, tamano, subido_por) VALUES (?,?,?,?,?,?,?)")
                            ->execute([$id, $comentarioId, $original, $rutaGuardada, $mime, $tamano, $autor]);
                    }
                }
            }

            $enviado = false;
            if ($visibleCliente) {
                $stmtT = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
                $stmtT->execute([$id]);
                $t = $stmtT->fetch(PDO::FETCH_ASSOC);
                if ($t && $t['solicitante_contacto'] && filter_var($t['solicitante_contacto'], FILTER_VALIDATE_EMAIL)) {
                    $html = plantilla_correo_html("Actualización de tu ticket #{$id}",
                        "<p>Hola " . e($t['solicitante']) . ",</p><div>" . $comentario . "</div><p class=\"small\">— {$autor}, Mesa de Ayuda NAVISSI</p>");
                    $enviado = enviar_correo($t['solicitante_contacto'], "Re: Ticket #{$id} — {$t['titulo']}", $html, $t['solicitante']);
                }
                $pdo->prepare("UPDATE tickets_comentarios SET enviado_correo = ? WHERE id = ?")->execute([$enviado ? 1 : 0, $comentarioId]);
            }

            hoja_vida_registrar($pdo, 'TICKET', (string) $id, $visibleCliente ? 'RESPUESTA_CLIENTE' : 'NOTA_INTERNA', $comentario, $autor, $id);
            $msg = $visibleCliente
                ? ['ok', $enviado ? 'Respuesta enviada al cliente por correo.' : 'Respuesta guardada, pero no se pudo enviar el correo (revisa la configuración SMTP/O365).']
                : ['ok', 'Nota interna agregada.'];
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
        if (!$asignado) {
            $msg = ['error', 'Escribe el nombre del técnico antes de asignar.'];
        } else {
            $pdo->prepare("UPDATE tickets SET asignado_a = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$asignado, $id]);
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
                ->execute([$id, 'Sistema', "Asignado a {$asignado}.", 'SISTEMA']);
            $msg = ['ok', 'Ticket asignado.'];

            $stmtT = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
            $stmtT->execute([$id]);
            $t = $stmtT->fetch(PDO::FETCH_ASSOC);
            if ($t && $t['solicitante_contacto'] && filter_var($t['solicitante_contacto'], FILTER_VALIDATE_EMAIL)) {
                $html = plantilla_correo_html("Tu ticket #{$id} fue asignado",
                    "<p>Hola " . e($t['solicitante']) . ",</p><p>Tu ticket <strong>#{$id}</strong> — \"" . e($t['titulo']) . "\" — fue asignado al técnico <strong>" . e($asignado) . "</strong>, quien se pondrá en contacto contigo.</p>");
                enviar_correo($t['solicitante_contacto'], "Ticket #{$id} asignado a {$asignado}", $html, $t['solicitante']);
            }
        }
    }

    if ($accion === 'vincular_equipo') {
        $serial=limpio($_POST['equipo_serial']??null);
        $q=$pdo->prepare("SELECT serial,sede_id FROM inventario WHERE lower(serial)=lower(?) OR lower(COALESCE(placa,''))=lower(?) LIMIT 1");$q->execute([$serial,$serial]);$eqV=$q->fetch(PDO::FETCH_ASSOC);
        if($eqV){$pdo->prepare("UPDATE tickets SET equipo_serial=?,sede_id=COALESCE(sede_id,?),actualizado_en=CURRENT_TIMESTAMP WHERE id=?")->execute([$eqV['serial'],$eqV['sede_id'],$id]);hoja_vida_registrar($pdo,'EQUIPO',$eqV['serial'],'TICKET_VINCULADO','Ticket #'.$id,usuario_actual()['nombre']??'TI',$id);$msg=['ok','Equipo vinculado. Ya están disponibles inventario, agente y acceso remoto.'];}
        else $msg=['error','No se encontró un equipo con ese serial o placa.'];
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

// Alcance personal: un EMPLEADO sin rol elevado solo puede abrir sus propios tickets por URL directa.
$personalTk = alcance_personal();
if ($personalTk !== null && $ticket['solicitante'] !== $personalTk['nombre'] && $ticket['solicitante_contacto'] !== $personalTk['email']) {
    layout_inicio('Sin acceso', 'Mesa de Ayuda', '../');
    echo '<div class="msg-error">' . icon('x') . ' Solo puedes ver los tickets que tú creaste.</div><a class="btn" href="mesa_ayuda.php">Volver</a>';
    layout_fin();
    exit;
}
// Un Director solo puede abrir tickets de gente de su propia área, por URL directa incluida.
if ($personalTk === null && alcance_area() !== null && $ticket['solicitante_area'] !== alcance_area()) {
    layout_inicio('Sin acceso', 'Mesa de Ayuda', '../');
    echo '<div class="msg-error">' . icon('x') . ' Ese ticket no pertenece a tu área.</div><a class="btn" href="mesa_ayuda.php">Volver</a>';
    layout_fin();
    exit;
}

$equipoRelacionado = null;
if (!empty($ticket['equipo_serial'])) {
    $stmtEq = $pdo->prepare("SELECT * FROM inventario WHERE serial = ?");
    $stmtEq->execute([$ticket['equipo_serial']]);
    $equipoRelacionado = $stmtEq->fetch(PDO::FETCH_ASSOC);
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

$stmtAdj = $pdo->prepare("SELECT * FROM tickets_adjuntos WHERE ticket_id = ? ORDER BY id ASC");
$stmtAdj->execute([$id]);
$adjuntosPorComentario = [];
foreach ($stmtAdj->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $adjuntosPorComentario[$a['comentario_id']][] = $a;
}

layout_inicio("Ticket #{$id}", 'Mesa de Ayuda', '../');
?>
<p class="small"><a href="mesa_ayuda.php"><?= icon('arrow-right', 'icon') ?> Volver a Mesa de Ayuda</a></p>
<h1><?= icon('ticket','icon-lg') ?> #<?= (int)$ticket['id'] ?> — <?= e($ticket['titulo']) ?>
    <span class="badge <?= $ticket['estado']==='CERRADO'?'badge-otro':($ticket['estado']==='RESUELTO POR IA'?'badge-activo':'badge-err') ?>"><?= e($ticket['estado']) ?></span>
</h1>
<p class="subtitle"><?= e($ticket['categoria']) ?><?= !empty($ticket['departamento']) ? ' · Departamento: '.e($ticket['departamento']) : '' ?> · Prioridad <?= e($ticket['prioridad']) ?> · Sede: <?= e($ticket['sede_nombre']) ?: '—' ?> · Origen: <?= e($ticket['origen'] ?? 'MANUAL') ?></p>

<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= icon('check') ?> <?= e($msg[1]) ?></div><?php endif; ?>

<div class="helpdesk-layout">
    <div>
        <div class="panel">
            <h3><?= icon('sliders') ?> Datos del ticket</h3>
            <table class="deftable">
                <tr><th>Solicitante</th><td><?= e($ticket['solicitante']) ?: '—' ?></td></tr>
                <tr><th>Contacto</th><td><?= e($ticket['solicitante_contacto']) ?: '—' ?></td></tr>
                <tr><th>Departamento</th><td><?= e($ticket['departamento'] ?? '') ?: 'Pendiente de clasificación' ?></td></tr>
                <tr><th>Revisión IA</th><td><?= !empty($ticket['diagnostico_ia']) ? e($ticket['diagnostico_ia']).' (confianza '.(int)($ticket['confianza_ia']??0).'%)' : 'Pendiente' ?></td></tr>
                <tr><th>Creado</th><td class="small"><?= e($ticket['creado_en']) ?></td></tr>
                <tr><th>Última actividad</th><td class="small"><?= e($ticket['actualizado_en']) ?></td></tr>
                <tr><th>SLA límite</th><td class="small"><?= e($ticket['sla_limite']) ?: '—' ?></td></tr>
            </table>
            <?php if (!empty($ticket['descripcion'])): ?>
            <h4 style="margin-top:14px;">Descripción</h4>
            <div class="wysiwyg-contenido"><?= $ticket['descripcion'] /* ya limpiado con limpio_html() al guardar */ ?></div>
            <?php endif; ?>
            <?php $adjuntosTicket = $adjuntosPorComentario[''] ?? []; if ($adjuntosTicket): ?>
            <h4 style="margin-top:14px;">Adjuntos del ticket</h4>
            <ul class="lista-adjuntos">
                <?php foreach ($adjuntosTicket as $a): ?>
                <li><a href="descargar_adjunto_ticket.php?id=<?= (int) $a['id'] ?>"><?= icon('file') ?> <?= e($a['nombre_archivo']) ?></a> <span class="small">(<?= number_format($a['tamano'] / 1024, 0) ?> KB)</span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php if ($equipoRelacionado && tiene_rol(['ADMIN', 'TI'])): ?>
        <div class="panel" style="border-left:4px solid var(--teal-500);">
            <h3><?= icon('zap') ?> Equipo relacionado</h3>
            <table class="deftable">
                <tr><th>Equipo</th><td><?= e($equipoRelacionado['marca']) ?> <?= e($equipoRelacionado['modelo']) ?><br><span class="small"><?= e($equipoRelacionado['serial']) ?></span></td></tr>
                <tr><th>Usuario</th><td><?= e($equipoRelacionado['asignado_a']) ?: '—' ?></td></tr>
                <tr><th>SO</th><td class="small"><?= e($equipoRelacionado['sistema_operativo']) ?: '—' ?></td></tr>
            </table>
            <?php if ($equipoRelacionado['rustdesk_id']): ?>
            <a class="btn" style="width:100%;justify-content:center;margin-top:8px;" href="rustdesk://<?= e($equipoRelacionado['rustdesk_id']) ?>?password=<?= e($equipoRelacionado['rustdesk_password']) ?>"><?= icon('zap') ?> Conectar remoto (RustDesk)</a>
            <?php elseif ($equipoRelacionado['ip_local']): ?>
            <a class="btn btn-secondary" style="width:100%;justify-content:center;margin-top:8px;" href="ms-rd:subscribe?url=rdp://full%20address=s:<?= e($equipoRelacionado['ip_local']) ?>" title="Requiere Escritorio Remoto de Windows habilitado"><?= icon('cloud') ?> RDP (<?= e($equipoRelacionado['ip_local']) ?>)</a>
            <?php else: ?>
            <p class="small">Este equipo aún no tiene agente RustDesk ni IP registrada — <a href="acceso_remoto.php">ver en Acceso Remoto</a>.</p>
            <?php endif; ?>
            <div class="toolbar" style="margin-top:8px"><a class="btn btn-secondary" href="equipo_detalle.php?id=<?= (int)$equipoRelacionado['id'] ?>"><?= icon('inventory') ?> Ver inventario</a><a class="btn btn-secondary" href="agente_inventario.php"><?= icon('zap') ?> Estado del agente</a></div>
        </div>
        <?php endif; ?>
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
            <form method="post" style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px"><input type="hidden" name="accion" value="vincular_equipo"><label class="small">Vincular equipo (serial o placa)</label><input name="equipo_serial" value="<?= e($ticket['equipo_serial']??'') ?>" placeholder="Ej. SERIAL-PC-001" style="width:100%;margin-bottom:8px"><button class="btn-secondary" style="width:100%;justify-content:center"><?= icon('inventory') ?> Vincular inventario y remoto</button></form>
        </div>
    </div>

    <div class="panel" style="display:flex;flex-direction:column;">
        <h3><?= icon('chat') ?> Conversación (<?= count($comentarios) ?>)</h3>
        <div style="max-height:460px;overflow-y:auto;padding:6px 2px;">
            <?php foreach ($comentarios as $c):
                $tipo = $c['tipo'] ?? 'COMENTARIO';
                $esRespuestaCliente = !empty($c['visible_cliente']);
                $claseBubble = $tipo === 'SISTEMA' ? 'system' : ($tipo === 'IA' ? 'ia' : ($c['autor'] === $ticket['solicitante'] ? 'theirs' : 'mine'));
            ?>
            <div class="conv-bubble <?= $claseBubble ?>">
                <?php if ($tipo !== 'SISTEMA'): ?>
                <div class="conv-meta">
                    <?= $tipo === 'IA' ? icon('robot') . ' Agente IA' : e($c['autor']) ?>
                    <?php if ($esRespuestaCliente): ?><span class="badge badge-activo" style="margin-left:6px;font-size:10px;"><?= icon('send') ?> <?= $c['enviado_correo'] ? 'Enviado al cliente' : 'No se pudo enviar' ?></span>
                    <?php elseif ($tipo !== 'IA'): ?><span class="badge badge-otro" style="margin-left:6px;font-size:10px;">Nota interna</span><?php endif; ?>
                </div>
                <?php endif; ?>
                <?= $c['comentario'] /* ya limpiado con limpio_html() al guardar */ ?>
                <?php if (!empty($adjuntosPorComentario[$c['id']])): ?>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach ($adjuntosPorComentario[$c['id']] as $a): ?>
                    <a href="descargar_adjunto_ticket.php?id=<?= (int)$a['id'] ?>" target="_blank" class="badge badge-otro" style="text-decoration:none;">
                        <?= icon('file') ?> <?= e($a['nombre_archivo']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="small" style="margin-top:5px;opacity:.7;"><?= e($c['creado_en']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="post" enctype="multipart/form-data" style="margin-top:14px;border-top:1px solid var(--line);padding-top:14px;" id="form-respuesta">
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
            <textarea name="comentario" id="textarea-comentario" class="wysiwyg" rows="3" style="width:100%;margin-bottom:10px;" placeholder="Escribe una actualización o respuesta..."></textarea>
            <label class="small">Adjuntar archivos (fotos, PDF, evidencia)</label>
            <input type="file" name="adjuntos[]" multiple style="width:100%;margin-bottom:10px;">
            <label class="small" style="display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                <input type="checkbox" name="visible_cliente" value="1" style="width:auto;" <?= $ticket['solicitante_contacto'] ? '' : 'disabled' ?>>
                Enviar como respuesta al cliente por correo<?= $ticket['solicitante_contacto'] ? '' : ' (sin correo de contacto registrado)' ?>
            </label>
            <button type="submit"><?= icon('send') ?> Enviar</button>
        </form>
    </div>
</div>
<script>
var selRespuesta = document.getElementById('selector-respuesta-rapida');
if (selRespuesta) {
    selRespuesta.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        var textarea = document.getElementById('textarea-comentario');
        textarea.value = opt.dataset.texto || '';
        // Si el WYSIWYG ya convirtio el textarea en editor visual, hay que
        // actualizar tambien el div visible (el textarea de atras queda oculto).
        var editable = textarea.previousSibling && textarea.previousSibling.querySelector ? textarea.previousSibling.querySelector('.wysiwyg-editable') : null;
        if (editable) editable.innerHTML = (opt.dataset.texto || '').replace(/\n/g, '<br>');
        document.getElementById('respuesta_rapida_id').value = this.value;
    });
}
</script>
<?php layout_fin(); ?>
