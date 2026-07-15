<?php
/**
 * NAVISSI INVENTARIO - Configuración y conexión.
 * Base de datos: SQLite en el directorio privado fuera del sitio web (sin
 * servidor MySQL que instalar - funciona local con solo tener PHP con
 * pdo_sqlite habilitado, que viene activo por defecto en casi cualquier PHP).
 */

error_reporting(E_ALL);
ini_set('display_errors', getenv('NAVISSI_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('America/Bogota');

define('BASE_DIR', __DIR__);

/**
 * Datos sensibles fuera del document root. La variable permite usar un volumen
 * dedicado en Docker; en la instalación local queda junto al proyecto.
 */
function navissi_private_dir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;
    $env = trim((string) getenv('NAVISSI_PRIVATE_DIR'));
    $dir = $env !== '' ? rtrim($env, '\\/') : dirname(BASE_DIR) . DIRECTORY_SEPARATOR . 'NAVISSI-INVENTARIO-private';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear el directorio privado de NAVISSI.');
    }
    @chmod($dir, 0700);
    return $dir;
}

function private_path(string $name): string {
    $name = ltrim(str_replace(['..', '\\'], ['', '/'], $name), '/');
    return navissi_private_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function tickets_adjuntos_dir(): string {
    $destino = private_path('uploads/tickets');
    if (!is_dir($destino) && !mkdir($destino, 0700, true) && !is_dir($destino)) throw new RuntimeException('No se pudo crear el directorio privado de adjuntos.');
    @chmod($destino, 0700);
    $origen = BASE_DIR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tickets_adjuntos';
    if (is_dir($origen)) {
        foreach (glob($origen . DIRECTORY_SEPARATOR . '*') ?: [] as $archivo) {
            if (!is_file($archivo)) continue;
            $final = $destino . DIRECTORY_SEPARATOR . basename($archivo);
            if (!file_exists($final) && (!@rename($archivo, $final))) {
                if (@copy($archivo, $final) && filesize($archivo) === filesize($final)) @unlink($archivo);
            }
            if (file_exists($final)) @chmod($final, 0600);
        }
        @rmdir($origen);
    }
    return $destino;
}

function migrar_archivo_privado(string $name, ?string $destinoPrivado = null): string {
    $destino = private_path($destinoPrivado ?? $name);
    $origen = BASE_DIR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $name;
    $parent = dirname($destino);
    if (!is_dir($parent)) @mkdir($parent, 0700, true);
    if (!file_exists($destino) && file_exists($origen)) {
        if (!@rename($origen, $destino)) {
            if (!@copy($origen, $destino) || filesize($origen) !== filesize($destino)) {
                throw new RuntimeException("No se pudo proteger el archivo {$name}.");
            }
            @unlink($origen);
        }
    }
    if (file_exists($destino)) @chmod($destino, 0600);
    return $destino;
}

if (getenv('NAVISSI_SKIP_FILE_MIGRATION') !== '1') {
    foreach (['navissi.sqlite', 'ia_config.json', 'ms365_config.json', 'smtp_config.json', 'siesa_integracion.json',
              'whatsapp_config.json', 'importador_config.json', 'smtp_correo.log', 'n8n_err.log', 'n8n_out.log',
              'ultima_sincronizacion_correo.txt'] as $archivoPrivado) {
        migrar_archivo_privado($archivoPrivado);
    }
    foreach (['INVENTARIO_INTEGRAL_IPS.xlsx', '~$INVENTARIO_INTEGRAL_IPS.xlsx',
              'Licencias Office 365 Activas.csv', 'Licencias Office 365 Asignadas.csv'] as $archivoLegadoIps) {
        migrar_archivo_privado($archivoLegadoIps, 'legacy-ips/' . $archivoLegadoIps);
    }
    foreach (glob(BASE_DIR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'navissi.sqlite.bak-*') ?: [] as $respaldoLegado) {
        migrar_archivo_privado(basename($respaldoLegado), 'backups/' . basename($respaldoLegado));
    }
}

define('DB_PATH', private_path('navissi.sqlite'));
define('MS365_CONFIG_PATH', private_path('ms365_config.json'));
define('WHATSAPP_CONFIG_PATH', private_path('whatsapp_config.json'));
define('MASTER_DIR', 'C:\\Users\\SISTEMAS\\OneDrive - GRUPO 10Z SAS\\TI 2026\\MASTER');
define('DATOS_PDV_PATH', 'C:\\Users\\SISTEMAS\\OneDrive - GRUPO 10Z SAS\\Escritorio\\PC ANTERIOS DE SISTEMAS\\DATOS PDV.xlsx');
define('CONTACTOS_TIENDAS_PATH', 'C:\\Users\\SISTEMAS\\Downloads\\ACTUALIZAR DATOS CONTACTOS DE TIENDAS NAVISSI 2026.xlsx');

// SUPER_ADMIN: ve y administra todo, sin excepción, ignora cualquier alcance de área.
// ADMIN, DIRECTOR, GERENCIA, CEO, COORDINADOR, ANALISTA: si tienen "area_responsable"
// asignada (ver Usuarios y roles), solo ven/gestionan datos de esa área. Sin área
// asignada, ven todo dentro de lo que su rol permite (comportamiento "abierto" para
// no romper cuentas ya creadas).
define('ROLES_DISPONIBLES', ['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO', 'COORDINADOR', 'ANALISTA', 'TI', 'RRHH', 'EMPLEADO']);

function respaldo_pre_fase0(): void {
    if (!file_exists(DB_PATH)) return;
    $dir = private_path('backups');
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $destino = $dir . DIRECTORY_SEPARATOR . 'pre-fase0-navissi.sqlite';
    if (!file_exists($destino) && !copy(DB_PATH, $destino)) {
        throw new RuntimeException('No se pudo crear el respaldo previo a Fase 0.');
    }
    if (file_exists($destino)) @chmod($destino, 0600);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $isNew = !file_exists(DB_PATH);
        if (!$isNew) respaldo_pre_fase0();
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        if ($isNew) {
            crear_esquema($pdo);
            migrar_esquema($pdo);
            migrar_esquema($pdo);
        } else {
            migrar_esquema($pdo);
        }
        migrar_fases_operativas($pdo);
        aplicar_migraciones_seguridad($pdo);
    }
    return $pdo;
}

