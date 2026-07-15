<?php
/**
 * Ingesta de correo -> tickets de Mesa de Ayuda, con triage de IA. Dos fuentes:
 *  - Microsoft Graph (mesadeayuda@navissi.com y cualquier otro buzón del tenant O365)
 *  - IMAP directo (mesadeayuda@grupo10z.com.co, en el hosting de correo de FreeHosting)
 * Pensado para llamarse tanto desde el botón manual en modules/correo_tickets.php
 * como desde api_correo_mesadeayuda.php (cron / tarea programada), sin duplicar código.
 */
require_once __DIR__ . '/graph_client.php';
require_once __DIR__ . '/imap_simple.php';
require_once __DIR__ . '/ia_triage.php';
require_once __DIR__ . '/mailer.php';

function correo_config_general_asegurar_tabla(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS config_general (clave TEXT PRIMARY KEY, valor TEXT)");
}

function obtener_buzones(PDO $pdo): array {
    correo_config_general_asegurar_tabla($pdo);
    $stmt = $pdo->query("SELECT valor FROM config_general WHERE clave = 'buzones_mesa_ayuda'");
    $v = $stmt ? $stmt->fetchColumn() : null;
    if ($v) return array_filter(array_map('trim', explode(',', $v)));
    return ['mesadeayuda@navissi.com'];
}

function guardar_buzones_mesa_ayuda(PDO $pdo, ?string $buzones): void {
    correo_config_general_asegurar_tabla($pdo);
    $pdo->prepare("INSERT INTO config_general (clave, valor) VALUES ('buzones_mesa_ayuda', ?) ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor")
        ->execute([$buzones]);
}

function config_imap(PDO $pdo): ?array {
    correo_config_general_asegurar_tabla($pdo);
    $stmt = $pdo->query("SELECT valor FROM config_general WHERE clave = 'imap_mesa_ayuda'");
    $v = $stmt ? $stmt->fetchColumn() : null;
    return $v ? json_decode($v, true) : null;
}

function guardar_config_imap(PDO $pdo, array $cfg): void {
    correo_config_general_asegurar_tabla($pdo);
    $pdo->prepare("INSERT INTO config_general (clave, valor) VALUES ('imap_mesa_ayuda', ?) ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor")
        ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
}

/**
 * Asigna automáticamente el técnico con menos tickets abiertos entre los roles
 * de soporte (TI, COORDINADOR). Devuelve el nombre asignado o null si no hay
 * ningún técnico activo disponible (el ticket queda sin asignar, como antes).
 */
function usuario_soporte_es_prueba(array $usuario): bool {
    $email = strtolower(trim((string)($usuario['email'] ?? '')));
    $nombre = strtolower(trim((string)($usuario['nombre'] ?? '')));
    return str_ends_with($email, '.local') || str_starts_with($email, 'prueba.') || str_starts_with($nombre, 'prueba ');
}

function autoasignar_tecnico(PDO $pdo, ?string $area = null): ?string {
    $sql = "
        SELECT u.nombre,
               u.email,
               (SELECT COUNT(*) FROM tickets t WHERE t.asignado_a = u.nombre AND t.estado NOT IN ('CERRADO','RESUELTO')) AS carga
        FROM usuarios_sistema u
        WHERE u.activo = 1 AND u.rol IN ('TI','COORDINADOR','ANALISTA','ADMIN','DIRECTOR')
          AND u.email NOT LIKE '%.local' AND u.email NOT LIKE 'prueba.%' AND lower(u.nombre) NOT LIKE 'prueba %'";
    $params = [];
    if ($area) { $sql .= " AND lower(trim(COALESCE(u.area_responsable,'')))=lower(trim(?))"; $params[] = $area; }
    $sql .= "
        ORDER BY carga ASC, u.nombre ASC
        LIMIT 1";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $fila = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return ($fila && !usuario_soporte_es_prueba($fila)) ? $fila['nombre'] : null;
}

