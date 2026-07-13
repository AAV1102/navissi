<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

if (!tiene_rol(['ADMIN', 'RRHH'])) {
    layout_inicio('Evaluaciones', 'Evaluaciones de Desempeño', '../');
    echo '<div class="msg-error">Solo RRHH puede gestionar evaluaciones de desempeño.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $doc = limpio($_POST['empleado_documento'] ?? null);
        $periodo = limpio($_POST['periodo'] ?? null);
        if (!$doc || !$periodo) {
            $msg = ['error', 'Empleado y periodo son obligatorios.'];
        } else {
            $stmt = $pdo->prepare("SELECT nombres FROM empleados WHERE documento = ?");
            $stmt->execute([$doc]);
            $nombre = $stmt->fetchColumn();

            $criterios = ['puntualidad', 'calidad_trabajo', 'trabajo_equipo', 'iniciativa', 'cumplimiento_metas'];
            $valores = [];
            foreach ($criterios as $c) $valores[$c] = (int) ($_POST[$c] ?? 3);
            $promedio = round(array_sum($valores) / count($valores), 2);

            $pdo->prepare("INSERT INTO evaluaciones_desempeno (empleado_documento, empleado_nombre, periodo, evaluador,
                puntualidad, calidad_trabajo, trabajo_equipo, iniciativa, cumplimiento_metas, promedio,
                fortalezas, oportunidades_mejora, plan_accion, estado)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$doc, $nombre, $periodo, usuario_actual()['nombre'] ?? 'RRHH',
                    $valores['puntualidad'], $valores['calidad_trabajo'], $valores['trabajo_equipo'], $valores['iniciativa'], $valores['cumplimiento_metas'],
                    $promedio, limpio($_POST['fortalezas'] ?? null), limpio($_POST['oportunidades_mejora'] ?? null),
                    limpio($_POST['plan_accion'] ?? null), limpio($_POST['estado'] ?? null) ?: 'BORRADOR']);
            $nuevoId = $pdo->lastInsertId();
            hoja_vida_registrar($pdo, 'EMPLEADO', $doc, 'EVALUACION_DESEMPENO', "Periodo {$periodo} - promedio {$promedio}/5", usuario_actual()['nombre'] ?? 'RRHH');
            $msg = ['ok', "Evaluación registrada. Promedio: {$promedio}/5."];
        }
    } elseif ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM evaluaciones_desempeno WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Eliminada.'];
    }
}

$documentoFiltro = trim($_GET['documento'] ?? '');
$sql = "SELECT * FROM evaluaciones_desempeno WHERE 1=1";
$params = [];
if ($documentoFiltro !== '') { $sql .= " AND empleado_documento = ?"; $params[] = $documentoFiltro; }
// Alcance personal: un EMPLEADO sin rol elevado solo ve sus propias evaluaciones.
$personalEv = alcance_personal();
if ($personalEv !== null) {
    $sql .= " AND empleado_documento = ?";
    $params[] = $personalEv['documento'];
}
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
$empleados = $pdo->query("SELECT documento, nombres FROM empleados WHERE estado='ACTIVO' ORDER BY nombres")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Evaluaciones de Desempeño', 'Evaluaciones de Desempeño', '../');
?>
<h1><?= icon('graduation','icon-lg') ?> Evaluaciones de Desempeño</h1>
<p class="subtitle">Calificación por criterios (1 a 5), promedio automático, y queda registrada en la hoja de vida del empleado.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3><?= icon('plus') ?> Nueva evaluación</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-form">
            <div><label>Empleado *</label>
                <input type="text" name="empleado_documento" list="lista-emp" required placeholder="Documento del empleado">
                <datalist id="lista-emp">
                    <?php foreach ($empleados as $emp): ?><option value="<?= e($emp['documento']) ?>"><?= e($emp['nombres']) ?></option><?php endforeach; ?>
                </datalist>
            </div>
            <div><label>Periodo *</label><input type="text" name="periodo" placeholder="2026-2" required></div>
            <div><label>Estado</label>
                <select name="estado">
                    <option value="BORRADOR">Borrador</option>
                    <option value="FINALIZADA">Finalizada</option>
                </select>
            </div>
        </div>
        <div class="grid-form">
            <?php foreach (['puntualidad'=>'Puntualidad','calidad_trabajo'=>'Calidad del trabajo','trabajo_equipo'=>'Trabajo en equipo','iniciativa'=>'Iniciativa','cumplimiento_metas'=>'Cumplimiento de metas'] as $campo => $label): ?>
            <div><label><?= $label ?> (1-5)</label>
                <select name="<?= $campo ?>">
                    <?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= $i==3?'selected':'' ?>><?= $i ?></option><?php endfor; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="grid-form">
            <div><label>Fortalezas</label><textarea name="fortalezas" rows="2" style="width:100%;"></textarea></div>
            <div><label>Oportunidades de mejora</label><textarea name="oportunidades_mejora" rows="2" style="width:100%;"></textarea></div>
            <div><label>Plan de acción</label><textarea name="plan_accion" rows="2" style="width:100%;"></textarea></div>
        </div>
        <button type="submit"><?= icon('check') ?> Guardar evaluación</button>
    </form>
</div>

<form class="toolbar" method="get">
    <input type="text" name="documento" placeholder="Filtrar por documento" value="<?= e($documentoFiltro) ?>">
    <button type="submit"><?= icon('search') ?> Filtrar</button>
</form>

<table>
    <tr><th>Empleado</th><th>Periodo</th><th>Promedio</th><th>Estado</th><th>Evaluador</th><th>Fecha</th><th></th></tr>
    <?php foreach ($evaluaciones as $ev): ?>
    <tr>
        <td><?= e($ev['empleado_nombre']) ?: e($ev['empleado_documento']) ?></td>
        <td><?= e($ev['periodo']) ?></td>
        <td><strong><?= e($ev['promedio']) ?>/5</strong></td>
        <td><span class="badge <?= $ev['estado']==='FINALIZADA'?'badge-activo':'badge-otro' ?>"><?= e($ev['estado']) ?></span></td>
        <td><?= e($ev['evaluador']) ?></td>
        <td class="small"><?= e($ev['creado_en']) ?></td>
        <td>
            <form class="inline" method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                <button type="submit" class="btn-danger" style="padding:4px 10px;font-size:12px;">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$evaluaciones): ?><tr><td colspan="7" class="small">Sin evaluaciones todavía.</td></tr><?php endif; ?>
</table>
<?php layout_fin(); ?>
