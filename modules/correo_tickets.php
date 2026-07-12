<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/graph_client.php';
require_once __DIR__ . '/../lib/ia_triage.php';
$pdo = db();
$msg = null;
$resultado = null;

function obtener_buzones(PDO $pdo): array {
    $stmt = $pdo->query("SELECT valor FROM config_general WHERE clave = 'buzones_mesa_ayuda'");
    $v = $stmt ? $stmt->fetchColumn() : null;
    if ($v) return array_filter(array_map('trim', explode(',', $v)));
    return ['sistemas@navissi.com', 'mesadeayuda@navissi.com'];
}

// tabla mínima de config general (clave/valor) para no crear una tabla nueva por cada ajuste suelto
$pdo->exec("CREATE TABLE IF NOT EXISTS config_general (clave TEXT PRIMARY KEY, valor TEXT)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar_buzones') {
        $pdo->prepare("INSERT INTO config_general (clave, valor) VALUES ('buzones_mesa_ayuda', ?) ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor")
            ->execute([limpio($_POST['buzones'] ?? null)]);
        $msg = ['ok', 'Buzones actualizados.'];
    }
    if ($accion === 'sincronizar') {
        if (!ms365_configurado()) {
            $msg = ['error', 'Primero configura la conexión en Microsoft 365.'];
        } else {
            $c = ms365_config();
            $gc = new GraphClient($c['tenant_id'], $c['client_id'], $c['client_secret']);
            $buzones = obtener_buzones($pdo);
            $creados = 0; $yaExistian = 0; $errores = [];
            foreach ($buzones as $buzon) {
                try {
                    $correos = $gc->leerCorreosNoLeidos($buzon);
                    foreach ($correos as $correo) {
                        $stmt = $pdo->prepare("SELECT id FROM correos_a_tickets WHERE mensaje_id = ?");
                        $stmt->execute([$correo['id']]);
                        if ($stmt->fetchColumn()) { $yaExistian++; continue; }

                        $remitente = $correo['from']['emailAddress']['address'] ?? 'desconocido';
                        $remitenteNombre = $correo['from']['emailAddress']['name'] ?? $remitente;
                        $asunto = $correo['subject'] ?? '(sin asunto)';

                        $slaLimite = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));
                        $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, solicitante, solicitante_contacto, sla_limite, origen)
                            VALUES (?,?,?,?,?,?,?,?)")
                            ->execute(["[{$buzon}] {$asunto}", $correo['bodyPreview'] ?? '', 'CORREO', 'MEDIA', $remitenteNombre, $remitente, $slaLimite, 'CORREO']);
                        $ticketId = $pdo->lastInsertId();

                        $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
                            ->execute([$ticketId, 'Sistema', "Ticket creado automáticamente desde el correo {$buzon}.", 'SISTEMA']);

                        $pdo->prepare("INSERT INTO correos_a_tickets (mensaje_id, buzon, remitente, asunto, ticket_id) VALUES (?,?,?,?,?)")
                            ->execute([$correo['id'], $buzon, $remitente, $asunto, $ticketId]);

                        hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'CREADO_DESDE_CORREO', $asunto, $remitente, $ticketId);
                        ia_triage_ticket($pdo, $ticketId);

                        // No se elimina ni mueve el correo del buzón principal - solo se marca leído para no duplicar el ticket.
                        $gc->marcarCorreoLeido($buzon, $correo['id']);
                        $creados++;
                    }
                } catch (GraphClientException $e) {
                    $errores[] = "{$buzon}: {$e->getMessage()}";
                }
            }
            $resultado = ['creados' => $creados, 'ya_existian' => $yaExistian, 'errores' => $errores];
        }
    }
}

$buzonesActuales = implode(', ', obtener_buzones($pdo));
$historial = $pdo->query("SELECT ct.*, t.titulo, t.estado FROM correos_a_tickets ct LEFT JOIN tickets t ON ct.ticket_id = t.id ORDER BY ct.id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Correo a Tickets', 'Correo → Tickets', '../');
?>
<h1><?= icon('ticket','icon-lg') ?> Correo → Tickets automáticos</h1>
<p class="subtitle">Los correos que lleguen a los buzones configurados se convierten en tickets de Mesa de Ayuda. El correo NO se borra ni se mueve del buzón principal - solo se marca como leído.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>
<?php if ($resultado): ?>
<div class="msg-ok">
    Tickets creados: <?= $resultado['creados'] ?> — ya existían: <?= $resultado['ya_existian'] ?>
    <?php foreach ($resultado['errores'] as $err): ?><br><span style="color:#a12b1f;"><?= e($err) ?></span><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Buzones a vigilar</h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="guardar_buzones">
        <input type="text" name="buzones" value="<?= e($buzonesActuales) ?>" style="min-width:400px;" placeholder="sistemas@navissi.com, mesadeayuda@navissi.com">
        <button type="submit">Guardar</button>
    </form>
    <p class="small" style="margin-top:10px;">
        Requisito en Azure AD: el permiso de aplicación <code>Mail.Read</code> concedido a esta misma app (Azure AD → tu app → Permisos de API → Microsoft Graph → Aplicación → Mail.Read → conceder consentimiento de administrador).
    </p>
    <form method="post" style="margin-top:10px;">
        <input type="hidden" name="accion" value="sincronizar">
        <button type="submit">📥 Revisar correos ahora</button>
    </form>
</div>

<div class="panel">
    <h3>Últimos correos convertidos (<?= count($historial) ?>)</h3>
    <table>
        <tr><th>Buzón</th><th>Remitente</th><th>Asunto</th><th>Ticket</th><th>Estado</th><th>Fecha</th></tr>
        <?php foreach ($historial as $h): ?>
        <tr>
            <td><?= e($h['buzon']) ?></td>
            <td><?= e($h['remitente']) ?></td>
            <td><?= e($h['asunto']) ?></td>
            <td><a href="ticket_detalle.php?id=<?= (int)$h['ticket_id'] ?>">#<?= (int)$h['ticket_id'] ?></a></td>
            <td><span class="badge <?= $h['estado']==='CERRADO'?'badge-otro':'badge-activo' ?>"><?= e($h['estado']) ?></span></td>
            <td class="small"><?= e($h['procesado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$historial): ?><tr><td colspan="6" class="small">Aún no se ha sincronizado ningún correo.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
