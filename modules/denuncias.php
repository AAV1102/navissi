<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

// Deliberadamente SIN tiene_rol() - cualquier usuario logueado puede reportar,
// incluyendo de forma anónima. El canal de denuncias debe ser accesible a todos.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria = limpio($_POST['categoria'] ?? null);
    $descripcion = limpio($_POST['descripcion'] ?? null);
    $anonimo = isset($_POST['anonimo']) ? 1 : 0;
    $areaInvolucrada = limpio($_POST['area_involucrada'] ?? null);
    if ($categoria && $descripcion) {
        $pdo->prepare("INSERT INTO denuncias (categoria, descripcion, anonimo, denunciante_documento, denunciante_nombre, area_involucrada, estado, creado_en) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)")
            ->execute([
                $categoria, $descripcion, $anonimo,
                $anonimo ? null : ($u['documento'] ?? null),
                $anonimo ? null : $u['nombre'],
                $areaInvolucrada, 'RECIBIDA',
            ]);
        $msg = ['ok', 'Tu denuncia fue registrada. Gracias por reportarlo — será revisada de forma confidencial.'];
    } else {
        $msg = ['error', 'Completa la categoría y la descripción.'];
    }
}

$misDenuncias = [];
if (!empty($u['documento'])) {
    $stmt = $pdo->prepare("SELECT * FROM denuncias WHERE denunciante_documento = ? ORDER BY creado_en DESC");
    $stmt->execute([$u['documento']]);
    $misDenuncias = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

layout_inicio('Canal de Denuncias', 'Canal de Denuncias', '../');
?>
<h1><?= icon('shield','icon-lg') ?> Canal de Denuncias</h1>
<p class="subtitle">Reporta situaciones de acoso, fraude, conflicto de interés u otras irregularidades. Puedes hacerlo de forma anónima.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Nueva denuncia</h3>
    <form method="post">
        <div class="grid-form">
            <div><label>Categoría *</label>
                <select name="categoria" required>
                    <?php foreach (['ACOSO','FRAUDE','CONFLICTO_INTERES','DISCRIMINACION','SEGURIDAD','OTRO'] as $c): ?>
                    <option value="<?= $c ?>"><?= ucfirst(strtolower(str_replace('_',' ',$c))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Área involucrada (opcional)</label><input type="text" name="area_involucrada" placeholder="Ej. Ventas, TI, Bodega"></div>
        </div>
        <label>Descripción de los hechos *</label>
        <textarea name="descripcion" rows="5" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" required placeholder="Describe qué pasó, cuándo y quiénes estuvieron involucrados"></textarea>
        <label class="small" style="display:flex;align-items:center;gap:6px;margin-bottom:12px;">
            <input type="checkbox" name="anonimo" style="width:auto;"> Enviar de forma anónima (no se guardará tu nombre)
        </label>
        <button type="submit"><?= icon('send') ?> Enviar denuncia</button>
    </form>
</div>

<?php if ($misDenuncias): ?>
<div class="panel">
    <h3>Mis denuncias enviadas (<?= count($misDenuncias) ?>)</h3>
    <p class="small">Solo se listan las que enviaste identificándote — las anónimas no quedan asociadas a tu cuenta.</p>
    <table>
        <tr><th>Categoría</th><th>Fecha</th><th>Estado</th><th>Respuesta</th></tr>
        <?php foreach ($misDenuncias as $d): ?>
        <tr>
            <td><?= e($d['categoria']) ?></td>
            <td class="small"><?= e($d['creado_en']) ?></td>
            <td><span class="badge <?= $d['estado']==='RESUELTA'?'badge-activo':'badge-warn' ?>"><?= e($d['estado']) ?></span></td>
            <td><?= $d['respuesta'] ? e($d['respuesta']) : '<span class="small">Sin respuesta aún</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
