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

/** Crea el ticket + comentario + registro de trazabilidad + dispara la IA, evitando duplicados por mensaje_id. */
function correo_crear_ticket_si_nuevo(PDO $pdo, string $mensajeId, string $buzon, string $remitente, string $remitenteNombre, string $asunto, string $cuerpo): bool {
    $stmt = $pdo->prepare("SELECT id FROM correos_a_tickets WHERE mensaje_id = ?");
    $stmt->execute([$mensajeId]);
    if ($stmt->fetchColumn()) return false;

    $slaLimite = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));
    $pdo->prepare("INSERT INTO tickets (titulo, descripcion, categoria, prioridad, solicitante, solicitante_contacto, sla_limite, origen)
        VALUES (?,?,?,?,?,?,?,?)")
        ->execute(["[{$buzon}] {$asunto}", $cuerpo, 'CORREO', 'MEDIA', $remitenteNombre, $remitente, $slaLimite, 'CORREO']);
    $ticketId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO tickets_comentarios (ticket_id, autor, comentario, tipo) VALUES (?,?,?,?)")
        ->execute([$ticketId, 'Sistema', "Ticket creado automáticamente desde el correo {$buzon}.", 'SISTEMA']);

    $pdo->prepare("INSERT INTO correos_a_tickets (mensaje_id, buzon, remitente, asunto, ticket_id) VALUES (?,?,?,?,?)")
        ->execute([$mensajeId, $buzon, $remitente, $asunto, $ticketId]);

    hoja_vida_registrar($pdo, 'TICKET', (string) $ticketId, 'CREADO_DESDE_CORREO', $asunto, $remitente, $ticketId);
    ia_triage_ticket($pdo, $ticketId);
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
                    $creado = correo_crear_ticket_si_nuevo($pdo, $correo['id'], $buzon, $remitente, $remitenteNombre, $asunto, $correo['bodyPreview'] ?? '');
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