/** Envía al remitente original la confirmación de creación del ticket, con el técnico asignado si aplica. */
function correo_notificar_creacion(string $para, string $paraNombre, int $ticketId, string $asunto, ?string $tecnico, string $estado = 'ABIERTO', ?string $departamento = null): void {
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return;
    if ($estado === 'RESUELTO POR IA') {
        $lineaTecnico = '<p>Nuestro agente virtual revisó el caso y envió una solución automática. El ticket quedó resuelto por IA.</p>';
    } elseif ($tecnico) {
        $lineaTecnico = '<p>El agente virtual realizó la revisión inicial y escaló el caso a <strong>' . e($tecnico) . '</strong>'
            . ($departamento ? ' del departamento <strong>' . e($departamento) . '</strong>' : '') . '.</p>';
    } else {
        $lineaTecnico = '<p>Nuestro agente virtual inició la revisión y el diagnóstico. Solo lo asignará a una persona si no puede resolverlo automáticamente.</p>';
    }
    $cuerpo = "<p>Hola " . e($paraNombre) . ",</p><p>Recibimos tu solicitud y creamos el ticket <strong>#{$ticketId}</strong>: \"" . e($asunto) . "\".</p>{$lineaTecnico}<p>Te avisaremos por este mismo correo cuando haya novedades.</p>";
    enviar_correo($para, "Ticket #{$ticketId} creado - Mesa de Ayuda", $cuerpo, $paraNombre);
}

function correo_notificar_tecnico_respaldo(PDO $pdo, int $ticketId, ?string $tecnico, string $asunto): void {
    if (!$tecnico) return;
    $stmtEstado = $pdo->prepare("SELECT 1 FROM tickets_comentarios WHERE ticket_id = ? AND comentario LIKE 'Notificación de asignación:%' LIMIT 1");
    $stmtEstado->execute([$ticketId]);
    if ($stmtEstado->fetchColumn()) return; // ia_triage_ticket ya notificó al técnico.
    $stmt = $pdo->prepare('SELECT email, nombre FROM usuarios_sistema WHERE activo = 1 AND (nombre = ? OR email = ?) LIMIT 1');
    $stmt->execute([$tecnico, $tecnico]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fila || !filter_var($fila['email'] ?? '', FILTER_VALIDATE_EMAIL)) return;
    $html = plantilla_correo_html("Nuevo ticket asignado #{$ticketId}", '<p>Se te asignó el ticket <strong>#' . (int) $ticketId . ' — ' . e($asunto) . '</strong>.</p><p>La IA no está configurada o no está disponible; revisa el caso en NAVISSI.</p>');
    $ok = enviar_correo($fila['email'], "Nuevo ticket #{$ticketId} asignado", $html, $fila['nombre']);
    $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo, visible_cliente, enviado_correo) VALUES (?,?,?,?,?,?)")
        ->execute([$ticketId, 'Sistema', 'Notificación de respaldo al técnico: ' . ($ok ? 'enviada' : 'no enviada') . '.', 'SISTEMA', 0, $ok ? 1 : 0]);
}

/** Crea el ticket + comentario + registro de trazabilidad + dispara la IA, evitando duplicados por mensaje_id. */
/** Rebotes automáticos (buzón lleno, dirección inexistente, etc.) no deben crear tickets - son ruido, no una solicitud real de un usuario. */
function correo_es_rebote_automatico(string $remitente, string $asunto): bool {
    if (preg_match('/^(mailer-daemon|postmaster|mail delivery subsystem|no-?reply)/i', $remitente)) return true;
    if (preg_match('/^(undeliverable|non-?delivery|delivery status notification|mail delivery failed|returned mail|failure notice)/i', trim($asunto))) return true;
    return false;
}

