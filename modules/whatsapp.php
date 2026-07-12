<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/whatsapp_client.php';
$pdo = db();
$msg = null;

define('WHATSAPP_CONFIG_PATH', BASE_DIR . '/data/whatsapp_config.json');

function whatsapp_config(): array {
    if (!file_exists(WHATSAPP_CONFIG_PATH)) return ['token' => '', 'phone_number_id' => '', 'verify_token' => ''];
    $d = json_decode(file_get_contents(WHATSAPP_CONFIG_PATH), true);
    return is_array($d) ? $d : ['token' => '', 'phone_number_id' => '', 'verify_token' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar') {
        file_put_contents(WHATSAPP_CONFIG_PATH, json_encode([
            'token' => trim($_POST['token'] ?? ''),
            'phone_number_id' => trim($_POST['phone_number_id'] ?? ''),
            'verify_token' => trim($_POST['verify_token'] ?? ''),
        ], JSON_PRETTY_PRINT));
        $msg = ['ok', 'Credenciales guardadas.'];
    }
    if ($accion === 'probar') {
        $c = whatsapp_config();
        try {
            $client = new WhatsAppClient($c['token'], $c['phone_number_id']);
            $info = $client->probarConexion();
            $msg = ['ok', 'Conexión exitosa. Número: ' . ($info['display_phone_number'] ?? $info['id'] ?? '—')];
        } catch (WhatsAppException $e) {
            $msg = ['error', $e->getMessage()];
        }
    }
    if ($accion === 'enviar_prueba') {
        $c = whatsapp_config();
        try {
            $client = new WhatsAppClient($c['token'], $c['phone_number_id']);
            $client->enviarTexto(trim($_POST['numero'] ?? ''), trim($_POST['texto'] ?? 'Prueba desde NAVISSI'));
            $msg = ['ok', 'Mensaje enviado.'];
        } catch (WhatsAppException $e) {
            $msg = ['error', $e->getMessage()];
        }
    }
}

$c = whatsapp_config();
$configurado = !empty($c['token']) && !empty($c['phone_number_id']);
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

layout_inicio('WhatsApp Business', 'WhatsApp Business', '../');
?>
<h1><?= icon('chat','icon-lg') ?> WhatsApp Business (Cloud API)</h1>
<p class="subtitle">Conexión real con la misma API de Meta que ya tenías funcionando en WorkManager.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>1. Credenciales (business.facebook.com → tu app → WhatsApp → Empezar)</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>Access Token (permanente, no el temporal de 24h)</label><input type="password" name="token" value="<?= e($c['token']) ?>"></div>
            <div><label>Phone Number ID</label><input type="text" name="phone_number_id" value="<?= e($c['phone_number_id']) ?>"></div>
            <div><label>Verify Token (lo inventas tú, para el webhook)</label><input type="text" name="verify_token" value="<?= e($c['verify_token']) ?>" placeholder="navissi_webhook_2026"></div>
        </div>
        <button type="submit">Guardar credenciales</button>
    </form>
    <?php if ($configurado): ?>
    <form method="post" style="display:inline-block;margin-top:10px;">
        <input type="hidden" name="accion" value="probar">
        <button type="submit" class="btn-secondary">Probar conexión</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($configurado): ?>
<div class="panel">
    <h3>2. Enviar mensaje de prueba</h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="enviar_prueba">
        <input type="text" name="numero" placeholder="573001234567 (con indicativo, sin +)" required>
        <input type="text" name="texto" placeholder="Mensaje..." value="Hola, esto es una prueba desde NAVISSI." style="min-width:280px;">
        <button type="submit">Enviar</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h3>3. Webhook (para recibir mensajes entrantes)</h3>
    <p class="small">En Meta Business → tu app → WhatsApp → Configuración → Webhook, registra:</p>
    <table>
        <tr><th>URL de callback</th><td><code><?= e($base) ?>/api_whatsapp_webhook.php</code></td></tr>
        <tr><th>Verify Token</th><td><?= e($c['verify_token']) ?: '(defínelo arriba primero)' ?></td></tr>
        <tr><th>Campo a suscribir</th><td><code>messages</code></td></tr>
    </table>
    <p class="small" style="margin-top:10px;">Cada mensaje entrante que llegue se convierte automáticamente en un ticket de Mesa de Ayuda (categoría WHATSAPP), igual que hace el correo→tickets.</p>
</div>
<?php layout_fin(); ?>
