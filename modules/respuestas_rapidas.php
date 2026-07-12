<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $titulo = limpio($_POST['titulo'] ?? null);
        $texto = limpio($_POST['texto'] ?? null);
        if ($titulo && $texto) {
            $pdo->prepare("INSERT INTO respuestas_rapidas (titulo, categoria, texto) VALUES (?,?,?)")
                ->execute([$titulo, limpio($_POST['categoria'] ?? null), $texto]);
            $msg = ['ok', 'Respuesta rápida creada.'];
        } else {
            $msg = ['error', 'Título y texto son obligatorios.'];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM respuestas_rapidas WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$respuestas = $pdo->query("SELECT * FROM respuestas_rapidas ORDER BY usos DESC, titulo")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Respuestas Rápidas', 'Respuestas Rápidas', '../');
?>
<h1><?= icon('zap','icon-lg') ?> Respuestas Rápidas</h1>
<p class="subtitle">Textos ya redactados para las preguntas más frecuentes de Mesa de Ayuda - úsalos con un clic desde cualquier ticket, sin escribir de cero.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nueva respuesta rápida</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Título *</label><input type="text" name="titulo" required placeholder="Ej: Restablecer contraseña Siesa"></div>
            <div><label>Categoría</label><input type="text" name="categoria" placeholder="SIESA, RED/WIFI, CORREO..."></div>
        </div>
        <textarea name="texto" rows="4" style="width:100%;margin-bottom:10px;" placeholder="El texto que se va a enviar/pegar en el ticket..." required></textarea>
        <button type="submit"><?= icon('plus') ?> Crear</button>
    </form>
</div>

<table>
    <tr><th>Título</th><th>Categoría</th><th>Texto</th><th>Usos</th><th></th></tr>
    <?php foreach ($respuestas as $r): ?>
    <tr>
        <td><strong><?= e($r['titulo']) ?></strong></td>
        <td><?= e($r['categoria']) ?></td>
        <td class="small" style="max-width:340px;"><?= e(mb_substr($r['texto'], 0, 140)) ?><?= mb_strlen($r['texto']) > 140 ? '…' : '' ?></td>
        <td><?= (int)$r['usos'] ?></td>
        <td>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$respuestas): ?><tr><td colspan="5" class="small">Sin respuestas rápidas todavía.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
