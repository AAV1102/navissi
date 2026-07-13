<?php
require_once __DIR__ . '/ia_client.php';
require_once __DIR__ . '/mailer.php';

/**
 * Autogestión con IA: cuando entra un ticket nuevo (de cualquier canal),
 * busca en la Base de Conocimiento, le pregunta al modelo si con eso alcanza
 * para resolverlo solo. Si sí, responde directo (autogestión, sin técnico).
 * Si no, decide a qué área escalar y deja el ticket listo para that área.
 * Todo queda registrado como comentario del ticket y en la hoja de vida.
 */
/** Le pide al modelo que clasifique el ticket en UNA de las categorías activas reales (no una lista fija a mano). */
function ia_clasificar_categoria(IAClient $client, array $ticket, array $categorias): string {
    $nombresValidos = array_column($categorias, 'nombre');
    $listaCategorias = implode("\n", array_map(
        fn($c) => "- {$c['nombre']}" . ($c['descripcion'] ? " ({$c['descripcion']})" : '') . ($c['area_responsable'] ? " — área: {$c['area_responsable']}" : ''),
        $categorias
    ));
    $systemPrompt = "Clasificas tickets de Mesa de Ayuda de Navissi/Grupo 10Z en UNA sola categoría de esta lista exacta "
        . "(usa el nombre EXACTO tal cual aparece, sin inventar categorías nuevas):\n{$listaCategorias}\n\n"
        . "Responde ÚNICAMENTE con el nombre exacto de la categoría, nada más.";
    $respuesta = trim($client->preguntar($systemPrompt, "Título: {$ticket['titulo']}\nDescripción: {$ticket['descripcion']}"));
    foreach ($nombresValidos as $n) {
        if (strcasecmp(trim($respuesta), $n) === 0 || str_contains(strtoupper($respuesta), strtoupper($n))) return $n;
    }
    return $nombresValidos[0] ?? 'SOPORTE';
}

