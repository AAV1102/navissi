<?php
// Replica la misma lógica real que tenías en modules/nomina/NominaManager.php de
// WorkManager: salario_devengado = (salario_base/30)*dias_trabajados; salud y
// pension = 4% cada uno del devengado; auxilio de transporte si el salario base
// es bajo; neto = devengado + bonificaciones - deducciones.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$msg = null;

const TOPE_AUXILIO_TRANSPORTE = 2600000; // 2 SMLV aprox. - ajustar cada año
const VALOR_AUXILIO_TRANSPORTE = 200000;

function calcular_nomina($salarioBase, $diasTrabajados, $otrasBonif, $otrasDeduc) {
    $devengado = ($salarioBase / 30) * $diasTrabajados;
    $auxilio = $salarioBase > 0 && $salarioBase <= TOPE_AUXILIO_TRANSPORTE ? (VALOR_AUXILIO_TRANSPORTE / 30) * $diasTrabajados : 0;
    $salud = $devengado * 0.04;
    $pension = $devengado * 0.04;
    $neto = $devengado + $auxilio + $otrasBonif - $salud - $pension - $otrasDeduc;
    return compact('devengado', 'auxilio', 'salud', 'pension', 'neto');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear_periodo') {
        $pdo->prepare("INSERT INTO periodos_nomina (nombre, fecha_inicio, fecha_fin) VALUES (?,?,?)")
            ->execute([limpio($_POST['nombre'] ?? null), limpio($_POST['fecha_inicio'] ?? null), limpio($_POST['fecha_fin'] ?? null)]);
        $msg = ['ok', 'Periodo creado.'];
    }
    if ($accion === 'generar_periodo') {
        $periodoId = (int) $_POST['periodo_id'];
        $empleados = $pdo->query("SELECT documento, nombres, salario FROM empleados WHERE estado='ACTIVO' AND documento IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        $n = 0;
        foreach ($empleados as $e) {
            $salario = (float) ($e['salario'] ?? 0);
            $c = calcular_nomina($salario, 30, 0, 0);
            try {
                $pdo->prepare("INSERT INTO nominas (periodo_id, empleado_documento, empleado_nombre, salario_base, dias_trabajados,
                    salario_devengado, auxilio_transporte, salud, pension, neto_pagar) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$periodoId, $e['documento'], $e['nombres'], $salario, 30, $c['devengado'], $c['auxilio'], $c['salud'], $c['pension'], $c['neto']]);
                $n++;
            } catch (PDOException $ex) { /* ya existe para este periodo, se omite */ }
        }
        $msg = ['ok', "{$n} nóminas generadas para el periodo (empleados sin salario registrado quedan en 0 - complétalo en RRHH)."];
    }
    if ($accion === 'recalcular') {
        $id = (int) $_POST['id'];
        $dias = (float) $_POST['dias_trabajados'];
        $otrasBonif = (float) $_POST['otras_bonificaciones'];
        $otrasDeduc = (float) $_POST['otras_deducciones'];
        $stmt = $pdo->prepare("SELECT salario_base FROM nominas WHERE id = ?");
        $stmt->execute([$id]);
        $salarioBase = (float) $stmt->fetchColumn();
        $c = calcular_nomina($salarioBase, $dias, $otrasBonif, $otrasDeduc);
        $pdo->prepare("UPDATE nominas SET dias_trabajados=?, otras_bonificaciones=?, otras_deducciones=?,
            salario_devengado=?, auxilio_transporte=?, salud=?, pension=?, neto_pagar=? WHERE id=?")
            ->execute([$dias, $otrasBonif, $otrasDeduc, $c['devengado'], $c['auxilio'], $c['salud'], $c['pension'], $c['neto'], $id]);
        $msg = ['ok', 'Recalculado.'];
    }
    if ($accion === 'marcar_pagada') {
        $pdo->prepare("UPDATE nominas SET estado='PAGADA' WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = ['ok', 'Marcada como pagada.'];
    }
}

$periodos = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM nominas n WHERE n.periodo_id = p.id) AS n_nominas,
    (SELECT COALESCE(SUM(neto_pagar),0) FROM nominas n WHERE n.periodo_id = p.id) AS total
    FROM periodos_nomina p ORDER BY p.fecha_inicio DESC")->fetchAll(PDO::FETCH_ASSOC);

