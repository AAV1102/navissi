<?php
require_once __DIR__ . '/icons.php';

/**
 * Estructura del menú: replica el orden de grupos que ya tenías definido en
 * WorkManager (dashboard.php -> $retailGroups). Cada item ahora tiene un
 * icono real (SVG) en vez de texto plano. Cada grupo declara qué roles lo
 * pueden ver.
 */
function nav_grupos() {
    $base = [
        'Mesa de servicios' => [
            'icon' => 'ticket', 'roles' => ['GERENCIA', 'CEO', 'ADMIN', 'TI', 'COORDINADOR', 'RRHH', 'DIRECTOR'],
            'items' => [
                'modules/mesa_ayuda.php' => ['Mesa de Ayuda', 'ticket'],
                'modules/tickets_historial.php' => ['Historial de tickets', 'file'],
                'modules/solicitudes.php' => ['Solicitudes de tiendas', 'store'],
                'modules/buscador_universal.php' => ['Buscador Universal', 'search'],
                'modules/base_conocimiento.php' => ['Base de Conocimiento', 'book'],
                'modules/respuestas_rapidas.php' => ['Respuestas Rápidas', 'zap'],
                'modules/catalogo_servicios.php' => ['Catálogo de Servicios', 'inventory'],
                'modules/aprobaciones.php' => ['Solicitudes y Aprobaciones', 'check'],
                'modules/categorias_tickets.php' => ['Categorías de Tickets', 'ticket'],
                'modules/alertas.php' => ['Alertas', 'bell'],
                'modules/umbrales_alertas.php' => ['Umbrales de Alertas', 'bell'],
                'modules/sla_politicas.php' => ['Políticas SLA', 'shield'],
                'modules/informes.php' => ['Informes', 'inventory'],
                'modules/plantillas_correo.php' => ['Plantillas de Correo', 'chat'],
                'modules/proyectos.php' => ['Proyectos', 'dashboard'],
                'modules/gestion_documental.php' => ['Gestión Documental', 'folder'],
                'modules/firmas_oficiales.php' => ['Firmas Oficiales', 'check'],
            ],
        ],
        'Inventario y activos' => [
            'icon' => 'inventory', 'roles' => ['SUPER_ADMIN', 'GERENCIA', 'CEO', 'ADMIN', 'TI', 'DIRECTOR', 'COORDINADOR', 'ANALISTA'],
            'areas' => ['Direccion de Tecnologia', 'Direccion de Logistica'],
            'items' => [
                'modules/inventario.php' => ['Inventario', 'inventory'],
                'modules/movimientos.php' => ['Movimientos', 'arrow-right'],
                'modules/documentos.php' => ['Documentos', 'folder'],
                'modules/importar.php' => ['Importador universal', 'upload'],
                'modules/exportar.php' => ['Exportador universal', 'file'],
                'modules/agente_inventario.php' => ['Agente de inventario', 'zap'],
                'modules/ordenes_agente.php' => ['Órdenes del agente', 'zap'],
                'modules/acceso_remoto.php' => ['Acceso Remoto', 'zap'],
                'modules/qr_equipos.php' => ['Códigos QR de Equipos', 'inventory'],
                'modules/grupos_codigos.php' => ['Códigos Agrupados', 'inventory'],
                'modules/logistica.php' => ['Logística y Bodega', 'inventory'],
                'modules/monitor_precios.php' => ['Monitor de Precios (scraping)', 'dollar'],
                'modules/mermas.php' => ['Mermas e Inventario Perdido', 'inventory'],
                'modules/bajas_equipos.php' => ['Bajas de Equipos', 'inventory'],
                'modules/compras_equipos.php' => ['Compras de Equipos', 'dollar'],
                'modules/gestion_parches.php' => ['Gestión de Parches', 'shield'],
                'modules/actas_equipos.php' => ['Actas de Entrega/Devolución', 'file'],
                'modules/network_discovery.php' => ['Network Discovery', 'cloud'],
                'modules/impresoras.php' => ['Impresoras', 'file'],
                'modules/vpn.php' => ['VPN', 'shield'],
                'modules/mikrotik.php' => ['Routers / Mikrotik', 'cloud'],
                'modules/ciberseguridad.php' => ['Ciberseguridad y Seguridad', 'shield'],
            ],
        ],
        'Tiendas e infraestructura' => [
            'icon' => 'store', 'roles' => ['GERENCIA', 'CEO', 'ADMIN', 'TI', 'COORDINADOR', 'DIRECTOR'],
            'areas' => ['Direccion de Tecnologia', 'Direccion de Logistica', 'Direccion Comercial'],
            'items' => [
                'modules/salud_tiendas.php' => ['Salud de Tiendas', 'store'],
                'modules/mapa_tiendas.php' => ['Mapa de Tiendas', 'store'],
                'modules/sedes.php' => ['Sedes', 'building'],
                'modules/formulario_tienda.php' => ['Formulario para tiendas', 'file'],
                'modules/contratos.php' => ['Contratos y Proveedores', 'file'],
                'modules/horario_laboral.php' => ['Horario Laboral', 'briefcase'],
                'modules/calendario.php' => ['Calendario', 'dashboard'],
                'modules/campanas.php' => ['Calendario de Colecciones', 'dashboard'],
            ],
        ],
        'Automatización e IA' => [
            'icon' => 'robot', 'roles' => ['GERENCIA', 'CEO', 'ADMIN', 'TI'],
            'areas' => ['Direccion de Tecnologia'],
            'items' => [
                'modules/inteligencia_operativa.php' => ['Inteligencia Operativa', 'dashboard'],
                'modules/retail_inteligencia.php' => ['Inventario Retail', 'inventory'],
                'modules/gobierno_secretos.php' => ['Gobierno de Secretos', 'shield'],
                'modules/automatizaciones.php' => ['Automatizaciones y alertas', 'bell'],
                'modules/notificaciones.php' => ['Centro de Notificaciones', 'send'],
                'modules/whatsapp.php' => ['WhatsApp Business', 'chat'],
                'modules/n8n.php' => ['n8n (flujos)', 'zap'],
                'modules/ia_multiagente.php' => ['IA Multiagente', 'robot'],
                'modules/centro_aplicaciones.php' => ['Centro de Aplicaciones', 'robot'],
            ],
        ],
        'CRM y ecommerce' => [
            'icon' => 'users', 'roles' => ['GERENCIA', 'CEO', 'ADMIN', 'COORDINADOR', 'DIRECTOR'],
            'areas' => ['Direccion Comercial', 'Direccion de Ecommerce', 'Direccion de Marketing'],
            'items' => [
                'modules/crm.php' => ['Clientes y proveedores', 'users'],
                'modules/comercial.php' => ['Comercial', 'dollar'],
                'modules/oportunidades.php' => ['Pipeline de Ventas', 'dollar'],
                'modules/comisiones.php' => ['Comisiones de Venta', 'dollar'],
                'modules/ecommerce.php' => ['Ecommerce', 'store'],
                'modules/marketing.php' => ['Marketing', 'megaphone'],
                'modules/monitor_precios.php' => ['Monitor de Precios (scraping)', 'dollar'],
                'modules/devoluciones.php' => ['Devoluciones y Garantías', 'inventory'],
                'modules/servicio_cliente.php' => ['Servicio al Cliente (PQRS)', 'chat'],
            ],
        ],
        'Canales y Microsoft 365' => [
            'icon' => 'cloud', 'roles' => ['GERENCIA', 'CEO', 'ADMIN', 'TI'],
            'areas' => ['Direccion de Tecnologia'],
            'items' => [
                'modules/identidades.php' => ['Gobierno de Identidades', 'users'],
                'modules/microsoft365.php' => ['Microsoft 365', 'cloud'],
                'modules/conexiones_microsoft.php' => ['OneDrive / SharePoint / Teams', 'folder'],
                'modules/correo_tickets.php' => ['Correo → Tickets', 'ticket'],
                'modules/credenciales.php' => ['Credenciales', 'key'],
                'modules/siesa.php' => ['Siesa', 'key'],
                'modules/siesa_integracion.php' => ['Conector Siesa', 'cloud'],
                'modules/licencias.php' => ['Licencias', 'shield'],
                'modules/noticias.php' => ['Noticias', 'megaphone'],
            ],
        ],
        'Producción y Operación' => [
            'icon' => 'check', 'roles' => ['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO', 'COORDINADOR'],
            'areas' => ['Direccion de Produccion', 'Direccion de Operacion'],
            'items' => [
                'modules/produccion.php' => ['Producción', 'inventory'],
                'modules/operacion.php' => ['Operación', 'check'],
            ],
        ],
        'Contabilidad' => [
            'icon' => 'dollar', 'roles' => ['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO'],
            'areas' => ['Direccion de Contabilidad'],
            'items' => [
                'modules/gastos_proveedor.php' => ['Gastos por Proveedor', 'dollar'],
                'modules/conciliacion_bancaria.php' => ['Conciliación Bancaria', 'dollar'],
            ],
        ],
        'Formularios y constructor' => [
            'icon' => 'file', 'roles' => ['ADMIN', 'DIRECTOR', 'RRHH', 'GERENCIA', 'CEO', 'SUPER_ADMIN'],
            'items' => [
                'modules/constructor_formularios.php' => ['Constructor de Formularios', 'file'],
            ],
        ],
        'Seguridad y gobierno' => [
            'icon' => 'shield', 'roles' => ['ADMIN'],
            'items' => [
                'modules/usuarios.php' => ['Usuarios y roles', 'users'],
                'modules/perfiles_modulos.php' => ['Perfiles por rol', 'shield'],
                'modules/personalizar_marca.php' => ['Personalizar Marca', 'inventory'],
                'modules/personalizar_textos.php' => ['Personalizar Textos del Menú', 'sliders'],
                'modules/2fa_configurar.php' => ['Mi Cuenta', 'shield'],
                'modules/auditoria.php' => ['Auditoría', 'log'],
                'modules/hoja_vida.php' => ['Hoja de Vida', 'file'],
                'modules/departamentos.php' => ['Departamentos y Cargos', 'briefcase'],
                'modules/organigrama.php' => ['Organigrama', 'dashboard'],
                'modules/diagnostico.php' => ['Diagnóstico del Sistema', 'shield'],
                'modules/campos_personalizados.php' => ['Campos Personalizados', 'inventory'],
            ],
        ],
        'Talento Humano' => [
            'icon' => 'briefcase', 'roles' => ['GERENCIA', 'CEO', 'ADMIN', 'RRHH', 'DIRECTOR'],
            'areas' => ['Direccion Recursos Humanos'],
            'items' => [
                'modules/rrhh.php' => ['Empleados', 'users'],
                'modules/sst_perfil.php' => ['SST - Perfil Sociodemográfico', 'shield'],
                'modules/asistencia.php' => ['Control de Asistencia', 'briefcase'],
                'modules/rrhh_certificados.php' => ['Certificados y desprendibles', 'dollar'],
                'modules/rrhh_documentos.php' => ['Documentos y firmas (OneDrive)', 'file'],
                'modules/documentacion.php' => ['Documentación / capacitación', 'graduation'],
                'modules/vacaciones.php' => ['Vacaciones y Permisos', 'briefcase'],
                'modules/nomina.php' => ['Nómina', 'dollar'],
                'modules/evaluaciones.php' => ['Evaluaciones de Desempeño', 'graduation'],
                'modules/denuncias_admin.php' => ['Gestión de Denuncias', 'shield'],
                'modules/vacantes.php' => ['Vacantes', 'briefcase'],
                'modules/huelleros.php' => ['Huelleros / Biométricos', 'briefcase'],
            ],
        ],
        'Legal y Finanzas' => [
            'icon' => 'dollar', 'roles' => ['ADMIN', 'GERENCIA', 'CEO'],
            'items' => [
                'modules/juridico.php' => ['Jurídico', 'file'],
                'modules/tesoreria.php' => ['Tesorería', 'dollar'],
            ],
        ],
    ];

    // Aplica etiquetas personalizadas (Personalizar Textos del Menú), sin tocar código.
    try {
        $pdo = db();
        $overrides = $pdo->query("SELECT href, etiqueta FROM etiquetas_menu")->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($overrides) {
            foreach ($base as &$grupo) {
                foreach ($grupo['items'] as $href => &$item) {
                    if (isset($overrides[$href])) $item[0] = $overrides[$href];
                }
                unset($item);
            }
            unset($grupo);
        }
    } catch (\Throwable $e) { /* si la tabla aun no existe (migracion no corrida), sigue con las etiquetas por defecto */ }

    return $base;
}

