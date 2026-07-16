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

/** Agente local de respaldo: enruta aun cuando el proveedor externo de IA no esté disponible. */
function ia_clasificar_determinista(array $ticket, array $categorias): string {
    $texto = mb_strtoupper(($ticket['titulo'] ?? '') . ' ' . ($ticket['descripcion'] ?? ''), 'UTF-8');
    $reglas = [
        'SIESA / FACTURACIÓN' => ['SIESA','FACTURA','FACTURACIÓN','CONTABLE','CONTABILIDAD','PROVEEDOR','PAGO','NOTA CRÉDITO'],
        'LOGÍSTICA Y BODEGA' => ['LOGÍSTICA','LOGISTICA','BODEGA','DESPACHO','TRASLADO','TRANSPORTE','RECEPCIÓN','RECEPCION','ALMACENAMIENTO','UBICACIÓN','UBICACION'],
        'INFRAESTRUCTURA' => ['INFRAESTRUCTURA','INTERNET','WIFI','RED','ROUTER','SWITCH','SERVIDOR','IMPRESORA','EQUIPO','COMPUTADOR','HARDWARE'],
        'TECNOLOGÍA Y SOFTWARE' => ['SOFTWARE','PROGRAMA','APLICACIÓN','ACCESO','CONTRASEÑA','CLAVE','MICROSOFT','OFFICE','CORREO'],
        'GESTIÓN HUMANA' => ['NÓMINA','NOMINA','VACACIONES','CONTRATO','INCAPACIDAD','RRHH','RECURSOS HUMANOS'],
        'SERVICIO AL CLIENTE' => ['CLIENTE','PQRS','QUEJA','RECLAMO','DEVOLUCIÓN','GARANTÍA'],
        'COMERCIAL' => ['VENTA','COMERCIAL','COTIZACIÓN','PEDIDO','DESCUENTO'],
    ];
    foreach ($reglas as $categoria=>$palabras) {
        foreach ($palabras as $palabra) if (str_contains($texto, $palabra)) return $categoria;
    }
    foreach ($categorias as $c) if (strcasecmp($c['nombre'], 'SOPORTE GENERAL') === 0) return $c['nombre'];
    return $categorias[0]['nombre'] ?? 'SOPORTE GENERAL';
}

