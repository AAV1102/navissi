<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg=null;if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['accion']??'')==='revocar'&&tiene_rol(['ADMIN','TI'])){$pdo->prepare("UPDATE agentes_tokens SET activo=0 WHERE id=?")->execute([(int)$_POST['id']]);$msg=['ok','Credencial revocada.'];}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['accion']??'')==='eliminar'&&tiene_rol(['ADMIN','TI'])){$pdo->prepare("DELETE FROM agentes_tokens WHERE id=?")->execute([(int)$_POST['id']]);$msg=['ok','Credencial eliminada de la lista.'];}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_rustdesk' && tiene_rol(['ADMIN', 'TI'])) {
    rustdesk_config_guardar((string) ($_POST['rustdesk_servidor'] ?? ''));
    $msg = ['ok', 'Servidor RustDesk actualizado. Los próximos instaladores que generes ya lo incluirán.'];
}
$base = rtrim(navissi_url_publica(), '/');
$rustdeskServidorGuardado = trim((string) (rustdesk_config_leer()['servidor'] ?? ''));

$ultimos = $pdo->query("SELECT * FROM inventario WHERE fuente = 'Agente automático' ORDER BY actualizado_en DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$tokensAgente=$pdo->query("SELECT a.*,s.nombre sede_nombre FROM agentes_tokens a LEFT JOIN sedes s ON s.id=a.sede_id ORDER BY a.id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);$sedesAgente=$pdo->query("SELECT nombre FROM sedes WHERE estado='ACTIVO' ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

layout_inicio('Agente de Inventario', 'Agente de inventario', '../');
?>
<h1><?= icon('zap','icon-lg') ?> Instalar el Agente NAVISSI</h1>
<p class="subtitle">Un script liviano (tipo GLPI/OCS Inventory) que corre en cada equipo, lee su hardware real y lo envía aquí automáticamente.</p>
<?php if($msg):?><div class="msg-<?=e($msg[0])?>"><?=e($msg[1])?></div><?php endif;?>

<div class="pasos-wizard">
    <div class="paso-wizard activo"><span class="paso-punto">1</span> Seleccionar SO</div>
    <div class="paso-wizard"><span class="paso-punto">2</span> Asignar sede</div>
    <div class="paso-wizard"><span class="paso-punto">3</span> Instalar agente</div>
</div>

<div class="panel">
    <h3>Seleccionar un sistema operativo</h3>
    <div class="so-selector">
        <label class="so-opcion activa"><input type="radio" name="so" checked> <?= icon('inventory') ?> Windows (.ps1)</label>
        <label class="so-opcion deshabilitada" title="Aún no disponible"><input type="radio" name="so" disabled> Mac — próximamente</label>
        <label class="so-opcion deshabilitada" title="Aún no disponible"><input type="radio" name="so" disabled> Linux — próximamente</label>
    </div>
    <p class="small" style="margin-top:10px;">Descarga e instala el agente en todos los equipos que quieras supervisar.</p>
</div>

<?php if (tiene_rol(['ADMIN', 'TI'])): ?>
<div class="panel" style="border-left:4px solid var(--accent-600);">
    <h3><?= icon('sliders') ?> Servidor de control remoto (RustDesk)</h3>
    <?php if (!$rustdeskServidorGuardado): ?>
    <p class="small">Sin servidor propio configurado: los instaladores igual instalan RustDesk y activan control remoto, pero usando el servidor público gratuito de RustDesk (el tráfico pasa por sus servidores). Si más adelante tienes un NAS con Docker y acceso al router, puedes montar tu propio servidor privado y ponerlo aquí.</p>
    <?php else: ?>
    <p class="small">Servidor propio configurado: <code><?= e($rustdeskServidorGuardado) ?></code>. Los próximos instaladores usarán este servidor privado en vez del público de RustDesk.</p>
    <?php endif; ?>
    <form method="post" class="toolbar" style="margin-top:8px;">
        <input type="hidden" name="accion" value="guardar_rustdesk">
        <input type="text" name="rustdesk_servidor" value="<?= e($rustdeskServidorGuardado) ?>" placeholder="Ej: 190.xx.xx.xx o rustdesk.grupo10z.com.co (opcional)" style="min-width:320px">
        <button type="submit"><?= icon('check') ?> Guardar</button>
    </form>
    <p class="small" style="margin-top:8px;">Opcional. Solo llénalo si vas a montar tu propio servidor (NAS con Docker + puertos 21115-21119 redirigidos en el router). Déjalo vacío para usar el servidor público gratuito de RustDesk.</p>
</div>
<?php endif; ?>

<div class="panel" style="border-left:4px solid var(--accent-600);">
    <h3><?= icon('zap') ?> Instalador de un clic (recomendado)</h3>
    <p class="small">Descarga un <code>.bat</code> que hace todo solo: instala el agente, reporta inventario cada cinco minutos, activa control remoto con RustDesk (<?= $rustdeskServidorGuardado ? 'usando tu servidor propio' : 'usando el servidor público de RustDesk' ?>) y deja en el escritorio el acceso <strong>Reportar problema a NAVISSI</strong>.</p>
    <form method="get" action="../instalar_agente.php" class="toolbar" style="margin-top:10px;">
        <select name="sede" required style="min-width:280px"><option value="">Selecciona la sede</option><?php foreach($sedesAgente as $sn):?><option><?=e($sn)?></option><?php endforeach;?></select>
        <button type="submit"><?= icon('upload') ?> Descargar instalar_agente_navissi.bat</button>
    </form>
    <p class="small" style="margin-top:8px;">Cópialo al equipo y ejecútalo como administrador. El empleado verá una ventana sencilla para crear tickets; NAVISSI asociará automáticamente usuario, serial, inventario y acceso remoto. Las tareas quedan bajo SYSTEM y el instalador se elimina al terminar para proteger su token.</p>
</div>

<div class="panel"><h3><?=icon('shield')?> Credenciales de agentes</h3><p class="small">Cada instalador genera una credencial única y la vincula al primer serial. Solo se conserva su hash.</p><table><tr><th>Instalador</th><th>Sede</th><th>Serial</th><th>Último uso</th><th>Estado</th><th></th></tr><?php foreach($tokensAgente as $t):?><tr><td><?=e($t['nombre'])?><br><code><?=e($t['token_prefijo'])?>…</code></td><td><?=e($t['sede_nombre'])?></td><td><?=e($t['serial_vinculado']?:'Pendiente')?></td><td><?=e($t['ultimo_uso_en']?:'Nunca')?></td><td><span class="badge <?=$t['activo']?'badge-activo':'badge-otro'?>"><?=$t['activo']?'ACTIVA':'REVOCADA'?></span></td><td>
<?php if($t['activo']):?><form method="post" style="display:inline;"><input type="hidden" name="accion" value="revocar"><input type="hidden" name="id" value="<?=$t['id']?>"><button class="link-btn">Revocar</button></form><?php endif;?>
<form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta credencial de la lista? Si algún equipo la sigue usando, dejará de poder reportarse.');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=$t['id']?>"><button class="link-btn" style="color:var(--err-fg);">Eliminar</button></form>
</td></tr><?php endforeach;?></table></div>

<div class="panel">
    <h3>Instalación manual (avanzado)</h3>
    <ol>
        <li>Descarga el script: <a href="../data/agente_navissi.ps1" download>agente_navissi.ps1</a></li>
        <li>Cópialo a cada equipo (o compártelo por una carpeta de red/OneDrive).</li>
        <li>Ejecuta una vez para probar:<br>
            <code style="background:#f4f6f9;padding:6px 10px;border-radius:6px;display:inline-block;margin-top:6px;">
                powershell -ExecutionPolicy Bypass -File agente_navissi.ps1 -Servidor "<?= e($base) ?>" -Sede "NOMBRE DE LA SEDE" -TokenFile "C:\ProgramData\NAVISSI\agent.token"
            </code>
        </li>
        <li>La instalación manual requiere crear primero una credencial y el archivo protegido <code>C:\ProgramData\NAVISSI\agent.token</code>. Por seguridad se recomienda usar el instalador de un clic.</li>
    </ol>
    <p class="small">El agente lee número de serie, marca, modelo, procesador, RAM, disco, sistema operativo, usuario, parches instalados y datos necesarios para soporte remoto. No recolecta documentos ni contraseñas personales.</p>
</div>

<div class="panel" style="border-left:4px solid var(--teal-500);">
    <h3><?= icon('robot') ?> Reportar Problema — autogestión para empleados sin conocimiento técnico</h3>
    <p class="small">Pensado para gente que no sabe de tecnología: el empleado solo describe lo que le pasa con sus propias
        palabras. El script detecta su equipo automáticamente (mismo serial que ya usa el agente de arriba), arma el
        ticket con la ficha técnica completa, y la IA intenta resolverlo en el momento. Si no puede, queda escalado a TI
        con todo el contexto ya listo - nadie tiene que llenar formularios técnicos.</p>
    <ol>
        <li>Descarga: <a href="../data/reportar_problema.ps1" download>reportar_problema.ps1</a></li>
        <li>Crea un acceso directo en el escritorio de cada equipo (icono "🆘 Reportar un problema") que ejecute:<br>
            <code style="background:#f4f6f9;padding:6px 10px;border-radius:6px;display:inline-block;margin-top:6px;">
                powershell -ExecutionPolicy Bypass -File reportar_problema.ps1 -Servidor "<?= e($base) ?>"
            </code>
        </li>
        <li>Al hacer doble clic, se abre una sola ventana pidiendo "¿qué te está pasando?" - el empleado escribe y listo.
            Si la IA resuelve el problema, se lo muestra ahí mismo; si no, le confirma que ya quedó reportado a TI.</li>
    </ol>
</div>

<div class="panel">
    <h3>Últimos equipos reportados por el agente (<?= count($ultimos) ?>)</h3>
    <table>
        <tr><th>Equipo</th><th>Usuario</th><th>Marca/Modelo</th><th>SO</th><th>RAM</th><th>Disco</th><th>Actualizado</th><th></th></tr>
        <?php foreach ($ultimos as $u): ?>
        <tr>
            <td><?= e($u['hostname'] ?: $u['serial']) ?></td>
            <td><?= e($u['asignado_a']) ?></td>
            <td><?= e($u['marca']) ?> <?= e($u['modelo']) ?></td>
            <td><?= e($u['sistema_operativo']) ?></td>
            <td><?= e($u['memoria']) ?></td>
            <td><?= e($u['almacenamiento']) ?></td>
            <td class="small"><?= e($u['actualizado_en']) ?></td>
            <td><a class="link-btn" href="equipo_detalle.php?id=<?= (int) $u['id'] ?>">Ver todo</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$ultimos): ?><tr><td colspan="8" class="small">Todavía no se ha ejecutado el agente en ningún equipo.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
