<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
$pdo = db();
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

$ultimos = $pdo->query("SELECT * FROM inventario WHERE fuente = 'Agente automático' ORDER BY actualizado_en DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Agente de Inventario', 'Agente de inventario', '../');
?>
<h1><?= icon('zap','icon-lg') ?> Instalar el Agente NAVISSI</h1>
<p class="subtitle">Un script liviano (tipo GLPI/OCS Inventory) que corre en cada equipo, lee su hardware real y lo envía aquí automáticamente - sin instalar nada, sin admin.</p>

<div class="pasos-wizard">
    <div class="paso-wizard activo"><span class="paso-punto">1</span> Seleccionar SO</div>
    <div class="paso-wizard"><span class="paso-punto">2</span> Asignar sede</div>
    <div class="paso-wizard"><span class="paso-punto">3</span> Instalar agente</div>
</div>

<div class="panel">
    <h3>Seleccionar un sistema operativo</h3>
    <div class="so-selector">
        <label class="so-opcion activa"><input type="radio" name="so" checked> <?= icon('inventory') ?> Windows (.ps1)</label>
        <label class="so-opcion deshabilitada" title="Aún no disponible"><input type="radio" name="so" disabled> Mac — próximamente</label>
        <label class="so-opcion deshabilitada" title="Aún no disponible"><input type="radio" name="so" disabled> Linux — próximamente</label>
    </div>
    <p class="small" style="margin-top:10px;">Descarga e instala el agente en todos los equipos que quieras supervisar.</p>
</div>

<div class="panel">
    <h3>Cómo desplegarlo</h3>
    <ol>
        <li>Descarga el script: <a href="../data/agente_navissi.ps1" download>agente_navissi.ps1</a></li>
        <li>Cópialo a cada equipo (o compártelo por una carpeta de red/OneDrive).</li>
        <li>Ejecuta una vez para probar:<br>
            <code style="background:#f4f6f9;padding:6px 10px;border-radius:6px;display:inline-block;margin-top:6px;">
                powershell -ExecutionPolicy Bypass -File agente_navissi.ps1 -Servidor "<?= e($base) ?>" -Sede "NOMBRE DE LA SEDE"
            </code>
        </li>
        <li>Para que se reporte solo, créalo como Tarea Programada de Windows (Programador de tareas → Crear tarea básica →
            Al iniciar sesión → Acción: iniciar el mismo comando de arriba). Se puede hacer con GPO si son muchos equipos a la vez.</li>
    </ol>
    <p class="small">El agente solo lee: número de serie, marca, modelo, procesador, RAM, disco, sistema operativo y usuario de Windows. No instala nada, no recolecta archivos ni contraseñas.</p>
</div>

<div class="panel" style="border-left:4px solid var(--teal-500);">
    <h3><?= icon('robot') ?> Reportar Problema — autogestión para empleados sin conocimiento técnico</h3>
    <p class="small">Pensado para gente que no sabe de tecnología: el empleado solo describe lo que le pasa con sus propias
        palabras. El script detecta su equipo automáticamente (mismo serial que ya usa el agente de arriba), arma el
        ticket con la ficha técnica completa, y la IA intenta resolverlo en el momento. Si no puede, queda escalado a TI
        con todo el contexto ya listo - nadie tiene que llenar formularios técnicos.</p>
    <ol>
        <li>Descarga: <a href="../data/reportar_problema.ps1" download>reportar_problema.ps1</a></li>
        <li>Crea un acceso directo en el escritorio de cada equipo (icono "🆘 Reportar un problema") que ejecute:<br>
            <code style="background:#f4f6f9;padding:6px 10px;border-radius:6px;display:inline-block;margin-top:6px;">
                powershell -ExecutionPolicy Bypass -File reportar_problema.ps1 -Servidor "<?= e($base) ?>"
            </code>
        </li>
        <li>Al hacer doble clic, se abre una sola ventana pidiendo "¿qué te está pasando?" - el empleado escribe y listo.
            Si la IA resuelve el problema, se lo muestra ahí mismo; si no, le confirma que ya quedó reportado a TI.</li>
    </ol>
</div>

<div class="panel">
    <h3>Últimos equipos reportados por el agente (<?= count($ultimos) ?>)</h3>
    <table>
        <tr><th>Serial</th><th>Usuario</th><th>Marca/Modelo</th><th>SO</th><th>RAM</th><th>Disco</th><th>Actualizado</th></tr>
        <?php foreach ($ultimos as $u): ?>
        <tr>
            <td><?= e($u['serial']) ?></td>
            <td><?= e($u['asignado_a']) ?></td>
            <td><?= e($u['marca']) ?> <?= e($u['modelo']) ?></td>
            <td><?= e($u['sistema_operativo']) ?></td>
            <td><?= e($u['memoria']) ?></td>
            <td><?= e($u['almacenamiento']) ?></td>
            <td class="small"><?= e($u['actualizado_en']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$ultimos): ?><tr><td colspan="7" class="small">Todavía no se ha ejecutado el agente en ningún equipo.</td></tr><?php endif; ?>
    </table>
</div>
<?php layout_fin(); ?>
