<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_login('../');
$pdo = db();
$msg = null;
$u = usuario_actual();
$puedeGestionar = tiene_rol(['SUPER_ADMIN', 'ADMIN', 'RRHH', 'DIRECTOR', 'GERENCIA', 'CEO']);
$miDocumento = $u['documento'] ?? null;

$camposTexto = [
    'nombre' => 'Nombre completo', 'celular' => 'Celular', 'nacionalidad' => 'Nacionalidad',
    'lugar_nacimiento' => 'Lugar de nacimiento', 'tipo_sangre' => 'Tipo de sangre (ej. O+)',
    'contacto_emergencia' => 'Contacto en caso de emergencia', 'tipo_vinculacion' => 'Tipo de vinculación',
    'turno_trabajo' => 'Turno de trabajo', 'nivel_educacion' => 'Nivel de educación',
    'direccion' => 'Dirección', 'municipio' => 'Municipio', 'tipo_vivienda' => 'Tipo de vivienda',
    'composicion_familiar' => 'Composición familiar', 'raza_ayudas' => 'Raza y ayudas',
    'edades_hijos' => 'Edades de los hijos', 'actividad_fisica' => 'Actividad física',
    'sufre_enfermedad' => '¿Sufre alguna enfermedad?', 'restriccion_medica' => 'Restricción médica',
    'uso_tiempo_libre' => 'Uso del tiempo libre', 'eps' => 'EPS', 'arl' => 'ARL', 'afp' => 'AFP',
];
$camposSelect = [
    'cabeza_hogar' => ['label' => '¿Es cabeza de hogar?', 'opciones' => ['Si', 'No']],
    'sexo' => ['label' => 'Sexo', 'opciones' => ['Femenino', 'Masculino', 'Otro']],
    'sector' => ['label' => 'Sector urbano o rural', 'opciones' => ['Urbano', 'Rural']],
    'pertenece_grupo_vulnerado' => ['label' => '¿Pertenece a algún grupo vulnerado?', 'opciones' => ['Si', 'No']],
    'estado_civil' => ['label' => 'Estado civil', 'opciones' => ['Soltero', 'Casado', 'Unión libre', 'Divorciado', 'Viudo']],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $documento = $puedeGestionar ? limpio($_POST['documento'] ?? null) : $miDocumento;
    if (!$documento) {
        $msg = ['error', 'No se pudo identificar el documento del empleado.'];
    } else {
        $datos = ['documento' => $documento];
        foreach (array_keys($camposTexto) as $c) $datos[$c] = limpio($_POST[$c] ?? null);
        foreach (array_keys($camposSelect) as $c) $datos[$c] = limpio($_POST[$c] ?? null);
        $datos['fecha_nacimiento'] = limpio($_POST['fecha_nacimiento'] ?? null);
        $datos['numero_hijos'] = trim((string) ($_POST['numero_hijos'] ?? '')) === '' ? null : (int) $_POST['numero_hijos'];
        $datos['estrato_socioeconomico'] = trim((string) ($_POST['estrato_socioeconomico'] ?? '')) === '' ? null : (int) $_POST['estrato_socioeconomico'];
        $datos['salario'] = trim((string) ($_POST['salario'] ?? '')) === '' ? null : (float) str_replace(',', '.', str_replace('.', '', $_POST['salario']));
        $datos['actualizado_por'] = $u['nombre'] ?? 'Sistema';

        $stmt = $pdo->prepare("SELECT id, completado_en FROM sst_perfil_sociodemografico WHERE documento = ?");
        $stmt->execute([$documento]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existente) $datos['completado_en'] = gmdate('Y-m-d H:i:s');

        $cols = implode(', ', array_keys($datos));
        $marcadores = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
        $actualizaciones = implode(', ', array_map(fn($k) => "$k = excluded.$k", array_keys($datos)));
        $pdo->prepare("INSERT INTO sst_perfil_sociodemografico ({$cols}, actualizado_en) VALUES ({$marcadores}, CURRENT_TIMESTAMP)
            ON CONFLICT(documento) DO UPDATE SET {$actualizaciones}, actualizado_en = CURRENT_TIMESTAMP")
            ->execute($datos);
        hoja_vida_registrar($pdo, 'EMPLEADO', $documento, $existente ? 'SST_ACTUALIZADO' : 'SST_DILIGENCIADO', 'Perfil sociodemográfico / SST', $u['nombre'] ?? 'Sistema', null);
        $msg = ['ok', 'Perfil guardado.'];
    }
}

// --- Vista: gestión (RRHH/ADMIN) o autogestión (el propio empleado) ---
$documentoVer = $puedeGestionar ? trim((string) ($_GET['documento'] ?? '')) : $miDocumento;
$perfilActivo = null;
if ($documentoVer) {
    $stmt = $pdo->prepare("SELECT * FROM sst_perfil_sociodemografico WHERE documento = ?");
    $stmt->execute([$documentoVer]);
    $perfilActivo = $stmt->fetch(PDO::FETCH_ASSOC);
}
$empleadoVer = null;
if ($documentoVer) {
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE documento = ? LIMIT 1");
    $stmt->execute([$documentoVer]);
    $empleadoVer = $stmt->fetch(PDO::FETCH_ASSOC);
}

$listado = [];
if ($puedeGestionar) {
    $listado = $pdo->query("SELECT e.documento, e.nombres, e.area, e.cargo, e.estado,
            p.completado_en, p.archivado_en, p.fecha_nacimiento
        FROM empleados e LEFT JOIN sst_perfil_sociodemografico p ON p.documento = e.documento
        ORDER BY (p.completado_en IS NULL) DESC, e.nombres")->fetchAll(PDO::FETCH_ASSOC);
}

layout_inicio('SST - Perfil Sociodemográfico', 'SST - Perfil Sociodemográfico', '../');
?>
<h1><?= icon('shield', 'icon-lg') ?> SST — Perfil Sociodemográfico</h1>
<p class="subtitle">Seguridad y Salud en el Trabajo. Información sensible: cada empleado diligencia y actualiza su propio perfil; RRHH/SST ven y gestionan todos. La edad se calcula sola a partir de la fecha de nacimiento — nunca hay que actualizarla a mano.</p>
<?php if ($msg): ?><div class="msg-<?= $msg[0] ?>"><?= e($msg[1]) ?></div><?php endif; ?>

<?php if ($puedeGestionar): ?>
<div class="panel">
    <h3>Empleados (<?= count($listado) ?>)</h3>
    <table>
        <tr><th>Nombre</th><th>Área</th><th>Cargo</th><th>Edad</th><th>Estado</th><th>Perfil SST</th><th></th></tr>
        <?php foreach ($listado as $l): ?>
        <tr>
            <td><?= e($l['nombres']) ?></td>
            <td><?= e($l['area'] ?: '—') ?></td>
            <td><?= e($l['cargo'] ?: '—') ?></td>
            <td><?= sst_edad($l['fecha_nacimiento']) ?? '—' ?></td>
            <td><span class="badge <?= $l['estado'] === 'ACTIVO' ? 'badge-activo' : '' ?>"><?= e($l['estado']) ?></span></td>
            <td>
                <?php if ($l['archivado_en']): ?><span class="badge badge-otro">ARCHIVADO (retirado)</span>
                <?php elseif ($l['completado_en']): ?><span class="badge badge-activo">COMPLETO</span>
                <?php else: ?><span class="badge badge-warn">PENDIENTE</span><?php endif; ?>
            </td>
            <td><a href="?documento=<?= urlencode($l['documento']) ?>">Ver / editar</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$listado): ?><tr><td colspan="7" class="small">Sin empleados registrados todavía.</td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($documentoVer && (!$puedeGestionar || $empleadoVer)): ?>
<div class="panel">
    <h3><?= icon('users') ?> <?= $puedeGestionar ? 'Perfil de ' . e($empleadoVer['nombres'] ?? $documentoVer) : 'Mi perfil sociodemográfico' ?></h3>
    <?php if ($perfilActivo && $perfilActivo['archivado_en']): ?>
        <div class="msg-error">Este perfil está archivado (empleado retirado el <?= e($perfilActivo['archivado_en']) ?>). Se conserva por obligación legal de SST, pero ya no se puede editar.</div>
    <?php else: ?>
    <?php if (!$perfilActivo): ?><div class="msg-warn" style="background:var(--accent-100);color:var(--accent-600);padding:10px 14px;border-radius:8px;margin-bottom:14px;">Pendiente de diligenciar por primera vez.</div><?php endif; ?>
    <form method="post" class="grid-form">
        <input type="hidden" name="accion" value="guardar">
        <?php if ($puedeGestionar): ?><input type="hidden" name="documento" value="<?= e($documentoVer) ?>"><?php endif; ?>

        <div><label>Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="<?= e($perfilActivo['fecha_nacimiento'] ?? '') ?>" onchange="document.getElementById('edad-calc').textContent = this.value ? Math.floor((new Date() - new Date(this.value)) / 31557600000) + ' años' : '—'"></div>
        <div><label>Edad (calculada automáticamente)</label><input type="text" id="edad-calc" value="<?= $perfilActivo ? (sst_edad($perfilActivo['fecha_nacimiento']) ?? '—') . ' años' : '—' ?>" disabled style="background:var(--bg);"></div>

        <?php foreach ($camposTexto as $campo => $label): ?>
        <div><label><?= e($label) ?></label><input type="text" name="<?= $campo ?>" value="<?= e($perfilActivo[$campo] ?? '') ?>"></div>
        <?php endforeach; ?>
        <?php foreach ($camposSelect as $campo => $def): ?>
        <div><label><?= e($def['label']) ?></label>
            <select name="<?= $campo ?>">
                <option value="">--</option>
                <?php foreach ($def['opciones'] as $op): ?><option <?= ($perfilActivo[$campo] ?? '') === $op ? 'selected' : '' ?>><?= e($op) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
        <div><label>Número de hijos</label><input type="number" name="numero_hijos" min="0" value="<?= e($perfilActivo['numero_hijos'] ?? '') ?>"></div>
        <div><label>Estrato socioeconómico</label><input type="number" name="estrato_socioeconomico" min="1" max="6" value="<?= e($perfilActivo['estrato_socioeconomico'] ?? '') ?>"></div>
        <div><label>Salario</label><input type="number" name="salario" min="0" step="0.01" value="<?= e($perfilActivo['salario'] ?? ($empleadoVer['salario'] ?? '')) ?>" placeholder="Pendiente si no está diligenciado"></div>

        <div style="grid-column:1/-1;"><button type="submit"><?= icon('check') ?> Guardar perfil</button></div>
    </form>
    <?php endif; ?>
</div>
<?php elseif (!$puedeGestionar): ?>
<div class="panel"><p class="small">No se encontró tu documento vinculado a un empleado — pide a RRHH que lo verifique.</p></div>
<?php endif; ?>
<?php layout_fin(); ?>