function layout_inicio($titulo, $activo, $prefix = '') {
    requiere_login($prefix);
    $u = usuario_actual();
    $pdo = db();
    $iniciales = mb_strtoupper(mb_substr($u['nombre'] ?? '?', 0, 1));
    $notificaciones = notificaciones_pendientes($pdo);
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php
$marcaConfigPathHead = __DIR__ . '/../data/marca_config.json';
$marcaHead = file_exists($marcaConfigPathHead) ? (json_decode(file_get_contents($marcaConfigPathHead), true) ?: []) : [];
?>
<title><?= e($titulo) ?> - <?= e($marcaHead['nombre_sitio'] ?? 'NAVISSI Inventario') ?></title>
<link rel="stylesheet" href="<?= $prefix ?>assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: time() ?>">
<?php if (!empty($marcaHead['color_acento'])): ?>
<style>:root{--accent-600:<?= e($marcaHead['color_acento']) ?>;--accent-500:<?= e($marcaHead['color_acento']) ?>;}</style>
<?php endif; ?>
<script>
(function () {
    var tema = localStorage.getItem('navissi_tema');
    if (!tema) tema = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', tema);
    var html = document.documentElement;
    var fuente = localStorage.getItem('navissi_fuente');
    if (fuente) html.style.fontSize = fuente + '%';
    if (localStorage.getItem('navissi_contraste') === '1') html.classList.add('alto-contraste');
    if (localStorage.getItem('navissi_movimiento') === '1') html.classList.add('reducir-movimiento');
    if (localStorage.getItem('navissi_subrayado') === '1') html.classList.add('subrayar-enlaces');
})();
</script>
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function (form) {
            if (form.querySelector('input[name="_csrf"]')) return;
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = '_csrf'; input.value = token;
            form.appendChild(input);
        });
    });
    const originalFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init || {};
        const method = (init.method || 'GET').toUpperCase();
        const url = typeof input === 'string' ? new URL(input, location.href) : new URL(input.url, location.href);
        if (url.origin === location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            const headers = new Headers(init.headers || (typeof input !== 'string' ? input.headers : undefined));
            headers.set('X-CSRF-Token', token);
            init.headers = headers;
        }
        return originalFetch(input, init);
    };
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.revelar-credencial');
        if (!button) return;
        const target = document.getElementById(button.dataset.target);
        if (button.classList.contains('visible')) {
            target.textContent = '••••••••'; button.textContent = 'Ver'; button.classList.remove('visible');
            return;
        }
        button.disabled = true;
        fetch('<?= $prefix ?>api_revelar_credencial.php?id=' + encodeURIComponent(button.dataset.id), {headers: {'Accept': 'application/json'}})
            .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
            .then(function (data) { target.textContent = data.secreto || '(vacía)'; button.textContent = 'Ocultar'; button.classList.add('visible'); })
            .catch(function () { target.textContent = 'No autorizado'; })
            .finally(function () { button.disabled = false; });
    });
})();
</script>
</head>
<body>
<a href="#contenido" class="skip-link">Saltar al contenido</a>
<div class="app-shell">
    <button class="sidebar-backdrop" id="sidebar-backdrop" hidden aria-hidden="true"></button>
    <aside class="sidebar" id="sidebar" aria-label="Menú principal">
        <div class="sidebar-brand">
            <a href="<?= $prefix ?>index.php">
                <?php
                $marcaConfigPath = __DIR__ . '/../data/marca_config.json';
                $marca = file_exists($marcaConfigPath) ? json_decode(file_get_contents($marcaConfigPath), true) : [];
                ?>
                <?php if (!empty($marca['logo'])): ?>
                <span class="brand-mark brand-mark-img"><img src="<?= $prefix ?>assets/uploads/<?= e($marca['logo']) ?>" alt="Logo"></span>
                <?php else: ?>
                <span class="brand-mark"><?= icon('inventory', 'icon icon-lg') ?></span>
                <?php endif; ?>
                <span class="sidebar-brand-text"><?= e($marca['nombre_sitio'] ?? 'NAVISSI') ?><br><span><?= e($marca['subtitulo'] ?? 'Backstage · Operación retail') ?></span></span>
            </a>
            <button class="sidebar-close" id="sidebar-close" aria-label="Cerrar menú"><?= icon('x','icon') ?></button>
        </div>
        <nav class="sidebar-nav">
            <a class="sidebar-link <?= $activo === 'Dashboard' ? 'active' : '' ?>" href="<?= $prefix ?>index.php" <?= $activo === 'Dashboard' ? 'aria-current="page"' : '' ?>><?= icon('dashboard') ?> Dashboard</a>
            <a class="sidebar-link <?= $activo === 'Mi Portal de Empleado' ? 'active' : '' ?>" href="<?= $prefix ?>modules/portal_empleado.php" <?= $activo === 'Mi Portal de Empleado' ? 'aria-current="page"' : '' ?>><?= icon('users') ?> Mi Portal / Autogestión</a>
            <a class="sidebar-link <?= $activo === 'Mis Accesos' ? 'active' : '' ?>" href="<?= $prefix ?>modules/mis_accesos.php" <?= $activo === 'Mis Accesos' ? 'aria-current="page"' : '' ?>><?= icon('key') ?> Mis Accesos</a>
            <a class="sidebar-link <?= $activo === 'Mis Documentos' ? 'active' : '' ?>" href="<?= $prefix ?>modules/mis_documentos.php" <?= $activo === 'Mis Documentos' ? 'aria-current="page"' : '' ?>><?= icon('folder') ?> Mis Documentos</a>
            <a class="sidebar-link <?= $activo === 'SST - Perfil Sociodemográfico' ? 'active' : '' ?>" href="<?= $prefix ?>modules/sst_perfil.php" <?= $activo === 'SST - Perfil Sociodemográfico' ? 'aria-current="page"' : '' ?>><?= icon('shield') ?> Mi Perfil SST</a>
            <a class="sidebar-link <?= $activo === 'Canales' ? 'active' : '' ?>" href="<?= $prefix ?>modules/canales.php" <?= $activo === 'Canales' ? 'aria-current="page"' : '' ?>><?= icon('cloud') ?> Canales</a>
            <a class="sidebar-link <?= $activo === 'Canal de Denuncias' ? 'active' : '' ?>" href="<?= $prefix ?>modules/denuncias.php" <?= $activo === 'Canal de Denuncias' ? 'aria-current="page"' : '' ?>><?= icon('shield') ?> Canal de Denuncias</a>
            <?php $modulosExtra = modulos_extra_usuario(); ?>
            <?php foreach (nav_grupos() as $grupo => $def):
                $tieneAccesoPorRol = tiene_rol($def['roles'])
                    && (empty($def['areas']) || tiene_acceso_universal_modulos() || in_array($u['area_responsable'] ?? null, $def['areas'], true));
                $items = $def['items'];
                if (!$tieneAccesoPorRol) {
                    // Aunque el rol no de acceso al grupo completo, se muestran
                    // los items puntuales que un admin le haya asignado a este
                    // usuario individualmente (ver Usuarios y roles).
                    $items = array_filter($items, fn($href) => in_array($href, $modulosExtra, true), ARRAY_FILTER_USE_KEY);
                    if (!$items) continue;
                }
                $labels = array_map(fn($i) => $i[0], $items);
                $grupoActivo = in_array($activo, $labels, true);
            ?>
            <div class="sidebar-group <?= $grupoActivo ? 'open' : '' ?>">
                <button type="button" class="sidebar-group-label" aria-expanded="<?= $grupoActivo ? 'true' : 'false' ?>">
                    <?= icon($def['icon']) ?> <span><?= e($grupo) ?></span> <?= icon('chevron-down', 'icon sidebar-caret') ?>
                </button>
                <div class="sidebar-group-items">
                    <?php foreach ($items as $href => [$label, $ic]): ?>
                    <a class="sidebar-link <?= $activo === $label ? 'active' : '' ?>" href="<?= $prefix . $href ?>" <?= $activo === $label ? 'aria-current="page"' : '' ?>><?= icon($ic) ?> <?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <a href="<?= $prefix ?>modules/base_conocimiento.php"><?= icon('book','icon') ?> Ayuda</a>
            <span class="small">NAVISSI · Grupo 10Z</span>
        </div>
    </aside>

    <div class="app-main">
        <?php if (viendo_como()): ?>
        <div class="ver-como-banner">
            <?= icon('users','icon') ?> Estás viendo NAVISSI como <strong><?= e($_SESSION['ver_como_rol']) ?></strong><?= !empty($_SESSION['ver_como_area']) ? ' (área: ' . e($_SESSION['ver_como_area']) . ')' : '' ?> — esto es solo una vista de prueba, tu cuenta real sigue siendo SUPER_ADMIN.
            <form method="post" action="<?= $prefix ?>api_ver_como.php" class="inline">
                <input type="hidden" name="volver" value="<?= e($_SERVER['REQUEST_URI'] ?? $prefix . 'index.php') ?>">
                <button type="submit" class="btn btn-secondary" style="padding:4px 12px;font-size:12px;">Volver a mi vista</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="topbar-slim">
            <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="sidebar" aria-label="Abrir menú"><?= icon('dashboard','icon') ?></button>
            <div class="topbar-center">
                <input type="search" id="buscador-modulos" class="buscador-modulos" placeholder="Buscar un módulo... (ej. contratos, tickets, nómina)" autocomplete="off" aria-label="Buscar un módulo" role="combobox" aria-expanded="false" aria-controls="resultados-modulos">
                <div id="resultados-modulos" class="resultados-modulos" role="listbox" hidden></div>
            </div>
            <div class="topbar-user">
                <?php if ($u['rol'] === 'SUPER_ADMIN'): ?>
                <div class="ver-como-switch">
                    <button type="button" class="btn btn-secondary icon-btn" id="ver-como-trigger" aria-expanded="false" aria-controls="ver-como-panel" title="Ver como otro rol"><?= icon('sliders','icon') ?></button>
                    <div class="ver-como-panel" id="ver-como-panel" hidden>
                        <p class="small" style="margin:0 0 10px;">Probar la interfaz como otro rol/área, sin salir de tu cuenta:</p>
                        <form method="post" action="<?= $prefix ?>api_ver_como.php">
                            <input type="hidden" name="volver" value="<?= e($_SERVER['REQUEST_URI'] ?? $prefix . 'index.php') ?>">
                            <label class="small">Rol</label>
                            <select name="rol">
                                <option value="">SUPER_ADMIN (mi vista real)</option>
                                <?php foreach (ROLES_DISPONIBLES as $r): if ($r === 'SUPER_ADMIN') continue; ?>
                                <option value="<?= e($r) ?>" <?= (($_SESSION['ver_como_rol'] ?? '') === $r) ? 'selected' : '' ?>><?= e($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="small">Área (opcional)</label>
                            <input type="text" name="area" value="<?= e($_SESSION['ver_como_area'] ?? '') ?>" placeholder="Ej. Marketing">
                            <button type="submit" style="width:100%;margin-top:10px;"><?= icon('check') ?> Aplicar</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <div class="notif-switch">
                    <button type="button" class="btn btn-secondary icon-btn" id="notif-trigger" aria-expanded="false" aria-controls="notif-panel" title="Notificaciones">
                        <?= icon('bell','icon') ?>
                        <?php if ($notificaciones): ?><span class="notif-dot"><?= count($notificaciones) ?></span><?php endif; ?>
                    </button>
                    <div class="notif-panel" id="notif-panel" hidden>
                        <p class="notif-panel-title">Notificaciones</p>
                        <?php if (!$notificaciones): ?>
                        <p class="small" style="padding:14px;margin:0;">No hay alertas pendientes ahora mismo.</p>
                        <?php else: foreach ($notificaciones as $n): ?>
                        <a class="notif-item notif-<?= $n['tipo'] ?>" href="<?= $prefix . $n['href'] ?>">
                            <?= icon($n['tipo'] === 'err' ? 'x' : ($n['tipo'] === 'warn' ? 'bell' : 'check')) ?>
                            <?= e($n['texto']) ?>
                        </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <div class="notif-switch">
                    <button type="button" class="btn btn-secondary icon-btn" id="ayuda-trigger" aria-expanded="false" aria-controls="ayuda-panel" title="Ayuda y recursos">?</button>
                    <div class="notif-panel ayuda-panel" id="ayuda-panel" hidden>
                        <div class="ayuda-panel-head">
                            <strong>Hola <?= e(explode(' ', $u['nombre'])[0]) ?>,</strong>
                            <p class="small">¿Cómo podemos hacer su día más fácil?</p>
                        </div>
                        <form method="get" action="<?= $prefix ?>modules/mesa_ayuda.php" class="ayuda-panel-cta">
                            <p class="small" style="margin:0 0 6px;font-weight:600;">Pregúntanos cualquier cosa</p>
                            <p class="small" style="margin:0 0 8px;">Crea un ticket y el equipo de TI te responde.</p>
                            <button type="submit" class="btn-secondary" style="width:100%;"><?= icon('ticket') ?> Ir a Mesa de Ayuda</button>
                        </form>
                        <div class="ayuda-panel-links">
                            <a href="<?= $prefix ?>modules/base_conocimiento.php"><?= icon('book') ?> Base de Conocimiento</a>
                            <a href="<?= $prefix ?>modules/informes.php"><?= icon('inventory') ?> Informes y estado del sistema</a>
                            <a href="<?= $prefix ?>modules/mesa_ayuda.php"><?= icon('ticket') ?> Mis tickets</a>
                        </div>
                        <p class="small ayuda-panel-foot">NAVISSI Inventario · soporte interno de Grupo 10Z</p>
                    </div>
                </div>
                <div class="user-switch">
                    <button type="button" class="user-chip user-chip-btn" id="user-trigger" aria-expanded="false" aria-controls="user-panel">
                        <span class="avatar"><?= e($iniciales) ?></span> <?= e($u['nombre']) ?> · <?= e($u['rol']) ?> <?= icon('chevron-down','icon') ?>
                    </button>
                    <div class="user-panel" id="user-panel" hidden>
                        <div class="user-panel-head">
                            <span class="avatar"><?= e($iniciales) ?></span>
                            <div><strong><?= e($u['nombre']) ?></strong><br><span class="small"><?= e($u['email']) ?></span></div>
                        </div>
                        <button type="button" class="user-panel-item" id="tema-trigger">
                            <?= icon('sun','icon tema-icon-sun') ?><?= icon('moon','icon tema-icon-moon') ?> Cambiar interfaz (claro/oscuro)
                        </button>
                        <a class="user-panel-item" href="<?= $prefix ?>modules/2fa_configurar.php"><?= icon('shield','icon') ?> Mi cuenta y contraseña</a>
                        <a class="user-panel-item user-panel-danger" href="<?= $prefix ?>logout.php"><?= icon('x','icon') ?> Cerrar sesión</a>
                    </div>
                </div>
            </div>
        </div>
<script>
(function () {
    var TODOS_MODULOS = [
        <?php foreach (nav_grupos() as $grupo => $def):
            $tieneAccesoGrupoBusqueda = tiene_rol($def['roles'])
                && (empty($def['areas']) || tiene_acceso_universal_modulos() || in_array($u['area_responsable'] ?? null, $def['areas'], true));
            $itemsBusqueda = $tieneAccesoGrupoBusqueda ? $def['items'] : array_filter($def['items'], fn($href) => in_array($href, $modulosExtra, true), ARRAY_FILTER_USE_KEY);
            foreach ($itemsBusqueda as $href => [$label, $ic]): ?>
        { label: <?= json_encode($label) ?>, grupo: <?= json_encode($grupo) ?>, href: <?= json_encode($prefix . $href) ?> },
        <?php endforeach; endforeach; ?>
    ];

    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebar-backdrop');
    var toggle = document.getElementById('nav-toggle');
    var cerrar = document.getElementById('sidebar-close');
    function abrirSidebar() { sidebar.classList.add('open'); backdrop.hidden = false; toggle.setAttribute('aria-expanded', 'true'); }
    function cerrarSidebar() { sidebar.classList.remove('open'); backdrop.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }
    toggle.addEventListener('click', function () { sidebar.classList.contains('open') ? cerrarSidebar() : abrirSidebar(); });
    backdrop.addEventListener('click', cerrarSidebar);
    if (cerrar) cerrar.addEventListener('click', cerrarSidebar);

    document.querySelectorAll('.sidebar-group-label').forEach(function (label) {
        label.addEventListener('click', function () {
            var grupo = label.closest('.sidebar-group');
            var abierto = grupo.classList.toggle('open');
            label.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        });
    });

    // Patron reutilizable: boton que abre/cierra un panel flotante (notificaciones, switch de rol).
    function crearToggle(triggerId, panelId) {
        var trig = document.getElementById(triggerId);
        var pan = document.getElementById(panelId);
        if (!trig || !pan) return null;
        trig.addEventListener('click', function () {
            var abierto = pan.hidden;
            pan.hidden = !abierto;
            trig.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        });
        return { trig: trig, pan: pan };
    }
    var togglers = [
        crearToggle('notif-trigger', 'notif-panel'),
        crearToggle('ayuda-trigger', 'ayuda-panel'),
        crearToggle('ver-como-trigger', 'ver-como-panel'),
        crearToggle('user-trigger', 'user-panel'),
    ].filter(Boolean);

    var temaBtn = document.getElementById('tema-trigger');
    if (temaBtn) {
        temaBtn.addEventListener('click', function () {
            var actual = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            var nuevo = actual === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', nuevo);
            localStorage.setItem('navissi_tema', nuevo);
        });
    }
    document.addEventListener('click', function (e) {
        togglers.forEach(function (t) {
            if (!t.pan.hidden && !t.pan.contains(e.target) && !t.trig.contains(e.target)) {
                t.pan.hidden = true;
                t.trig.setAttribute('aria-expanded', 'false');
            }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            togglers.forEach(function (t) { t.pan.hidden = true; t.trig.setAttribute('aria-expanded', 'false'); });
            cerrarSidebar();
        }
    });

    var buscador = document.getElementById('buscador-modulos');
    var resultados = document.getElementById('resultados-modulos');
    function cerrarBusqueda() { resultados.hidden = true; buscador.setAttribute('aria-expanded', 'false'); }
    buscador.addEventListener('input', function () {
        var q = buscador.value.trim().toLowerCase();
        if (!q) { cerrarBusqueda(); return; }
        var coincidencias = TODOS_MODULOS.filter(function (m) { return m.label.toLowerCase().indexOf(q) !== -1 || m.grupo.toLowerCase().indexOf(q) !== -1; }).slice(0, 10);
        resultados.innerHTML = '';
        if (!coincidencias.length) {
            resultados.innerHTML = '<div class="resultado-vacio">Sin coincidencias</div>';
        } else {
            coincidencias.forEach(function (m) {
                var a = document.createElement('a');
                a.href = m.href;
                a.setAttribute('role', 'option');
                a.innerHTML = '<strong>' + m.label + '</strong><span class="small"> — ' + m.grupo + '</span>';
                resultados.appendChild(a);
            });
        }
        resultados.hidden = false;
        buscador.setAttribute('aria-expanded', 'true');
    });
    document.addEventListener('click', function (e) {
        if (!resultados.contains(e.target) && e.target !== buscador) cerrarBusqueda();
    });
    buscador.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarBusqueda(); });
})();
</script>
<?php
// Migas de pan: se calculan solas a partir de en qué grupo del menú vive el
// módulo activo, sin tener que declararlas a mano en cada página.
$grupoActivoNombre = null;
if ($activo !== 'Dashboard') {
    foreach (nav_grupos() as $grupoNombre => $def) {
        $labels = array_map(fn($i) => $i[0], $def['items']);
        if (in_array($activo, $labels, true)) { $grupoActivoNombre = $grupoNombre; break; }
    }
}
?>
<?php if ($activo !== 'Dashboard'): ?>
<nav class="breadcrumbs" aria-label="Ruta de navegación">
    <a href="<?= $prefix ?>index.php"><?= icon('dashboard','icon') ?> Dashboard</a>
    <?php if ($grupoActivoNombre): ?><span class="breadcrumb-sep">/</span><span><?= e($grupoActivoNombre) ?></span><?php endif; ?>
    <span class="breadcrumb-sep">/</span><span class="breadcrumb-actual"><?= e($activo) ?></span>
