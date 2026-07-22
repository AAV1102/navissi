<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$u = usuario_actual();
$msg = null;

if (!tiene_rol(['GERENCIA', 'CEO', 'ADMIN', 'RRHH', 'DIRECTOR'])) {
    layout_inicio('Vacantes', 'Vacantes', '../');
    echo '<div class="msg-error">No tienes permiso para gestionar vacantes.</div>';
    layout_fin();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_vacante') {
        $titulo = limpio($_POST['titulo'] ?? null);
        if ($titulo) {
            $pdo->prepare("INSERT INTO vacantes (titulo, area, descripcion, requisitos, estado, creado_por) VALUES (?,?,?,?,?,?)")
                ->execute([$titulo, limpio($_POST['area'] ?? null), limpio($_POST['descripcion'] ?? null),
                    limpio($_POST['requisitos'] ?? null), 'ABIERTA', $u['nombre']]);
            $msg = ['ok', 'Vacante publicada.'];
        }
    } elseif ($accion === 'cambiar_estado') {
        $pdo->prepare("UPDATE vacantes SET estado = ? WHERE id = ?")
            ->execute([limpio($_POST['estado'] ?? null), (int) ($_POST['id'] ?? 0)]);
        $msg = ['ok', 'Vacante actualizada.'];
    } elseif ($accion === 'cambiar_estado_candidato') {
        $pdo->prepare("UPDATE candidatos SET estado = ?, notas = ? WHERE id = ?")
            ->execute([limpio($_POST['estado'] ?? null), limpio($_POST['notas'] ?? null), (int) ($_POST['id'] ?? 0)]);
        $msg = ['ok', 'Candidato actualizado.'];
    }
}

$vacantes = $pdo->query("SELECT * FROM vacantes ORDER BY (estado='ABIERTA') DESC, creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
$candidatosPorVacante = [];
foreach ($vacantes as $v) {
    $stmt = $pdo->prepare("SELECT * FROM candidatos WHERE vacante_id = ? ORDER BY creado_en DESC");
    $stmt->execute([$v['id']]);
    $candidatosPorVacante[$v['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Informe de contrataciones: cuánto y cada cuánto se contrata gente por este
// proceso (candidato_id IS NOT NULL = vino de un proceso de selección real,
// no de una carga manual de nómina antigua).
$contratacionesPorMes = $pdo->query("SELECT substr(fecha_ingreso, 1, 7) AS mes, COUNT(*) AS total
    FROM empleados WHERE candidato_id IS NOT NULL AND fecha_ingreso IS NOT NULL
    GROUP BY mes ORDER BY mes DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$totalContratadosProceso = (int) $pdo->query("SELECT COUNT(*) FROM empleados WHERE candidato_id IS NOT NULL")->fetchColumn();
$promedioDiasProceso = $pdo->query("SELECT AVG(julianday(e.fecha_ingreso) - julianday(c.creado_en))
    FROM empleados e JOIN candidatos c ON c.id = e.candidato_id WHERE e.fecha_ingreso IS NOT NULL")->fetchColumn();

layout_inicio('Vacantes', 'Vacantes', '../');
?>
<h1><?= icon('briefcase','icon-lg') ?> Reclutamiento y Selección</h1>
<p class="subtitle">Publica vacantes y da seguimiento a los candidatos que aplican.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="cards">
    <div class="card"><div class="num"><?= $totalContratadosProceso ?></div><div class="label">Contratados vía este proceso</div></div>
    <div class="card"><div class="num"><?= $promedioDiasProceso ? round($promedioDiasProceso) : '—' ?></div><div class="label">Días promedio de postulación a contratación</div></div>
    <div class="card"><div class="num"><?= count(array_filter($vacantes, fn($v) => $v['estado'] === 'ABIERTA')) ?></div><div class="label">Vacantes abiertas ahora</div></div>
</div>

<?php if ($contratacionesPorMes): ?>
<div class="panel">
    <h3><?= icon('log') ?> Contrataciones por mes (últimos 12 meses con datos)</h3>
    <table>
        <tr><th>Mes</th><th>Contratados</th></tr>
        <?php foreach ($contratacionesPorMes as $cm): ?>
        <tr><td><?= e($cm['mes']) ?></td><td><?= (int) $cm['total'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="panel">
    <h3>Nueva vacante</h3>
    <form method="post">
        <input type="hidden" name="accion" value="crear_vacante">
        <div class="grid-form">
            <div><label>Título *</label><input type="text" name="titulo" required></div>
            <div><label>Área</label><input type="text" name="area" placeholder="Ej. Ventas, TI"></div>
        </div>
        <label>Descripción</label>
        <textarea name="descripcion" rows="3" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;"></textarea>
        <label>Requisitos</label>
        <textarea name="requisitos" rows="3" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:10px;"></textarea>
        <button type="submit">Publicar vacante</button>
    </form>
</div>

<?php foreach ($vacantes as $v): $cands = $candidatosPorVacante[$v['id']]; ?>
<div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
        <div>
            <strong><?= e($v['titulo']) ?></strong> <span class="small"><?= e($v['area']) ?></span>
            <p class="small" style="margin:4px 0;"><?= e($v['descripcion']) ?></p>
        </div>
        <form method="post" class="inline">
            <input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <select name="estado" onchange="this.form.requestSubmit()">
                <?php foreach (['ABIERTA','EN_PROCESO','CERRADA'] as $s): ?>
                <option value="<?= $s ?>" <?= $v['estado']===$s?'selected':'' ?>><?= ucfirst(strtolower(str_replace('_',' ',$s))) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <p class="small">Enlace para postulantes: <a href="<?= '../postular.php?vacante=' . (int)$v['id'] ?>" target="_blank"><?= e(navissi_url_publica('postular.php?vacante=' . (int)$v['id'])) ?></a> · <?= count($cands) ?> candidato(s)</p>

    <?php if ($cands): ?>
    <table>
        <tr><th>Candidato</th><th>Contacto</th><th>CV</th><th>Etapa del proceso</th><th></th></tr>
        <?php foreach ($cands as $c): ?>
        <tr>
            <td><?= e($c['nombre']) ?></td>
            <td class="small"><?= e($c['email']) ?><?= $c['celular'] ? '<br>' . e($c['celular']) : '' ?></td>
            <td><?php if ($c['cv_ruta']): ?><a href="descargar_cv.php?id=<?= (int)$c['id'] ?>" target="_blank">Ver CV</a><?php else: ?><span class="small">—</span><?php endif; ?></td>
            <td><span class="badge <?= $c['estado']==='CONTRATADO'?'badge-activo':($c['estado']==='RECHAZADO'?'badge-err':'badge-otro') ?>"><?= e(ucfirst(strtolower(str_replace('_',' ',$c['estado'])))) ?></span></td>
            <td><a href="candidato_detalle.php?id=<?= (int)$c['id'] ?>">Gestionar proceso →</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?><p class="small">Aún no hay candidatos para esta vacante.</p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$vacantes): ?><p class="small">No hay vacantes publicadas.</p><?php endif; ?>
<?php layout_fin(); ?>
