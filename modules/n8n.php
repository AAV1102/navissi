<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_roles(['ADMIN', 'TI'], '../');
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS config_general (clave TEXT PRIMARY KEY, valor TEXT)");
$msg = null;

define('N8N_URL', 'http://localhost:5678');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_webhook') {
    $pdo->prepare("INSERT INTO config_general (clave, valor) VALUES ('n8n_webhook_ticket', ?) ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor")
        ->execute([limpio($_POST['webhook_ticket'] ?? null)]);
    $msg = ['ok', 'Guardado.'];
}

$conectado = false;
try {
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $resp = @file_get_contents(N8N_URL, false, $ctx);
    $conectado = $resp !== false;
} catch (Exception $e) { $conectado = false; }

$webhookGuardado = $pdo->query("SELECT valor FROM config_general WHERE clave = 'n8n_webhook_ticket'")->fetchColumn();

layout_inicio('n8n', 'n8n (flujos)', '../');
?>
<h1><?= icon('zap','icon-lg') ?> n8n — Motor de Automatizaciones</h1>
<p class="subtitle">Ya está instalado y corriendo en este equipo (Node.js + n8n, sin Docker). Se usa para conectar WhatsApp, correos y otros sistemas entre sí con flujos visuales, sin programar.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Estado</h3>
    <?php if ($conectado): ?>
        <div class="msg-ok">✅ n8n está corriendo en <?= e(N8N_URL) ?></div>
    <?php else: ?>
        <div class="msg-error">n8n no está respondiendo ahora mismo. Abre <code>INICIAR_N8N.bat</code> (en la carpeta del software) para encenderlo.</div>
    <?php endif; ?>
    <a class="btn" href="<?= N8N_URL ?>" target="_blank">Abrir editor de n8n</a>
    <p class="small" style="margin-top:10px;">
        <strong>Primera vez:</strong> n8n te va a pedir crear una cuenta de propietario (tu correo y una clave) - es 100% local,
        queda guardado en tu máquina, no se envía a ningún lado. Después de eso ya puedes crear flujos.
    </p>
</div>

<div class="panel">
    <h3>Webhook para crear tickets desde n8n</h3>
    <p class="small">
        Cuando armes un flujo en n8n (por ejemplo "mensaje de WhatsApp entra → crear ticket"), el último paso debe hacer un
        POST a esta URL con JSON <code>{"evento_id":"id-unico-del-mensaje","titulo":"...","descripcion":"...","solicitante":"...","solicitante_contacto":"..."}</code>:
    </p>
    <p><code style="background:#f4f6f9;padding:6px 10px;border-radius:6px;display:inline-block;">
        <?php $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>
        <?= e($base) ?>/api_webhook_ticket.php
    </code></p>
    <p class="small">La solicitud debe llevar <code>X-Navissi-Signature</code> con el HMAC-SHA256 hexadecimal del cuerpo JSON exacto y un <code>evento_id</code> estable para evitar duplicados en reintentos.</p>
    <details>
        <summary>Ver secreto de firma para configurar n8n</summary>
        <code style="word-break:break-all;"><?= e(navissi_webhook_secret()) ?></code>
        <p class="small">Trátalo como una contraseña. Si se comparte por error, TI debe rotar <code>webhook_hmac.key</code> en el directorio privado.</p>
    </details>
</div>

<div class="panel">
    <h3>Flujos sugeridos para armar (guía rápida)</h3>
    <table>
        <tr><th>Flujo</th><th>Disparador</th><th>Acción final</th></tr>
        <tr><td>WhatsApp → Ticket</td><td>Webhook de WhatsApp Cloud API (nuevo mensaje)</td><td>POST a <code>api_webhook_ticket.php</code></td></tr>
        <tr><td>Alerta de licencias por correo</td><td>Cron cada mañana</td><td>Consultar <code>api_agente.php</code>/Automatizaciones y enviar correo si hay alertas críticas</td></tr>
        <tr><td>Recordatorio de tickets vencidos (SLA)</td><td>Cron cada hora</td><td>Revisar tickets con sla_limite pasado y notificar al asignado</td></tr>
    </table>
    <p class="small" style="margin-top:10px;">Si quieres, en el siguiente turno te armo el flujo completo de WhatsApp→Ticket ya importable en n8n (un archivo .json que solo importas), en cuanto me des el Access Token y Phone Number ID de WhatsApp Business.</p>
</div>
<div class="panel"><h3><?=icon('zap')?> Flujo operativo listo para importar</h3><p>Ejecuta aperturas, cierres, SLA y entrega de notificaciones cada 15 minutos.</p><a class="btn" href="descargar_workflow_n8n.php"><?=icon('upload')?> Descargar workflow n8n</a> <a class="btn btn-secondary" href="automatizaciones.php">Ver ejecuciones</a><p class="small">Reemplaza <code>REEMPLAZAR_SECRETO_HMAC</code> antes de activarlo.</p></div>
<div class="panel"><h3><?=icon('dashboard')?> Fase 6 · inteligencia verificable</h3><p>El flujo operativo ya ejecuta los agentes de control después de revisar tiendas, SLA y notificaciones. Para invocarlos por separado, n8n puede enviar un POST firmado a <code><?=$base?>/api_inteligencia.php</code> con <code>{"action":"run","correlation_id":"..."}</code>; usa <code>action: status</code> para consultar métricas.</p><a class="btn btn-secondary" href="inteligencia_operativa.php">Ver hallazgos y evidencia</a></div>
<div class="panel"><h3><?=icon('inventory')?> Fase 7 · datos comerciales Siesa</h3><p>n8n puede enviar productos, existencias y ventas a <code><?=$base?>/api_retail.php</code>. El cuerpo debe incluir un <code>source_id</code> único y la misma firma HMAC de los demás endpoints. Cada lote admite hasta 20.000 filas y es idempotente.</p><a class="btn btn-secondary" href="retail_inteligencia.php">Inventario Retail</a></div>
<?php layout_fin(); ?>
