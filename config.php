<?php
/**
 * NAVISSI INVENTARIO - Configuración y conexión.
 * Base de datos: SQLite en data/navissi.sqlite (cero configuración, sin
 * servidor MySQL que instalar - funciona local con solo tener PHP con
 * pdo_sqlite habilitado, que viene activo por defecto en casi cualquier PHP).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('America/Bogota');

define('BASE_DIR', __DIR__);
define('DB_PATH', BASE_DIR . '/data/navissi.sqlite');
define('MASTER_DIR', 'C:\\Users\\SISTEMAS\\OneDrive - GRUPO 10Z SAS\\TI 2026\\MASTER');
define('DATOS_PDV_PATH', 'C:\\Users\\SISTEMAS\\OneDrive - GRUPO 10Z SAS\\Escritorio\\PC ANTERIOS DE SISTEMAS\\DATOS PDV.xlsx');
define('CONTACTOS_TIENDAS_PATH', 'C:\\Users\\SISTEMAS\\Downloads\\ACTUALIZAR DATOS CONTACTOS DE TIENDAS NAVISSI 2026.xlsx');

// SUPER_ADMIN: ve y administra todo, sin excepción, ignora cualquier alcance de área.
// ADMIN, DIRECTOR, GERENCIA, CEO, COORDINADOR, ANALISTA: si tienen "area_responsable"
// asignada (ver Usuarios y roles), solo ven/gestionan datos de esa área. Sin área
// asignada, ven todo dentro de lo que su rol permite (comportamiento "abierto" para
// no romper cuentas ya creadas).
define('ROLES_DISPONIBLES', ['SUPER_ADMIN', 'ADMIN', 'DIRECTOR', 'GERENCIA', 'CEO', 'COORDINADOR', 'ANALISTA', 'TI', 'RRHH', 'EMPLEADO']);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $isNew = !file_exists(DB_PATH);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        if ($isNew) {
            crear_esquema($pdo);
        } else {
            migrar_esquema($pdo);
        }
    }
    return $pdo;
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
    if (!in_array('adjunto_ruta', $columnasVac, true)) {
        $pdo->exec("ALTER TABLE vacaciones_permisos ADD COLUMN adjunto_ruta TEXT");
    }
    if (!in_array('adjunto_nombre', $columnasVac, true)) {
        $pdo->exec("ALTER TABLE vacaciones_permisos ADD COLUMN adjunto_nombre TEXT");
    }

    $columnasSolic = array_column($pdo->query("PRAGMA table_info(solicitudes_aprobacion)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $nuevasSolic = ['nivel_actual' => "TEXT DEFAULT 'DIRECTOR'", 'escalado_por' => 'TEXT', 'escalado_en' => 'TEXT', 'escalado_motivo' => 'TEXT'];
    foreach ($nuevasSolic as $col => $tipo) {
        if (!in_array($col, $columnasSolic, true)) {
            $pdo->exec("ALTER TABLE solicitudes_aprobacion ADD COLUMN {$col} {$tipo}");
        }
    }

    $columnasTicketsArea = array_column($pdo->query("PRAGMA table_info(tickets)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('solicitante_area', $columnasTicketsArea, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN solicitante_area TEXT");
    }

    $columnasCargos = array_column($pdo->query("PRAGMA table_info(cargos)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('rol_sugerido', $columnasCargos, true)) {
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
            ->execute(['Administrador', 'admin@navissi.com', password_hash('navissi2026', PASSWORD_DEFAULT), 'SUPER_ADMIN']);
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
        ->execute(['Administrador', 'admin@navissi.com', password_hash('navissi2026', PASSWORD_DEFAULT), 'SUPER_ADMIN']);
}

// ---------------- Sesión / autenticación ----------------
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
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['usuario'] ?? null;
}

function requiere_login($prefix = '') {
    if (session_status() === PHP_SESSION_NONE) session_start();
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
    if ($rol === 'SUPER_ADMIN' || in_array($rol, $rolesPermitidos, true)) return true;
    // Un usuario puede tener un segundo perfil (ej. ADMIN + EMPLEADO) - da acceso a lo que cualquiera de los dos permita.
    $secundario = rol_secundario_efectivo();
    return $secundario !== null && in_array($secundario, $rolesPermitidos, true);
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

/** SUPER_ADMIN (real, sin estar "viendo como" otro rol) ve y gestiona todo. */
function usuario_ve_todo(): bool {
    // Gerencia y CEO tienen perfil tipo Admin: ven todos los módulos y todos los datos
    // sin restricción de área, igual que SUPER_ADMIN pero sin las capacidades de
    // administración de cuentas/seguridad (eso lo sigue decidiendo tiene_rol(['ADMIN']) puntual).
    return in_array(rol_efectivo(), ['SUPER_ADMIN', 'GERENCIA', 'CEO'], true);
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

function e($v) {
    // ENT_SUBSTITUTE: si el texto trae bytes UTF-8 inválidos (p.ej. de una fuente
    // externa mal codificada), reemplaza el carácter raro en vez de devolver toda
    // la cadena vacía (así era antes - un solo caracter dañado borraba el texto
    // completo en pantalla sin ningún aviso).
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

define('MS365_CONFIG_PATH', BASE_DIR . '/data/ms365_config.json');

function ms365_config(): array {
    if (!file_exists(MS365_CONFIG_PATH)) {
        return ['tenant_id' => '', 'client_id' => '', 'client_secret' => ''];
    }
    $data = json_decode(file_get_contents(MS365_CONFIG_PATH), true);
    return is_array($data) ? $data : ['tenant_id' => '', 'client_id' => '', 'client_secret' => ''];
}

function ms365_config_guardar(string $tenantId, string $clientId, string $clientSecret) {
    file_put_contents(MS365_CONFIG_PATH, json_encode([
        'tenant_id' => trim($tenantId),
        'client_id' => trim($clientId),
        'client_secret' => trim($clientSecret),
    ], JSON_PRETTY_PRINT));
}

function ms365_configurado(): bool {
    $c = ms365_config();
    return !empty($c['tenant_id']) && !empty($c['client_id']) && !empty($c['client_secret']);
}
