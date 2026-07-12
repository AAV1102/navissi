<?php
// Formulario público (sin necesidad de abrir todo el software) para que cada
// tienda reporte/actualice su información tecnológica. Queda en cola de
// revisión (solicitudes_actualizacion) - TI aprueba antes de que toque el
// inventario real, para no dañar datos por error de digitación.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sedeId = sede_id_por_nombre($pdo, $_POST['sede'] ?? null, false);
    $datos = [
        'serial' => limpio($_POST['serial'] ?? null),
        'placa' => limpio($_POST['placa'] ?? null),
        'tipo' => limpio($_POST['tipo'] ?? null),
        'marca' => limpio($_POST['marca'] ?? null),
        'modelo' => limpio($_POST['modelo'] ?? null),
        'asignado_a' => limpio($_POST['asignado_a'] ?? null),
        'estado_reportado' => limpio($_POST['estado_reportado'] ?? null),
        'observaciones' => limpio($_POST['observaciones'] ?? null),
    ];
    $pdo->prepare("INSERT INTO solicitudes_actualizacion (sede_id, reporta_nombre, reporta_cargo, tipo, datos) VALUES (?,?,?,?,?)")
        ->execute([$sedeId, limpio($_POST['reporta_nombre'] ?? null), limpio($_POST['reporta_cargo'] ?? null),
            limpio($_POST['tipo_solicitud'] ?? null) ?: 'ACTUALIZACION', json_encode($datos, JSON_UNESCAPED_UNICODE)]);
    $enviado = true;
}

$sedes = $pdo->query("SELECT * FROM sedes WHERE estado='ACTIVO' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Actualizar información tecnológica', 'Formulario Tiendas', '../');
?>
<h1><?= icon('file','icon-lg') ?> Actualización de información tecnológica - Tiendas</h1>
<p class="subtitle">Diligencia este formulario cada vez que cambie un equipo, se dañe, se preste o llegue uno nuevo a tu tienda. TI revisa y actualiza el inventario oficial.</p>

<?php if ($enviado): ?>
<div class="msg-ok">
    ¡Gracias! Tu reporte fue enviado y quedó en la cola de revisión de TI.
    <br><a href="formulario_tienda.php">Enviar otro reporte</a>
</div>
<?php else: ?>
<div class="panel">
    <form method="post">
        <div class="grid-form">
            <div><label>Tu nombre *</label><input type="text" name="reporta_nombre" required></div>
            <div><label>Tu cargo</label><input type="text" name="reporta_cargo"></div>
            <div><label>Sede *</label>
                <select name="sede" required>
                    <option value="">-- selecciona tu tienda --</option>
                    <?php foreach ($sedes as $s): ?><option><?= e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid-form">
            <div><label>Tipo de reporte</label>
                <select name="tipo_solicitud">
                    <option value="ACTUALIZACION">Actualizar datos de un equipo existente</option>
                    <option value="NUEVO">Equipo nuevo que llegó a la tienda</option>
                    <option value="DAÑO">Reportar un daño / falla</option>
                    <option value="OTRO">Otro</option>
                </select>
            </div>
            <div><label>Serial</label><input type="text" name="serial"></div>
            <div><label>Placa</label><input type="text" name="placa"></div>
            <div><label>Tipo de equipo</label>
                <select name="tipo">
                    <?php foreach (['PORTATIL','ESCRITORIO','ALL IN ONE','IMPRESORA','CAMARA','TABLET','SERVIDOR','POS','OTRO'] as $t): ?>
                    <option><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Marca</label><input type="text" name="marca"></div>
            <div><label>Modelo</label><input type="text" name="modelo"></div>
            <div><label>Asignado a (persona)</label><input type="text" name="asignado_a"></div>
            <div><label>Estado actual</label>
                <select name="estado_reportado">
                    <?php foreach (['FUNCIONANDO BIEN','CON FALLA','DAÑADO','NO SE USA'] as $es): ?>
                    <option><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <label class="small">Observaciones / detalle del reporte</label><br>
        <textarea name="observaciones" rows="4" style="width:100%;padding:8px;border:1px solid #d3dae3;border-radius:6px;font-family:inherit;margin-bottom:14px;"></textarea>
        <button type="submit">Enviar reporte</button>
    </form>
</div>
<?php endif; ?>
<?php layout_fin(); ?>