/**
 * Autogestión con IA: cuando entra un ticket nuevo (de cualquier canal):
 *  1. Clasifica el ticket en una categoría/área real (RRHH, TI, INVENTARIO...).
 *  2. Busca SOLO en la base de conocimiento de ESA categoría (nunca mezcla áreas).
 *  3. Si alcanza para resolverlo, responde al cliente por correo.
 *  4. Si no, escala y asigna al técnico por defecto configurado para ESA categoría
 *     específicamente (un ticket de RRHH nunca cae en el técnico de TI, y viceversa).
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

    $categorias = $pdo->query("SELECT nombre, descripcion, area_responsable, tecnico_default FROM categorias_tickets WHERE activa = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    if (!$categorias) return; // sin categorías configuradas, no hay a qué clasificar

    try {
        $client = new IAClient($config['proveedor'] ?? 'anthropic', $config['api_key']);
        $categoriaDetectada = ia_clasificar_categoria($client, $ticket, $categorias);
    } catch (IAException $e) {
        hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'IA_ERROR', $e->getMessage(), 'IA', $ticketId);
        return;
    }

    // El ticket queda re-categorizado de inmediato, aunque la IA no logre resolverlo sola.
    $pdo->prepare("UPDATE tickets SET categoria = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$categoriaDetectada, $ticketId]);
    $catInfo = current(array_filter($categorias, fn($c) => $c['nombre'] === $categoriaDetectada)) ?: null;
    $tecnicoDefault = $catInfo['tecnico_default'] ?? null;

    // Base de conocimiento SOLO de la categoría detectada — nunca mezcla el contexto de otra área.
    $stmtKb = $pdo->prepare("SELECT titulo, contenido FROM base_conocimiento WHERE categoria = ? LIMIT 8");
    $stmtKb->execute([$categoriaDetectada]);
    $articulos = $stmtKb->fetchAll(PDO::FETCH_ASSOC);
    $contextoArticulos = $articulos
        ? implode("\n---\n", array_map(fn($a) => "### {$a['titulo']}\n{$a['contenido']}", $articulos))
        : "(no hay artículos de la base de conocimiento para la categoría {$categoriaDetectada})";

    $systemPrompt = "Eres el agente de autogestión de Mesa de Ayuda de Navissi/Grupo 10Z, especializado ÚNICAMENTE en "
        . "la categoría \"{$categoriaDetectada}\". Tu trabajo es intentar resolver el ticket usando SOLO la base de "
        . "conocimiento de abajo (que ya está filtrada a esta categoría). Reglas estrictas:\n"
        . "1. Si la base de conocimiento tiene una solución clara y aplicable, respóndela en pasos concretos, y termina "
        . "tu respuesta EXACTAMENTE con la línea: RESUELTO\n"
        . "2. Si no hay información suficiente para resolverlo con certeza (nunca inventes pasos ni uses conocimiento de "
        . "otras áreas), responde brevemente que se necesita un responsable humano del área {$categoriaDetectada}, y termina "
        . "tu respuesta EXACTAMENTE con la línea: ESCALAR\n\n"
        . "BASE DE CONOCIMIENTO DE \"{$categoriaDetectada}\":\n{$contextoArticulos}";

    try {
        $respuesta = $client->preguntar($systemPrompt, "Título: {$ticket['titulo']}\nDescripción: {$ticket['descripcion']}");
    } catch (IAException $e) {
        hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'IA_ERROR', $e->getMessage(), 'IA', $ticketId);
        return;
    }

    $resuelto = str_contains($respuesta, 'RESUELTO');
    $textoLimpio = trim(preg_replace('/ESCALAR\s*$/', '', preg_replace('/RESUELTO\s*$/', '', $respuesta)));
    $tieneContacto = $ticket['solicitante_contacto'] && filter_var($ticket['solicitante_contacto'], FILTER_VALIDATE_EMAIL);

    if ($resuelto) {
        $enviado = false;
        if ($tieneContacto) {
            $html = plantilla_correo_html("Solución a tu ticket #{$ticketId}",
                "<p>Hola " . e($ticket['solicitante']) . ",</p><p>" . nl2br(e($textoLimpio)) . "</p><p class=\"small\">— Agente IA, Mesa de Ayuda NAVISSI ({$categoriaDetectada})</p>");
            $enviado = enviar_correo($ticket['solicitante_contacto'], "Solución a tu ticket #{$ticketId} — {$ticket['titulo']}", $html, $ticket['solicitante']);
        }
        $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo, visible_cliente, enviado_correo) VALUES (?,?,?,?,?,?)")
            ->execute([$ticketId, 'Agente IA', $textoLimpio, 'IA', 1, $enviado ? 1 : 0]);
        $pdo->prepare("UPDATE tickets SET estado = 'RESUELTO POR IA', actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticketId]);
        hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'AUTOGESTION_IA', "La IA clasificó el ticket como {$categoriaDetectada} y lo resolvió sola, usando solo el conocimiento de esa área, respondiendo al cliente por correo.", 'IA', $ticketId);
    } else {
        $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
            ->execute([$ticketId, 'Agente IA', $textoLimpio, 'IA']);

        if ($tecnicoDefault) {
            $pdo->prepare("UPDATE tickets SET asignado_a = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$tecnicoDefault, $ticketId]);
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
                ->execute([$ticketId, 'Sistema', "Clasificado como {$categoriaDetectada} y asignado automáticamente a {$tecnicoDefault}.", 'SISTEMA']);
            hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'ESCALADO_IA', "Clasificado como {$categoriaDetectada} y asignado a {$tecnicoDefault} (técnico por defecto de esa categoría, no de otra área).", 'IA', $ticketId);
        } else {
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
                ->execute([$ticketId, 'Sistema', "Clasificado como {$categoriaDetectada} - sin técnico por defecto configurado para esa categoría todavía.", 'SISTEMA']);
            hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'ESCALADO_IA', "Clasificado como {$categoriaDetectada} (no se pudo autogestionar; falta configurar técnico por defecto para esa categoría en Categorías de Tickets).", 'IA', $ticketId);
        }
    }
}
