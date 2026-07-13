<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN'])) {
    layout_inicio('Personalizar Textos', 'Personalizar Textos', '../');
    echo '<div class="msg-error">Solo ADMIN puede personalizar los textos del menú.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    foreach ($_POST['etiqueta'] ?? [] as $href => $texto) {
        $texto = trim($texto);
        $href = str_replace('__', '/', $href); // los name= de HTML no permiten "/", se codifican con "__"
        if ($texto === '') {
            $pdo->prepare("DELETE FROM etiquetas_menu WHERE href = ?")->execute([$href]);
        } else {
            $pdo->prepare("INSERT INTO etiquetas_menu (href, etiqueta) VALUES (?,?) ON CONFLICT(href) DO UPDATE SET etiqueta = excluded.etiqueta")
                ->execute([$href, $texto]);
        }
    }
    $msg = ['ok', 'Textos del menú actualizados. Recarga cualquier página para verlos.'];
}

// nav_grupos() ya devuelve las etiquetas personalizadas aplicadas - para editar
// necesitamos también los nombres ORIGINALES, así que leemos la definición base
// directo del archivo fuente en memoria antes de que se le apliquen los overrides.
// Truco simple: leemos los overrides actuales y los restamos mentalmente mostrando
// el valor actual (ya sea el default o el personalizado) como punto de partida.
$grupos = nav_grupos();
$overridesActuales = $pdo->query("SELECT href, etiqueta FROM etiquetas_menu")->fetchAll(PDO::FETCH_KEY_PAIR);

layout_inicio('Personalizar Textos', 'Personalizar Textos', '../');
?>
<h1><?= icon('sliders','icon-lg') ?> Personalizar Textos del Menú</h1>
<p class="subtitle">Cambia el nombre que ve todo el mundo para cada módulo del menú lateral, sin tocar código. Deja el campo vacío para volver al nombre original.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="accion" value="guardar">
    <?php foreach ($grupos as $nombreGrupo => $def): ?>
    <div class="panel">
        <h3><?= icon($def['icon']) ?> <?= e($nombreGrupo) ?></h3>
        <table>
            <tr><th>Módulo</th><th>Texto personalizado</th></tr>
            <?php foreach ($def['items'] as $href => [$label, $ic]): ?>
            <tr>
                <td><?= icon($ic) ?> <?= e($label) ?><?= isset($overridesActuales[$href]) ? ' <span class="badge badge-otro small">personalizado</span>' : '' ?></td>
                <td><input type="text" name="etiqueta[<?= e(str_replace('/', '__', $href)) ?>]" value="<?= isset($overridesActuales[$href]) ? e($overridesActuales[$href]) : '' ?>" placeholder="<?= e($label) ?>" style="width:100%;"></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>
    <button type="submit"><?= icon('check') ?> Guardar todos los textos</button>
</form>
<?php layout_fin(); ?>