$periodoActivo = (int) ($_GET['periodo'] ?? ($periodos[0]['id'] ?? 0));
$nominas = [];
if ($periodoActivo) {
    $stmt = $pdo->prepare("SELECT * FROM nominas WHERE periodo_id = ? ORDER BY empleado_nombre");
    $stmt->execute([$periodoActivo]);
    $nominas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

layout_inicio('Nómina', 'Nómina', '../');
?>
<h1><?= icon('dollar','icon-lg') ?> Nómina</h1>
<p class="subtitle">Cálculo automático: devengado por días trabajados, salud y pensión (4% c/u), auxilio de transporte, y neto a pagar.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Nuevo periodo</h3>
    <form method="post" class="toolbar">
        <input type="hidden" name="accion" value="crear_periodo">
        <input type="text" name="nombre" placeholder="Ej: Julio 2026" required>
        <input type="date" name="fecha_inicio" required>
        <input type="date" name="fecha_fin" required>
        <button type="submit">Crear periodo</button>
    </form>
</div>

<div class="panel">
    <h3>Periodos</h3>
    <table>
        <tr><th>Periodo</th><th>Rango</th><th>Nóminas generadas</th><th>Total neto</th><th></th></tr>
        <?php foreach ($periodos as $p): ?>
        <tr style="<?= $p['id']==$periodoActivo ? 'background:#eaf1f8;' : '' ?>">
            <td><a href="?periodo=<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></a></td>
            <td><?= e($p['fecha_inicio']) ?> a <?= e($p['fecha_fin']) ?></td>
            <td><?= (int)$p['n_nominas'] ?></td>
            <td>$<?= number_format($p['total'],0,',','.') ?></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="accion" value="generar_periodo"><input type="hidden" name="periodo_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" style="padding:4px 10px;font-size:12px;">Generar para todos los activos</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$periodos): ?><tr><td colspan="5" class="small">Crea el primer periodo arriba.</td></tr><?php endif; ?>
    </table>
</div>

<?php if ($periodoActivo && $nominas): ?>
<div class="panel">
    <h3>Detalle del periodo</h3>
    <table>
        <tr><th>Empleado</th><th>Salario base</th><th>Días</th><th>Devengado</th><th>Aux. transporte</th><th>Bonif.</th><th>Salud</th><th>Pensión</th><th>Deduc.</th><th>Neto</th><th>Estado</th><th></th></tr>
        <?php foreach ($nominas as $n): ?>
        <tr>
            <td><?= e($n['empleado_nombre']) ?></td>
            <td>$<?= number_format($n['salario_base'],0,',','.') ?></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="accion" value="recalcular"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                    <input type="number" name="dias_trabajados" value="<?= e($n['dias_trabajados']) ?>" style="width:55px;" step="0.5">
            </td>
            <td>$<?= number_format($n['salario_devengado'],0,',','.') ?></td>
            <td>$<?= number_format($n['auxilio_transporte'],0,',','.') ?></td>
            <td><input type="number" name="otras_bonificaciones" value="<?= e($n['otras_bonificaciones']) ?>" style="width:80px;"></td>
            <td>$<?= number_format($n['salud'],0,',','.') ?></td>
            <td>$<?= number_format($n['pension'],0,',','.') ?></td>
            <td><input type="number" name="otras_deducciones" value="<?= e($n['otras_deducciones']) ?>" style="width:80px;"></td>
            <td><strong>$<?= number_format($n['neto_pagar'],0,',','.') ?></strong></td>
            <td><span class="badge <?= $n['estado']==='PAGADA'?'badge-activo':'badge-otro' ?>"><?= e($n['estado']) ?></span></td>
            <td>
                    <button type="submit" style="padding:4px 8px;font-size:11px;">Recalcular</button>
                </form>
                <?php if ($n['estado'] !== 'PAGADA'): ?>
                <form method="post" class="inline">
                    <input type="hidden" name="accion" value="marcar_pagada"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                    <button type="submit" style="padding:4px 8px;font-size:11px;">Pagar</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p class="small" style="margin-top:10px;">Auxilio de transporte se calcula automáticamente para salarios base hasta $<?= number_format(TOPE_AUXILIO_TRANSPORTE,0,',','.') ?> - ajusta el tope cada año en el código si cambia la ley.</p>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
