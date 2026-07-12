<?php
require_once __DIR__ . '/ia_client.php';

/**
 * Autogestión con IA: cuando entra un ticket nuevo (de cualquier canal),
 * busca en la Base de Conocimiento, le pregunta al modelo si con eso alcanza
 * para resolverlo solo. Si sí, responde directo (autogestión, sin técnico).
 * Si no, decide a qué área escalar y deja el ticket listo para that área.
 * Todo queda registrado como comentario del ticket y en la hoja de vida.
 */
function ia_triage_ticket(PDO $pdo, int $ticketId) {
    $configPath = BASE_DIR . '/data/ia_config.json';
    if (!file_exists($configPath)) return; // IA no configurada: no hace nada, el ticket sigue como manual

    $config = json_decode(file_get_contents($configPath), true);
    if (empty($config['api_key'])) return;

    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) return;

    // Busca artículos relevantes por coincidencia simple de palabras del título.
    $palabras = array_filter(preg_split('/\s+/', $ticket['titulo'] . ' ' . $ticket['descripcion']), fn($w) => strlen($w) > 3);
    $articulos = [];
    if ($palabras) {
        $condiciones = implode(' OR ', array_fill(0, count($palabras), "(titulo LIKE ? OR contenido LIKE ?)"));
        $params = [];
        foreach ($palabras as $p) { $params[] = "%{$p}%"; $params[] = "%{$p}%"; }
        $stmt = $pdo->prepare("SELECT titulo, contenido FROM base_conocimiento WHERE {$condiciones} LIMIT 5");
        $stmt->execute($params);
        $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $contextoArticulos = $articulos
        ? implode("\n---\n", array_map(fn($a) => "### {$a['titulo']}\n{$a['contenido']}", $articulos))
        : '(no hay artículos de la base de conocimiento que coincidan)';

    $systemPrompt = "Eres el agente de autogestión de Mesa de Ayuda de Navissi/Grupo 10Z. Tu trabajo es intentar resolver "
        . "el ticket usando SOLO la base de conocimiento de abajo. Reglas estrictas:\n"
        . "1. Si la base de conocimiento tiene una solución clara y aplicable, respóndela en pasos concretos, y termina "
        . "tu respuesta EXACTAMENTE con la línea: RESUELTO\n"
        . "2. Si no hay información suficiente para resolverlo con certeza (nunca inventes pasos), responde brevemente por "
        . "qué no puedes resolverlo solo, indica a qué área debe escalarse (TI, RRHH, INVENTARIO o COORDINACION), y termina "
        . "tu respuesta EXACTAMENTE con la línea: ESCALAR:<AREA> (ejemplo: ESCALAR:TI)\n\n"
        . "BASE DE CONOCIMIENTO DISPONIBLE:\n{$contextoArticulos}";

    try {
        $client = new IAClient($config['proveedor'] ?? 'anthropic', $config['api_key']);
        $respuesta = $client->preguntar($systemPrompt, "Título: {$ticket['titulo']}\nDescripción: {$ticket['descripcion']}");
    } catch (IAException $e) {
        hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'IA_ERROR', $e->getMessage(), 'IA', $ticketId);
        return;
    }

    $resuelto = str_contains($respuesta, 'RESUELTO');
    $textoLimpio = trim(preg_replace('/RESUELTO\s*$/', '', preg_replace('/ESCALAR:\w+\s*$/', '', $respuesta)));

    $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
        ->execute([$ticketId, 'Agente IA', $textoLimpio, 'IA']);

    if ($resuelto) {
        $pdo->prepare("UPDATE tickets SET estado = 'RESUELTO POR IA', actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticketId]);
        hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'AUTOGESTION_IA', 'La IA resolvió el ticket sin técnico, usando la base de conocimiento.', 'IA', $ticketId);
    } else {
        preg_match('/ESCALAR:(\w+)/', $respuesta, $m);
        $area = $m[1] ?? 'TI';
        $pdo->prepare("UPDATE tickets SET categoria = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$area, $ticketId]);
        $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
            ->execute([$ticketId, 'Sistema', "Escalado automáticamente al área {$area} - requiere técnico.", 'SISTEMA']);
        hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'ESCALADO_IA', "Escalado al área {$area} por la IA (no se pudo autogestionar).", 'IA', $ticketId);
    }
}
