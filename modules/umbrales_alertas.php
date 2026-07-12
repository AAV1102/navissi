<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Umbrales de Alertas', 'Umbrales de Alertas', '../');
    echo '<div class="msg-error">Solo TI puede configurar los umbrales.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['valor'] ?? [] as $id => $valor) {
        $pdo->prepare("UPDATE umbrales_alertas SET valor = ?, activo = ? WHERE id = ?")
            ->execute([(int) $valor, isset($_POST['activo'][$id]) ? 1 : 0, (int) $id]);
    }
    $msg = ['ok', 'Umbrales actualizados. Se aplican en el próximo cálculo de alertas.'];
}

$umbrales = $pdo->query("SELECT * FROM umbrales_alertas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Umbrales de Alertas', 'Umbrales de Alertas', '../');
?>
<h1><?= icon('bell','icon-lg') ?> Umbrales de Alertas</h1>
<p class="subtitle">Los números que disparan las alertas automáticas del sistema (Alertas, Network Discovery, Contratos) — ajustables sin tocar código.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form method="post">
    <table>
        <tr><th>Umbral</th><th>Descripción</th><th>Valor</th><th>Activo</th></tr>
        <?php foreach ($umbrales as $u): ?>
        <tr>
            <td><strong><?= e($u['nombre']) ?></strong></td>
            <td class="small"><?= e($u['descripcion']) ?></td>
            <td>
                <input type="number" name="valor[<?= (int)$u['id'] ?>]" value="<?= (int)$u['valor'] ?>" min="1" style="width:80px;">
                <span class="small"><?= e($u['unidad']) ?></span>
            </td>
            <td><input type="checkbox" name="activo[<?= (int)$u['id'] ?>]" <?= $u['activo']?'checked':'' ?> style="width:18px;height:18px;"></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <button type="submit" style="margin-top:14px;"><?= icon('check') ?> Guardar umbrales</button>
</form>
<?php layout_fin(); ?>
