<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'TI'])) {
    layout_inicio('Códigos Agrupados', 'Códigos Agrupados', '../');
    echo '<div class="msg-error">Solo TI puede gestionar grupos de códigos.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $nombre = limpio($_POST['nombre'] ?? null);
        $prefijo = strtoupper(limpio($_POST['prefijo'] ?? null) ?: '');
        if (!$nombre || !$prefijo) {
            $msg = ['error', 'Nombre y prefijo son obligatorios.'];
        } else {
            $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
            $pdo->prepare("INSERT INTO grupos_codigos (nombre, prefijo, digitos, siguiente_numero, sede_id, tipo_equipo) VALUES (?,?,?,?,?,?)")
                ->execute([$nombre, $prefijo, (int) ($_POST['digitos'] ?? 4) ?: 4, (int) ($_POST['inicio'] ?? 1) ?: 1, $sedeId, limpio($_POST['tipo_equipo'] ?? null)]);
            $msg = ['ok', 'Grupo de códigos creado.'];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM grupos_codigos WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Grupo eliminado.'];
    } elseif ($accion === 'asignar') {
        $grupoId = (int) $_POST['grupo_id'];
        $equipoId = (int) $_POST['equipo_id'];
        $stmt = $pdo->prepare("SELECT * FROM grupos_codigos WHERE id = ?");
        $stmt->execute([$grupoId]);
        $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmtEq = $pdo->prepare("SELECT * FROM inventario WHERE id = ?");
        $stmtEq->execute([$equipoId]);
        $equipo = $stmtEq->fetch(PDO::FETCH_ASSOC);
        if ($grupo && $equipo) {
            $codigo = $grupo['prefijo'] . '-' . str_pad((string) $grupo['siguiente_numero'], (int) $grupo['digitos'], '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE inventario SET placa = ? WHERE id = ?")->execute([$codigo, $equipoId]);
            $pdo->prepare("UPDATE grupos_codigos SET siguiente_numero = siguiente_numero + 1 WHERE id = ?")->execute([$grupoId]);
            hoja_vida_registrar($pdo, 'EQUIPO', $equipo['serial'] ?: (string) $equipoId, 'CODIGO_ASIGNADO', "Código {$codigo} asignado desde el grupo \"{$grupo['nombre']}\"", usuario_actual()['nombre'] ?? 'TI');
            $msg = ['ok', "Código {$codigo} asignado al equipo."];
        } else {
            $msg = ['error', 'Grupo o equipo no encontrado.'];
        }
    }
}

$grupos = $pdo->query("SELECT g.*, s.nombre AS sede_nombre FROM grupos_codigos g LEFT JOIN sedes s ON g.sede_id = s.id ORDER BY g.nombre")->fetchAll(PDO::FETCH_ASSOC);
$sedes = $pdo->query("SELECT * FROM sedes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$equiposSinCodigo = $pdo->query("SELECT id, serial, marca, modelo, placa FROM inventario WHERE placa IS NULL OR placa = '' ORDER BY marca")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Códigos Agrupados', 'Códigos Agrupados', '../');
?>
<h1><?= icon('inventory','icon-lg') ?> Códigos Agrupados de Activos</h1>
<p class="subtitle">Numeración consecutiva por grupo (sede/tipo de equipo) — el mismo esquema de placas/códigos internos que se maneja en gestión de activos por lotes, para que cada equipo tenga un código único, corto y trazable.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nuevo grupo de códigos</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Nombre del grupo *</label><input type="text" name="nombre" required placeholder="Portátiles Medellín"></div>
            <div><label>Prefijo *</label><input type="text" name="prefijo" required placeholder="MED-LAP" style="text-transform:uppercase;"></div>
            <div><label>Dígitos del consecutivo</label><input type="number" name="digitos" value="4" min="2" max="8"></div>
            <div><label>Empieza en</label><input type="number" name="inicio" value="1" min="1"></div>
            <div><label>Tipo de equipo</label>
                <select name="tipo_equipo">
                    <option value="">-- cualquiera --</option>
                    <?php foreach (['PORTATIL','ESCRITORIO','IMPRESORA','MONITOR','SERVIDOR','RED','OTRO'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Sede</label>
                <select name="sede">
                    <option value="">-- cualquiera --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit"><?= icon('check') ?> Crear grupo</button>
    </form>
</div>

<div class="panel">
    <h3><?= icon('zap') ?> Asignar siguiente código a un equipo</h3>
    <?php if (!$grupos): ?><p class="small">Crea primero un grupo de códigos arriba.</p>
    <?php elseif (!$equiposSinCodigo): ?><p class="small">Todos los equipos ya tienen placa/código asignado.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="accion" value="asignar">
        <div class="grid-form">
            <div><label>Grupo</label>
                <select name="grupo_id" required>
                    <?php foreach ($grupos as $g): ?>
                    <option value="<?= (int)$g['id'] ?>"><?= e($g['nombre']) ?> — próximo: <?= e($g['prefijo']) ?>-<?= str_pad((string)$g['siguiente_numero'], (int)$g['digitos'], '0', STR_PAD_LEFT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Equipo (sin código todavía)</label>
                <select name="equipo_id" required>
                    <?php foreach ($equiposSinCodigo as $e): ?>
                    <option value="<?= (int)$e['id'] ?>"><?= e($e['marca']) ?> <?= e($e['modelo']) ?> — <?= e($e['serial']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit"><?= icon('check') ?> Asignar código</button>
    </form>
    <?php endif; ?>
</div>

<table>
    <tr><th>Grupo</th><th>Prefijo</th><th>Sede</th><th>Tipo</th><th>Próximo código</th><th></th></tr>
    <?php foreach ($grupos as $g): ?>
    <tr>
        <td><?= e($g['nombre']) ?></td>
        <td><code><?= e($g['prefijo']) ?></code></td>
        <td><?= e($g['sede_nombre']) ?: 'Cualquiera' ?></td>
        <td><?= e($g['tipo_equipo']) ?: 'Cualquiera' ?></td>
        <td><code><?= e($g['prefijo']) ?>-<?= str_pad((string)$g['siguiente_numero'], (int)$g['digitos'], '0', STR_PAD_LEFT) ?></code></td>
        <td>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar este grupo? No borra los códigos ya asignados.');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$grupos): ?><tr><td colspan="6" class="small">Sin grupos creados.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
