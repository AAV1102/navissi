<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();

$alertas = [];

// 1. Licencias M365 agotadas o casi agotadas
$stmt = $pdo->query("SELECT nombre, compradas, consumidas FROM ms365_licencias WHERE compradas > 0 AND compradas < 100000");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
    $libres = $l['compradas'] - $l['consumidas'];
    if ($libres <= 0) {
        $alertas[] = ['critica', "Licencia \"{$l['nombre']}\" agotada: {$l['consumidas']}/{$l['compradas']} en uso.", 'microsoft365.php'];
    } elseif ($libres <= 2) {
        $alertas[] = ['advertencia', "Licencia \"{$l['nombre']}\" casi agotada: solo {$libres} disponible(s).", 'microsoft365.php'];
    }
}

// 2. Cuentas Microsoft 365 bloqueadas
$bloqueadas = (int) $pdo->query("SELECT COUNT(*) FROM ms365_usuarios WHERE cuenta_activa = 0")->fetchColumn();
if ($bloqueadas > 0) {
    $alertas[] = ['info', "{$bloqueadas} cuenta(s) de Microsoft 365 bloqueada(s) - revisa si corresponde a retiros pendientes de desactivar en otros sistemas.", 'microsoft365.php'];
}

// 3. Tickets urgentes sin asignar
$stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE prioridad = 'URGENTE' AND (asignado_a IS NULL OR asignado_a = '') AND estado != 'CERRADO'");
$n = (int) $stmt->fetchColumn();
if ($n > 0) $alertas[] = ['critica', "{$n} ticket(s) URGENTE sin asignar.", 'mesa_ayuda.php?prioridad=URGENTE'];

// 4. Tickets abiertos hace más de 3 días sin actividad
$stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado != 'CERRADO' AND datetime(actualizado_en) < datetime('now', '-3 days')");
$n = (int) $stmt->fetchColumn();
if ($n > 0) $alertas[] = ['advertencia', "{$n} ticket(s) sin actividad hace más de 3 días.", 'mesa_ayuda.php'];

// 5. Equipos en reparación hace más de 30 días
$stmt = $pdo->query("SELECT COUNT(*) FROM inventario WHERE estado = 'EN REPARACION' AND datetime(actualizado_en) < datetime('now', '-30 days')");
$n = (int) $stmt->fetchColumn();
if ($n > 0) $alertas[] = ['advertencia', "{$n} equipo(s) llevan más de 30 días \"en reparación\" - revisar si siguen así de verdad.", 'inventario.php'];

// 6. Sedes sin ninguna credencial registrada (wifi/siesa/correo)
$stmt = $pdo->query("SELECT COUNT(*) FROM sedes s WHERE s.estado='ACTIVO' AND NOT EXISTS (SELECT 1 FROM credenciales c WHERE c.sede_id = s.id)");
$n = (int) $stmt->fetchColumn();
if ($n > 0) $alertas[] = ['info', "{$n} sede(s) activa(s) sin ninguna credencial registrada (wifi, Siesa, etc).", 'sedes.php'];

// 7. Sedes sin equipos registrados
$stmt = $pdo->query("SELECT COUNT(*) FROM sedes s WHERE s.estado='ACTIVO' AND NOT EXISTS (SELECT 1 FROM inventario i WHERE i.sede_id = s.id)");
$n = (int) $stmt->fetchColumn();
if ($n > 0) $alertas[] = ['info', "{$n} sede(s) activa(s) sin ningún equipo registrado en el inventario.", 'inventario.php'];

// 8. Solicitudes de actualización de tienda pendientes de revisar
$stmt = $pdo->query("SELECT COUNT(*) FROM solicitudes_actualizacion WHERE estado = 'PENDIENTE'");
$n = (int) $stmt->fetchColumn();
if ($n > 0) $alertas[] = ['advertencia', "{$n} solicitud(es) de actualización enviadas por tiendas esperando revisión.", 'solicitudes.php'];

// 9. Secreto de Microsoft configurado (recordatorio de rotación, no hay fecha de expiración real disponible aquí)
if (ms365_configurado()) {
    $alertas[] = ['info', "Conexión Microsoft 365 activa. Recuerda rotar el Client Secret en Azure antes de que expire.", 'microsoft365.php'];
}

usort($alertas, fn($a, $b) => array_search($a[0], ['critica','advertencia','info']) <=> array_search($b[0], ['critica','advertencia','info']));

$conteo = ['critica' => 0, 'advertencia' => 0, 'info' => 0];
foreach ($alertas as $a) $conteo[$a[0]]++;

layout_inicio('Automatizaciones', 'Automatizaciones', '../');
?>
<h1>Automatizaciones y Alertas</h1>
<p class="subtitle">Reglas que se evalúan en vivo cada vez que abres esta página, sobre tus datos reales - sin necesidad de configurar nada.</p>

<div class="cards">
    <div class="card" style="border-left-color:#a12b1f;"><div class="num"><?= $conteo['critica'] ?></div><div class="label">Críticas</div></div>
    <div class="card" style="border-left-color:#c99a1f;"><div class="num"><?= $conteo['advertencia'] ?></div><div class="label">Advertencias</div></div>
    <div class="card"><div class="num"><?= $conteo['info'] ?></div><div class="label">Informativas</div></div>
</div>

<div class="panel">
    <?php if (!$alertas): ?>
        <p class="small">✅ No hay alertas activas en este momento. Todo dentro de lo esperado.</p>
    <?php else: foreach ($alertas as [$nivel, $texto, $link]): ?>
        <div class="msg-<?= $nivel === 'critica' ? 'error' : ($nivel === 'advertencia' ? 'error' : 'ok') ?>" style="<?= $nivel==='advertencia' ? 'background:#fff3cd;color:#7a5c00;' : ($nivel==='info' ? 'background:#e7f1fb;color:#1f4e78;' : '') ?>">
            <?= e($texto) ?> <a href="<?= e($link) ?>" style="margin-left:8px;font-weight:600;">Ver →</a>
        </div>
    <?php endforeach; endif; ?>
</div>

<div class="panel">
    <h3>Reglas activas (se evalúan automáticamente, sin configuración)</h3>
    <table>
        <tr><th>Regla</th><th>Qué hace</th></tr>
        <tr><td>Licencias M365 agotándose</td><td>Avisa cuando quedan ≤2 licencias libres de un tipo, o ya están en 0.</td></tr>
        <tr><td>Cuentas M365 bloqueadas</td><td>Cuenta cuántas cuentas están desactivadas en Microsoft.</td></tr>
        <tr><td>Tickets urgentes sin asignar</td><td>Avisa si hay tickets prioridad URGENTE sin técnico asignado.</td></tr>
        <tr><td>Tickets estancados</td><td>Avisa de tickets abiertos sin ninguna actividad en 3+ días.</td></tr>
        <tr><td>Equipos "en reparación" por mucho tiempo</td><td>Avisa de equipos marcados así hace 30+ días - probable dato desactualizado.</td></tr>
        <tr><td>Sedes sin credenciales / sin equipos</td><td>Detecta huecos de información por sede.</td></tr>
        <tr><td>Solicitudes de tiendas pendientes</td><td>Avisa cuando una tienda reportó una actualización que TI aún no revisó.</td></tr>
    </table>
    <p class="small" style="margin-top:10px;">
        ¿Necesitas que alguna de estas alertas también llegue por correo o WhatsApp automáticamente (no solo al abrir esta pantalla)?
        Eso requiere un disparador externo (tarea programada de Windows + SMTP o API de WhatsApp) - dime cuál priorizamos y lo conectamos.
    </p>
</div>
<?php layout_fin(); ?>
