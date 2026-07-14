<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/notificaciones.php';
requiere_roles(['ADMIN', 'TI', 'GERENCIA', 'CEO', 'DIRECTOR'], '../');
$pdo = db();
$msg = null;
$cfg = notificaciones_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if (!tiene_rol(['ADMIN', 'TI'])) {
        $msg = ['error', 'Solo TI puede cambiar canales o entregas.'];
    } elseif ($accion === 'guardar') {
        $hook = trim((string)($_POST['teams_webhook'] ?? ''));
        $nuevo = [
            'correo_habilitado' => !empty($_POST['correo_habilitado']),
            'correos_operacion' => trim((string)($_POST['correos_operacion'] ?? '')),
            'whatsapp_habilitado' => !empty($_POST['whatsapp_habilitado']),
            'whatsapp_destino' => preg_replace('/\D+/', '', (string)($_POST['whatsapp_destino'] ?? '')),
            'teams_habilitado' => !empty($_POST['teams_habilitado']),
            'teams_webhook' => $hook ?: (string)($cfg['teams_webhook'] ?? ''),
        ];
        $errores = notificaciones_config_validar($nuevo);
        if ($errores) {
            $msg = ['error', implode(' ', $errores)];
        } else {
            notificaciones_config_guardar($nuevo);
            $cfg = notificaciones_config();
            $msg = ['ok', 'Canales validados y guardados de forma cifrada.'];
        }
    } elseif ($accion === 'procesar') {
        $resultado = notificaciones_procesar($pdo, 50);
        $msg = $resultado['ocupada']
            ? ['error', 'La cola ya está siendo procesada por otra ejecución.']
            : ['ok', "Procesadas {$resultado['procesadas']}: {$resultado['enviadas']} enviadas, {$resultado['errores']} con reintento y {$resultado['agotadas']} fallidas."];
    } elseif ($accion === 'probar') {
        $canal = strtoupper((string)($_POST['canal'] ?? ''));
        $destino = match ($canal) {
            'CORREO' => (preg_split('/[,;\s]+/', (string)$cfg['correos_operacion'], -1, PREG_SPLIT_NO_EMPTY) ?: [])[0] ?? '',
            'WHATSAPP' => (string)$cfg['whatsapp_destino'],
            'TEAMS' => 'Operación NAVISSI',
            default => '',
        };
        $habilitado = match ($canal) {
            'CORREO' => (bool)$cfg['correo_habilitado'], 'WHATSAPP' => (bool)$cfg['whatsapp_habilitado'],
            'TEAMS' => (bool)$cfg['teams_habilitado'], default => false,
        };
        if (!$habilitado || !$destino) {
            $msg = ['error', 'Guarda y habilita el canal antes de probarlo.'];
        } else {
            $clave = 'PRUEBA_' . $canal . '_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));
            notificacion_encolar($pdo, $clave, $canal, $destino, 'Prueba de entrega NAVISSI', 'Canal operativo validado desde el Centro de Notificaciones.', ['tipo' => 'PRUEBA']);
            notificaciones_procesar($pdo, 100);
            $stmt = $pdo->prepare('SELECT estado,ultimo_error FROM notificaciones_cola WHERE clave_unica=?');
            $stmt->execute([$clave]);
            $prueba = $stmt->fetch(PDO::FETCH_ASSOC);
            $msg = ($prueba['estado'] ?? '') === 'ENVIADA'
                ? ['ok', "Prueba de {$canal} entregada correctamente."]
                : ['error', "La prueba de {$canal} no se entregó: " . ($prueba['ultimo_error'] ?? 'revisa la configuración.')];
        }
    } elseif ($accion === 'reintentar') {
        $pdo->prepare("UPDATE notificaciones_cola SET estado='PENDIENTE',intentos=0,proximo_intento_en=NULL,ultimo_error=NULL WHERE id=? AND estado IN('ERROR','FALLIDA')")->execute([(int)$_POST['id']]);
        $msg = ['ok', 'Notificación preparada para un nuevo intento.'];
    } elseif ($accion === 'descartar') {
        $pdo->prepare("UPDATE notificaciones_cola SET estado='DESCARTADA',proximo_intento_en=NULL WHERE id=? AND estado!='ENVIADA'")->execute([(int)$_POST['id']]);
        $msg = ['ok', 'Notificación descartada.'];
    }
}

