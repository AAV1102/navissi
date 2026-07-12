<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $titulo = limpio($_POST['titulo'] ?? null);
        if ($titulo) {
            $pdo->prepare("INSERT INTO base_conocimiento (titulo, categoria, contenido, autor) VALUES (?,?,?,?)")
                ->execute([$titulo, limpio($_POST['categoria'] ?? null) ?: 'GENERAL', limpio($_POST['contenido'] ?? null), limpio($_POST['autor'] ?? null) ?: 'TI']);
            $msg = ['ok', 'Artículo publicado.'];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM base_conocimiento WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminado.'];
    }
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM base_conocimiento WHERE 1=1";
$params = [];
if ($busqueda !== '') { $sql .= " AND (titulo LIKE ? OR contenido LIKE ? OR categoria LIKE ?)"; $params = ["%{$busqueda}%","%{$busqueda}%","%{$busqueda}%"]; }
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$populares = $pdo->query("SELECT id, titulo FROM base_conocimiento ORDER BY titulo ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recientes = $pdo->query("SELECT id, titulo FROM base_conocimiento ORDER BY creado_en DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $pdo->query("SELECT categoria, COUNT(*) c FROM base_conocimiento GROUP BY categoria ORDER BY c DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Base de Conocimiento', 'Base de Conocimiento', '../');
?>
<div style="display:flex; justify-content:space-between; align-items:start; gap:14px; flex-wrap:wrap;">
    <div>
        <h1><?= icon('book','icon-lg') ?> Centro de Base de Conocimientos</h1>
        <p class="subtitle">Soluciones y guías rápidas para respuestas frecuentes de soporte - búscalas antes de crear un ticket nuevo.</p>
    </div>
</div>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<form method="get" class="bc-buscador">
    <input type="search" name="q" placeholder="¿Tienes una pregunta? Escribe una palabra clave..." value="<?= e($busqueda) ?>">
    <button type="submit"><?= icon('search') ?></button>
</form>

<div class="panel" data-form-manual="1" id="panel-nuevo-articulo" hidden>
    <h3>Nuevo artículo</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>Título *</label><input type="text" name="titulo" required></div>
            <div><label>Categoría</label><input type="text" name="categoria" placeholder="RED/WIFI, SIESA, IMPRESORAS..."></div>
            <div><label>Autor</label><input type="text" name="autor"></div>
        </div>
        <textarea name="contenido" rows="5" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;" placeholder="Solución paso a paso..."></textarea>
        <button type="submit">Publicar</button>
        <button type="button" class="btn-secondary" onclick="document.getElementById('panel-nuevo-articulo').hidden=true;">Cancelar</button>
    </form>
</div>
<button type="button" class="btn-abrir-form-auto" onclick="document.getElementById('panel-nuevo-articulo').hidden=false;this.hidden=true;" style="margin-bottom:16px;"><?= icon('plus') ?> Nuevo artículo</button>

<div class="bc-layout">
    <div class="bc-articulos">
        <?php if (!$articulos): ?>
        <div class="panel" style="text-align:center;padding:50px 20px;">
            <div style="font-size:44px;opacity:.5;"><?= icon('book','icon-lg') ?></div>
            <h2 style="margin:10px 0 6px;">No tiene base de conocimientos</h2>
            <p class="small">Reúna categorías, secciones y artículos para crear manuales y tutoriales de utilidad para su organización.</p>
        </div>
        <?php else: foreach ($articulos as $a): ?>
        <div class="panel">
            <h3><?= e($a['titulo']) ?>
                <form method="post" style="display:inline;float:right;" onsubmit="return confirm('¿Eliminar?');">
                    <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="btn-danger" style="font-size:11px;padding:4px 10px;">Eliminar</button>
                </form>
            </h3>
            <p class="small"><?= e($a['categoria']) ?> · <?= e($a['autor']) ?> · <?= e($a['creado_en']) ?></p>
            <p style="white-space:pre-wrap;"><?= nl2br(e($a['contenido'])) ?></p>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <aside class="bc-sidebar">
        <div class="bc-box">
            <h4><?= icon('chat') ?> ¿Necesita ayuda?</h4>
            <p class="small">¿No ha podido encontrar lo que estaba buscando? Crea un <a href="mesa_ayuda.php">ticket de soporte</a>.</p>
        </div>
        <div class="bc-box">
            <h4>Todos los artículos (A-Z)</h4>
            <?php if ($populares): foreach ($populares as $p): ?><p class="small">· <?= e($p['titulo']) ?></p><?php endforeach; else: ?><p class="small">Sin artículos todavía.</p><?php endif; ?>
        </div>
        <div class="bc-box">
            <h4>Artículos más recientes</h4>
            <?php if ($recientes): foreach ($recientes as $r): ?><p class="small">· <?= e($r['titulo']) ?></p><?php endforeach; else: ?><p class="small">Sin artículos todavía.</p><?php endif; ?>
        </div>
        <div class="bc-box">
            <h4>Categorías</h4>
            <?php if ($categorias): foreach ($categorias as $c): ?><p class="small">· <?= e($c['categoria']) ?> (<?= (int)$c['c'] ?>)</p><?php endforeach; else: ?><p class="small">Sin categorías todavía.</p><?php endif; ?>
        </div>
    </aside>
</div>
<?php layout_fin(); ?>
