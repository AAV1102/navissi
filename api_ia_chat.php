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
    $q=mb_strtoupper(trim($pregunta),'UTF-8');
    $nombre=trim((string)($contexto['nombre']??''));
    if(preg_match('/^(HOLA|HOLI|BUENAS|BUENOS D[IÍ]AS|BUENAS TARDES|BUENAS NOCHES)[!,. ]*$/u',$q)){
        return '¡Hola'.($nombre?' '.explode(' ',$nombre)[0]:'').'! 👋 Soy el asistente de NAVISSI. Puedo consultar datos reales y ayudarte a actuar. Puedes preguntarme, por ejemplo: “¿qué equipos necesitan atención?”, “muéstrame los tickets abiertos” o “¿cómo reporto un problema?”.';
    }
    if(preg_match('/^(GRACIAS|MUCHAS GRACIAS|OK|LISTO|ENTENDIDO)[!,. ]*$/u',$q)) return 'Con gusto. ¿Quieres que revisemos tickets, equipos, agentes, acceso remoto o automatizaciones?';
    if (preg_match('/^(EQUIPOS?|COMPUTADORES?|PCS?|INVENTARIO)[!,. ]*$/u', $q)) {
        return "Ahora mismo hay {$contexto['equipos']} equipos registrados; {$contexto['agentes']} reportaron en las últimas 24 horas y " . max(0, $contexto['equipos'] - $contexto['agentes']) . " requieren revisión. Puedes preguntarme por equipos sin agente, sin ubicación, parches o acceso remoto.";
    }
    if (str_contains($q,'SIN AGENTE') || str_contains($q,'DESCONECT')) {
        $n=(int)$pdo->query("SELECT COUNT(*) FROM inventario WHERE ultima_conexion_agente IS NULL OR ultima_conexion_agente < datetime('now','-24 hours')")->fetchColumn();
        return "Detecté {$n} equipos sin reporte del agente en las últimas 24 horas. Revisa Inventario → Agente de inventario para descargar el instalador y ver el último contacto.";
    }
    // Memoria local regenerativa: recupera artículos de conocimiento sin enviar datos a terceros.
    $terminos = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($pregunta),'UTF-8')), fn($w)=>mb_strlen($w)>=4));
    if ($terminos) {
        $cond=[]; $params=[];
        foreach ($terminos as $i=>$term) { $cond[]="(LOWER(titulo) LIKE ? OR LOWER(contenido) LIKE ?)"; $params[]='%'.$term.'%'; $params[]='%'.$term.'%'; }
        $stmt=$pdo->prepare('SELECT titulo, contenido FROM base_conocimiento WHERE '.implode(' OR ',$cond).' ORDER BY creado_en DESC LIMIT 1');
        $stmt->execute($params); $kb=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($kb) return "Encontré una guía en la Base de Conocimiento: {$kb['titulo']}\n\n" . mb_substr(trim(strip_tags($kb['contenido'])),0,900);
    }
    if(str_contains($q,'LOGIST')||str_contains($q,'BODEGA')){
        $sin=(int)$pdo->query("SELECT COUNT(*) FROM inventario WHERE trim(COALESCE(ubicacion_bodega,''))='' ")->fetchColumn();
        return "Logística está activa. Hay {$sin} equipos sin ubicación de bodega. Entra a Inventario y activos → Logística y Bodega para escanear seriales, mover equipos y consultar trazabilidad.";
    }
    if(str_contains($q,'REMOT')||str_contains($q,'RUSTDESK')) return "Hay {$contexto['remotos']} equipos con RustDesk listos para conexión. Abre Inventario y activos → Acceso Remoto; también puedes entrar al ticket vinculado y usar Conectar.";
    if(str_contains($q,'AGENTE')||str_contains($q,'INVENTARIO')||str_contains($q,'EQUIPO')||str_contains($q,'COMPUTADOR')||str_contains($q,'PC')) {
        $sinAgente=max(0,$contexto['equipos']-$contexto['agentes']);
        return "Tenemos {$contexto['equipos']} equipos registrados. {$contexto['agentes']} reportaron en las últimas 24 horas y {$sinAgente} no lo hicieron. Puedo ayudarte a revisar los desconectados, los que no tienen ubicación, sus parches o su acceso remoto. ¿Cuál quieres consultar?";
    }
    if(str_contains($q,'AUTOMAT')){
        $ultima=$pdo->query("SELECT estado,resumen,iniciado_en FROM automatizacion_ejecuciones ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $ultima ? "La última automatización se ejecutó {$ultima['iniciado_en']} con estado {$ultima['estado']}: {$ultima['resumen']}. Puedes revisarla en Automatización e IA → Automatizaciones y alertas." : "Todavía no hay ejecuciones registradas. Ve a Automatización e IA → Automatizaciones y alertas y ejecuta el primer ciclo.";
    }
    if(str_contains($q,'TICKET')||str_contains($q,'MESA')) return "Actualmente hay {$contexto['tickets']} tickets abiertos. La Mesa de Ayuda clasifica por departamento, intenta autogestión y solo escala a una persona si no encuentra una solución segura.";
    if(str_contains($q,'SIESA')||str_contains($q,'FACTUR')) return "Los casos de Siesa, facturación, proveedores, pagos y notas crédito se enrutan al departamento Dirección de Contabilidad.";
    if(str_contains($q,'AYUDA')||str_contains($q,'QUE PUEDES')||str_contains($q,'QUÉ PUEDES')) return 'Puedo consultar tickets, equipos, empleados, sedes, logística, agentes y automatizaciones; también te indico dónde realizar cada acción. Cuéntame con tus palabras qué necesitas.';
    return 'Entendí “'.mb_substr(trim($pregunta),0,80).'”, pero necesito un poco más de contexto para darte un dato correcto. ¿Te refieres a tickets, equipos, empleados, sedes, logística o automatizaciones?';
}