$counts = $pdo->query('SELECT estado,COUNT(*) FROM notificaciones_cola GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
$items = $pdo->query('SELECT * FROM notificaciones_cola ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$smtpCfg = smtp_config() ?: [];
$smtp = !empty($smtpCfg['host']) && !empty($smtpCfg['usuario']) && !empty($smtpCfg['password']);
$wa = leer_config_json(WHATSAPP_CONFIG_PATH) ?: [];
$waok = !empty($wa['token']) && !empty($wa['phone_number_id']);
$teams = !empty($cfg['teams_webhook']);
layout_inicio('Centro de Notificaciones', 'Centro de Notificaciones', '../');
?>
<div class="page-kicker">CANALES · ENTREGA Y AUDITORÍA</div>
<h1><?= icon('send', 'icon-lg') ?> Centro de Notificaciones</h1>
<p class="subtitle">Cola auditable con validación, bloqueo de concurrencia y reintentos para correo, WhatsApp y Teams.</p>
<?php if ($msg): ?><div class="msg-<?= e($msg[0]) ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards notification-cards">
<?php foreach (['PENDIENTE' => 'Pendientes', 'ENVIADA' => 'Enviadas', 'ERROR' => 'Con reintento', 'FALLIDA' => 'Fallidas', 'DESCARTADA' => 'Descartadas'] as $estado => $etiqueta): ?>
    <div class="card <?= in_array($estado, ['ERROR','FALLIDA'], true) ? 'card-err' : ($estado === 'ENVIADA' ? 'card-ok' : '') ?>">
        <div class="num"><?= (int)($counts[$estado] ?? 0) ?></div><div class="label"><?= $etiqueta ?></div>
    </div>
<?php endforeach; ?>
</div>

<div class="panel-grid-2">
<div class="panel">
    <h3>Canales</h3>
    <form method="post">
        <input type="hidden" name="accion" value="guardar">
        <label class="channel-row"><input type="checkbox" name="correo_habilitado" <?= $cfg['correo_habilitado'] ? 'checked' : '' ?>><span><strong>Correo</strong><small><?= $smtp ? 'SMTP configurado' : 'Falta SMTP' ?></small></span></label>
        <label>Destinatarios operativos</label><input name="correos_operacion" value="<?= e($cfg['correos_operacion']) ?>" placeholder="ti@navissi.com, operaciones@navissi.com">
        <label class="channel-row"><input type="checkbox" name="whatsapp_habilitado" <?= $cfg['whatsapp_habilitado'] ? 'checked' : '' ?>><span><strong>WhatsApp</strong><small><?= $waok ? 'Cloud API configurada' : 'Faltan credenciales' ?></small></span></label>
        <input name="whatsapp_destino" value="<?= e($cfg['whatsapp_destino']) ?>" placeholder="573001234567">
        <label class="channel-row"><input type="checkbox" name="teams_habilitado" <?= $cfg['teams_habilitado'] ? 'checked' : '' ?>><span><strong>Teams</strong><small><?= $teams ? 'Webhook configurado' : 'Falta webhook' ?></small></span></label>
        <input type="password" name="teams_webhook" placeholder="Dejar vacío para conservar">
        <button>Validar y guardar canales</button>
    </form>
</div>
<div class="panel">
    <h3>Estado de entrega</h3>
    <?php foreach ([['CORREO', 'Correo', $smtp, $cfg['correo_habilitado']], ['WHATSAPP', 'WhatsApp', $waok, $cfg['whatsapp_habilitado']], ['TEAMS', 'Teams', $teams, $cfg['teams_habilitado']]] as [$codigo, $nombre, $listo, $activo]): ?>
        <div class="delivery-status"><span class="status-dot <?= $listo && $activo ? 'ok' : 'off' ?>"></span><strong><?= $nombre ?></strong><small><?= !$listo ? 'No configurado' : ($activo ? 'Activo' : 'Disponible, inactivo') ?></small>
        <?php if ($listo && $activo && tiene_rol(['ADMIN','TI'])): ?><form method="post" class="inline"><input type="hidden" name="accion" value="probar"><input type="hidden" name="canal" value="<?= $codigo ?>"><button class="link-btn">Probar</button></form><?php endif; ?></div>
    <?php endforeach; ?>
    <?php if (tiene_rol(['ADMIN','TI'])): ?><form method="post" style="margin-top:18px"><input type="hidden" name="accion" value="procesar"><button><?= icon('send') ?> Procesar cola ahora</button></form><?php endif; ?>
</div>
</div>

<div class="panel"><h3>Historial de entrega</h3><table class="tabla-tickets"><thead><tr><th>Canal</th><th>Destino</th><th>Mensaje</th><th>Estado</th><th>Intentos</th><th></th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><?= e($item['canal']) ?></td><td><?= e($item['destinatario']) ?></td><td><strong><?= e($item['asunto']) ?></strong><?php if ($item['ultimo_error']): ?><br><span class="small"><?= e($item['ultimo_error']) ?></span><?php endif; ?></td><td><span class="badge <?= $item['estado'] === 'ENVIADA' ? 'badge-activo' : (in_array($item['estado'], ['ERROR','FALLIDA'], true) ? 'badge-err' : 'badge-warn') ?>"><?= e($item['estado']) ?></span></td><td><?= (int)$item['intentos'] ?></td><td>
<?php if (tiene_rol(['ADMIN','TI']) && in_array($item['estado'], ['ERROR','FALLIDA'], true)): ?><form method="post" class="inline"><input type="hidden" name="accion" value="reintentar"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="link-btn">Reintentar</button></form><?php endif; ?>
<?php if (tiene_rol(['ADMIN','TI']) && !in_array($item['estado'], ['ENVIADA','DESCARTADA'], true)): ?><form method="post" class="inline"><input type="hidden" name="accion" value="descartar"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="link-btn">Descartar</button></form><?php endif; ?>
</td></tr><?php endforeach; ?>
<?php if (!$items): ?><tr><td colspan="6" class="small">Todavía no hay entregas registradas.</td></tr><?php endif; ?>
</tbody></table></div>
<?php layout_fin(); ?>
