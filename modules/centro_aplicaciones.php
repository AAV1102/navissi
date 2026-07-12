<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$rustdeskActivo = file_exists(__DIR__ . '/../rustdesk-server/id_ed25519.pub');
$ms365Activo = file_exists(__DIR__ . '/../data/ms365_config.json');
$iaActivo = file_exists(__DIR__ . '/../data/ia_config.json');
$equiposConAgente = (int) $pdo->query("SELECT COUNT(*) FROM inventario WHERE rustdesk_id IS NOT NULL AND rustdesk_id != ''")->fetchColumn();
$licenciasM365 = (int) $pdo->query("SELECT COUNT(*) FROM ms365_usuarios")->fetchColumn();

$apps = [
    ['icono' => 'cloud', 'color' => '#0078d4', 'categoria' => 'Microsoft', 'nombre' => 'Microsoft 365', 'desc' => 'Usuarios, licencias, OneDrive, SharePoint, Teams y correo → tickets, sincronizado vía Graph API.', 'activo' => $ms365Activo, 'stat' => $ms365Activo ? "{$licenciasM365} cuentas sincronizadas" : 'No configurado', 'href' => 'microsoft365.php'],
    ['icono' => 'chat', 'color' => '#25d366', 'categoria' => 'Mensajería', 'nombre' => 'WhatsApp Business', 'desc' => 'Recibe reportes de empleados por WhatsApp y conviértelos en tickets automáticamente, con IA de primera respuesta.', 'activo' => null, 'stat' => 'Ver configuración', 'href' => 'whatsapp.php'],
    ['icono' => 'zap', 'color' => '#ea4b71', 'categoria' => 'Automatización', 'nombre' => 'n8n', 'desc' => 'Flujos de automatización propios, sin licencias — corre localmente y se conecta a cualquier módulo vía webhook.', 'activo' => null, 'stat' => 'Ver flujos', 'href' => 'n8n.php'],
    ['icono' => 'robot', 'color' => '#7c3aed', 'categoria' => 'Inteligencia Artificial', 'nombre' => 'IA Multiagente', 'desc' => 'Triage automático de tickets, redacción de movimientos, chat de soporte en cada módulo (Claude / GPT / Gemini).', 'activo' => $iaActivo, 'stat' => $iaActivo ? 'Configurada' : 'Sin configurar', 'href' => 'ia_multiagente.php'],
    ['icono' => 'zap', 'color' => '#00a4ef', 'categoria' => 'Acceso Remoto', 'nombre' => 'RustDesk (self-hosted)', 'desc' => 'Control remoto propio, sin depender de terceros — funciona dentro o fuera de la red de la empresa.', 'activo' => $rustdeskActivo, 'stat' => "{$equiposConAgente} equipos con agente", 'href' => 'acceso_remoto.php'],
    ['icono' => 'key', 'color' => '#f59e0b', 'categoria' => 'ERP', 'nombre' => 'Siesa', 'desc' => 'Credenciales y accesos de Siesa ERP/POS centralizados junto al resto del inventario de TI.', 'activo' => null, 'stat' => 'Ver credenciales', 'href' => 'siesa.php'],
    ['icono' => 'inventory', 'color' => '#0d9488', 'categoria' => 'Inventario', 'nombre' => 'Agente de Inventario', 'desc' => 'Recolecta specs de hardware reales de cada equipo automáticamente vía PowerShell, sin instalar nada pesado.', 'activo' => null, 'stat' => 'Ver agente', 'href' => 'agente_inventario.php'],
    ['icono' => 'inventory', 'color' => '#e31c6c', 'categoria' => 'Activos', 'nombre' => 'Códigos QR de Equipos', 'desc' => 'Etiqueta imprimible fija por equipo: escanéala y ves la ficha, hoja de vida y tickets en tiempo real.', 'activo' => null, 'stat' => 'Ver etiquetas', 'href' => 'qr_equipos.php'],
];

layout_inicio('Centro de Aplicaciones', 'Centro de Aplicaciones', '../');
?>
<h1><?= icon('robot','icon-lg') ?> Trabaja de forma más inteligente</h1>
<p class="subtitle">Todas las integraciones reales de NAVISSI en un solo lugar — conectadas de verdad, no una vitrina de apps de terceros.</p>

<div class="apps-grid">
    <?php foreach ($apps as $app): ?>
    <a class="app-card" href="<?= e($app['href']) ?>">
        <div class="app-card-top">
            <span class="app-icon" style="background:<?= e($app['color']) ?>"><?= icon($app['icono']) ?></span>
            <?php if ($app['activo'] === true): ?><span class="badge badge-activo">Conectado</span>
            <?php elseif ($app['activo'] === false): ?><span class="badge badge-otro">Sin conectar</span>
            <?php endif; ?>
        </div>
        <span class="app-categoria"><?= e($app['categoria']) ?></span>
        <h3><?= e($app['nombre']) ?></h3>
        <p><?= e($app['desc']) ?></p>
        <div class="app-card-foot"><?= icon('check','icon') ?> <?= e($app['stat']) ?></div>
    </a>
    <?php endforeach; ?>
</div>
<?php layout_fin(); ?>