function migrar_fases_operativas(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS salud_tiendas_registros (
        id INTEGER PRIMARY KEY AUTOINCREMENT, sede_id INTEGER NOT NULL REFERENCES sedes(id) ON DELETE CASCADE,
        momento TEXT NOT NULL DEFAULT 'VALIDACION', estado TEXT NOT NULL DEFAULT 'OK', internet_ok INTEGER DEFAULT 0,
        pos_ok INTEGER DEFAULT 0, impresora_ok INTEGER DEFAULT 0, datafono_ok INTEGER DEFAULT 0, observaciones TEXT,
        responsable TEXT, ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL, creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS config_general (clave TEXT PRIMARY KEY, valor TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS automatizacion_ejecuciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT, correlacion TEXT NOT NULL UNIQUE, disparador TEXT NOT NULL DEFAULT 'MANUAL',
        estado TEXT NOT NULL DEFAULT 'EJECUTANDO', resumen TEXT, detalles_json TEXT, iniciado_en TEXT DEFAULT CURRENT_TIMESTAMP, finalizado_en TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets_escalamientos (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
        nivel INTEGER NOT NULL DEFAULT 1, motivo TEXT NOT NULL, destinatario TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(ticket_id,nivel)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS notificaciones_cola (
        id INTEGER PRIMARY KEY AUTOINCREMENT, clave_unica TEXT NOT NULL UNIQUE, canal TEXT NOT NULL, destinatario TEXT NOT NULL,
        asunto TEXT, contenido TEXT NOT NULL, metadatos_json TEXT, estado TEXT NOT NULL DEFAULT 'PENDIENTE', intentos INTEGER NOT NULL DEFAULT 0,
        proximo_intento_en TEXT, ultimo_error TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP, enviado_en TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS agentes_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE, token_prefijo TEXT NOT NULL,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL, serial_vinculado TEXT, activo INTEGER DEFAULT 1,
        ultimo_uso_en TEXT, ultima_ip TEXT, expira_en TEXT, creado_por TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS catalogo_servicios (
        id INTEGER PRIMARY KEY AUTOINCREMENT, codigo TEXT NOT NULL UNIQUE, nombre TEXT NOT NULL, descripcion TEXT,
        categoria TEXT NOT NULL DEFAULT 'OPERACION', area_responsable TEXT NOT NULL, nivel_aprobacion TEXT NOT NULL DEFAULT 'DIRECTOR',
        requiere_monto INTEGER NOT NULL DEFAULT 0, monto_escalamiento REAL, sla_horas INTEGER NOT NULL DEFAULT 24,
        crea_ticket INTEGER NOT NULL DEFAULT 0, categoria_ticket TEXT, prioridad_ticket TEXT DEFAULT 'MEDIA',
        activo INTEGER NOT NULL DEFAULT 1, orden INTEGER NOT NULL DEFAULT 0, creado_en TEXT DEFAULT CURRENT_TIMESTAMP, actualizado_en TEXT,
        area_tramite TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS solicitudes_aprobacion_eventos (
        id INTEGER PRIMARY KEY AUTOINCREMENT, solicitud_id INTEGER NOT NULL REFERENCES solicitudes_aprobacion(id) ON DELETE CASCADE,
        accion TEXT NOT NULL, estado_anterior TEXT, estado_nuevo TEXT, nivel TEXT, actor TEXT, comentario TEXT,
        metadatos_json TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ciclos_identidad (
        id INTEGER PRIMARY KEY AUTOINCREMENT, codigo TEXT UNIQUE, tipo TEXT NOT NULL, empleado_documento TEXT,
        empleado_nombre TEXT NOT NULL, correo_corporativo TEXT, area_origen TEXT, area_destino TEXT, cargo TEXT,
        fecha_efectiva TEXT, solicitud_id INTEGER UNIQUE REFERENCES solicitudes_aprobacion(id) ON DELETE SET NULL,
        estado TEXT NOT NULL DEFAULT 'PENDIENTE', progreso INTEGER NOT NULL DEFAULT 0, creado_por TEXT,
        aprobado_por TEXT, aprobado_en TEXT, completado_en TEXT, observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP, actualizado_en TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ciclos_identidad_tareas (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ciclo_id INTEGER NOT NULL REFERENCES ciclos_identidad(id) ON DELETE CASCADE,
        codigo TEXT NOT NULL, titulo TEXT NOT NULL, sistema TEXT, area_responsable TEXT, modo TEXT NOT NULL DEFAULT 'MANUAL',
        orden INTEGER NOT NULL DEFAULT 0, obligatoria INTEGER NOT NULL DEFAULT 1, estado TEXT NOT NULL DEFAULT 'PENDIENTE',
        evidencia TEXT, ultimo_error TEXT, ejecutado_por TEXT, ejecutado_en TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(ciclo_id,codigo)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inteligencia_ejecuciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT, correlacion TEXT NOT NULL UNIQUE, disparador TEXT NOT NULL DEFAULT 'MANUAL',
        estado TEXT NOT NULL DEFAULT 'EJECUTANDO', agentes_json TEXT, hallazgos_generados INTEGER NOT NULL DEFAULT 0,
        resumen TEXT, iniciado_en TEXT DEFAULT CURRENT_TIMESTAMP, finalizado_en TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inteligencia_hallazgos (
        id INTEGER PRIMARY KEY AUTOINCREMENT, clave_unica TEXT NOT NULL UNIQUE, dominio TEXT NOT NULL, agente TEXT NOT NULL,
        severidad TEXT NOT NULL DEFAULT 'MEDIA', titulo TEXT NOT NULL, resumen TEXT NOT NULL, evidencia_json TEXT,
        accion_url TEXT, estado TEXT NOT NULL DEFAULT 'ACTIVO', ultima_ejecucion_id INTEGER REFERENCES inteligencia_ejecuciones(id) ON DELETE SET NULL,
        ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL, gestionado_por TEXT, gestionado_en TEXT,
        detectado_en TEXT DEFAULT CURRENT_TIMESTAMP, actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP, resuelto_en TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS retail_importaciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT, checksum TEXT NOT NULL UNIQUE, archivo TEXT NOT NULL, tipo TEXT NOT NULL,
        estado TEXT NOT NULL DEFAULT 'PROCESANDO', filas_leidas INTEGER NOT NULL DEFAULT 0, filas_importadas INTEGER NOT NULL DEFAULT 0,
        filas_error INTEGER NOT NULL DEFAULT 0, detalle TEXT, importado_por TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP, finalizado_en TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS retail_importacion_errores (
        id INTEGER PRIMARY KEY AUTOINCREMENT, importacion_id INTEGER NOT NULL REFERENCES retail_importaciones(id) ON DELETE CASCADE,
        dataset TEXT, hoja TEXT, fila INTEGER, motivo TEXT NOT NULL, datos_json TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS retail_productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT, sku TEXT NOT NULL UNIQUE, referencia TEXT, descripcion TEXT, categoria TEXT,
        color TEXT, talla TEXT, costo REAL, precio REAL, activo INTEGER NOT NULL DEFAULT 1,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP, actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS retail_existencias (
        id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT NOT NULL, sku TEXT NOT NULL REFERENCES retail_productos(sku) ON UPDATE CASCADE,
        sede_codigo TEXT NOT NULL, sede_nombre TEXT, unidades REAL NOT NULL DEFAULT 0, costo_unitario REAL,
        importacion_id INTEGER REFERENCES retail_importaciones(id) ON DELETE SET NULL, fuente TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(fecha,sku,sede_codigo)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS retail_ventas (
        id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT NOT NULL, documento TEXT NOT NULL, linea TEXT NOT NULL DEFAULT '1',
        sku TEXT NOT NULL REFERENCES retail_productos(sku) ON UPDATE CASCADE, sede_codigo TEXT NOT NULL, sede_nombre TEXT,
        unidades REAL NOT NULL DEFAULT 0, valor_neto REAL NOT NULL DEFAULT 0, costo REAL, canal TEXT,
        importacion_id INTEGER REFERENCES retail_importaciones(id) ON DELETE SET NULL, fuente TEXT, creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(documento,linea,sku,sede_codigo)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS siesa_integracion_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, correlacion TEXT UNIQUE, accion TEXT NOT NULL, modo TEXT NOT NULL,
        estado TEXT NOT NULL DEFAULT 'EJECUTANDO', codigo_http INTEGER, resumen TEXT, detalle_json TEXT,
        iniciado_por TEXT, iniciado_en TEXT DEFAULT CURRENT_TIMESTAMP, finalizado_en TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS secretos_escaneos (
        id INTEGER PRIMARY KEY AUTOINCREMENT, correlacion TEXT NOT NULL UNIQUE, origen TEXT NOT NULL DEFAULT 'MANUAL',
        estado TEXT NOT NULL DEFAULT 'EJECUTANDO', raices_revisadas INTEGER NOT NULL DEFAULT 0,
        archivos_revisados INTEGER NOT NULL DEFAULT 0, archivos_omitidos INTEGER NOT NULL DEFAULT 0,
        hallazgos INTEGER NOT NULL DEFAULT 0, resumen TEXT, iniciado_por TEXT,
        iniciado_en TEXT DEFAULT CURRENT_TIMESTAMP, finalizado_en TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS secretos_hallazgos (
        id INTEGER PRIMARY KEY AUTOINCREMENT, clave_unica TEXT NOT NULL UNIQUE,
        archivo_hash TEXT NOT NULL, archivo_nombre TEXT NOT NULL, fuente TEXT NOT NULL,
        archivo_modificado_en TEXT, hoja TEXT NOT NULL, tipo TEXT NOT NULL DEFAULT 'CREDENCIAL',
        severidad TEXT NOT NULL DEFAULT 'ALTA', columnas_json TEXT NOT NULL DEFAULT '[]',
        registros_estimados INTEGER NOT NULL DEFAULT 0, estado TEXT NOT NULL DEFAULT 'ACTIVO',
        responsable TEXT, fecha_objetivo TEXT, evidencia TEXT, gestionado_por TEXT, gestionado_en TEXT,
        ultimo_escaneo_id INTEGER REFERENCES secretos_escaneos(id) ON DELETE SET NULL,
        detectado_en TEXT DEFAULT CURRENT_TIMESTAMP, actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP, resuelto_en TEXT
    )");
    $columnasSolicitud = array_column($pdo->query("PRAGMA table_info(solicitudes_aprobacion)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach ([
        'codigo' => 'TEXT', 'catalogo_id' => 'INTEGER', 'sede_id' => 'INTEGER', 'fecha_limite' => 'TEXT',
        'ticket_id' => 'INTEGER', 'datos_json' => 'TEXT', 'actualizado_en' => 'TEXT'
    ] as $columna => $tipo) {
        if (!in_array($columna, $columnasSolicitud, true)) $pdo->exec("ALTER TABLE solicitudes_aprobacion ADD COLUMN {$columna} {$tipo}");
    }
    $serviciosIniciales = [
        ['ACC-SIS','Acceso o licencia de sistema','Altas de acceso, permisos, licencias y cambios de perfil.','IDENTIDADES','Direccion de Tecnologia','DIRECTOR',0,null,24,1,'ACCESOS','ALTA',10],
        ['USR-ALTA-BAJA','Alta, traslado o retiro de usuario','Creación, traslado o baja coordinada de cuentas y accesos.','IDENTIDADES','Direccion de Tecnologia','DIRECTOR',0,null,16,1,'ACCESOS','ALTA',20],
        ['EQ-CAMBIO','Cambio o asignación de equipo','Compra, reposición, traslado o asignación de un activo tecnológico.','ACTIVOS','Direccion de Tecnologia','DIRECTOR',1,3000000,48,1,'EQUIPOS','ALTA',30],
        ['TIENDA-MTO','Mantenimiento de tienda','Intervención de infraestructura, mobiliario o servicios de una tienda.','TIENDAS','Direccion Infraestructura','DIRECTOR',1,2000000,48,1,'INFRAESTRUCTURA','ALTA',40],
        ['COMPRA-OP','Compra operativa','Solicitud de compra para operación, logística o abastecimiento.','COMPRAS','Direccion de Logistica','DIRECTOR',1,1000000,48,0,null,'MEDIA',50],
        ['CAMPANA','Campaña comercial o colección','Aprobación de campaña, activación, colección o iniciativa comercial.','COMERCIAL','Direccion Marketing','GERENCIA',1,null,72,0,null,'MEDIA',60],
        ['CONTRATO','Contrato o proveedor','Alta, renovación o cambio contractual con un proveedor.','LEGAL','Gerencia','GERENCIA',1,null,72,0,null,'MEDIA',70],
        ['RRHH-SOL','Solicitud interáreas de Talento Humano','Trámite que requiere coordinación o aprobación de Recursos Humanos.','PERSONAS','Direccion Recursos Humanos','DIRECTOR',0,null,48,0,null,'MEDIA',80],
        ['OTRO','Otra solicitud interáreas','Solicitud operativa que no corresponde a los servicios anteriores.','OPERACION','Direccion de Operaciones','DIRECTOR',0,null,48,0,null,'MEDIA',99]
    ];
    $insertarServicio = $pdo->prepare("INSERT OR IGNORE INTO catalogo_servicios(codigo,nombre,descripcion,categoria,area_responsable,nivel_aprobacion,requiere_monto,monto_escalamiento,sla_horas,crea_ticket,categoria_ticket,prioridad_ticket,orden) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($serviciosIniciales as $servicio) $insertarServicio->execute($servicio);
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_salud_tienda_sede_fecha ON salud_tiendas_registros(sede_id,creado_en DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_automatizacion_fecha ON automatizacion_ejecuciones(iniciado_en DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notificaciones_estado ON notificaciones_cola(estado,proximo_intento_en,creado_en)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_agentes_token_activo ON agentes_tokens(token_hash,activo)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_solicitud_codigo ON solicitudes_aprobacion(codigo) WHERE codigo IS NOT NULL");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_solicitud_area_estado ON solicitudes_aprobacion(area_responsable,estado,fecha_limite)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_solicitud_eventos_fecha ON solicitudes_aprobacion_eventos(solicitud_id,creado_en)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ciclos_identidad_estado ON ciclos_identidad(estado,fecha_efectiva)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ciclos_identidad_documento ON ciclos_identidad(empleado_documento,creado_en)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ciclo_tareas_estado ON ciclos_identidad_tareas(ciclo_id,estado,orden)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inteligencia_estado ON inteligencia_hallazgos(estado,severidad,actualizado_en DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inteligencia_dominio ON inteligencia_hallazgos(dominio,estado)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inteligencia_ejecucion ON inteligencia_hallazgos(ultima_ejecucion_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_retail_existencias_fecha ON retail_existencias(fecha DESC,sku,sede_codigo)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_retail_ventas_fecha ON retail_ventas(fecha DESC,sku,sede_codigo)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_retail_productos_ref ON retail_productos(referencia,categoria,talla)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_retail_errores_importacion ON retail_importacion_errores(importacion_id,fila)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_siesa_integracion_fecha ON siesa_integracion_log(iniciado_en DESC,estado)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_secretos_estado ON secretos_hallazgos(estado,severidad,fecha_objetivo)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_secretos_archivo ON secretos_hallazgos(archivo_hash,hoja)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_secretos_escaneo_fecha ON secretos_escaneos(iniciado_en DESC,estado)");
}

function migrar_esquema(PDO $pdo) {
    $columnasSedes = array_column($pdo->query("PRAGMA table_info(sedes)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevas = [
        'zona' => 'TEXT', 'coordinadora' => 'TEXT', 'coordinadora_celular' => 'TEXT',
        'administradora' => 'TEXT', 'administradora_celular' => 'TEXT',
        'segunda_encargada' => 'TEXT', 'segunda_encargada_celular' => 'TEXT',
        'correo_corporativo' => 'TEXT', 'ip_asignada' => 'TEXT',
        'actualizado_en' => 'TEXT',
    ];
    foreach ($nuevas as $col => $tipo) {
        if (!in_array($col, $columnasSedes, true)) {
            $pdo->exec("ALTER TABLE sedes ADD COLUMN {$col} {$tipo}");
        }
    }

    $columnasEmpleados = array_column($pdo->query("PRAGMA table_info(empleados)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasEmp = ['fecha_ingreso' => 'TEXT', 'tipo_contrato' => 'TEXT', 'salario' => 'REAL'];
    foreach ($nuevasEmp as $col => $tipo) {
        if (!in_array($col, $columnasEmpleados, true)) {
            $pdo->exec("ALTER TABLE empleados ADD COLUMN {$col} {$tipo}");
        }
    }

    // ---- Catálogo de servicios: el ticket resultante puede ir a un área distinta
    // de la que aprueba (ej. Contabilidad solicita, Director de Contabilidad aprueba,
    // pero el trámite final lo hace RRHH). Si queda vacío, se comporta como antes
    // (el ticket va al área que aprobó). ----
    $columnasCatalogo = array_column($pdo->query("PRAGMA table_info(catalogo_servicios)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('area_tramite', $columnasCatalogo, true)) {
        $pdo->exec("ALTER TABLE catalogo_servicios ADD COLUMN area_tramite TEXT");
    }

    // ---- Comercial: clientes comerciales + cotizaciones con items, relacionadas a
    // sede y vendedor (usuarios_sistema). ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes_comerciales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        nit TEXT,
        contacto TEXT,
        telefono TEXT,
        email TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        vendedor_usuario_id INTEGER REFERENCES usuarios_sistema(id) ON DELETE SET NULL,
        estado TEXT DEFAULT 'ACTIVO',
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotizaciones_comerciales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo TEXT UNIQUE,
        cliente_id INTEGER NOT NULL REFERENCES clientes_comerciales(id) ON DELETE CASCADE,
        vendedor_usuario_id INTEGER REFERENCES usuarios_sistema(id) ON DELETE SET NULL,
        valor_total REAL DEFAULT 0,
        estado TEXT DEFAULT 'BORRADOR',
        valido_hasta TEXT,
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotizaciones_comerciales_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cotizacion_id INTEGER NOT NULL REFERENCES cotizaciones_comerciales(id) ON DELETE CASCADE,
        descripcion TEXT NOT NULL,
        cantidad REAL DEFAULT 1,
        valor_unitario REAL DEFAULT 0
    )");

    // ---- Ecommerce: pedidos online con items, relacionados a sede de despacho. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS pedidos_ecommerce (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo TEXT UNIQUE,
        cliente_nombre TEXT NOT NULL,
        cliente_email TEXT,
        cliente_telefono TEXT,
        canal TEXT DEFAULT 'WEB',
        sede_despacho_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        valor_total REAL DEFAULT 0,
        estado TEXT DEFAULT 'PENDIENTE',
        guia_envio TEXT,
        transportadora TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pedidos_ecommerce_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pedido_id INTEGER NOT NULL REFERENCES pedidos_ecommerce(id) ON DELETE CASCADE,
        producto TEXT NOT NULL,
        cantidad INTEGER DEFAULT 1,
        valor_unitario REAL DEFAULT 0
    )");

    // ---- Marketing: campañas de marketing (distinto de campanas.php, que es
    // calendario de colecciones de producto) con presupuesto y resultados reales. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS campanas_marketing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        canal TEXT,
        objetivo TEXT,
        presupuesto REAL,
        fecha_inicio TEXT,
        fecha_fin TEXT,
        estado TEXT DEFAULT 'PLANEADA',
        alcance INTEGER,
        conversiones INTEGER,
        inversion_real REAL,
        responsable_usuario_id INTEGER REFERENCES usuarios_sistema(id) ON DELETE SET NULL,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Producción: órdenes de producción, relacionadas a sede/planta y responsable. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS ordenes_produccion (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo TEXT UNIQUE,
        producto TEXT NOT NULL,
        cantidad REAL NOT NULL,
        unidad TEXT DEFAULT 'UND',
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        responsable_usuario_id INTEGER REFERENCES usuarios_sistema(id) ON DELETE SET NULL,
        estado TEXT DEFAULT 'PENDIENTE',
        fecha_programada TEXT,
        fecha_inicio_real TEXT,
        fecha_fin_real TEXT,
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Operación: checklist operativo diario por sede (apertura, cierre, arqueo,
    // limpieza, etc.), relacionado a sede y responsable. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS checklist_operativo (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        frecuencia TEXT DEFAULT 'DIARIA',
        activo INTEGER DEFAULT 1,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS checklist_operativo_registros (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        checklist_id INTEGER NOT NULL REFERENCES checklist_operativo(id) ON DELETE CASCADE,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        completado_por TEXT,
        completado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        observaciones TEXT
    )");

    // ---- Aprobador de gastos por proveedor: cuando se identifica un proveedor en un
    // documento/factura, se le puede asignar un responsable de aprobar el gasto (por
    // area). Contabilidad ve si ya fue aprobado antes de pasarlo a pago. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS proveedores_aprobadores (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        proveedor_nombre TEXT NOT NULL,
        proveedor_nit TEXT,
        area TEXT NOT NULL,
        aprobador_usuario_id INTEGER REFERENCES usuarios_sistema(id) ON DELETE SET NULL,
        activo INTEGER DEFAULT 1,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(proveedor_nombre, area)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS gastos_proveedor (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        proveedor_nombre TEXT NOT NULL,
        proveedor_nit TEXT,
        numero_factura TEXT,
        area TEXT,
        valor REAL,
        descripcion TEXT,
        estado TEXT DEFAULT 'PENDIENTE',
        aprobador_usuario_id INTEGER REFERENCES usuarios_sistema(id) ON DELETE SET NULL,
        aprobado_por TEXT,
        aprobado_en TEXT,
        comentario_aprobador TEXT,
        contabilizada INTEGER DEFAULT 0,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_gastos_proveedor_estado ON gastos_proveedor (estado)");

    // ---- Logística: ubicación física dentro de la bodega ----
    $columnasInv = array_column($pdo->query("PRAGMA table_info(inventario)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('ubicacion_bodega', $columnasInv, true)) {
        $pdo->exec("ALTER TABLE inventario ADD COLUMN ubicacion_bodega TEXT");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS movimientos_bodega (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        equipo_serial TEXT NOT NULL,
        ubicacion_anterior TEXT,
        ubicacion_nueva TEXT,
        movido_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Tableros de Proyectos (Kanban) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS tableros (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        area TEXT,
        descripcion TEXT,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tablero_columnas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tablero_id INTEGER NOT NULL REFERENCES tableros(id) ON DELETE CASCADE,
        nombre TEXT NOT NULL,
        orden INTEGER DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tablero_tareas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tablero_id INTEGER NOT NULL REFERENCES tableros(id) ON DELETE CASCADE,
        columna_id INTEGER NOT NULL REFERENCES tablero_columnas(id) ON DELETE CASCADE,
        titulo TEXT NOT NULL,
        descripcion TEXT,
        responsable_documento TEXT,
        responsable_nombre TEXT,
        prioridad TEXT DEFAULT 'NORMAL',
        fecha_vencimiento TEXT,
        orden INTEGER DEFAULT 0,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Control de Asistencia ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS asistencia (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        empleado_documento TEXT NOT NULL,
        empleado_nombre TEXT,
        fecha TEXT NOT NULL,
        hora_entrada TEXT,
        hora_salida TEXT,
        ip_entrada TEXT,
        ip_salida TEXT,
        UNIQUE(empleado_documento, fecha)
    )");

    // ---- Canal de Denuncias ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS denuncias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        categoria TEXT DEFAULT 'OTRO',
        descripcion TEXT NOT NULL,
        anonimo INTEGER DEFAULT 1,
        denunciante_documento TEXT,
        denunciante_nombre TEXT,
        area_involucrada TEXT,
        estado TEXT DEFAULT 'RECIBIDA',
        respuesta TEXT,
        atendido_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        resuelto_en TEXT
    )");

    // ---- Reclutamiento y Selección ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS vacantes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo TEXT NOT NULL,
        area TEXT,
        descripcion TEXT,
        requisitos TEXT,
        estado TEXT DEFAULT 'ABIERTA',
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS candidatos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        vacante_id INTEGER NOT NULL REFERENCES vacantes(id) ON DELETE CASCADE,
        nombre TEXT NOT NULL,
        documento TEXT,
        email TEXT,
        celular TEXT,
        cv_ruta TEXT,
        cv_nombre TEXT,
        estado TEXT DEFAULT 'RECIBIDO',
        notas TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categorias_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL UNIQUE, descripcion TEXT, area_responsable TEXT,
        color TEXT DEFAULT '#e31c6c', activa INTEGER DEFAULT 1, creado_en TEXT DEFAULT CURRENT_TIMESTAMP, tecnico_default TEXT
    )");
    $columnasCategoriasTk = array_column($pdo->query("PRAGMA table_info(categorias_tickets)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['area_responsable'=>'TEXT','color'=>"TEXT DEFAULT '#e31c6c'",'activa'=>'INTEGER DEFAULT 1','creado_en'=>'TEXT','tecnico_default'=>'TEXT'] as $col=>$tipo) {
        if (!in_array($col, $columnasCategoriasTk, true)) $pdo->exec("ALTER TABLE categorias_tickets ADD COLUMN {$col} {$tipo}");
    }

    // ---- Mesa de Ayuda: adjuntos y respuestas al cliente ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets_adjuntos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id INTEGER NOT NULL,
        comentario_id INTEGER,
        nombre_archivo TEXT NOT NULL,
        ruta TEXT NOT NULL,
        tipo_mime TEXT,
        tamano INTEGER,
        subido_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $columnasTicketsCom = array_column($pdo->query("PRAGMA table_info(tickets_comentarios)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasTicketsCom = ['visible_cliente' => 'INTEGER DEFAULT 0', 'enviado_correo' => 'INTEGER DEFAULT 0'];
    foreach ($nuevasTicketsCom as $col => $tipo) {
        if (!in_array($col, $columnasTicketsCom, true)) {
            $pdo->exec("ALTER TABLE tickets_comentarios ADD COLUMN {$col} {$tipo}");
        }
    }

    // ---- Firmas oficiales guardadas (se adjuntan automáticamente en certificados/actas) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS firmas_oficiales (
        area TEXT PRIMARY KEY,
        firma_jpeg_base64 TEXT NOT NULL,
        nombre_firmante TEXT,
        cargo_firmante TEXT,
        actualizado_por TEXT,
        actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Devoluciones y garantías de producto vendido (distinto de actas de equipos TI) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS devoluciones_producto (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
        cliente_nombre TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        producto TEXT NOT NULL,
        referencia TEXT,
        talla TEXT,
        motivo TEXT NOT NULL,
        tipo_solucion TEXT DEFAULT 'CAMBIO',
        valor REAL,
        estado TEXT DEFAULT 'SOLICITADA',
        observaciones TEXT,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        resuelto_en TEXT
    )");

    // ---- Mermas e inventario perdido (justificación de diferencias de stock) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS mermas_inventario (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        producto TEXT NOT NULL,
        referencia TEXT,
        cantidad REAL DEFAULT 1,
        motivo TEXT NOT NULL,
        valor_estimado REAL,
        estado TEXT DEFAULT 'REPORTADA',
        aprobado_por TEXT,
        aprobado_en TEXT,
        observaciones TEXT,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Comisiones de venta por asesor/tienda (calculadas sobre oportunidades GANADA) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS comisiones_venta (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        oportunidad_id INTEGER REFERENCES oportunidades(id) ON DELETE CASCADE,
        vendedor_documento TEXT,
        vendedor_nombre TEXT NOT NULL,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        valor_venta REAL NOT NULL,
        porcentaje REAL DEFAULT 0,
        valor_comision REAL DEFAULT 0,
        periodo TEXT,
        estado TEXT DEFAULT 'PENDIENTE',
        pagado_en TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Calendario de colecciones/campañas (moda, diseño gráfico, comercial) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS campanas_coleccion (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        temporada TEXT,
        area_responsable TEXT,
        fecha_inicio TEXT,
        fecha_lanzamiento TEXT,
        fecha_fin TEXT,
        estado TEXT DEFAULT 'PLANEACION',
        descripcion TEXT,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Portal de proveedores (autogestión vía enlace con token, sin login completo) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS proveedores_portal_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contrato_id INTEGER REFERENCES contratos(id) ON DELETE CASCADE,
        token TEXT UNIQUE NOT NULL,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        expira_en TEXT,
        usado_en TEXT
    )");
    // ---- Monitor de precios: vigila tiendas online (Shopify, Zara, u otras con
    // datos estructurados JSON-LD) y guarda cada escaneo para comparar precio
    // lleno, precio con descuento y % de descuento a través del tiempo. ----
    // ---- SST: perfil sociodemografico por empleado (RRHH / Seguridad y Salud
    // en el Trabajo). Datos sensibles - solo RRHH/SST/ADMIN ven todos, cada
    // empleado solo ve y diligencia el suyo. La edad NUNCA se guarda como
    // numero fijo: se calcula siempre a partir de fecha_nacimiento para que
    // nunca quede desactualizada. archivado_en marca cuando el empleado fue
    // retirado, sin borrar el historico (obligacion legal de SST). ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS sst_perfil_sociodemografico (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        documento TEXT NOT NULL UNIQUE,
        nombre TEXT,
        celular TEXT,
        nacionalidad TEXT,
        fecha_nacimiento TEXT,
        lugar_nacimiento TEXT,
        tipo_sangre TEXT,
        contacto_emergencia TEXT,
        cabeza_hogar TEXT,
        tipo_vinculacion TEXT,
        turno_trabajo TEXT,
        nivel_educacion TEXT,
        sexo TEXT,
        direccion TEXT,
        municipio TEXT,
        sector TEXT,
        pertenece_grupo_vulnerado TEXT,
        tipo_vivienda TEXT,
        estado_civil TEXT,
        composicion_familiar TEXT,
        raza_ayudas TEXT,
        numero_hijos INTEGER,
        edades_hijos TEXT,
        actividad_fisica TEXT,
        sufre_enfermedad TEXT,
        restriccion_medica TEXT,
        uso_tiempo_libre TEXT,
        estrato_socioeconomico INTEGER,
        eps TEXT,
        arl TEXT,
        afp TEXT,
        completado_en TEXT,
        actualizado_por TEXT,
        actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        archivado_en TEXT
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sst_perfil_documento ON sst_perfil_sociodemografico (documento)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS monitor_precios_sitios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        url TEXT NOT NULL,
        tipo TEXT NOT NULL DEFAULT 'jsonld',
        activo INTEGER DEFAULT 1,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    if ((int) $pdo->query("SELECT COUNT(*) FROM monitor_precios_sitios")->fetchColumn() === 0) {
        // Tienda oficial propia precargada - el resto de sitios (cualquier
        // Shopify, Zara, o sitio con datos estructurados) se agregan desde la
        // interfaz, se detectan solos.
        $pdo->prepare("INSERT INTO monitor_precios_sitios (nombre, url, tipo, creado_por) VALUES (?,?,?,?)")
            ->execute(['NAVISSI (tienda oficial)', 'https://navissi.com', 'shopify', 'Sistema']);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS monitor_precios_escaneos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sitio_id INTEGER NOT NULL REFERENCES monitor_precios_sitios(id) ON DELETE CASCADE,
        productos_encontrados INTEGER DEFAULT 0,
        error TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS monitor_precios_productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        escaneo_id INTEGER NOT NULL REFERENCES monitor_precios_escaneos(id) ON DELETE CASCADE,
        clave TEXT NOT NULL,
        producto TEXT,
        variante TEXT,
        precio REAL,
        precio_antes REAL,
        descuento_pct REAL,
        disponible INTEGER DEFAULT 1,
        url TEXT
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_monitor_precios_escaneos_sitio ON monitor_precios_escaneos (sitio_id, creado_en DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_monitor_precios_productos_escaneo ON monitor_precios_productos (escaneo_id, clave)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS proveedores_actualizaciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contrato_id INTEGER REFERENCES contratos(id) ON DELETE CASCADE,
        tipo TEXT DEFAULT 'FACTURA',
        descripcion TEXT,
        archivo_ruta TEXT,
        archivo_nombre TEXT,
        estado TEXT DEFAULT 'PENDIENTE_REVISION',
        revisado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Mapa de tiendas: coordenadas para la vista geográfica ----
    $columnasSedesMapa = array_column($pdo->query("PRAGMA table_info(sedes)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['latitud' => 'REAL', 'longitud' => 'REAL'] as $col => $tipo) {
        if (!in_array($col, $columnasSedesMapa, true)) {
            $pdo->exec("ALTER TABLE sedes ADD COLUMN {$col} {$tipo}");
        }
    }

    // ---- Tipos de documento de RRHH configurables (sin tocar codigo): Admin/RRHH
    // pueden agregar nuevos formatos (ej. "Cotizacion de proveedor de dotacion",
    // "Poliza de seguro") en vez de quedar atados a una lista fija. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS tipos_documento_rrhh (
        nombre TEXT PRIMARY KEY,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    if ((int) $pdo->query("SELECT COUNT(*) FROM tipos_documento_rrhh")->fetchColumn() === 0) {
        $stmtTipo = $pdo->prepare("INSERT OR IGNORE INTO tipos_documento_rrhh (nombre) VALUES (?)");
        foreach (['CONTRATO', 'OTROSI', 'HOJA DE VIDA', 'CERTIFICADO', 'INCAPACIDAD', 'OTRO'] as $t) {
            $stmtTipo->execute([$t]);
        }
    }

    // ---- Dispositivos de confianza: permite saltar el codigo 2FA en un equipo
    // ya verificado antes (30 dias), sin guardar la clave ni la sesion en si -
    // solo un token propio de "este navegador ya paso 2FA una vez". ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS dispositivos_confianza (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL REFERENCES usuarios_sistema(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL UNIQUE,
        nombre_dispositivo TEXT,
        ip_registro TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        expira_en TEXT NOT NULL,
        ultima_vez TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_dispositivos_usuario ON dispositivos_confianza (usuario_id)");

    // ---- Constructor de formularios sin codigo: Admin/Director/RRHH arman un
    // formulario (titulo + campos dinamicos), se comparte un link publico, y las
    // respuestas quedan guardadas para revisar/exportar. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS formularios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo TEXT NOT NULL,
        descripcion TEXT,
        area TEXT,
        token_publico TEXT UNIQUE,
        activo INTEGER DEFAULT 1,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formularios_campos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        formulario_id INTEGER NOT NULL REFERENCES formularios(id) ON DELETE CASCADE,
        etiqueta TEXT NOT NULL,
        tipo TEXT NOT NULL DEFAULT 'texto',
        opciones TEXT,
        requerido INTEGER DEFAULT 0,
        orden INTEGER DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formularios_respuestas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        formulario_id INTEGER NOT NULL REFERENCES formularios(id) ON DELETE CASCADE,
        respuestas_json TEXT NOT NULL,
        enviado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_formularios_campos_form ON formularios_campos (formulario_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_formularios_respuestas_form ON formularios_respuestas (formulario_id)");

    // ---- Baja formal de equipos: registro auditable (motivo, aprobacion) en vez de
    // solo cambiar el campo estado a mano, sin dejar rastro de por que ni quien aprobo. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventario_bajas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        equipo_id INTEGER REFERENCES inventario(id) ON DELETE SET NULL,
        equipo_serial TEXT,
        tipo_baja TEXT DEFAULT 'OBSOLETO',
        motivo TEXT NOT NULL,
        valor_libros REAL,
        estado TEXT DEFAULT 'SOLICITADA',
        solicitado_por TEXT,
        aprobado_por TEXT,
        aprobado_en TEXT,
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Historial de compras por equipo: de donde vino cada equipo (proveedor,
    // factura, fecha, valor), enlazado al inventario. ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS compras_equipo (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        equipo_id INTEGER REFERENCES inventario(id) ON DELETE SET NULL,
        equipo_serial TEXT,
        proveedor TEXT NOT NULL,
        numero_factura TEXT,
        fecha_compra TEXT,
        valor REAL,
        articulo TEXT,
        observaciones TEXT,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Etiquetas de menú personalizadas (edición de textos sin tocar código) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS etiquetas_menu (
        href TEXT PRIMARY KEY,
        etiqueta TEXT NOT NULL
    )");

    // ---- Impresoras ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS impresoras (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        ubicacion TEXT,
        area TEXT,
        marca TEXT,
        modelo TEXT,
        tipo TEXT,
        ip_red TEXT,
        estado TEXT DEFAULT 'ACTIVA',
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- VPN ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS vpn_conexiones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        tipo TEXT,
        servidor TEXT,
        usuario TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        estado TEXT DEFAULT 'ACTIVA',
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Mikrotik / Routers ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS routers_red (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        marca TEXT DEFAULT 'MIKROTIK',
        modelo TEXT,
        ip_gestion TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        usuario_admin TEXT,
        estado TEXT DEFAULT 'ACTIVO',
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Huelleros / Biométricos ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS biometricos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        marca TEXT,
        modelo TEXT,
        ip_red TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        capacidad_huellas INTEGER,
        estado TEXT DEFAULT 'ACTIVO',
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Jurídico ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS casos_juridicos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo TEXT NOT NULL,
        tipo TEXT,
        contraparte TEXT,
        responsable TEXT,
        estado TEXT DEFAULT 'ABIERTO',
        fecha_apertura TEXT,
        fecha_cierre TEXT,
        descripcion TEXT,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Tesorería ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS movimientos_tesoreria (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        concepto TEXT NOT NULL,
        monto REAL NOT NULL,
        cuenta TEXT,
        fecha TEXT,
        responsable TEXT,
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Ciberseguridad / Seguridad física ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS incidentes_seguridad (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        titulo TEXT NOT NULL,
        severidad TEXT DEFAULT 'MEDIA',
        estado TEXT DEFAULT 'ABIERTO',
        descripcion TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        reportado_por TEXT,
        resolucion TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        resuelto_en TEXT
    )");

    // ---- Servicio al Cliente / PQRS ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS pqrs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        cliente_nombre TEXT NOT NULL,
        cliente_documento TEXT,
        cliente_contacto TEXT,
        canal TEXT DEFAULT 'WEB',
        referencia_liveconnect TEXT,
        descripcion TEXT NOT NULL,
        estado TEXT DEFAULT 'RECIBIDA',
        respuesta TEXT,
        atendido_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        resuelto_en TEXT
    )");

    // ---- Actas de Entrega / Devolución de Equipos (firma digital) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS actas_equipos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        empleado_documento TEXT,
        empleado_nombre TEXT,
        equipo_serial TEXT,
        equipo_descripcion TEXT,
        accesorios TEXT,
        estado_equipo TEXT,
        observaciones TEXT,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        firma_entrega TEXT,
        firmado_entrega_por TEXT,
        firmado_entrega_en TEXT,
        firmado_entrega_ip TEXT,
        firma_empleado TEXT,
        firmado_empleado_en TEXT,
        firmado_empleado_ip TEXT
    )");

    // ---- Gestión Documental ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS gd_carpetas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        carpeta_padre_id INTEGER REFERENCES gd_carpetas(id) ON DELETE CASCADE,
        area TEXT,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS gd_archivos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        carpeta_id INTEGER NOT NULL REFERENCES gd_carpetas(id) ON DELETE CASCADE,
        nombre_archivo TEXT NOT NULL,
        ruta TEXT NOT NULL,
        version INTEGER DEFAULT 1,
        tipo_mime TEXT,
        tamano INTEGER,
        descripcion TEXT,
        subido_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS gd_versiones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        archivo_id INTEGER NOT NULL REFERENCES gd_archivos(id) ON DELETE CASCADE,
        version INTEGER,
        ruta TEXT NOT NULL,
        tamano INTEGER,
        subido_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // ---- Pipeline de Oportunidades (CRM) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS oportunidades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
        titulo TEXT NOT NULL,
        valor REAL,
        etapa TEXT DEFAULT 'PROSPECTO',
        responsable_documento TEXT,
        responsable_nombre TEXT,
        fecha_cierre_esperada TEXT,
        notas TEXT,
        orden INTEGER DEFAULT 0,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS enlaces_colaboracion (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        nombre TEXT NOT NULL,
        url TEXT NOT NULL,
        area TEXT,
        empleado_documento TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $columnasLecciones = array_column($pdo->query("PRAGMA table_info(lecciones)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasLecciones = ['tipo' => "TEXT DEFAULT 'TEXTO'", 'archivo_ruta' => 'TEXT', 'archivo_nombre' => 'TEXT'];
    foreach ($nuevasLecciones as $col => $tipo) {
        if (!in_array($col, $columnasLecciones, true)) {
            $pdo->exec("ALTER TABLE lecciones ADD COLUMN {$col} {$tipo}");
        }
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS examenes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        curso_id INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
        titulo TEXT NOT NULL,
        nota_minima INTEGER DEFAULT 60,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS examen_preguntas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        examen_id INTEGER NOT NULL REFERENCES examenes(id) ON DELETE CASCADE,
        texto TEXT NOT NULL,
        orden INTEGER DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS examen_opciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pregunta_id INTEGER NOT NULL REFERENCES examen_preguntas(id) ON DELETE CASCADE,
        texto TEXT NOT NULL,
        es_correcta INTEGER DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS examen_resultados (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        examen_id INTEGER NOT NULL REFERENCES examenes(id) ON DELETE CASCADE,
        empleado_documento TEXT NOT NULL,
        empleado_nombre TEXT,
        puntaje INTEGER,
        aprobado INTEGER DEFAULT 0,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $columnasDoc = array_column($pdo->query("PRAGMA table_info(documentos)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasDoc = ['empleado_documento' => 'TEXT', 'requiere_firma' => 'INTEGER DEFAULT 0', 'firmado_en' => 'TEXT', 'firmado_ip' => 'TEXT'];
    foreach ($nuevasDoc as $col => $tipo) {
        if (!in_array($col, $columnasDoc, true)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN {$col} {$tipo}");
        }
    }

    $columnasVac = array_column($pdo->query("PRAGMA table_info(vacaciones_permisos)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if ($columnasVac && !in_array('adjunto_ruta', $columnasVac, true)) {
        $pdo->exec("ALTER TABLE vacaciones_permisos ADD COLUMN adjunto_ruta TEXT");
    }
    if ($columnasVac && !in_array('adjunto_nombre', $columnasVac, true)) {
        $pdo->exec("ALTER TABLE vacaciones_permisos ADD COLUMN adjunto_nombre TEXT");
    }

    $columnasSolic = array_column($pdo->query("PRAGMA table_info(solicitudes_aprobacion)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasSolic = ['nivel_actual' => "TEXT DEFAULT 'DIRECTOR'", 'escalado_por' => 'TEXT', 'escalado_en' => 'TEXT', 'escalado_motivo' => 'TEXT'];
    foreach ($nuevasSolic as $col => $tipo) {
        if ($columnasSolic && !in_array($col, $columnasSolic, true)) {
            $pdo->exec("ALTER TABLE solicitudes_aprobacion ADD COLUMN {$col} {$tipo}");
        }
    }

    $columnasTicketsArea = array_column($pdo->query("PRAGMA table_info(tickets)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('solicitante_area', $columnasTicketsArea, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN solicitante_area TEXT");
    }

    $columnasCargos = array_column($pdo->query("PRAGMA table_info(cargos)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if ($columnasCargos && !in_array('rol_sugerido', $columnasCargos, true)) {
        $pdo->exec("ALTER TABLE cargos ADD COLUMN rol_sugerido TEXT");
    }
    $columnasEmp2 = array_column($pdo->query("PRAGMA table_info(empleados)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('cargo_id', $columnasEmp2, true)) {
        $pdo->exec("ALTER TABLE empleados ADD COLUMN cargo_id INTEGER REFERENCES cargos(id) ON DELETE SET NULL");
    }

    $columnasCred = array_column($pdo->query("PRAGMA table_info(credenciales)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('usuario_id', $columnasCred, true)) {
        $pdo->exec("ALTER TABLE credenciales ADD COLUMN usuario_id INTEGER REFERENCES usuarios_sistema(id) ON DELETE SET NULL");
    }

    $columnasUsuarios = array_column($pdo->query("PRAGMA table_info(usuarios_sistema)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasUsuarios = ['totp_secreto' => 'TEXT', 'totp_habilitado' => 'INTEGER DEFAULT 0', 'area_responsable' => 'TEXT', 'sso_microsoft_id' => 'TEXT', 'rol_secundario' => 'TEXT', 'password_temporal' => 'INTEGER DEFAULT 0'];
    foreach ($nuevasUsuarios as $col => $tipo) {
        if (!in_array($col, $columnasUsuarios, true)) {
            $pdo->exec("ALTER TABLE usuarios_sistema ADD COLUMN {$col} {$tipo}");
        }
    }

    // La cuenta admin inicial pasa a SUPER_ADMIN (ve todo, sin excepcion) ahora que
    // ADMIN puede quedar limitado a un area si se le asigna una.
    $pdo->exec("UPDATE usuarios_sistema SET rol = 'SUPER_ADMIN' WHERE email = 'admin@navissi.com' AND rol = 'ADMIN'");

    $columnasInventario = array_column($pdo->query("PRAGMA table_info(inventario)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasInventario = ['rustdesk_id' => 'TEXT', 'rustdesk_password' => 'TEXT', 'ip_local' => 'TEXT', 'ultima_conexion_agente' => 'TEXT', 'asignado_documento' => 'TEXT'];
    foreach ($nuevasInventario as $col => $tipo) {
        if (!in_array($col, $columnasInventario, true)) {
            $pdo->exec("ALTER TABLE inventario ADD COLUMN {$col} {$tipo}");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS importador_carpeta_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        archivo TEXT NOT NULL,
        hoja TEXT,
        mtime INTEGER,
        tamano INTEGER,
        destino TEXT,
        importados INTEGER DEFAULT 0,
        actualizados INTEGER DEFAULT 0,
        omitidos INTEGER DEFAULT 0,
        error TEXT,
        procesado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(archivo, hoja, mtime)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_modulos_extra (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL REFERENCES usuarios_sistema(id) ON DELETE CASCADE,
        modulo_href TEXT NOT NULL,
        UNIQUE(usuario_id, modulo_href)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS perfiles_modulos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rol TEXT NOT NULL,
        modulo_href TEXT NOT NULL,
        UNIQUE(rol, modulo_href)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL REFERENCES usuarios_sistema(id) ON DELETE CASCADE,
        token TEXT NOT NULL UNIQUE,
        expira_en TEXT NOT NULL,
        usado INTEGER DEFAULT 0,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS metricas_historicas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        clave TEXT NOT NULL,
        valor REAL NOT NULL,
        fecha TEXT NOT NULL,
        UNIQUE(clave, fecha)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS horarios_sede (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE CASCADE,
        dia_semana INTEGER NOT NULL,
        hora_apertura TEXT,
        hora_cierre TEXT,
        cerrado INTEGER DEFAULT 0,
        UNIQUE(sede_id, dia_semana)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS campos_personalizados_def (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entidad TEXT NOT NULL,
        nombre_campo TEXT NOT NULL,
        tipo TEXT DEFAULT 'TEXTO',
        opciones TEXT,
        UNIQUE(entidad, nombre_campo)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS campos_personalizados_valor (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campo_id INTEGER NOT NULL REFERENCES campos_personalizados_def(id) ON DELETE CASCADE,
        entidad_id INTEGER NOT NULL,
        valor TEXT,
        UNIQUE(campo_id, entidad_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS calendario_eventos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo TEXT NOT NULL,
        descripcion TEXT,
        tipo TEXT DEFAULT 'REUNION',
        fecha_inicio TEXT NOT NULL,
        fecha_fin TEXT,
        todo_el_dia INTEGER DEFAULT 0,
        responsable TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
        creado_por TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS umbrales_alertas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        clave TEXT NOT NULL UNIQUE,
        nombre TEXT NOT NULL,
        descripcion TEXT,
        valor INTEGER NOT NULL,
        unidad TEXT DEFAULT 'días',
        activo INTEGER DEFAULT 1
    )");
    if ((int) $pdo->query("SELECT COUNT(*) FROM umbrales_alertas")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO umbrales_alertas (clave, nombre, descripcion, valor, unidad) VALUES
            ('AGENTE_INACTIVO_DIAS', 'Equipo sin reportar', 'Días sin que el agente reporte antes de generar alerta', 30, 'días'),
            ('CONTRATO_VENCE_DIAS', 'Contrato por vencer', 'Días de anticipación para avisar que un contrato vence', 15, 'días'),
            ('DISPOSITIVO_RED_INACTIVO_DIAS', 'Dispositivo de red inactivo', 'Días sin verse en Network Discovery antes de marcarlo inactivo', 7, 'días')");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS categorias_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL UNIQUE,
        descripcion TEXT,
        area_responsable TEXT,
        color TEXT DEFAULT '#e31c6c',
        activa INTEGER DEFAULT 1,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    if ((int) $pdo->query("SELECT COUNT(*) FROM categorias_tickets")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO categorias_tickets (nombre, color) VALUES
            ('SOPORTE', '#e31c6c'), ('HARDWARE', '#0d9488'), ('SOFTWARE', '#4f46e5'),
            ('RED', '#f59e0b'), ('ACCESOS', '#7c3aed'), ('RRHH', '#0ea5e9')");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS dispositivos_red (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        mac TEXT,
        hostname TEXT,
        fabricante TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        inventario_id INTEGER REFERENCES inventario(id) ON DELETE SET NULL,
        primera_vez_visto TEXT DEFAULT CURRENT_TIMESTAMP,
        ultima_vez_visto TEXT DEFAULT CURRENT_TIMESTAMP,
        estado TEXT DEFAULT 'DESCONOCIDO',
        UNIQUE(ip, mac)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS plantillas_correo (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        evento TEXT,
        asunto TEXT NOT NULL,
        cuerpo TEXT NOT NULL,
        activa INTEGER DEFAULT 1,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    if ((int) $pdo->query("SELECT COUNT(*) FROM plantillas_correo")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO plantillas_correo (nombre, evento, asunto, cuerpo) VALUES
            ('Ticket creado', 'TICKET_CREADO', 'Ticket #{id} recibido: {titulo}', 'Hola {solicitante},\n\nRecibimos tu solicitud \"{titulo}\" y ya está en cola de atención. Te avisaremos cuando un técnico la tome.\n\nEquipo de TI · Grupo 10Z'),
            ('Ticket resuelto', 'TICKET_RESUELTO', 'Ticket #{id} resuelto: {titulo}', 'Hola {solicitante},\n\nTu solicitud \"{titulo}\" fue marcada como resuelta. Si el problema persiste, responde este ticket para reabrirlo.\n\nEquipo de TI · Grupo 10Z'),
            ('SLA por vencer', 'SLA_ALERTA', 'Atención: el ticket #{id} está por vencer su SLA', 'El ticket \"{titulo}\" vence su SLA pronto. Por favor revísalo cuanto antes.\n\nNAVISSI Inventario')");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS parches_equipo (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        inventario_id INTEGER REFERENCES inventario(id) ON DELETE CASCADE,
        kb TEXT NOT NULL,
        descripcion TEXT,
        tipo TEXT,
        fecha_instalado TEXT,
        estado TEXT DEFAULT 'INSTALADO',
        reportado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(inventario_id, kb)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sla_politicas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        descripcion TEXT,
        prioridad TEXT NOT NULL,
        tiempo_respuesta_horas REAL NOT NULL,
        tiempo_resolucion_horas REAL NOT NULL,
        horario_laboral TEXT DEFAULT 'L-V 8:00-18:00',
        activo INTEGER DEFAULT 1,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    if ((int) $pdo->query("SELECT COUNT(*) FROM sla_politicas")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO sla_politicas (nombre, prioridad, tiempo_respuesta_horas, tiempo_resolucion_horas) VALUES
            ('SLA Urgente', 'URGENTE', 1, 4),
            ('SLA Alta', 'ALTA', 2, 8),
            ('SLA Media', 'MEDIA', 4, 24),
            ('SLA Baja', 'BAJA', 8, 72)");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS alertas_sistema (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        clave_unica TEXT UNIQUE,
        titulo TEXT NOT NULL,
        categoria TEXT NOT NULL,
        gravedad TEXT DEFAULT 'ADVERTENCIA',
        entidad_tipo TEXT,
        entidad_id TEXT,
        ticket_id INTEGER,
        estado TEXT DEFAULT 'ACTIVA',
        pospuesta_hasta TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
        resuelto_en TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS grupos_codigos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        prefijo TEXT NOT NULL,
        digitos INTEGER DEFAULT 4,
        siguiente_numero INTEGER DEFAULT 1,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        tipo_equipo TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contratos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        proveedor_nombre TEXT NOT NULL,
        tipo TEXT DEFAULT 'OTRO',
        numero_contrato TEXT,
        descripcion TEXT,
        fecha_inicio TEXT,
        fecha_fin TEXT,
        valor REAL,
        periodicidad_pago TEXT,
        responsable TEXT,
        sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
        estado TEXT DEFAULT 'VIGENTE',
        observaciones TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS solicitudes_aprobacion (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        solicitante_documento TEXT,
        solicitante_nombre TEXT,
        area_responsable TEXT,
        descripcion TEXT NOT NULL,
        monto REAL,
        prioridad TEXT DEFAULT 'NORMAL',
        estado TEXT DEFAULT 'PENDIENTE',
        aprobador TEXT,
        comentario_aprobador TEXT,
        resuelto_en TEXT,
        creado_en TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $columnasTickets = array_column($pdo->query("PRAGMA table_info(tickets)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('sla_limite', $columnasTickets, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN sla_limite TEXT");
    }
    if (!in_array('origen', $columnasTickets, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN origen TEXT DEFAULT 'MANUAL'");
    }
    if (!in_array('creado_por_documento', $columnasTickets, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN creado_por_documento TEXT");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios_sistema (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            documento TEXT,
            password_hash TEXT NOT NULL,
            rol TEXT NOT NULL DEFAULT 'EMPLEADO',
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            area_responsable TEXT,
            activo INTEGER DEFAULT 1,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS correos_a_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mensaje_id TEXT UNIQUE,
            buzon TEXT,
            remitente TEXT,
            asunto TEXT,
            ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
            procesado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ");

    if ((int) $pdo->query("SELECT COUNT(*) FROM usuarios_sistema")->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO usuarios_sistema (nombre, email, password_hash, rol) VALUES (?,?,?,?)")
            ->execute(['Administrador', 'admin@navissi.com', password_hash(navissi_bootstrap_password(), PASSWORD_DEFAULT), 'SUPER_ADMIN']);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ms365_usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            graph_id TEXT UNIQUE,
            nombre TEXT,
            correo TEXT,
            departamento TEXT,
            cargo TEXT,
            cuenta_activa INTEGER DEFAULT 1,
            licencias TEXT,
            actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS ms365_licencias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sku_id TEXT UNIQUE,
            nombre TEXT,
            compradas INTEGER,
            consumidas INTEGER,
            actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS ms365_sync_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha TEXT DEFAULT CURRENT_TIMESTAMP,
            resultado TEXT,
            detalle TEXT
        );
        CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            descripcion TEXT,
            categoria TEXT DEFAULT 'SOPORTE',
            prioridad TEXT DEFAULT 'MEDIA',
            estado TEXT DEFAULT 'ABIERTO',
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            solicitante TEXT,
            solicitante_contacto TEXT,
            asignado_a TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            cerrado_en TEXT,
            sla_limite TEXT
        );
        CREATE TABLE IF NOT EXISTS tickets_comentarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
            autor TEXT,
            comentario TEXT,
            tipo TEXT DEFAULT 'COMENTARIO',
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS cursos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            area TEXT NOT NULL,
            titulo TEXT NOT NULL,
            descripcion TEXT,
            estado TEXT DEFAULT 'PUBLICADO',
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS lecciones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            curso_id INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
            titulo TEXT NOT NULL,
            contenido TEXT,
            orden INTEGER DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS progreso_cursos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            leccion_id INTEGER NOT NULL REFERENCES lecciones(id) ON DELETE CASCADE,
            empleado_documento TEXT NOT NULL,
            empleado_nombre TEXT,
            completado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(leccion_id, empleado_documento)
        );
        CREATE TABLE IF NOT EXISTS noticias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            contenido TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            fijado INTEGER DEFAULT 0,
            autor TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS solicitudes_actualizacion (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            reporta_nombre TEXT,
            reporta_cargo TEXT,
            tipo TEXT DEFAULT 'ACTUALIZACION',
            datos TEXT,
            estado TEXT DEFAULT 'PENDIENTE',
            revisado_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            revisado_en TEXT
        );
        CREATE TABLE IF NOT EXISTS movimientos_equipos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inventario_id INTEGER REFERENCES inventario(id) ON DELETE SET NULL,
            tipo TEXT NOT NULL,
            fecha TEXT DEFAULT CURRENT_TIMESTAMP,
            responsable TEXT,
            destinatario TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            motivo TEXT,
            observaciones TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS documentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre_archivo TEXT,
            ruta TEXT,
            categoria TEXT DEFAULT 'OTRO',
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
            descripcion TEXT,
            subido_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS desprendibles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_documento TEXT NOT NULL,
            periodo TEXT NOT NULL,
            nombre_archivo TEXT,
            ruta TEXT,
            subido_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS periodos_nomina (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            fecha_inicio TEXT NOT NULL,
            fecha_fin TEXT NOT NULL,
            estado TEXT DEFAULT 'ABIERTO'
        );
        CREATE TABLE IF NOT EXISTS nominas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            periodo_id INTEGER NOT NULL REFERENCES periodos_nomina(id) ON DELETE CASCADE,
            empleado_documento TEXT NOT NULL,
            empleado_nombre TEXT,
            salario_base REAL DEFAULT 0,
            dias_trabajados REAL DEFAULT 30,
            salario_devengado REAL DEFAULT 0,
            auxilio_transporte REAL DEFAULT 0,
            otras_bonificaciones REAL DEFAULT 0,
            salud REAL DEFAULT 0,
            pension REAL DEFAULT 0,
            otras_deducciones REAL DEFAULT 0,
            neto_pagar REAL DEFAULT 0,
            estado TEXT DEFAULT 'PENDIENTE',
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(periodo_id, empleado_documento)
        );
        CREATE TABLE IF NOT EXISTS vacaciones_permisos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_documento TEXT NOT NULL,
            empleado_nombre TEXT,
            tipo TEXT DEFAULT 'VACACIONES',
            fecha_inicio TEXT,
            fecha_fin TEXT,
            dias INTEGER,
            motivo TEXT,
            estado TEXT DEFAULT 'SOLICITADO',
            aprobado_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS base_conocimiento (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            categoria TEXT,
            contenido TEXT,
            autor TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS respuestas_rapidas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            categoria TEXT,
            texto TEXT NOT NULL,
            usos INTEGER DEFAULT 0,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS evaluaciones_desempeno (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_documento TEXT NOT NULL,
            empleado_nombre TEXT,
            periodo TEXT NOT NULL,
            evaluador TEXT,
            puntualidad INTEGER DEFAULT 3,
            calidad_trabajo INTEGER DEFAULT 3,
            trabajo_equipo INTEGER DEFAULT 3,
            iniciativa INTEGER DEFAULT 3,
            cumplimiento_metas INTEGER DEFAULT 3,
            promedio REAL DEFAULT 3,
            fortalezas TEXT,
            oportunidades_mejora TEXT,
            plan_accion TEXT,
            estado TEXT DEFAULT 'BORRADOR',
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS solicitudes_aprobacion (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo TEXT NOT NULL,
            solicitante_documento TEXT,
            solicitante_nombre TEXT,
            area_responsable TEXT,
            descripcion TEXT NOT NULL,
            monto REAL,
            prioridad TEXT DEFAULT 'NORMAL',
            estado TEXT DEFAULT 'PENDIENTE',
            aprobador TEXT,
            comentario_aprobador TEXT,
            resuelto_en TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS importador_carpeta_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            archivo TEXT NOT NULL,
            hoja TEXT,
            mtime INTEGER,
            tamano INTEGER,
            destino TEXT,
            importados INTEGER DEFAULT 0,
            actualizados INTEGER DEFAULT 0,
            omitidos INTEGER DEFAULT 0,
            error TEXT,
            procesado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(archivo, hoja, mtime)
        );
        CREATE TABLE IF NOT EXISTS usuario_modulos_extra (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL REFERENCES usuarios_sistema(id) ON DELETE CASCADE,
            modulo_href TEXT NOT NULL,
            UNIQUE(usuario_id, modulo_href)
        );
        CREATE TABLE IF NOT EXISTS perfiles_modulos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rol TEXT NOT NULL,
            modulo_href TEXT NOT NULL,
            UNIQUE(rol, modulo_href)
        );
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL REFERENCES usuarios_sistema(id) ON DELETE CASCADE,
            token TEXT NOT NULL UNIQUE,
            expira_en TEXT NOT NULL,
            usado INTEGER DEFAULT 0,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS metricas_historicas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clave TEXT NOT NULL,
            valor REAL NOT NULL,
            fecha TEXT NOT NULL,
            UNIQUE(clave, fecha)
        );
        CREATE TABLE IF NOT EXISTS horarios_sede (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE CASCADE,
            dia_semana INTEGER NOT NULL,
            hora_apertura TEXT,
            hora_cierre TEXT,
            cerrado INTEGER DEFAULT 0,
            UNIQUE(sede_id, dia_semana)
        );
        CREATE TABLE IF NOT EXISTS campos_personalizados_def (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entidad TEXT NOT NULL,
            nombre_campo TEXT NOT NULL,
            tipo TEXT DEFAULT 'TEXTO',
            opciones TEXT,
            UNIQUE(entidad, nombre_campo)
        );
        CREATE TABLE IF NOT EXISTS campos_personalizados_valor (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campo_id INTEGER NOT NULL REFERENCES campos_personalizados_def(id) ON DELETE CASCADE,
            entidad_id INTEGER NOT NULL,
            valor TEXT,
            UNIQUE(campo_id, entidad_id)
        );
        CREATE TABLE IF NOT EXISTS calendario_eventos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            descripcion TEXT,
            tipo TEXT DEFAULT 'REUNION',
            fecha_inicio TEXT NOT NULL,
            fecha_fin TEXT,
            todo_el_dia INTEGER DEFAULT 0,
            responsable TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
            creado_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS umbrales_alertas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clave TEXT NOT NULL UNIQUE,
            nombre TEXT NOT NULL,
            descripcion TEXT,
            valor INTEGER NOT NULL,
            unidad TEXT DEFAULT 'días',
            activo INTEGER DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS categorias_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE,
            descripcion TEXT,
            area_responsable TEXT,
            color TEXT DEFAULT '#e31c6c',
            activa INTEGER DEFAULT 1,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS dispositivos_red (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            mac TEXT,
            hostname TEXT,
            fabricante TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            inventario_id INTEGER REFERENCES inventario(id) ON DELETE SET NULL,
            primera_vez_visto TEXT DEFAULT CURRENT_TIMESTAMP,
            ultima_vez_visto TEXT DEFAULT CURRENT_TIMESTAMP,
            estado TEXT DEFAULT 'DESCONOCIDO',
            UNIQUE(ip, mac)
        );
        CREATE TABLE IF NOT EXISTS plantillas_correo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            evento TEXT,
            asunto TEXT NOT NULL,
            cuerpo TEXT NOT NULL,
            activa INTEGER DEFAULT 1,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS parches_equipo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inventario_id INTEGER REFERENCES inventario(id) ON DELETE CASCADE,
            kb TEXT NOT NULL,
            descripcion TEXT,
            tipo TEXT,
            fecha_instalado TEXT,
            estado TEXT DEFAULT 'INSTALADO',
            reportado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(inventario_id, kb)
        );
        CREATE TABLE IF NOT EXISTS sla_politicas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            descripcion TEXT,
            prioridad TEXT NOT NULL,
            tiempo_respuesta_horas REAL NOT NULL,
            tiempo_resolucion_horas REAL NOT NULL,
            horario_laboral TEXT DEFAULT 'L-V 8:00-18:00',
            activo INTEGER DEFAULT 1,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS alertas_sistema (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clave_unica TEXT UNIQUE,
            titulo TEXT NOT NULL,
            categoria TEXT NOT NULL,
            gravedad TEXT DEFAULT 'ADVERTENCIA',
            entidad_tipo TEXT,
            entidad_id TEXT,
            ticket_id INTEGER,
            estado TEXT DEFAULT 'ACTIVA',
            pospuesta_hasta TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            resuelto_en TEXT
        );
        CREATE TABLE IF NOT EXISTS grupos_codigos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            prefijo TEXT NOT NULL,
            digitos INTEGER DEFAULT 4,
            siguiente_numero INTEGER DEFAULT 1,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            tipo_equipo TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS contratos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            proveedor_nombre TEXT NOT NULL,
            tipo TEXT DEFAULT 'OTRO',
            numero_contrato TEXT,
            descripcion TEXT,
            fecha_inicio TEXT,
            fecha_fin TEXT,
            valor REAL,
            periodicidad_pago TEXT,
            responsable TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            estado TEXT DEFAULT 'VIGENTE',
            observaciones TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS clientes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            nit_cedula TEXT,
            tipo TEXT DEFAULT 'CLIENTE',
            telefono TEXT,
            email TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            estado TEXT DEFAULT 'ACTIVO',
            observaciones TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS documentos_rrhh (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_documento TEXT NOT NULL,
            tipo TEXT DEFAULT 'CONTRATO',
            nombre_archivo TEXT,
            ruta_local TEXT,
            onedrive_url TEXT,
            estado_firma TEXT DEFAULT 'PENDIENTE',
            firmado_por TEXT,
            firmado_en TEXT,
            firmado_ip TEXT,
            subido_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS hoja_vida (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entidad_tipo TEXT NOT NULL,
            entidad_id TEXT NOT NULL,
            evento TEXT NOT NULL,
            detalle TEXT,
            autor TEXT,
            ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS departamentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE,
            responsable TEXT,
            estado TEXT DEFAULT 'ACTIVO'
        );
        CREATE TABLE IF NOT EXISTS cargos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            departamento_id INTEGER REFERENCES departamentos(id) ON DELETE SET NULL,
            estado TEXT DEFAULT 'ACTIVO'
        );
    ");

    $columnasTickets2 = array_column($pdo->query("PRAGMA table_info(tickets)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['equipo_serial' => 'TEXT', 'canal_media_url' => 'TEXT'] as $col => $tipo) {
        if (!in_array($col, $columnasTickets2, true)) {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN {$col} {$tipo}");
        }
    }

    $columnasMov = array_column($pdo->query("PRAGMA table_info(movimientos_equipos)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasMov = [
        'destinatario_documento' => 'TEXT', 'detalles_json' => 'TEXT',
        'firma_nombre' => 'TEXT', 'firma_documento' => 'TEXT', 'firma_fecha' => 'TEXT', 'firma_ip' => 'TEXT',
    ];
    foreach ($nuevasMov as $col => $tipo) {
        if (!in_array($col, $columnasMov, true)) {
            $pdo->exec("ALTER TABLE movimientos_equipos ADD COLUMN {$col} {$tipo}");
        }
    }
}

/** Registra un evento en la hoja de vida de un empleado o equipo. Trazabilidad de una sola vía (no se edita ni se borra). */
function hoja_vida_registrar(PDO $pdo, string $entidadTipo, string $entidadId, string $evento, ?string $detalle = null, ?string $autor = null, ?int $ticketId = null) {
    if (!$entidadId) return;
    $pdo->prepare("INSERT INTO hoja_vida (entidad_tipo, entidad_id, evento, detalle, autor, ticket_id) VALUES (?,?,?,?,?,?)")
        ->execute([$entidadTipo, $entidadId, $evento, $detalle, $autor ?: 'Sistema', $ticketId]);
}

function crear_esquema(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE sedes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE,
            ciudad TEXT,
            direccion TEXT,
            proveedor_internet TEXT,
            ip_red TEXT,
            estado TEXT DEFAULT 'ACTIVO',
            zona TEXT,
            coordinadora TEXT,
            coordinadora_celular TEXT,
            administradora TEXT,
            administradora_celular TEXT,
            segunda_encargada TEXT,
            segunda_encargada_celular TEXT,
            correo_corporativo TEXT,
            ip_asignada TEXT,
            actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE inventario (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            serial TEXT UNIQUE,
            placa TEXT,
            asignado_a TEXT,
            asignado_documento TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            area TEXT,
            cargo TEXT,
            tipo TEXT,
            marca TEXT,
            modelo TEXT,
            sistema_operativo TEXT,
            procesador TEXT,
            memoria TEXT,
            almacenamiento TEXT,
            estado TEXT DEFAULT 'ACTIVO',
            fuente TEXT,
            rustdesk_id TEXT,
            rustdesk_password TEXT,
            ip_local TEXT,
            ultima_conexion_agente TEXT,
            actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE credenciales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            sistema TEXT NOT NULL,
            usuario TEXT,
            contrasena TEXT,
            categoria TEXT,
            estado TEXT DEFAULT 'ACTIVO',
            origen TEXT,
            usuario_id INTEGER REFERENCES usuarios_sistema(id) ON DELETE SET NULL,
            UNIQUE(sistema, usuario, sede_id)
        );

        CREATE TABLE licencias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            proveedor TEXT,
            tipo TEXT,
            cantidad INTEGER,
            valor_mes REAL,
            valor_anual REAL,
            observaciones TEXT
        );

        CREATE TABLE empleados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            documento TEXT,
            nombres TEXT,
            cargo TEXT,
            area TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            email TEXT,
            estado TEXT DEFAULT 'ACTIVO',
            fecha_ingreso TEXT,
            tipo_contrato TEXT,
            salario REAL
        );

        CREATE TABLE importaciones_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            archivo TEXT,
            hoja TEXT,
            fila INTEGER,
            motivo TEXT,
            datos TEXT,
            fecha TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE ms365_usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            graph_id TEXT UNIQUE,
            nombre TEXT,
            correo TEXT,
            departamento TEXT,
            cargo TEXT,
            cuenta_activa INTEGER DEFAULT 1,
            licencias TEXT,
            actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE ms365_licencias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sku_id TEXT UNIQUE,
            nombre TEXT,
            compradas INTEGER,
            consumidas INTEGER,
            actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE ms365_sync_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha TEXT DEFAULT CURRENT_TIMESTAMP,
            resultado TEXT,
            detalle TEXT
        );

        CREATE TABLE tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            descripcion TEXT,
            categoria TEXT DEFAULT 'SOPORTE',
            prioridad TEXT DEFAULT 'MEDIA',
            estado TEXT DEFAULT 'ABIERTO',
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            solicitante TEXT,
            solicitante_contacto TEXT,
            asignado_a TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            cerrado_en TEXT,
            sla_limite TEXT
        );

        CREATE TABLE tickets_comentarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
            autor TEXT,
            comentario TEXT,
            tipo TEXT DEFAULT 'COMENTARIO',
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE cursos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            area TEXT NOT NULL,
            titulo TEXT NOT NULL,
            descripcion TEXT,
            estado TEXT DEFAULT 'PUBLICADO',
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE lecciones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            curso_id INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
            titulo TEXT NOT NULL,
            contenido TEXT,
            orden INTEGER DEFAULT 0
        );
        CREATE TABLE progreso_cursos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            leccion_id INTEGER NOT NULL REFERENCES lecciones(id) ON DELETE CASCADE,
            empleado_documento TEXT NOT NULL,
            empleado_nombre TEXT,
            completado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(leccion_id, empleado_documento)
        );
        CREATE TABLE noticias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            contenido TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            fijado INTEGER DEFAULT 0,
            autor TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE solicitudes_actualizacion (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            reporta_nombre TEXT,
            reporta_cargo TEXT,
            tipo TEXT DEFAULT 'ACTUALIZACION',
            datos TEXT,
            estado TEXT DEFAULT 'PENDIENTE',
            revisado_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP,
            revisado_en TEXT
        );
        CREATE TABLE movimientos_equipos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inventario_id INTEGER REFERENCES inventario(id) ON DELETE SET NULL,
            tipo TEXT NOT NULL,
            fecha TEXT DEFAULT CURRENT_TIMESTAMP,
            responsable TEXT,
            destinatario TEXT,
            destinatario_documento TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            motivo TEXT,
            observaciones TEXT,
            detalles_json TEXT,
            firma_nombre TEXT,
            firma_documento TEXT,
            firma_fecha TEXT,
            firma_ip TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE documentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre_archivo TEXT,
            ruta TEXT,
            categoria TEXT DEFAULT 'OTRO',
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
            descripcion TEXT,
            subido_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE desprendibles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_documento TEXT NOT NULL,
            periodo TEXT NOT NULL,
            nombre_archivo TEXT,
            ruta TEXT,
            subido_por TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE clientes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            nit_cedula TEXT,
            tipo TEXT DEFAULT 'CLIENTE',
            telefono TEXT,
            email TEXT,
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            estado TEXT DEFAULT 'ACTIVO',
            observaciones TEXT,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE usuarios_sistema (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            documento TEXT,
            password_hash TEXT NOT NULL,
            rol TEXT NOT NULL DEFAULT 'EMPLEADO',
            sede_id INTEGER REFERENCES sedes(id) ON DELETE SET NULL,
            activo INTEGER DEFAULT 1,
            creado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE correos_a_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mensaje_id TEXT UNIQUE,
            buzon TEXT,
            remitente TEXT,
            asunto TEXT,
            ticket_id INTEGER REFERENCES tickets(id) ON DELETE SET NULL,
            procesado_en TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $sedes = ['PRINCIPAL - MEDELLIN','Outlet','Santa Fe Medellín 1','Santa Fe Medellín 2',
        'Viva Envigado','Premium Plaza','Molinos','Fabricato','Unicentro Cali','Chipichape Cali',
        'Nuestro Bogotá','Santa Fe Bogotá','Viva Barranquilla','Cartagena Caribe Plaza',
        'San Nicolás 1','San Nicolás 2','Mayorca','Lleras'];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO sedes (nombre) VALUES (?)");
    foreach ($sedes as $s) $stmt->execute([$s]);

    $pdo->prepare("INSERT INTO usuarios_sistema (nombre, email, password_hash, rol) VALUES (?,?,?,?)")
        ->execute(['Administrador', 'admin@navissi.com', password_hash(navissi_bootstrap_password(), PASSWORD_DEFAULT), 'SUPER_ADMIN']);
}

// ---------------- Sesión / autenticación ----------------
function iniciar_sesion_segura(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    $segura = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('NAVISSISESSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $segura,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string {
    iniciar_sesion_segura();
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function csrf_token_valido(?string $token): bool {
    iniciar_sesion_segura();
    return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

function csrf_requerir(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);
    if (!csrf_token_valido(is_string($token) ? $token : null)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Solicitud vencida o no autorizada. Recarga la página.']);
        exit;
    }
}

function navissi_bootstrap_password(): string {
    $ruta = private_path('bootstrap-admin.txt');
    $env = (string) getenv('NAVISSI_BOOTSTRAP_PASSWORD');
    if ($env !== '') return $env;
    if (file_exists($ruta)) return trim((string) file_get_contents($ruta));
    $clave = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    file_put_contents($ruta, $clave . PHP_EOL, LOCK_EX);
    @chmod($ruta, 0600);
    return $clave;
}

function navissi_secret_key(): string {
    $ruta = private_path('encryption.key');
    if (!file_exists($ruta)) {
        file_put_contents($ruta, base64_encode(random_bytes(32)), LOCK_EX);
        @chmod($ruta, 0600);
    }
    $key = base64_decode(trim((string) file_get_contents($ruta)), true);
    if (!is_string($key) || strlen($key) !== 32) throw new RuntimeException('Clave de cifrado inválida.');
    return $key;
}

function secreto_cifrado(?string $valor): bool {
    return is_string($valor) && str_starts_with($valor, 'enc:v1:');
}

function secreto_cifrar(?string $valor): ?string {
    if ($valor === null || $valor === '' || secreto_cifrado($valor)) return $valor;
    $iv = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt($valor, 'aes-256-gcm', navissi_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('No se pudo cifrar el secreto.');
    return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
}

function secreto_descifrar(?string $valor): ?string {
    if ($valor === null || $valor === '' || !secreto_cifrado($valor)) return $valor;
    $raw = base64_decode(substr($valor, 7), true);
    if (!is_string($raw) || strlen($raw) < 29) return null;
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', navissi_secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? null : $plain;
}

/**
 * Guarda un array de configuracion sensible (credenciales de Microsoft 365,
 * SMTP, proveedores de IA, etc.) cifrado en disco con el mismo AES-256-GCM
 * que ya protege la tabla `credenciales`, en vez de JSON plano.
 */
function guardar_config_json(string $ruta, array $datos): void {
    $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $parent = dirname($ruta);
    if (!is_dir($parent)) @mkdir($parent, 0700, true);
    file_put_contents($ruta, secreto_cifrar($json), LOCK_EX);
    @chmod($ruta, 0600);
}

/** Lee una configuracion guardada con guardar_config_json(). Migra de forma
 *  transparente los archivos JSON planos que hayan quedado de antes. */
function leer_config_json(string $ruta): ?array {
    if (!file_exists($ruta)) return null;
    $contenido = trim((string) file_get_contents($ruta));
    if ($contenido === '') return null;
    $json = secreto_cifrado($contenido) ? secreto_descifrar($contenido) : $contenido;
    $datos = json_decode((string) $json, true);
    if (!is_array($datos)) return null;
    if (!secreto_cifrado($contenido)) {
        try { guardar_config_json($ruta, $datos); } catch (Throwable $e) { /* se reintenta en la siguiente escritura */ }
    }
    return $datos;
}

function navissi_webhook_secret(): string {
    $ruta = private_path('webhook_hmac.key');
    if (!file_exists($ruta)) {
        file_put_contents($ruta, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($ruta, 0600);
    }
    return trim((string) file_get_contents($ruta));
}

function firma_hmac_valida(string $body, ?string $firma, string $secreto): bool {
    if (!$firma || $secreto === '') return false;
    $firma = str_starts_with($firma, 'sha256=') ? substr($firma, 7) : $firma;
    return hash_equals(hash_hmac('sha256', $body, $secreto), strtolower($firma));
}

function aplicar_migraciones_seguridad(PDO $pdo): void {
    $cols = array_column($pdo->query("PRAGMA table_info(usuarios_sistema)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('password_temporal', $cols, true)) $pdo->exec("ALTER TABLE usuarios_sistema ADD COLUMN password_temporal INTEGER DEFAULT 0");

    $stmt = $pdo->prepare("SELECT id, password_hash FROM usuarios_sistema WHERE email = ? COLLATE NOCASE AND activo = 1 LIMIT 1");
    $stmt->execute(['admin@navissi.com']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin && password_verify('navissi2026', $admin['password_hash'])) {
        $pdo->prepare("UPDATE usuarios_sistema SET password_hash = ?, password_temporal = 1 WHERE id = ?")
            ->execute([password_hash(navissi_bootstrap_password(), PASSWORD_DEFAULT), $admin['id']]);
    } elseif ($admin && file_exists(private_path('bootstrap-admin.txt'))
        && password_verify(trim((string) file_get_contents(private_path('bootstrap-admin.txt'))), $admin['password_hash'])) {
        $pdo->prepare("UPDATE usuarios_sistema SET password_temporal = 1 WHERE id = ?")->execute([$admin['id']]);
    }

    $existeCredenciales = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='credenciales'")->fetchColumn();
    if ($existeCredenciales) {
        $filas = $pdo->query("SELECT id, contrasena FROM credenciales WHERE contrasena IS NOT NULL AND contrasena <> ''")->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare("UPDATE credenciales SET contrasena = ? WHERE id = ?");
        foreach ($filas as $fila) if (!secreto_cifrado($fila['contrasena'])) $update->execute([secreto_cifrar($fila['contrasena']), $fila['id']]);
    }
}

/** Arma el array de sesión de un usuario autenticado (usado por login normal y SSO). */
function sesion_desde_usuario(array $u): array {
    return ['id' => $u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'], 'rol' => $u['rol'],
        'rol_secundario' => $u['rol_secundario'] ?? null,
        'documento' => $u['documento'], 'sede_id' => $u['sede_id'], 'area_responsable' => $u['area_responsable'] ?? null];
}

/**
 * true si el usuario NO tiene ningún rol (principal ni secundario) con privilegios más
 * allá de EMPLEADO. Se usa para forzar el panel personal en vez del dashboard global,
 * sin importar por qué URL intente entrar.
 */
function es_solo_empleado(): bool {
    $rol = rol_efectivo();
    if ($rol !== 'EMPLEADO') return false;
    $secundario = rol_secundario_efectivo();
    return $secundario === null || $secundario === 'EMPLEADO';
}

/**
 * Mantiene sincronizado el usuario_sistema vinculado a un empleado (por documento)
 * cada vez que RRHH cambia su área o cargo — así "Usuarios y roles", "Perfiles por
 * rol" y el alcance por área de Directores siempre reflejan la realidad de RRHH sin
 * que haya que tocar dos pantallas distintas a mano.
 */
function sincronizar_usuario_desde_empleado(PDO $pdo, ?string $documento, ?string $area, ?string $nombreCargo): void {
    if (!$documento) return;
    $stmt = $pdo->prepare("SELECT id FROM usuarios_sistema WHERE documento = ?");
    $stmt->execute([$documento]);
    $usuarioId = $stmt->fetchColumn();
    if (!$usuarioId) return;
    $pdo->prepare("UPDATE usuarios_sistema SET area_responsable = ? WHERE id = ?")->execute([$area ?: null, $usuarioId]);
}

/** Edad calculada siempre a partir de la fecha de nacimiento - nunca se guarda como numero fijo. */
function sst_edad(?string $fechaNacimiento): ?int {
    if (!$fechaNacimiento) return null;
    $ts = strtotime($fechaNacimiento);
    if (!$ts) return null;
    $nacimiento = new DateTime('@' . $ts);
    $hoy = new DateTime('now');
    return $hoy->diff($nacimiento)->y;
}

/**
 * Cuando un empleado pasa a INACTIVO (retiro), esto se propaga a todo el
 * sitio: se desactiva su cuenta de NAVISSI (ya no puede iniciar sesion) y su
 * perfil de SST queda archivado (se conserva por obligacion legal, pero deja
 * de aparecer como "pendiente" y ya no se puede editar desde autogestion).
 * Si vuelve a ACTIVO, se reactiva todo de nuevo.
 */
function sincronizar_retiro_empleado(PDO $pdo, ?string $documento, string $estado, ?string $actor = null): void {
    if (!$documento) return;
    $inactivo = $estado === 'INACTIVO';

    $stmt = $pdo->prepare("SELECT id, activo FROM usuarios_sistema WHERE documento = ?");
    $stmt->execute([$documento]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario && (int) $usuario['activo'] !== ($inactivo ? 0 : 1)) {
        $pdo->prepare("UPDATE usuarios_sistema SET activo = ? WHERE id = ?")->execute([$inactivo ? 0 : 1, $usuario['id']]);
    }

    $pdo->prepare("UPDATE sst_perfil_sociodemografico SET archivado_en = ? WHERE documento = ?")
        ->execute([$inactivo ? gmdate('Y-m-d H:i:s') : null, $documento]);

    hoja_vida_registrar($pdo, 'EMPLEADO', $documento, $inactivo ? 'RETIRADO' : 'REACTIVADO',
        $inactivo ? 'Empleado marcado INACTIVO: cuenta desactivada y perfil SST archivado en todo el sitio.' : 'Empleado reactivado: cuenta y perfil SST reactivados.',
        $actor ?: 'Sistema', null);
}

/**
 * Resuelve el área real de quien solicita algo (ticket, aprobación...), cruzando por
 * nombre o correo contra RRHH/usuarios - así un Director puede ver solo lo de su área
 * sin que el solicitante tenga que escribirla a mano cada vez.
 */
function area_por_solicitante(PDO $pdo, ?string $nombre, ?string $contacto = null): ?string {
    if ($nombre) {
        $stmt = $pdo->prepare("SELECT area FROM empleados WHERE nombres = ? COLLATE NOCASE LIMIT 1");
        $stmt->execute([$nombre]);
        $area = $stmt->fetchColumn();
        if ($area) return $area;
    }
    if ($contacto) {
        $stmt = $pdo->prepare("SELECT area_responsable FROM usuarios_sistema WHERE email = ? COLLATE NOCASE LIMIT 1");
        $stmt->execute([$contacto]);
        $area = $stmt->fetchColumn();
        if ($area) return $area;
    }
    return null;
}

/** Rol adicional del usuario en sesión (ej. un ADMIN que también tiene el perfil EMPLEADO), o null si no tiene. */
function rol_secundario_efectivo(): ?string {
    if (!empty($_SESSION['ver_como_rol'])) return null; // al "ver como", no se mezclan perfiles reales
    $u = usuario_actual();
    return $u['rol_secundario'] ?? null;
}

function usuario_actual(): ?array {
    iniciar_sesion_segura();
    return $_SESSION['usuario'] ?? null;
}

function requiere_login($prefix = '') {
    iniciar_sesion_segura();
    if (empty($_SESSION['usuario'])) {
        header("Location: {$prefix}login.php");
        exit;
    }
}

/**
 * Un SUPER_ADMIN puede "ver como" otro rol/área para validar permisos sin
 * tener que crear una cuenta de prueba. rol_efectivo()/alcance_area() usan
 * esto para las reglas de permisos; usuario_actual() nunca cambia (sigue
 * mostrando quién eres de verdad en la barra superior).
 */
function viendo_como(): bool {
    $u = usuario_actual();
    return $u && $u['rol'] === 'SUPER_ADMIN' && !empty($_SESSION['ver_como_rol']);
}

function rol_efectivo(): ?string {
    $u = usuario_actual();
    if (!$u) return null;
    if (viendo_como()) return $_SESSION['ver_como_rol'];
    return $u['rol'];
}

function tiene_rol(array $rolesPermitidos): bool {
    $u = usuario_actual();
    if (!$u) return false;
    $rol = rol_efectivo();
    // SUPER_ADMIN, GERENCIA y CEO abren cualquier modulo (igual que ven todos
    // los datos via usuario_ve_todo()) - son perfil tipo Admin de toda la
    // empresa, solo sin poder crear/borrar cuentas de otros usuarios (eso
    // sigue exigiendo tiene_rol(['ADMIN']) explicito).
    if (in_array($rol, ['SUPER_ADMIN', 'GERENCIA', 'CEO'], true)) return true;
    // El Director de RRHH tiene el mismo alcance total (lo pidio el dueño de
    // la cuenta) - el resto de Directores solo tienen el acceso ampliado de
    // su propia area (alcance_area() ya los limita en los datos que ven).
    if ($rol === 'DIRECTOR' && in_array($u['area_responsable'] ?? null, ['Direccion Recursos Humanos', 'RRHH'], true)) return true;
    if (in_array($rol, $rolesPermitidos, true)) return true;
    // Un usuario puede tener un segundo perfil (ej. ADMIN + EMPLEADO) - da acceso a lo que cualquiera de los dos permita.
    $secundario = rol_secundario_efectivo();
    return $secundario !== null && in_array($secundario, $rolesPermitidos, true);
}

function requiere_roles(array $rolesPermitidos, string $prefix = ''): void {
    requiere_login($prefix);
    if (!tiene_rol($rolesPermitidos)) {
        http_response_code(403);
        exit('No tienes permisos para acceder a este módulo.');
    }
}

/**
 * Modulos extra que un administrador le dio a un usuario puntual aunque su
 * rol normalmente no los incluya (ej. darle "Contratos" a alguien de RRHH).
 * Se cachea en sesion para no consultar la base de datos en cada item del menu.
 */
function modulos_extra_usuario(): array {
    $u = usuario_actual();
    if (!$u) return [];
    if (!isset($_SESSION['modulos_extra_cache'])) {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT modulo_href FROM usuario_modulos_extra WHERE usuario_id = ?");
        $stmt->execute([$u['id']]);
        $_SESSION['modulos_extra_cache'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $_SESSION['modulos_extra_cache'];
}

/** Nombre de la cookie de dispositivo de confianza (2FA). */
const COOKIE_DISPOSITIVO_CONFIANZA = 'navissi_dispositivo';

/** Registra el dispositivo actual como confiable por 30 dias y deja la cookie puesta. */
function dispositivo_confianza_registrar(PDO $pdo, int $usuarioId): void {
    $token = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', strtotime('+30 days'));
    $agente = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Dispositivo'), 0, 120);
    $pdo->prepare("INSERT INTO dispositivos_confianza (usuario_id, token_hash, nombre_dispositivo, ip_registro, expira_en) VALUES (?,?,?,?,?)")
        ->execute([$usuarioId, hash('sha256', $token), $agente, $_SERVER['REMOTE_ADDR'] ?? null, $expira]);
    $seguro = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(COOKIE_DISPOSITIVO_CONFIANZA, $usuarioId . ':' . $token, [
        'expires' => strtotime('+30 days'), 'path' => '/', 'secure' => $seguro, 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/** Si la cookie de dispositivo de confianza es valida para este usuario, actualiza su ultimo uso y devuelve true. */
function dispositivo_confianza_valido(PDO $pdo, int $usuarioId): bool {
    $cookie = $_COOKIE[COOKIE_DISPOSITIVO_CONFIANZA] ?? '';
    if (!$cookie || !str_contains($cookie, ':')) return false;
    [$uidCookie, $token] = explode(':', $cookie, 2);
    if ((int) $uidCookie !== $usuarioId) return false;
    $stmt = $pdo->prepare("SELECT id FROM dispositivos_confianza WHERE usuario_id = ? AND token_hash = ? AND expira_en > datetime('now')");
    $stmt->execute([$usuarioId, hash('sha256', $token)]);
    $id = $stmt->fetchColumn();
    if (!$id) return false;
    $pdo->prepare("UPDATE dispositivos_confianza SET ultima_vez = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
    return true;
}

/** SUPER_ADMIN (real, sin estar "viendo como" otro rol) ve y gestiona todo. */
function usuario_ve_todo(): bool {
    // Director, Gerencia y CEO tienen perfil tipo Admin: ven todos los módulos y todos
    // los datos sin restricción de área (incluso si tienen un area_responsable asignada
    // para efectos de organigrama/nómina), igual que SUPER_ADMIN pero sin las capacidades
    // de administración de cuentas/seguridad (eso lo sigue decidiendo tiene_rol(['ADMIN'])).
    return in_array(rol_efectivo(), ['SUPER_ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO'], true);
}

/** Area a la que esta limitado el usuario actual (NULL = sin restriccion de area). */
function alcance_area(): ?string {
    $u = usuario_actual();
    if (!$u || usuario_ve_todo()) return null;
    if (viendo_como()) return $_SESSION['ver_como_area'] ?: null;
    return $u['area_responsable'] ?: null;
}

/**
 * Igual que alcance_area(), pero para recortar un módulo a SOLO lo del usuario logueado
 * (no lo de su área, lo suyo puntual) - para cuando un EMPLEADO sin ningún rol elevado
 * recibe acceso individual a un módulo que normalmente es de gestión (Inventario,
 * Mesa de Ayuda, Documentos...). Devuelve null si el usuario tiene algún rol elevado
 * (principal o secundario) - en ese caso el módulo se comporta como siempre, sin recorte.
 */
function alcance_personal(): ?array {
    $u = usuario_actual();
    if (!$u || !es_solo_empleado()) return null;
    return ['documento' => $u['documento'] ?? null, 'nombre' => $u['nombre'] ?? null, 'email' => $u['email'] ?? null, 'id' => $u['id'] ?? null];
}

/**
 * Sincroniza el Centro de Alertas con el estado real del sistema (no genera
 * datos falsos): equipos que dejaron de reportar, contratos por vencer,
 * tickets con SLA vencido. Cada alerta tiene una clave unica para no
 * duplicarse en cada visita; si la condicion ya no aplica, la autoresuelve.
 */
function umbral(PDO $pdo, string $clave, int $porDefecto): int {
    $stmt = $pdo->prepare("SELECT valor FROM umbrales_alertas WHERE clave = ? AND activo = 1");
    $stmt->execute([$clave]);
    $v = $stmt->fetchColumn();
    return $v !== false ? (int) $v : $porDefecto;
}

function sincronizar_alertas(PDO $pdo): void {
    $vistas = [];
    $diasAgente = umbral($pdo, 'AGENTE_INACTIVO_DIAS', 30);
    $diasContrato = umbral($pdo, 'CONTRATO_VENCE_DIAS', 15);

    // CAST explícito: sin él, SQLite compara el "?" ligado como texto contra el
    // resultado numérico de julianday() y la condición nunca es verdadera.
    $stmt = $pdo->prepare("SELECT id, serial, marca, modelo, ultima_conexion_agente FROM inventario
        WHERE estado = 'ACTIVO' AND ultima_conexion_agente IS NOT NULL
        AND julianday('now') - julianday(ultima_conexion_agente) > CAST(? AS REAL)");
    $stmt->execute([$diasAgente]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $eq) {
        $clave = "AGENTE_INACTIVO_{$eq['id']}";
        $vistas[] = $clave;
        $pdo->prepare("INSERT INTO alertas_sistema (clave_unica, titulo, categoria, gravedad, entidad_tipo, entidad_id, estado)
            VALUES (?,?,?,?,?,?,'ACTIVA') ON CONFLICT(clave_unica) DO UPDATE SET estado = CASE WHEN estado='RESUELTA' THEN 'RESUELTA' ELSE 'ACTIVA' END")
            ->execute([$clave, "{$eq['marca']} {$eq['modelo']} ({$eq['serial']}) sin reportar hace más de {$diasAgente} días", 'Inventario', 'ADVERTENCIA', 'EQUIPO', $eq['serial']]);
    }

    $stmt = $pdo->prepare("SELECT id, proveedor_nombre, tipo, fecha_fin FROM contratos
        WHERE estado='VIGENTE' AND fecha_fin IS NOT NULL AND julianday(fecha_fin) - julianday('now') BETWEEN 0 AND CAST(? AS REAL)");
    $stmt->execute([$diasContrato]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $clave = "CONTRATO_VENCE_{$c['id']}";
        $vistas[] = $clave;
        $pdo->prepare("INSERT INTO alertas_sistema (clave_unica, titulo, categoria, gravedad, entidad_tipo, entidad_id, estado)
            VALUES (?,?,?,?,?,?,'ACTIVA') ON CONFLICT(clave_unica) DO UPDATE SET estado = CASE WHEN estado='RESUELTA' THEN 'RESUELTA' ELSE 'ACTIVA' END")
            ->execute([$clave, "Contrato de {$c['proveedor_nombre']} ({$c['tipo']}) vence el {$c['fecha_fin']}", 'Contratos', 'CRITICO', 'CONTRATO', (string) $c['id']]);
    }

    $stmt = $pdo->query("SELECT id, titulo FROM tickets WHERE estado NOT IN ('CERRADO','RESUELTO POR IA') AND sla_limite IS NOT NULL AND sla_limite < datetime('now')");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $clave = "SLA_VENCIDO_{$t['id']}";
        $vistas[] = $clave;
        $pdo->prepare("INSERT INTO alertas_sistema (clave_unica, titulo, categoria, gravedad, entidad_tipo, entidad_id, ticket_id, estado)
            VALUES (?,?,?,?,?,?,?,'ACTIVA') ON CONFLICT(clave_unica) DO UPDATE SET estado = CASE WHEN estado='RESUELTA' THEN 'RESUELTA' ELSE 'ACTIVA' END")
            ->execute([$clave, "SLA vencido: #{$t['id']} {$t['titulo']}", 'Mesa de Ayuda', 'CRITICO', 'TICKET', (string) $t['id'], $t['id']]);
    }

    $vistas[] = '__ninguna__'; // evita un NOT IN () vacio, que en SQLite no excluye nada
    $marcadores = implode(',', array_fill(0, count($vistas), '?'));
    $pdo->prepare("UPDATE alertas_sistema SET estado='RESUELTA', resuelto_en=CURRENT_TIMESTAMP
        WHERE estado='ACTIVA' AND clave_unica NOT IN ({$marcadores})
        AND categoria IN ('Inventario','Contratos','Mesa de Ayuda')")->execute($vistas);
}

/** Notificaciones/alertas reales para la campana de la barra superior (sin datos falsos: todo viene de consultas en vivo). */
function notificaciones_pendientes(PDO $pdo): array {
    $notas = [];
    $area = alcance_area();

    if (tiene_rol(['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO', 'COORDINADOR'])) {
        $sql = "SELECT COUNT(*) FROM solicitudes_aprobacion WHERE estado='PENDIENTE'" . ($area ? " AND area_responsable = " . $pdo->quote($area) : '');
        $n = (int) $pdo->query($sql)->fetchColumn();
        if ($n > 0) $notas[] = ['tipo' => 'warn', 'texto' => "{$n} solicitud" . ($n === 1 ? '' : 'es') . " por aprobar", 'href' => 'modules/aprobaciones.php'];
    }
    if (tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI'])) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM contratos WHERE estado='VIGENTE' AND fecha_fin IS NOT NULL AND julianday(fecha_fin) - julianday('now') BETWEEN 0 AND 30")->fetchColumn();
        if ($n > 0) $notas[] = ['tipo' => 'warn', 'texto' => "{$n} contrato" . ($n === 1 ? '' : 's') . " por vencer (30 días)", 'href' => 'modules/contratos.php'];
    }
    if (tiene_rol(['SUPER_ADMIN', 'ADMIN', 'TI'])) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado NOT IN ('CERRADO','RESUELTO POR IA') AND sla_limite IS NOT NULL AND sla_limite < datetime('now')")->fetchColumn();
        if ($n > 0) $notas[] = ['tipo' => 'err', 'texto' => "{$n} ticket" . ($n === 1 ? '' : 's') . " con SLA vencido", 'href' => 'modules/mesa_ayuda.php'];
        $nAbiertos = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE estado='ABIERTO'")->fetchColumn();
        if ($nAbiertos > 0) $notas[] = ['tipo' => 'info', 'texto' => "{$nAbiertos} ticket" . ($nAbiertos === 1 ? '' : 's') . " abierto" . ($nAbiertos === 1 ? '' : 's'), 'href' => 'modules/mesa_ayuda.php'];
    }
    return $notas;
}

/** Busca la plantilla activa de un evento y reemplaza {variables} por valores reales. */
function plantilla_renderizar(PDO $pdo, string $evento, array $variables): ?array {
    $stmt = $pdo->prepare("SELECT * FROM plantillas_correo WHERE evento = ? AND activa = 1 LIMIT 1");
    $stmt->execute([$evento]);
    $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plantilla) return null;
    $reemplazar = function (string $texto) use ($variables) {
        foreach ($variables as $clave => $valor) {
            $texto = str_replace('{' . $clave . '}', (string) $valor, $texto);
        }
        return $texto;
    };
    return ['asunto' => $reemplazar($plantilla['asunto']), 'cuerpo' => $reemplazar($plantilla['cuerpo'])];
}

/**
 * Guarda el valor de HOY para una metrica (una fila por dia, se sobreescribe
 * si ya se guardo hoy) y devuelve la diferencia real contra el ultimo valor
 * guardado ANTES de hoy — así las tarjetas del dashboard muestran un cambio
 * real, no decorativo.
 */
function tendencia_metrica(PDO $pdo, string $clave, float $valorActual): ?float {
    $hoy = date('Y-m-d');
    $anterior = $pdo->prepare("SELECT valor FROM metricas_historicas WHERE clave = ? AND fecha < ? ORDER BY fecha DESC LIMIT 1");
    $anterior->execute([$clave, $hoy]);
    $valorAnterior = $anterior->fetchColumn();

    $pdo->prepare("INSERT INTO metricas_historicas (clave, valor, fecha) VALUES (?,?,?)
        ON CONFLICT(clave, fecha) DO UPDATE SET valor = excluded.valor")
        ->execute([$clave, $valorActual, $hoy]);

    return $valorAnterior !== false ? $valorActual - (float) $valorAnterior : null;
}

/** HTML pequeño de flecha + delta para pegar junto a un numero de tarjeta. */
function badge_tendencia(?float $delta): string {
    if ($delta === null) return '';
    if (abs($delta) < 0.0001) return '<span class="tendencia tendencia-igual">— sin cambio</span>';
    $arriba = $delta > 0;
    $clase = $arriba ? 'tendencia-arriba' : 'tendencia-abajo';
    $flecha = $arriba ? '↑' : '↓';
    $valor = $delta == (int) $delta ? abs((int) $delta) : abs($delta);
    return "<span class=\"tendencia {$clase}\">{$flecha} {$valor} desde ayer</span>";
}

/** Consulta real el horario configurado (horarios_sede) y dice si la sede está abierta AHORA. */
function sede_esta_abierta(PDO $pdo, int $sedeId): ?array {
    $diaHoy = (int) date('w');
    $stmt = $pdo->prepare("SELECT * FROM horarios_sede WHERE sede_id = ? AND dia_semana = ?");
    $stmt->execute([$sedeId, $diaHoy]);
    $h = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$h) return null; // sin horario configurado todavia
    if ($h['cerrado']) return ['abierta' => false, 'motivo' => 'Cerrado hoy'];
    $ahora = date('H:i');
    $abierta = $ahora >= $h['hora_apertura'] && $ahora <= $h['hora_cierre'];
    return ['abierta' => $abierta, 'apertura' => $h['hora_apertura'], 'cierre' => $h['hora_cierre']];
}

function sede_id_por_nombre(PDO $pdo, ?string $nombre, bool $crearSiNoExiste = true): ?int {
    $nombre = $nombre ? trim($nombre) : null;
    if (!$nombre) return null;
    $stmt = $pdo->prepare("SELECT id FROM sedes WHERE nombre = ? COLLATE NOCASE");
    $stmt->execute([$nombre]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int) $row['id'];

    $stmt = $pdo->prepare("SELECT id FROM sedes WHERE nombre LIKE ? COLLATE NOCASE");
    $stmt->execute(['%' . $nombre . '%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int) $row['id'];

    if (!$crearSiNoExiste) return null;

    $stmt = $pdo->prepare("INSERT INTO sedes (nombre) VALUES (?)");
    $stmt->execute([$nombre]);
    return (int) $pdo->lastInsertId();
}

function limpio($v) {
    if ($v === null) return null;
    $v = trim((string) $v);
    return $v === '' ? null : $v;
}

/**
 * Limpia HTML que viene de un editor WYSIWYG (contenteditable) antes de
 * guardarlo: solo deja etiquetas de formato basico y quita atributos on*
 * (onclick, onerror...) y href/src con "javascript:" para evitar XSS
 * guardado. El HTML resultante se puede imprimir sin volver a escapar
 * (a diferencia de limpio(), que no filtra nada).
 */
function limpio_html($v): ?string {
    if ($v === null) return null;
    $v = trim((string) $v);
    if ($v === '' || $v === '<br>') return null;
    $permitidas = '<b><strong><i><em><u><ul><ol><li><br><p><a><span>';
    $v = strip_tags($v, $permitidas);
    $v = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $v);
    $v = preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>]*\2/i', '$1="#"', $v);
    return $v === '' ? null : $v;
}

function e($v) {
    // ENT_SUBSTITUTE: si el texto trae bytes UTF-8 inválidos (p.ej. de una fuente
    // externa mal codificada), reemplaza el carácter raro en vez de devolver toda
    // la cadena vacía (así era antes - un solo caracter dañado borraba el texto
    // completo en pantalla sin ningún aviso).
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ms365_config(): array {
    $data = leer_config_json(MS365_CONFIG_PATH);
    return is_array($data) ? $data : ['tenant_id' => '', 'client_id' => '', 'client_secret' => ''];
}

function ms365_config_guardar(string $tenantId, string $clientId, string $clientSecret) {
    guardar_config_json(MS365_CONFIG_PATH, [
        'tenant_id' => trim($tenantId),
        'client_id' => trim($clientId),
        'client_secret' => trim($clientSecret),
    ]);
}

function ms365_configurado(): bool {
    $c = ms365_config();
    return !empty($c['tenant_id']) && !empty($c['client_id']) && !empty($c['client_secret']);
}

// Las acciones autenticadas deben provenir de una página que emitió el token.
// Los webhooks públicos declaran CSRF_EXEMPT y aplican su propia firma HMAC.
$scriptActual = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (PHP_SAPI !== 'cli' && str_contains($scriptActual, '/modules/') && !defined('LOGIN_EXEMPT')) {
    requiere_login('../');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !defined('CSRF_EXEMPT')) {
    iniciar_sesion_segura();
    if (!empty($_SESSION['usuario'])) csrf_requerir();
}