</nav>
<?php endif; ?>
<main id="contenido"><?php
}

function layout_fin() {
    $prefix = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/modules/') !== false ? '../' : '';
    $marcaConfigPathFooter = __DIR__ . '/../data/marca_config.json';
    $marcaFooter = file_exists($marcaConfigPathFooter) ? (json_decode(file_get_contents($marcaConfigPathFooter), true) ?: []) : [];
    ?></main>
<footer class="site-footer">
    <span><?= e($marcaFooter['texto_footer'] ?? 'NAVISSI Inventario · Grupo 10Z SAS') ?> · <?= date('Y') ?> · <span class="footer-marca">Creado por Anderson Ayala Vera — Director de Tecnología</span></span>
    <span class="site-footer-links">
        <a href="<?= $prefix ?>modules/base_conocimiento.php"><?= icon('book','icon') ?> Base de Conocimiento</a>
        <a href="<?= $prefix ?>modules/mesa_ayuda.php"><?= icon('ticket','icon') ?> Mesa de Ayuda</a>
    </span>
</footer>
    </div>
</div>

<button id="a11y-launcher" title="Opciones de accesibilidad" aria-label="Opciones de accesibilidad" aria-expanded="false" aria-controls="a11y-panel"><?= icon('accessibility', 'icon icon-lg') ?></button>
<div id="a11y-panel" hidden role="dialog" aria-label="Opciones de accesibilidad">
    <div class="a11y-head">
        <strong><?= icon('accessibility','icon') ?> Accesibilidad</strong>
        <button id="a11y-close" aria-label="Cerrar"><?= icon('x') ?></button>
    </div>
    <div class="a11y-body">
        <div class="a11y-row">
            <span><?= icon('type','icon') ?> Tamaño de texto</span>
            <div class="a11y-btns">
                <button type="button" id="a11y-font-menos" aria-label="Reducir texto">A-</button>
                <button type="button" id="a11y-font-reset" aria-label="Restablecer texto">A</button>
                <button type="button" id="a11y-font-mas" aria-label="Aumentar texto">A+</button>
            </div>
        </div>
        <label class="a11y-row a11y-toggle">
            <span><?= icon('contrast','icon') ?> Alto contraste</span>
            <input type="checkbox" id="a11y-contraste">
        </label>
        <label class="a11y-row a11y-toggle">
            <span><?= icon('sliders','icon') ?> Reducir movimiento</span>
            <input type="checkbox" id="a11y-movimiento">
        </label>
        <label class="a11y-row a11y-toggle">
            <span><?= icon('users','icon') ?> Subrayar enlaces</span>
            <input type="checkbox" id="a11y-subrayado">
        </label>
        <label class="a11y-row a11y-toggle">
            <span><?= icon('sliders','icon') ?> Reordenar arrastrando (tarjetas/paneles)</span>
            <input type="checkbox" id="a11y-reordenar">
        </label>
        <button type="button" id="a11y-limpiar" class="btn btn-secondary" style="width:100%;margin-top:6px;">Restablecer todo</button>
    </div>
</div>
<script>
(function () {
    var html = document.documentElement;
    var PASOS = [87.5, 100, 112.5, 125, 137.5];
    function aplicarFuente(pct) { html.style.fontSize = pct + '%'; localStorage.setItem('navissi_fuente', pct); }
    function indiceActual() {
        var actual = parseFloat(localStorage.getItem('navissi_fuente')) || 100;
        var idx = PASOS.indexOf(actual);
        return idx === -1 ? 1 : idx;
    }
    var idx = indiceActual();
    aplicarFuente(PASOS[idx]);
    if (localStorage.getItem('navissi_contraste') === '1') { html.classList.add('alto-contraste'); document.getElementById('a11y-contraste').checked = true; }
    if (localStorage.getItem('navissi_movimiento') === '1') { html.classList.add('reducir-movimiento'); document.getElementById('a11y-movimiento').checked = true; }
    if (localStorage.getItem('navissi_subrayado') === '1') { html.classList.add('subrayar-enlaces'); document.getElementById('a11y-subrayado').checked = true; }

    document.getElementById('a11y-font-menos').addEventListener('click', function () { idx = Math.max(0, idx - 1); aplicarFuente(PASOS[idx]); });
    document.getElementById('a11y-font-mas').addEventListener('click', function () { idx = Math.min(PASOS.length - 1, idx + 1); aplicarFuente(PASOS[idx]); });
    document.getElementById('a11y-font-reset').addEventListener('click', function () { idx = 1; aplicarFuente(PASOS[idx]); });

    document.getElementById('a11y-contraste').addEventListener('change', function (e) {
        html.classList.toggle('alto-contraste', e.target.checked);
        localStorage.setItem('navissi_contraste', e.target.checked ? '1' : '0');
    });
    document.getElementById('a11y-movimiento').addEventListener('change', function (e) {
        html.classList.toggle('reducir-movimiento', e.target.checked);
        localStorage.setItem('navissi_movimiento', e.target.checked ? '1' : '0');
    });
    document.getElementById('a11y-subrayado').addEventListener('change', function (e) {
        html.classList.toggle('subrayar-enlaces', e.target.checked);
        localStorage.setItem('navissi_subrayado', e.target.checked ? '1' : '0');
    });
    document.getElementById('a11y-limpiar').addEventListener('click', function () {
        ['navissi_fuente', 'navissi_contraste', 'navissi_movimiento', 'navissi_subrayado'].forEach(function (k) { localStorage.removeItem(k); });
        html.style.fontSize = ''; html.classList.remove('alto-contraste', 'reducir-movimiento', 'subrayar-enlaces');
        document.getElementById('a11y-contraste').checked = false;
        document.getElementById('a11y-movimiento').checked = false;
        document.getElementById('a11y-subrayado').checked = false;
        idx = 1;
    });

    var lanzador = document.getElementById('a11y-launcher');
    var panel = document.getElementById('a11y-panel');
    lanzador.addEventListener('click', function () {
        var abierto = panel.hidden;
        panel.hidden = !abierto;
        lanzador.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    document.getElementById('a11y-close').addEventListener('click', function () { panel.hidden = true; lanzador.setAttribute('aria-expanded', 'false'); });
    document.addEventListener('click', function (e) {
        if (!panel.hidden && !panel.contains(e.target) && !lanzador.contains(e.target)) { panel.hidden = true; lanzador.setAttribute('aria-expanded', 'false'); }
    });
})();
</script>

<button id="ia-chat-launcher" title="Asistente NAVISSI" onclick="document.getElementById('ia-chat-panel').classList.toggle('open')"><?= icon('chat', 'icon icon-lg') ?></button>
<div id="ia-chat-panel">
    <div class="ia-chat-head">
        <div><strong>Asistente NAVISSI</strong><br><span class="small">Pregúntame sobre cualquier módulo</span></div>
        <button class="ia-chat-close" onclick="document.getElementById('ia-chat-panel').classList.remove('open')"><?= icon('x') ?></button>
    </div>
    <div class="ia-chat-body" id="ia-chat-body">
        <div class="ia-msg bot">Hola, soy el asistente de NAVISSI. Puedo ayudarte con tickets, inventario, RRHH, sedes... ¿en qué te ayudo?</div>
    </div>
    <form class="ia-chat-foot" id="ia-chat-form">
        <input type="text" id="ia-chat-input" placeholder="Escribe tu pregunta..." autocomplete="off">
        <button type="submit"><?= icon('send') ?></button>
    </form>
</div>
<script>
(function () {
    const form = document.getElementById('ia-chat-form');
    const input = document.getElementById('ia-chat-input');
    const body = document.getElementById('ia-chat-body');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const texto = input.value.trim();
        if (!texto) return;
        const userMsg = document.createElement('div');
        userMsg.className = 'ia-msg user';
        userMsg.textContent = texto;
        body.appendChild(userMsg);
        input.value = '';
        body.scrollTop = body.scrollHeight;

        const loading = document.createElement('div');
        loading.className = 'ia-msg bot';
        loading.textContent = 'Pensando...';
        body.appendChild(loading);
        body.scrollTop = body.scrollHeight;

        fetch('<?= $prefix ?>api_ia_chat.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ pregunta: texto, modulo: document.title })
        }).then(r => r.json()).then(data => {
            loading.textContent = data.respuesta || data.error || 'Sin respuesta.';
        }).catch(() => { loading.textContent = 'Error de conexión.'; });
    });
})();
</script>
<script src="<?= $prefix ?>assets/app.js?v=<?= @filemtime(__DIR__ . '/../assets/app.js') ?: time() ?>" defer></script>
</body>
</html><?php
}
