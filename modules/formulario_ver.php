<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/icons.php';
$pdo = db();

$token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));
$stmt = $pdo->prepare("SELECT * FROM formularios WHERE token_publico = ? AND activo = 1");
$stmt->execute([$token]);
$formulario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$formulario) {
    http_response_code(404);
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><title>Formulario no disponible</title>
    <link rel="stylesheet" href="../assets/style.css"></head><body class="auth-body">
    <div class="auth-shell"><div class="auth-panel"><div class="auth-card">
    <h1>Formulario no disponible</h1><p>Este formulario no existe o ya no está activo.</p>
    </div></div></div></body></html><?php
    exit;
}

$stmtC = $pdo->prepare("SELECT * FROM formularios_campos WHERE formulario_id = ? ORDER BY orden ASC");
$stmtC->execute([$formulario['id']]);
$campos = $stmtC->fetchAll(PDO::FETCH_ASSOC);

$enviado = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuestas = [];
    foreach ($campos as $c) {
        $valor = $_POST['campo_' . $c['id']] ?? '';
        if ($c['requerido'] && $valor === '') {
            $error = "El campo \"{$c['etiqueta']}\" es obligatorio.";
            break;
        }
        $respuestas[$c['etiqueta']] = is_array($valor) ? array_map('limpio', $valor) : limpio($valor);
    }
    if (!$error) {
        $pdo->prepare("INSERT INTO formularios_respuestas (formulario_id, respuestas_json, enviado_por) VALUES (?,?,?)")
            ->execute([$formulario['id'], json_encode($respuestas, JSON_UNESCAPED_UNICODE), limpio($_POST['enviado_por'] ?? null)]);
        $enviado = true;
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($formulario['titulo']) ?></title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="auth-body">
<div class="auth-shell">
    <div class="auth-panel" style="margin:0 auto;">
        <div class="auth-card">
            <p class="auth-eyebrow">Grupo 10Z SAS</p>
            <h1><?= e($formulario['titulo']) ?></h1>
            <?php if ($formulario['descripcion']): ?><p><?= nl2br(e($formulario['descripcion'])) ?></p><?php endif; ?>

            <?php if ($enviado): ?>
                <div class="msg-ok">¡Gracias! Tu respuesta quedó registrada.</div>
            <?php else: ?>
                <?php if ($error): ?><div class="msg-error"><?= e($error) ?></div><?php endif; ?>
                <form method="post">
                    <div><label>Tu nombre (opcional)</label><input type="text" name="enviado_por"></div>
                    <?php foreach ($campos as $c): $opciones = $c['opciones'] ? array_map('trim', explode(',', $c['opciones'])) : []; ?>
                    <div style="margin-top:12px;">
                        <label><?= e($c['etiqueta']) ?><?= $c['requerido'] ? ' *' : '' ?></label>
                        <?php if ($c['tipo'] === 'textarea'): ?>
                            <textarea name="campo_<?= (int) $c['id'] ?>" <?= $c['requerido'] ? 'required' : '' ?>></textarea>
                        <?php elseif ($c['tipo'] === 'select'): ?>
                            <select name="campo_<?= (int) $c['id'] ?>" <?= $c['requerido'] ? 'required' : '' ?>>
                                <option value="">Selecciona...</option>
                                <?php foreach ($opciones as $op): ?><option value="<?= e($op) ?>"><?= e($op) ?></option><?php endforeach; ?>
                            </select>
                        <?php elseif ($c['tipo'] === 'checkbox'): ?>
                            <select name="campo_<?= (int) $c['id'] ?>" <?= $c['requerido'] ? 'required' : '' ?>>
                                <option value="">Selecciona...</option>
                                <option value="Sí">Sí</option>
                                <option value="No">No</option>
                            </select>
                        <?php elseif ($c['tipo'] === 'fecha'): ?>
                            <input type="date" name="campo_<?= (int) $c['id'] ?>" <?= $c['requerido'] ? 'required' : '' ?>>
                        <?php elseif ($c['tipo'] === 'numero'): ?>
                            <input type="number" name="campo_<?= (int) $c['id'] ?>" <?= $c['requerido'] ? 'required' : '' ?>>
                        <?php else: ?>
                            <input type="text" name="campo_<?= (int) $c['id'] ?>" <?= $c['requerido'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" style="margin-top:16px;"><?= icon('check') ?> Enviar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
