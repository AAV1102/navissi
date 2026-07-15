<?php
// El chat usa JSON y sesión autenticada; no envía formularios HTML con token CSRF.
define('CSRF_EXEMPT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/ia_client.php';
header('Content-Type: application/json; charset=utf-8');
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error' => 'Sesión expirada, recarga la página.']); exit; }

$pdo = db();
$configPath = private_path('ia_config.json');
$config = file_exists($configPath) ? leer_config_json($configPath) : [];

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$pregunta = trim($data['pregunta'] ?? '');
if (!$pregunta) { echo json_encode(['error' => 'Escribe una pregunta.']); exit; }

$u = usuario_actual();
$ticketsAbiertos = $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado != 'CERRADO'")->fetchColumn();
$equipos = $pdo->query("SELECT COUNT(*) FROM inventario")->fetchColumn();
$empleados = $pdo->query("SELECT COUNT(*) FROM empleados WHERE estado='ACTIVO'")->fetchColumn();
$sedes = $pdo->query("SELECT COUNT(*) FROM sedes WHERE estado='ACTIVO'")->fetchColumn();
$agentesActivos = (int)$pdo->query("SELECT COUNT(*) FROM inventario WHERE ultima_conexion_agente >= datetime('now','-24 hours')")->fetchColumn();
$remotos = (int)$pdo->query("SELECT COUNT(*) FROM inventario WHERE trim(COALESCE(rustdesk_id,'')) != ''")->fetchColumn();

function respuesta_agente_local(PDO $pdo, string $pregunta, array $contexto): string {
    $q=mb_strtoupper($pregunta,'UTF-8');
    if(str_contains($q,'LOGIST')||str_contains($q,'BODEGA')){
        $sin=(int)$pdo->query("SELECT COUNT(*) FROM inventario WHERE trim(COALESCE(ubicacion_bodega,''))='' ")->fetchColumn();
        return "Logística está activa. Hay {$sin} equipos sin ubicación de bodega. Entra a Inventario y activos → Logística y Bodega para escanear seriales, mover equipos y consultar trazabilidad.";
    }
    if(str_contains($q,'REMOT')||str_contains($q,'RUSTDESK')) return "Hay {$contexto['remotos']} equipos con RustDesk listos para conexión. Abre Inventario y activos → Acceso Remoto; también puedes entrar al ticket vinculado y usar Conectar.";
    if(str_contains($q,'AGENTE')||str_contains($q,'INVENTARIO')) return "Inventario tiene {$contexto['equipos']} equipos; {$contexto['agentes']} reportaron durante las últimas 24 horas. Revisa Inventario y activos → Agente de inventario para instaladores, tokens y últimos reportes.";
    if(str_contains($q,'AUTOMAT')){
        $ultima=$pdo->query("SELECT estado,resumen,iniciado_en FROM automatizacion_ejecuciones ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $ultima ? "La última automatización se ejecutó {$ultima['iniciado_en']} con estado {$ultima['estado']}: {$ultima['resumen']}. Puedes revisarla en Automatización e IA → Automatizaciones y alertas." : "Todavía no hay ejecuciones registradas. Ve a Automatización e IA → Automatizaciones y alertas y ejecuta el primer ciclo.";
    }
    if(str_contains($q,'TICKET')||str_contains($q,'MESA')) return "Actualmente hay {$contexto['tickets']} tickets abiertos. La Mesa de Ayuda clasifica por departamento, intenta autogestión y solo escala a una persona si no encuentra una solución segura.";
    if(str_contains($q,'SIESA')||str_contains($q,'FACTUR')) return "Los casos de Siesa, facturación, proveedores, pagos y notas crédito se enrutan al departamento Dirección de Contabilidad.";
    return "Puedo consultar el estado real de tickets, inventario, agentes, acceso remoto, logística y automatizaciones. Por ejemplo escribe: “¿cuántos agentes están conectados?”, “estado de automatizaciones” o “equipos sin ubicación de bodega”.";
}

$systemPrompt = "Eres el asistente general del software NAVISSI Inventario (Grupo 10Z / Navissi retail). "
    . "Ayudas a {$u['nombre']} (rol {$u['rol']}) a moverse por el sistema y resolver dudas sobre tickets, inventario, "
    . "sedes, RRHH, credenciales y licencias. Responde breve y concreto, en español. "
    . "Contexto real actual: {$ticketsAbiertos} tickets abiertos, {$equipos} equipos en inventario, "
    . "{$empleados} empleados activos, {$sedes} sedes activas. "
    . "Si te preguntan algo que requiere datos que no tienes aquí, sugiere en qué módulo del menú lo pueden ver.";

if (empty($config['api_key'])) {
    echo json_encode(['respuesta'=>respuesta_agente_local($pdo,$pregunta,['tickets'=>(int)$ticketsAbiertos,'equipos'=>(int)$equipos,'agentes'=>$agentesActivos,'remotos'=>$remotos]),'modo'=>'AGENTE_LOCAL'],JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $client = new IAClient($config['proveedor'] ?? 'anthropic', $config['api_key']);
    $respuesta = $client->preguntar($systemPrompt, $pregunta);
    echo json_encode(['respuesta' => $respuesta]);
} catch (IAException $e) {
    echo json_encode(['respuesta'=>respuesta_agente_local($pdo,$pregunta,['tickets'=>(int)$ticketsAbiertos,'equipos'=>(int)$equipos,'agentes'=>$agentesActivos,'remotos'=>$remotos]),'modo'=>'RESPALDO_LOCAL'],JSON_UNESCAPED_UNICODE);
}