/** Vincula automáticamente el ticket al inventario por serial/placa o por identidad del remitente. */
function correo_detectar_equipo(PDO $pdo,string $remitente,string $asunto,string $cuerpo): ?array {
    preg_match_all('/[A-Za-z0-9][A-Za-z0-9._-]{3,39}/', $asunto.' '.$cuerpo, $m);
    $buscar=$pdo->prepare("SELECT serial,sede_id FROM inventario WHERE lower(serial)=lower(?) OR lower(COALESCE(placa,''))=lower(?) LIMIT 1");
    foreach(array_unique($m[0]??[]) as $token){$buscar->execute([$token,$token]);if($e=$buscar->fetch(PDO::FETCH_ASSOC))return $e;}
    if(filter_var($remitente,FILTER_VALIDATE_EMAIL)){
        $q=$pdo->prepare("SELECT i.serial,i.sede_id FROM usuarios_sistema u JOIN inventario i ON lower(i.asignado_a)=lower(u.nombre) WHERE lower(u.email)=lower(?) AND u.activo=1 ORDER BY i.actualizado_en DESC LIMIT 1");
        $q->execute([$remitente]);if($e=$q->fetch(PDO::FETCH_ASSOC))return $e;
    }
    return null;
}

function correo_crear_ticket_si_nuevo(PDO $pdo, string $mensajeId, string $buzon, string $remitente, string $remitenteNombre, string $asunto, string $cuerpo): bool {
    $stmt = $pdo->prepare("SELECT id FROM correos_a_tickets WHERE mensaje_id = ?");
    $stmt->execute([$mensajeId]);
    if ($stmt->fetchColumn()) return false;
    // Nunca convertir en ticket un mensaje enviado por el mismo buzón que se
    // está leyendo. Las confirmaciones y alertas internas podrían regresar a
    // la bandeja y crear un ciclo infinito de tickets y respuestas.
    $esMensajePropio = strcasecmp(trim($remitente), trim($buzon)) === 0;
    if ($esMensajePropio || correo_es_rebote_automatico($remitente, $asunto)) {
        // Se marca como procesado igual (para no revisarlo de nuevo cada vez) pero sin crear ticket.
        $pdo->prepare("INSERT INTO correos_a_tickets (mensaje_id, buzon, remitente, asunto, ticket_id) VALUES (?,?,?,?,NULL)")
            ->execute([$mensajeId, $buzon, $remitente, $asunto]);
        return false;
    }

    $slaLimite = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));
    $equipoDetectado = correo_detectar_equipo($pdo,$remitente,$asunto,$cuerpo);
    // El cuerpo del correo es contenido NO confiable (lo escribe quien sea que
    // envie el correo) - se limpia con limpio_html() antes de guardarlo, porque
    // el detalle del ticket ahora renderiza descripcion como HTML (para que el
    // editor WYSIWYG funcione), y sin este saneamiento un correo malicioso
    // podria inyectar <script>/onerror en la pantalla de un tecnico de TI.
    $cuerpoSeguro = limpio_html($cuerpo) ?? '';
    $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, sede_id, solicitante, solicitante_contacto, asignado_a, sla_limite, origen, equipo_serial)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute(["[{$buzon}] {$asunto}", $cuerpoSeguro, 'CORREO', 'MEDIA', $equipoDetectado['sede_id']??null, $remitenteNombre, $remitente, null, $slaLimite, 'CORREO', $equipoDetectado['serial']??null]);
    $ticketId = $pdo->lastInsertId();

    $comentarioSistema = "Ticket creado automáticamente desde el correo {$buzon}. Enviado primero al agente virtual para revisión y diagnóstico.";
    $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
        ->execute([$ticketId, 'Sistema', $comentarioSistema, 'SISTEMA']);

    $pdo->prepare("INSERT INTO correos_a_tickets (mensaje_id, buzon, remitente, asunto, ticket_id) VALUES (?,?,?,?,?)")
        ->execute([$mensajeId, $buzon, $remitente, $asunto, $ticketId]);

    hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'CREADO_DESDE_CORREO', $asunto, $remitente, $ticketId);
    if($equipoDetectado) hoja_vida_registrar($pdo,'EQUIPO',$equipoDetectado['serial'],'TICKET_VINCULADO_DESDE_CORREO',$asunto,$remitente,(int)$ticketId);
    ia_triage_ticket($pdo, $ticketId);
    $estadoFinal = $pdo->prepare('SELECT estado,asignado_a,departamento FROM tickets WHERE id=?');
    $estadoFinal->execute([$ticketId]); $final = $estadoFinal->fetch(PDO::FETCH_ASSOC) ?: [];
    $tecnico = $final['asignado_a'] ?? null;
    correo_notificar_creacion($remitente, $remitenteNombre, $ticketId, $asunto, $tecnico, $final['estado'] ?? 'ABIERTO', $final['departamento'] ?? null);
    correo_notificar_tecnico_respaldo($pdo, (int) $ticketId, $tecnico, $asunto);
    return true;
}

