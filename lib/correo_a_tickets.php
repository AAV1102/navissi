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
function autoasignar_tecnico(PDO $pdo): ?string {
    $stmt = $pdo->query("
        SELECT u.nombre,
               (SELECT COUNT(*) FROM tickets t WHERE t.asignado_a = u.nombre AND t.estado NOT IN ('CERRADO','RESUELTO')) AS carga
        FROM usuarios_sistema u
        WHERE u.activo = 1 AND u.rol IN ('TI','COORDINADOR')
        ORDER BY carga ASC, u.nombre ASC
        LIMIT 1
    ");
    $fila = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $fila['nombre'] ?? null;
}

/** Envía al remitente original la confirmación de creación del ticket, con el técnico asignado si aplica. */
function correo_notificar_creacion(string $para, string $paraNombre, int $ticketId, string $asunto, ?string $tecnico): void {
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return;
    $lineaTecnico = $tecnico
        ? "<p>Tu caso fue asignado al técnico <strong>{$tecnico}</strong>, quien se pondrá en contacto contigo.</p>"
        : "<p>Tu caso está en cola y será asignado a un técnico en breve.</p>";
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
function correo_crear_ticket_si_nuevo(PDO $pdo, string $mensajeId, string $buzon, string $remitente, string $remitenteNombre, string $asunto, string $cuerpo): bool {
    $stmt = $pdo->prepare("SELECT id FROM correos_a_tickets WHERE mensaje_id = ?");
    $stmt->execute([$mensajeId]);
    if ($stmt->fetchColumn()) return false;

    $tecnico = autoasignar_tecnico($pdo);
    $slaLimite = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));
    $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, solicitante, solicitante_contacto, asignado_a, sla_limite, origen)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute(["[{$buzon}] {$asunto}", $cuerpo, 'CORREO', 'MEDIA', $remitenteNombre, $remitente, $tecnico, $slaLimite, 'CORREO']);
    $ticketId = $pdo->lastInsertId();

    $comentarioSistema = $tecnico
        ? "Ticket creado automáticamente desde el correo {$buzon}. Asignado automáticamente a {$tecnico}."
        : "Ticket creado automáticamente desde el correo {$buzon}. Sin técnico disponible para asignación automática.";
    $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
        ->execute([$ticketId, 'Sistema', $comentarioSistema, 'SISTEMA']);

    $pdo->prepare("INSERT INTO correos_a_tickets (mensaje_id, buzon, remitente, asunto, ticket_id) VALUES (?,?,?,?,?)")
        ->execute([$mensajeId, $buzon, $remitente, $asunto, $ticketId]);

    hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'CREADO_DESDE_CORREO', $asunto, $remitente, $ticketId);
    ia_triage_ticket($pdo, $ticketId);
    correo_notificar_creacion($remitente, $remitenteNombre, $ticketId, $asunto, $tecnico);
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
                    if ($creado) { $creados++; $gc->marcarCorreoLeido($buzon, $correo['id']); }
                    else $yaExistian++;
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
