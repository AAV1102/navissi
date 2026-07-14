<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/graph_client.php';
// La credencial de aplicación y las acciones Graph modifican el tenant real.
requiere_roles(['ADMIN', 'TI'], '../');
set_time_limit(180); // sincronizar consulta la licencia de cada usuario uno por uno, puede tardar en tenants grandes
$pdo = db();
$msg = null;
$nuevaClaveGenerada = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_config') {
        ms365_config_guardar($_POST['tenant_id'] ?? '', $_POST['client_id'] ?? '', $_POST['client_secret'] ?? '');
        $msg = ['ok', 'Credenciales guardadas.'];
    }

    if ($accion === 'probar_conexion') {
        try {
            $c = ms365_config();
            $gc = new GraphClient($c['tenant_id'], $c['client_id'], $c['client_secret']);
            $gc->probarConexion();
            $msg = ['ok', 'Conexión exitosa con Microsoft Graph.'];
        } catch (GraphClientException $e) {
            $msg = ['error', $e->getMessage()];
        }
    }

    if ($accion === 'sincronizar') {
        try {
            $c = ms365_config();
            $gc = new GraphClient($c['tenant_id'], $c['client_id'], $c['client_secret']);

            $skus = $gc->listarSkus();
            $mapaSku = [];
            foreach ($skus as $sku) {
                $mapaSku[$sku['skuId']] = $sku['skuPartNumber'];
                $pdo->prepare("INSERT INTO ms365_licencias (sku_id, nombre, compradas, consumidas, actualizado_en)
                    VALUES (?,?,?,?,CURRENT_TIMESTAMP)
                    ON CONFLICT(sku_id) DO UPDATE SET nombre=excluded.nombre, compradas=excluded.compradas, consumidas=excluded.consumidas, actualizado_en=CURRENT_TIMESTAMP")
                    ->execute([$sku['skuId'], $sku['skuPartNumber'], $sku['prepaidUnits']['enabled'] ?? 0, $sku['consumedUnits'] ?? 0]);
            }

            $usuarios = $gc->listarUsuarios();
            $n = 0;
            foreach ($usuarios as $u) {
                $licDetalle = $gc->licenciasDeUsuario($u['id']);
                $nombresLic = array_map(fn($l) => $mapaSku[$l['skuId']] ?? $l['skuId'], $licDetalle);
                $pdo->prepare("INSERT INTO ms365_usuarios (graph_id, nombre, correo, departamento, cargo, cuenta_activa, licencias, actualizado_en)
                    VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)
                    ON CONFLICT(graph_id) DO UPDATE SET nombre=excluded.nombre, correo=excluded.correo,
                        departamento=excluded.departamento, cargo=excluded.cargo, cuenta_activa=excluded.cuenta_activa,
                        licencias=excluded.licencias, actualizado_en=CURRENT_TIMESTAMP")
                    ->execute([
                        $u['id'], $u['displayName'] ?? null, $u['mail'] ?? $u['userPrincipalName'] ?? null,
                        $u['department'] ?? null, $u['jobTitle'] ?? null, !empty($u['accountEnabled']) ? 1 : 0,
                        implode(', ', $nombresLic),
                    ]);
                $n++;
            }

            $pdo->prepare("INSERT INTO ms365_sync_log (resultado, detalle) VALUES ('ok', ?)")
                ->execute(["{$n} usuarios, " . count($skus) . " tipos de licencia sincronizados"]);
            $msg = ['ok', "Sincronizado: {$n} usuarios, " . count($skus) . " tipos de licencia."];
        } catch (GraphClientException $e) {
            $pdo->prepare("INSERT INTO ms365_sync_log (resultado, detalle) VALUES ('error', ?)")->execute([$e->getMessage()]);
            $msg = ['error', $e->getMessage()];
        }
    }

    if ($accion === 'restablecer_clave') {
        try {
            $c = ms365_config();
            $gc = new GraphClient($c['tenant_id'], $c['client_id'], $c['client_secret']);
            $graphId = $_POST['graph_id'];
            $nueva = generar_contrasena_temporal();
            $gc->restablecerContrasena($graphId, $nueva, true);
            $nuevaClaveGenerada = $nueva;
            $stmt = $pdo->prepare("SELECT correo FROM ms365_usuarios WHERE graph_id = ?");
            $stmt->execute([$graphId]);
            $correo = $stmt->fetchColumn();
            $pdo->prepare("INSERT INTO ms365_sync_log (resultado, detalle) VALUES ('reset_password', ?)")
                ->execute(["Contraseña restablecida para {$correo} - el usuario debe cambiarla al iniciar sesión"]);
            $msg = ['ok', "Contraseña restablecida para {$correo}. Entrégasela de forma segura - se pedirá cambiarla al primer inicio de sesión."];
        } catch (GraphClientException $e) {
            $msg = ['error', $e->getMessage()];
        }
    }
}