$systemPrompt = "Eres el asistente general del software NAVISSI Inventario (Grupo 10Z / Navissi retail). "
    . "Ayudas a {$u['nombre']} (rol {$u['rol']}) a moverse por el sistema y resolver dudas sobre tickets, inventario, "
    . "sedes, RRHH, credenciales y licencias. Responde breve y concreto, en español. "
    . "Contexto real actual: {$ticketsAbiertos} tickets abiertos, {$equipos} equipos en inventario, "
    . "{$empleados} empleados activos, {$sedes} sedes activas. "
    . "Si te preguntan algo que requiere datos que no tienes aquí, sugiere en qué módulo del menú lo pueden ver.";

// 'local'/'ollama' intenta primero la IA local real (Ollama, respuestas libres);
// si Ollama no está corriendo en el servidor, cae al agente de reglas basado en
// datos reales (respuesta_agente_local) - nunca deja al usuario sin respuesta.
$esLocal = in_array($config['proveedor'] ?? '', ['local', 'ollama'], true);
if (!$esLocal && empty($config['api_key'])) {
    echo json_encode(['respuesta'=>respuesta_agente_local($pdo,$pregunta,['tickets'=>(int)$ticketsAbiertos,'equipos'=>(int)$equipos,'agentes'=>$agentesActivos,'remotos'=>$remotos,'nombre'=>$u['nombre']??'']),'modo'=>'AGENTE_LOCAL'],JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $client = new IAClient($config['proveedor'] ?? 'anthropic', $config['api_key'] ?? '');
    $respuesta = $client->preguntar($systemPrompt, $pregunta);
    echo json_encode(['respuesta' => $respuesta]);
} catch (IAException $e) {
    echo json_encode(['respuesta'=>respuesta_agente_local($pdo,$pregunta,['tickets'=>(int)$ticketsAbiertos,'equipos'=>(int)$equipos,'agentes'=>$agentesActivos,'remotos'=>$remotos,'nombre'=>$u['nombre']??'']),'modo'=>'RESPALDO_LOCAL'],JSON_UNESCAPED_UNICODE);
}