function ia_autoasignar_tecnico(PDO $pdo, ?string $departamento): ?string {
    $sql="SELECT u.nombre,(SELECT COUNT(*) FROM tickets t WHERE t.asignado_a=u.nombre AND t.estado NOT IN('CERRADO','RESUELTO POR IA')) carga FROM usuarios_sistema u WHERE u.activo=1 AND u.rol IN('TI','COORDINADOR','ANALISTA','ADMIN','DIRECTOR') AND u.email NOT LIKE '%.local' AND u.email NOT LIKE 'prueba.%' AND lower(u.nombre) NOT LIKE 'prueba %'";
    $args=[];if($departamento){$sql.=" AND lower(trim(COALESCE(u.area_responsable,'')))=lower(trim(?))";$args[]=$departamento;}
    $sql.=' ORDER BY carga,u.nombre LIMIT 1';$q=$pdo->prepare($sql);$q->execute($args);$nombre=$q->fetchColumn();return $nombre!==false?(string)$nombre:null;
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
    $configPath = private_path('ia_config.json');
    $config = file_exists($configPath) ? leer_config_json($configPath) : [];

    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) return;

    $categorias = $pdo->query("SELECT nombre, descripcion, area_responsable, tecnico_default FROM categorias_tickets WHERE activa = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    if (!$categorias) return; // sin categorías configuradas, no hay a qué clasificar

    $client = null;
    $categoriaDetectada = ia_clasificar_determinista($ticket, $categorias);
    $proveedor = $config['proveedor'] ?? 'anthropic';
    // El proveedor 'local'/'ollama' no necesita api_key (habla con Ollama en
    // la misma red, sin costo); los demás sí la requieren.
    if (!empty($config['api_key']) || in_array($proveedor, ['local', 'ollama'], true)) {
        try {
            $client = new IAClient($proveedor, $config['api_key'] ?? '');
            $categoriaDetectada = ia_clasificar_categoria($client, $ticket, $categorias);
        } catch (IAException $e) {
            $client = null;
            hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'IA_ERROR', $e->getMessage() . ' Se aplicó el agente local de respaldo (reglas).', 'IA', $ticketId);
        }
    }

    $catInfo = current(array_filter($categorias, fn($c) => $c['nombre'] === $categoriaDetectada)) ?: null;
    $tecnicoDefault = $catInfo['tecnico_default'] ?? null;
    $departamento = $catInfo['area_responsable'] ?? null;
    $diagnosticoInicial = "Revisión automática completada. Clasificación: {$categoriaDetectada}" . ($departamento ? ". Departamento responsable: {$departamento}." : '.');
    $pdo->prepare("UPDATE tickets SET categoria=?,departamento=?,diagnostico_ia=?,confianza_ia=?,actualizado_en=CURRENT_TIMESTAMP WHERE id=?")
        ->execute([$categoriaDetectada,$departamento,$diagnosticoInicial,$client ? 85 : 65,$ticketId]);
    $pdo->prepare("INSERT INTO tickets_comentarios(ticket_id,autor,comentario,tipo) VALUES(?,?,?,'IA')")
        ->execute([$ticketId,'Agente virtual',$diagnosticoInicial . ' Buscando una solución segura en la base de conocimiento.']);

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

    if ($client) {
        try {
            $respuesta = $client->preguntar($systemPrompt, "Título: {$ticket['titulo']}\nDescripción: {$ticket['descripcion']}");
        } catch (IAException $e) {
            hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'IA_ERROR', $e->getMessage(), 'IA', $ticketId);
            $respuesta = 'No fue posible completar una solución automática confiable. El caso requiere revisión humana del departamento responsable. ESCALAR';
        }
    } else {
        // El agente local solo autogestiona cuando encuentra una coincidencia
        // fuerte con un artículo aprobado de la misma categoría.
        $textoTicket = mb_strtoupper(($ticket['titulo'] ?? '').' '.($ticket['descripcion'] ?? ''),'UTF-8');
        $mejorArticulo = null; $mejorPuntaje = 0;
        foreach ($articulos as $articulo) {
            $palabras = array_unique(preg_split('/[^\pL\pN]+/u', mb_strtoupper($articulo['titulo'],'UTF-8'), -1, PREG_SPLIT_NO_EMPTY));
            $puntaje = 0;
            foreach ($palabras as $palabra) if (mb_strlen($palabra) >= 5 && str_contains($textoTicket,$palabra)) $puntaje++;
            if ($puntaje > $mejorPuntaje) { $mejorPuntaje=$puntaje; $mejorArticulo=$articulo; }
        }
        $respuesta = ($mejorArticulo && $mejorPuntaje >= 2 && mb_strlen(trim($mejorArticulo['contenido'])) >= 50)
            ? trim($mejorArticulo['contenido'])."\nRESUELTO"
            : ($articulos
                ? 'La base de conocimiento no contiene una coincidencia suficientemente segura. El caso será escalado con el diagnóstico preliminar. ESCALAR'
                : 'No existe todavía una solución aprobada en la base de conocimiento para este caso. Se requiere revisión humana del departamento responsable. ESCALAR');
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

        // El técnico configurado debe ser una cuenta real, activa y del mismo departamento.
        if ($tecnicoDefault) {
            $validar = $pdo->prepare("SELECT nombre,email FROM usuarios_sistema WHERE activo=1 AND (nombre=? OR email=?) AND email NOT LIKE '%.local' AND email NOT LIKE 'prueba.%' AND lower(nombre) NOT LIKE 'prueba %' AND (? IS NULL OR lower(trim(COALESCE(area_responsable,'')))=lower(trim(?))) LIMIT 1");
            $validar->execute([$tecnicoDefault,$tecnicoDefault,$departamento,$departamento]);
            if (!$validar->fetch(PDO::FETCH_ASSOC)) $tecnicoDefault = null;
        }
        if (!$tecnicoDefault) $tecnicoDefault = ia_autoasignar_tecnico($pdo, $departamento);

        if ($tecnicoDefault) {
            $pdo->prepare("UPDATE tickets SET asignado_a = ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?")->execute([$tecnicoDefault, $ticketId]);
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
                ->execute([$ticketId, 'Sistema', "Clasificado como {$categoriaDetectada} y asignado automáticamente a {$tecnicoDefault}.", 'SISTEMA']);
            hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'ESCALADO_IA', "Clasificado como {$categoriaDetectada} y asignado a {$tecnicoDefault} (técnico por defecto de esa categoría, no de otra área).", 'IA', $ticketId);

            $enviadoAsignacion = false;
            if ($tieneContacto) {
                $htmlAsig = plantilla_correo_html("Tu ticket #{$ticketId} ya fue asignado",
                    "<p>Hola " . e($ticket['solicitante']) . ",</p>"
                    . "<p>Tu solicitud <strong>#{$ticketId} — " . e($ticket['titulo']) . "</strong> fue clasificada como <strong>{$categoriaDetectada}</strong> "
                    . "y quedó asignada a <strong>" . e($tecnicoDefault) . "</strong>, quien te contactará pronto.</p>"
                    . "<p class=\"small\">Puedes hacer seguimiento con el número de ticket #{$ticketId}.</p>");
                $enviadoAsignacion = enviar_correo($ticket['solicitante_contacto'], "Tu ticket #{$ticketId} fue asignado a {$tecnicoDefault}", $htmlAsig, $ticket['solicitante']);
            }
            // Notifica también al técnico real (si está registrado en NAVISSI).
            // El campo tecnico_default es texto histórico, por eso se resuelve
            // por nombre y nunca se intenta adivinar un dominio de correo.
            $stmtTecnico = $pdo->prepare("SELECT email, nombre FROM usuarios_sistema WHERE activo=1 AND (nombre=? OR email=?) AND email NOT LIKE '%.local' AND email NOT LIKE 'prueba.%' AND lower(nombre) NOT LIKE 'prueba %' LIMIT 1");
            $stmtTecnico->execute([$tecnicoDefault, $tecnicoDefault]);
            $tecnico = $stmtTecnico->fetch(PDO::FETCH_ASSOC) ?: null;
            $enviadoTecnico = false;
            if ($tecnico && filter_var($tecnico['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $htmlTecnico = plantilla_correo_html("Nuevo ticket asignado #{$ticketId}",
                    "<p>Hola " . e($tecnico['nombre']) . ",</p>"
                    . "<p>Se te asignó el ticket <strong>#{$ticketId} — " . e($ticket['titulo']) . "</strong>.</p>"
                    . "<p>Categoría: <strong>" . e($categoriaDetectada) . "</strong>. Prioridad: <strong>" . e($ticket['prioridad']) . "</strong>.</p>"
                    . "<p class=\"small\">Ingresa a NAVISSI para atenderlo y dejar trazabilidad.</p>");
                $enviadoTecnico = enviar_correo($tecnico['email'], "Nuevo ticket #{$ticketId} asignado", $htmlTecnico, $tecnico['nombre']);
            }
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo, visible_cliente, enviado_correo) VALUES (?,?,?,?,?,?)")
                ->execute([$ticketId, 'Sistema', "Notificación de asignación: cliente " . ($enviadoAsignacion ? 'enviado' : 'no enviado') . "; técnico " . ($enviadoTecnico ? 'enviado' : 'no enviado') . ".", 'SISTEMA', 1, ($enviadoAsignacion || $enviadoTecnico) ? 1 : 0]);
        } else {
            $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
                ->execute([$ticketId, 'Sistema', "Clasificado como {$categoriaDetectada} - sin técnico por defecto configurado para esa categoría todavía.", 'SISTEMA']);
            hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'ESCALADO_IA', "Clasificado como {$categoriaDetectada} (no se pudo autogestionar; falta configurar técnico por defecto para esa categoría en Categorías de Tickets).", 'IA', $ticketId);
        }
    }
}