$cfg = ms365_config();
$configurado = ms365_configurado();
$usuarios = $pdo->query("SELECT * FROM ms365_usuarios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$licencias = $pdo->query("SELECT * FROM ms365_licencias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$ultimaSync = $pdo->query("SELECT * FROM ms365_sync_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

layout_inicio('Microsoft 365', 'Microsoft 365', '../');
?>
<h1><?= icon('cloud','icon-lg') ?> Microsoft 365 / Azure AD</h1>
<p class="subtitle">Sincroniza usuarios, estado de cuenta y licencias directo desde Microsoft Graph.</p>

<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>
<?php if ($nuevaClaveGenerada): ?>
<div class="msg-ok">
    <strong>Contraseña temporal generada:</strong> <code style="font-size:16px;background:#fff;padding:4px 10px;border-radius:4px;"><?= e($nuevaClaveGenerada) ?></code>
    <br><span class="small">Esta clave solo se muestra una vez aquí - Microsoft no permite volver a consultarla. Guárdala o entrégala ahora.</span>
</div>
<?php endif; ?>

<div class="panel">
    <h3>1. Conexión con Azure AD</h3>
    <p class="small">
        Necesitas una app registrada en <strong>portal.azure.com → Azure Active Directory → Registros de aplicaciones → Nuevo registro</strong>,
        con permisos de tipo <strong>Application</strong> (no delegados): <code>User.Read.All</code>, <code>Organization.Read.All</code>,
        <code>Directory.Read.All</code> y, para restablecer contraseñas, <code>User-PasswordProfile.ReadWrite.All</code> — luego
        pide "Conceder consentimiento de administrador" y genera un "Client secret" en Certificados y secretos.
    </p>
    <form method="post">
        <input type="hidden" name="accion" value="guardar_config">
        <div class="grid-form">
            <div><label>Tenant ID</label><input type="text" name="tenant_id" value="<?= e($cfg['tenant_id']) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></div>
            <div><label>Client ID (Application ID)</label><input type="text" name="client_id" value="<?= e($cfg['client_id']) ?>"></div>
            <div><label>Client Secret</label><input type="password" name="client_secret" value="<?= e($cfg['client_secret']) ?>"></div>
        </div>
        <button type="submit">Guardar credenciales</button>
    </form>
    <?php if ($configurado): ?>
    <form method="post" style="margin-top:10px;display:inline-block;">
        <input type="hidden" name="accion" value="probar_conexion">
        <button type="submit" class="btn-secondary">Probar conexión</button>
    </form>
    <form method="post" style="margin-top:10px;display:inline-block;">
        <input type="hidden" name="accion" value="sincronizar">
        <button type="submit">🔄 Sincronizar ahora</button>
    </form>
    <a href="conexiones_microsoft.php" class="btn btn-secondary" style="margin-top:10px;display:inline-block;">📁 OneDrive / SharePoint / Teams</a>
    <?php else: ?>
    <p class="small" style="color:#a12b1f;">Completa y guarda las credenciales para poder sincronizar.</p>
    <?php endif; ?>
    <?php if ($ultimaSync): ?>
    <p class="small">Última sincronización: <?= e($ultimaSync['fecha']) ?> — <?= e($ultimaSync['resultado']) ?> — <?= e($ultimaSync['detalle']) ?></p>
    <?php endif; ?>
    <p class="small" style="margin-top:10px;">
        <strong>Nota sobre "tiempo real":</strong> este botón trae los datos más recientes al instante (sincronización bajo demanda).
        Un sincronizado automático 100% en vivo (sin clic) requeriría que este software esté publicado en un servidor con
        dirección pública para que Microsoft le avise por webhook - si más adelante llevas esta app a un hosting/nube, se puede
        agregar esa parte. Por ahora, cada clic en "Sincronizar ahora" trae el estado real y actual de Microsoft.
    </p>
</div>

<div class="panel">
    <h3>Licencias (<?= count($licencias) ?>)</h3>
    <?php if (!$licencias): ?><p class="small">Aún no sincronizado.</p><?php else: ?>
    <table>
        <tr><th>Licencia</th><th>Compradas</th><th>Consumidas</th><th>Disponibles</th></tr>
        <?php foreach ($licencias as $l): ?>
        <tr>
            <td><?= e($l['nombre']) ?></td>
            <td><?= (int)$l['compradas'] ?></td>
            <td><?= (int)$l['consumidas'] ?></td>
            <td><?= (int)$l['compradas'] - (int)$l['consumidas'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Usuarios (<?= count($usuarios) ?>)</h3>
    <?php if (!$usuarios): ?><p class="small">Aún no sincronizado.</p><?php else: ?>
    <table>
        <tr><th>Nombre</th><th>Correo</th><th>Departamento</th><th>Cuenta</th><th>Licencias</th><th></th></tr>
        <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><?= e($u['nombre']) ?></td>
            <td><?= e($u['correo']) ?></td>
            <td><?= e($u['departamento']) ?></td>
            <td><span class="badge <?= $u['cuenta_activa'] ? 'badge-activo' : 'badge-otro' ?>"><?= $u['cuenta_activa'] ? 'ACTIVA' : 'BLOQUEADA' ?></span></td>
            <td><?= e($u['licencias']) ?></td>
            <td>
                <form class="inline" method="post" onsubmit="return confirm('¿Restablecer la contraseña de <?= e($u['correo']) ?>? Se generará una nueva y el usuario deberá cambiarla al iniciar sesión.');">
                    <input type="hidden" name="accion" value="restablecer_clave">
                    <input type="hidden" name="graph_id" value="<?= e($u['graph_id']) ?>">
                    <button type="submit" class="btn-secondary" style="padding:4px 10px;font-size:12px;">Restablecer clave</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php layout_fin(); ?>
