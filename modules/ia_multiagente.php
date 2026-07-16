<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/ia_client.php';
// Las claves de IA y el contexto operativo son de administración de TI.
// Un empleado autenticado no debe poder consultar ni reemplazar esta configuración.
requiere_roles(['ADMIN', 'TI'], '../');
$pdo = db();
$msg = null; $respuesta = null;

define('IA_CONFIG_PATH', private_path('ia_config.json'));
function ia_config(): array {
    $d = leer_config_json(IA_CONFIG_PATH);
    return is_array($d) ? $d : ['proveedor' => 'anthropic', 'api_key' => ''];
}

// Cada "agente" tiene un rol/contexto distinto - así se arma lo "multiagente":
// cada uno ve solo su dominio de datos reales, no todo mezclado.
function agentes_disponibles(): array {
    return [
        'TI' => 'Eres el agente de soporte de TI de Navissi/Grupo 10Z. Ayudas con diagnósticos de tickets, inventario y credenciales. Sé breve y práctico.',
        'RRHH' => 'Eres el agente de Recursos Humanos de Navissi. Ayudas con dudas sobre nómina, vacaciones, certificados. No inventes cifras que no te den.',
        'INVENTARIO' => 'Eres el agente de inventario y activos tecnológicos de Navissi. Ayudas a decidir sobre bajas, repotenciamientos y asignaciones.',
        'COORDINACION' => 'Eres el agente de coordinación de tiendas de Navissi. Ayudas con temas de sedes, contactos y comunicación entre tiendas.',
    ];
}

function contexto_real(PDO $pdo, string $agente): string {
    switch ($agente) {
        case 'TI':
            $abiertos = $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado != 'CERRADO'")->fetchColumn();
            $equipos = $pdo->query("SELECT COUNT(*) FROM inventario")->fetchColumn();
            return "Contexto real actual: {$abiertos} tickets abiertos, {$equipos} equipos en inventario.";
        case 'RRHH':
            $empleados = $pdo->query("SELECT COUNT(*) FROM empleados WHERE estado='ACTIVO'")->fetchColumn();
            $vacaciones = $pdo->query("SELECT COUNT(*) FROM vacaciones_permisos WHERE estado='SOLICITADO'")->fetchColumn();
            return "Contexto real actual: {$empleados} empleados activos, {$vacaciones} solicitudes de vacaciones/permisos pendientes.";
        case 'INVENTARIO':
            $reparacion = $pdo->query("SELECT COUNT(*) FROM inventario WHERE estado='EN REPARACION'")->fetchColumn();
            return "Contexto real actual: {$reparacion} equipos en reparación.";
        case 'COORDINACION':
            $sedes = $pdo->query("SELECT COUNT(*) FROM sedes WHERE estado='ACTIVO'")->fetchColumn();
            return "Contexto real actual: {$sedes} sedes activas.";
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'guardar_config') {
        $actual = ia_config();
        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        guardar_config_json(IA_CONFIG_PATH, [
            'proveedor' => $_POST['proveedor'] ?? 'anthropic',
            // Campo vacío significa conservar la clave existente; nunca se
            // vuelve a imprimir el secreto en HTML.
            'api_key' => $apiKey !== '' ? $apiKey : (string)($actual['api_key'] ?? ''),
        ]);
        $msg = ['ok', 'Configuración guardada.'];
    }
    if ($accion === 'preguntar') {
        $c = ia_config();
        if (empty($c['api_key'])) {
            $msg = ['error', 'Configura primero tu clave de API arriba.'];
        } else {
            $agente = $_POST['agente'] ?? 'TI';
            $prompts = agentes_disponibles();
            $systemPrompt = ($prompts[$agente] ?? '') . ' ' . contexto_real($pdo, $agente);
            try {
                $client = new IAClient($c['proveedor'], $c['api_key']);
                $respuesta = $client->preguntar($systemPrompt, limpio($_POST['pregunta'] ?? ''));
            } catch (IAException $e) {
                $msg = ['error', $e->getMessage()];
            }
        }
    }
}

$c = ia_config();
$configurado = !empty($c['api_key']);

layout_inicio('IA Multiagente', 'IA Multiagente', '../');
?>
<h1>IA Multiagente</h1>
<p class="subtitle">Varios agentes especializados (TI, RRHH, Inventario, Coordinación), cada uno con contexto real de tus datos - no respuestas genéricas.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Configuración</h3>
    <p class="small">¿No tienes clave todavía? La más rápida y sin costo es Google Gemini: entra a
        <a href="https://aistudio.google.com/apikey" target="_blank">aistudio.google.com/apikey</a> con tu cuenta de Google,
        clic en "Create API key" y pégala aquí - no pide tarjeta de crédito.</p>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="guardar_config">
        <div><label>Proveedor</label>
            <select name="proveedor">
                <option value="local" <?= $c['proveedor']==='local'?'selected':'' ?>>NAVISSI Local (sin internet ni API)</option>
                <option value="gemini" <?= $c['proveedor']==='gemini'?'selected':'' ?>>Google Gemini (gratis, sin tarjeta)</option>
                <option value="anthropic" <?= $c['proveedor']==='anthropic'?'selected':'' ?>>Anthropic (Claude)</option>
                <option value="openai" <?= $c['proveedor']==='openai'?'selected':'' ?>>OpenAI (GPT)</option>
            </select>
        </div>
        <div style="grid-column:span 2;"><label>API Key</label><input type="password" name="api_key" value="" autocomplete="new-password" placeholder="Vacío para conservar la clave actual"></div>
        <div style="align-self:end;"><button type="submit">Guardar</button></div>
    </form>
</div>

<?php if ($configurado): ?>
<div class="panel">
    <h3>Consultar un agente</h3>
    <form method="post">
        <input type="hidden" name="accion" value="preguntar">
        <div class="grid-form">
            <div><label>Agente</label>
                <select name="agente">
                    <?php foreach (array_keys(agentes_disponibles()) as $a): ?><option><?= $a ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <textarea name="pregunta" rows="3" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Escribe tu pregunta..." required></textarea>
        <button type="submit">Preguntar</button>
    </form>
    <?php if ($respuesta): ?>
    <div class="panel" style="background:#eaf1f8;margin-top:14px;"><?= nl2br(e($respuesta)) ?></div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="msg-error">Configura una clave de API (Anthropic o OpenAI) arriba para activar los agentes.</div>
<?php endif; ?>

<div class="panel">
    <h3>Agentes disponibles</h3>
    <table>
        <tr><th>Agente</th><th>Especialidad</th><th>Ve datos reales de</th></tr>
        <tr><td>TI</td><td>Diagnóstico, tickets, inventario, credenciales</td><td>Tickets abiertos, equipos</td></tr>
        <tr><td>RRHH</td><td>Nómina, vacaciones, certificados</td><td>Empleados activos, solicitudes pendientes</td></tr>
        <tr><td>INVENTARIO</td><td>Bajas, repotenciamientos, asignaciones</td><td>Equipos en reparación</td></tr>
        <tr><td>COORDINACION</td><td>Sedes, contactos, comunicación entre tiendas</td><td>Sedes activas</td></tr>
    </table>
</div>
<?php layout_fin(); ?>
