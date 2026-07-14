<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/xlsx_reader.php';
requiere_roles(['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO'], '../');
$pdo = db();
$msg = null;
$resultado = null;

/**
 * Cruza dos listados (banco y Siesa) por valor exacto (redondeado a pesos) y,
 * si hay fecha en ambos, por fecha igual o cercana (+-3 dias, por diferencias
 * de fecha de compensacion). Devuelve conciliados, solo-en-banco y solo-en-siesa.
 */
function conciliar(array $banco, array $siesa): array {
    $siesaRestante = $siesa;
    $conciliados = [];
    foreach ($banco as $i => $fBanco) {
        $match = null;
        foreach ($siesaRestante as $j => $fSiesa) {
            if ($fSiesa === null) continue;
            $mismoValor = abs((float) $fBanco['valor'] - (float) $fSiesa['valor']) < 1;
            if (!$mismoValor) continue;
            $mismaFecha = true;
            if ($fBanco['fecha'] && $fSiesa['fecha']) {
                $diffDias = abs(strtotime($fBanco['fecha']) - strtotime($fSiesa['fecha'])) / 86400;
                $mismaFecha = $diffDias <= 3;
            }
            if ($mismaFecha) { $match = $j; break; }
        }
        if ($match !== null) {
            $conciliados[] = ['banco' => $fBanco, 'siesa' => $siesaRestante[$match]];
            $siesaRestante[$match] = null;
        } else {
            $conciliados[] = ['banco' => $fBanco, 'siesa' => null];
        }
    }
    $soloSiesa = array_values(array_filter($siesaRestante, fn($f) => $f !== null));
    return ['conciliados' => $conciliados, 'solo_siesa' => $soloSiesa];
}

function extraer_filas(array $filasAssoc): array {
    $out = [];
    foreach ($filasAssoc as $f) {
        $get = function ($keys) use ($f) { foreach ($keys as $k) if (isset($f[$k]) && $f[$k] !== '') return $f[$k]; return null; };
        $valorBruto = $get(['VALOR', 'Valor', 'MONTO', 'Monto', 'IMPORTE', 'DEBITO', 'CREDITO']);
        $valor = is_numeric($valorBruto) ? (float) $valorBruto : (float) str_replace(['$', '.', ','], ['', '', '.'], (string) $valorBruto);
        if (!$valor) continue;
        $fechaBruta = $get(['FECHA', 'Fecha', 'FECHA_MOVIMIENTO']);
        $fecha = null;
        if ($fechaBruta) {
            $ts = is_numeric($fechaBruta) ? (((float) $fechaBruta) - 25569) * 86400 : strtotime((string) $fechaBruta);
            if ($ts) $fecha = gmdate('Y-m-d', (int) $ts);
        }
        $out[] = [
            'fecha' => $fecha,
            'valor' => $valor,
            'referencia' => $get(['REFERENCIA', 'Referencia', 'DOCUMENTO', 'Documento', 'NUMERO', 'DESCRIPCION', 'Descripcion']),
        ];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'conciliar') {
    try {
        if (empty($_FILES['banco']['tmp_name']) || empty($_FILES['siesa']['tmp_name'])) {
            throw new InvalidArgumentException('Sube ambos archivos: banco y Siesa.');
        }
        $tmpBanco = __DIR__ . '/../data/tmp_banco_' . uniqid() . '.xlsx';
        $tmpSiesa = __DIR__ . '/../data/tmp_siesa_' . uniqid() . '.xlsx';
        move_uploaded_file($_FILES['banco']['tmp_name'], $tmpBanco);
        move_uploaded_file($_FILES['siesa']['tmp_name'], $tmpSiesa);

        $sheetsBanco = xlsx_read_all_sheets($tmpBanco);
        $sheetsSiesa = xlsx_read_all_sheets($tmpSiesa);
        $filasBanco = extraer_filas(xlsx_rows_to_assoc(reset($sheetsBanco)));
        $filasSiesa = extraer_filas(xlsx_rows_to_assoc(reset($sheetsSiesa)));

        $resultado = conciliar($filasBanco, $filasSiesa);
        @unlink($tmpBanco);
        @unlink($tmpSiesa);

        $coincidencias = count(array_filter($resultado['conciliados'], fn($c) => $c['siesa'] !== null));
        $msg = ['ok', "Cruce completado: {$coincidencias} coincidencias, " . (count($resultado['conciliados']) - $coincidencias) . " solo en banco, " . count($resultado['solo_siesa']) . " solo en Siesa."];
    } catch (Throwable $e) {
        $msg = ['error', $e->getMessage()];
    }
}

layout_inicio('Conciliación Bancaria', 'Conciliación Bancaria', '../');
?>
<h1><?= icon('dollar', 'icon-lg') ?> Conciliación Bancaria vs Siesa</h1>
<p class="subtitle">Sube el extracto del banco y el listado de movimientos de Siesa (ambos en Excel) y te muestro qué coincide y qué no, por valor y fecha cercana.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<div class="panel">
    <h3>Cargar archivos</h3>
    <form method="post" enctype="multipart/form-data" class="grid-form">
        <input type="hidden" name="accion" value="conciliar">
        <div><label>Extracto bancario (.xlsx) *</label><input type="file" name="banco" accept=".xlsx" required></div>
        <div><label>Movimientos Siesa (.xlsx) *</label><input type="file" name="siesa" accept=".xlsx" required></div>
        <div style="grid-column:1/-1;"><button type="submit"><?= icon('zap') ?> Conciliar</button></div>
    </form>
    <p class="small" style="margin-top:10px;">Busca las columnas VALOR/MONTO/IMPORTE y FECHA en la primera hoja de cada archivo (encabezados en la primera fila). El cruce es por valor exacto y fecha con hasta 3 días de diferencia (por compensación bancaria).</p>
</div>

<?php if ($resultado): ?>
<div class="panel">
    <h3>Movimientos del banco (<?= count($resultado['conciliados']) ?>)</h3>
    <table>
        <tr><th>Fecha banco</th><th>Valor</th><th>Referencia</th><th>Estado</th><th>Fecha Siesa</th></tr>
        <?php foreach ($resultado['conciliados'] as $c): ?>
        <tr>
            <td><?= e($c['banco']['fecha'] ?: '—') ?></td>
            <td>$<?= number_format($c['banco']['valor'], 0, ',', '.') ?></td>
            <td class="small"><?= e($c['banco']['referencia'] ?: '—') ?></td>
            <td><span class="badge <?= $c['siesa'] ? 'badge-activo' : 'badge-err' ?>"><?= $c['siesa'] ? 'COINCIDE' : 'SOLO EN BANCO' ?></span></td>
            <td><?= $c['siesa'] ? e($c['siesa']['fecha'] ?: '—') : '—' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<div class="panel">
    <h3>Solo en Siesa, sin movimiento bancario asociado (<?= count($resultado['solo_siesa']) ?>)</h3>
    <table>
        <tr><th>Fecha</th><th>Valor</th><th>Referencia</th></tr>
        <?php foreach ($resultado['solo_siesa'] as $s): ?>
        <tr><td><?= e($s['fecha'] ?: '—') ?></td><td>$<?= number_format($s['valor'], 0, ',', '.') ?></td><td class="small"><?= e($s['referencia'] ?: '—') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$resultado['solo_siesa']): ?><tr><td colspan="3" class="small">Ninguno — todo lo de Siesa tiene su movimiento bancario.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
