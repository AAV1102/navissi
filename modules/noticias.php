<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'publicar') {
        $titulo = limpio($_POST['titulo'] ?? null);
        if ($titulo) {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $pdo->prepare("INSERT INTO noticias (titulo, contenido, sede_id, fijado, autor) VALUES (?,?,?,?,?)")
                ->execute([$titulo, limpio($_POST['contenido'] ?? null), $sedeId, !empty($_POST['fijado']) ? 1 : 0, limpio($_POST['autor'] ?? null) ?: 'Comunicaciones']);
            $msg = ['ok', 'Publicado.'];
        } else {
            $msg = ['error', 'El título es obligatorio.'];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM noticias WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$stmt = $pdo->query("SELECT n.*, s.nombre AS sede_nombre FROM noticias n LEFT JOIN sedes s ON n.sede_id = s.id ORDER BY n.fijado DESC, n.creado_en DESC");
$noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Noticias', 'Noticias', '../');
?>
<h1><?= icon('megaphone','icon-lg') ?> Comunicación y Noticias</h1>
<p class="subtitle">Cartelera interna: anuncios generales o dirigidos a una sede en particular.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Publicar anuncio</h3>
    <form method="post">
        <input type="hidden" name="accion" value="publicar">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>Título *</label><input type="text" name="titulo" required></div>
            <div><label>Autor</label><input type="text" name="autor" placeholder="Comunicaciones"></div>
            <div><label>Dirigido a sede</label>
                <select name="sede">
                    <option value="">-- todas las sedes --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label><input type="checkbox" name="fijado" value="1"> Fijar arriba</label></div>
        </div>
        <textarea name="contenido" rows="4" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Contenido del anuncio..."></textarea>
        <button type="submit">Publicar</button>
    </form>
</div>

<?php foreach ($noticias as $n): ?>
<div class="panel" style="<?= $n['fijado'] ? 'border-left:4px solid #c99a1f;' : '' ?>">
    <h3><?= $n['fijado'] ? '📌 ' : '' ?><?= e($n['titulo']) ?>
        <form method="post" style="display:inline;float:right;" onsubmit="return confirm('¿Eliminar este anuncio?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
            <button type="submit" class="btn-danger" style="font-size:11px;padding:4px 10px;">Eliminar</button>
        </form>
    </h3>
    <p class="small"><?= e($n['autor']) ?> · <?= e($n['sede_nombre']) ?: 'Todas las sedes' ?> · <?= e($n['creado_en']) ?></p>
    <p style="white-space:pre-wrap;"><?= nl2br(e($n['contenido'])) ?></p>
</div>
<?php endforeach; ?>
<?php if (!$noticias): ?><p class="small">Aún no hay anuncios publicados.</p><?php endif; ?>
<?php layout_fin(); ?>