/** Revisa Graph (buzones O365 configurados) + IMAP (si está configurado). Devuelve resumen. */
function sincronizar_correo_a_tickets(PDO $pdo): array {
    $creados = 0; $yaExistian = 0; $errores = [];

    if (ms365_configurado()) {
        $c = ms365_config();
        $gc = new GraphClient($c['tenant_id'], $c['client_id'], $c['client_secret']);
        foreach (obtener_buzones($pdo) as $buzon) {
            try {
                foreach ($gc->leerCorreosNoLeidos($buzon) as $correo) {
                    $remitente = $correo['from']['emailAddress']['address'] ?? 'desconocido';
                    $remitenteNombre = $correo['from']['emailAddress']['name'] ?? $remitente;
                    $asunto = $correo['subject'] ?? '(sin asunto)';
                    $cuerpoGraph = $correo['body']['content'] ?? ($correo['bodyPreview'] ?? '');
                    // Graph puede devolver HTML o texto según la preferencia
                    // del buzón; guardamos texto limpio para evitar markup en
                    // la descripción y en los prompts de IA.
                    $cuerpoGraph = trim(html_entity_decode(strip_tags((string) $cuerpoGraph), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $creado = correo_crear_ticket_si_nuevo($pdo, $correo['id'], $buzon, $remitente, $remitenteNombre, $asunto, $cuerpoGraph);
                    if ($creado) $creados++; else $yaExistian++;
                    // También marcar duplicados y rebotes procesados; de lo contrario
                    // quedan no leídos y se vuelven a consultar en cada ejecución.
                    $gc->marcarCorreoLeido($buzon, $correo['id']);
                }
            } catch (GraphClientException $e) {
                $errores[] = "{$buzon} (Microsoft 365): {$e->getMessage()}";
            }
        }
    }

    $cfgImap = config_imap($pdo);
    if ($cfgImap && !empty($cfgImap['host']) && !empty($cfgImap['usuario'])) {
        try {
            $imap = new ImapSimple($cfgImap['host'], (int) ($cfgImap['puerto'] ?: 993), $cfgImap['usuario'], $cfgImap['password']);
            foreach ($imap->buscarNoLeidos() as $id) {
                $msg = $imap->leerMensaje($id);
                $mensajeId = $cfgImap['usuario'] . '-imap-' . $id . '-' . md5($msg['asunto'] . $msg['remitente']);
                $creado = correo_crear_ticket_si_nuevo($pdo, $mensajeId, $cfgImap['usuario'], $msg['remitente'], $msg['remitente_nombre'], $msg['asunto'], $msg['cuerpo']);
                if ($creado) { $creados++; $imap->marcarLeido($id); }
                else $yaExistian++;
            }
            $imap->cerrar();
        } catch (ImapSimpleException $e) {
            $errores[] = "{$cfgImap['usuario']} (IMAP): {$e->getMessage()}";
        }
    }

    return ['creados' => $creados, 'ya_existian' => $yaExistian, 'errores' => $errores];
}
