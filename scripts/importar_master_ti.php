<?php
/**
 * Importador de datos reales desde la carpeta MASTER (TI 2026) hacia NAVISSI.
 * Solo CLI — maneja credenciales reales, nunca se expone por web.
 *
 * Uso:
 *   php scripts/importar_master_ti.php --dry-run
 *   php scripts/importar_master_ti.php
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Solo CLI.'); }

require_once __DIR__ . '/../config.php';
$pdo = db();
$dryRun = in_array('--dry-run', $argv, true);

$carpetaMaster = 'C:/Users/SISTEMAS/OneDrive - GRUPO 10Z SAS/TI 2026/MASTER';
$archivoInventario = $carpetaMaster . '/INVENTARIO_MAESTRO_NAVISSI.xlsx';
$archivoMaestroTI = $carpetaMaster . '/MAESTRO_TI_GRUPO10Z.xlsx';

// ---------------- Lector XLSX minimo (zip + simplexml, sin dependencias) ----------------

function xlsx_shared_strings(ZipArchive $zip): array {
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) return [];
    $sx = simplexml_load_string($xml);
    $out = [];
    foreach ($sx->si as $si) {
        if (isset($si->t)) { $out[] = (string) $si->t; continue; }
        $texto = '';
        foreach ($si->r as $r) { $texto .= (string) $r->t; }
        $out[] = $texto;
    }
    return $out;
}

function xlsx_hoja_nombre_a_archivo(ZipArchive $zip): array {
    $wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $ridArchivo = [];
    foreach ($rels->Relationship as $rel) {
        $target = (string) $rel['Target'];
        $ridArchivo[(string) $rel['Id']] = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
    }
    $mapa = [];
    foreach ($wb->sheets->sheet as $hoja) {
        $rid = (string) $hoja->attributes('r', true)->id;
        $mapa[(string) $hoja['name']] = $ridArchivo[$rid] ?? null;
    }
    return $mapa;
}

function xlsx_col_letra_a_indice(string $ref): int {
    preg_match('/^([A-Z]+)/', $ref, $m);
    $letras = $m[1];
    $idx = 0;
    for ($i = 0; $i < strlen($letras); $i++) { $idx = $idx * 26 + (ord($letras[$i]) - 64); }
    return $idx - 1;
}

function xlsx_serial_a_fecha($serial): ?string {
    if (!is_numeric($serial)) return null;
    $epoch = new DateTime('1899-12-30');
    $epoch->modify('+' . (int) $serial . ' days');
    return $epoch->format('Y-m-d');
}

/** Devuelve array de filas (cada fila = array indexado 0..N por columna), incluye fila de encabezado como fila 0. */
function xlsx_leer_hoja(ZipArchive $zip, string $rutaHoja, array $sharedStrings): array {
    $xml = $zip->getFromName($rutaHoja);
    if ($xml === false) return [];
    $sx = simplexml_load_string($xml);
    $filas = [];
    foreach ($sx->sheetData->row as $row) {
        $fila = [];
        foreach ($row->c as $c) {
            $col = xlsx_col_letra_a_indice((string) $c['r']);
            $tipo = (string) $c['t'];
            $valor = isset($c->v) ? (string) $c->v : '';
            if ($tipo === 's') $valor = $sharedStrings[(int) $valor] ?? '';
            elseif ($tipo === 'inlineStr') $valor = (string) $c->is->t;
            $fila[$col] = $valor;
        }
        $max = $fila ? max(array_keys($fila)) : -1;
        $filaCompleta = [];
        for ($i = 0; $i <= $max; $i++) { $filaCompleta[$i] = $fila[$i] ?? ''; }
        $filas[] = $filaCompleta;
    }
    return $filas;
}

function xlsx_abrir(string $ruta): array {
    $zip = new ZipArchive();
    if ($zip->open($ruta, ZipArchive::RDONLY) !== true) {
        fwrite(STDERR, "No se pudo abrir: {$ruta}\n");
        exit(1);
    }
    $shared = xlsx_shared_strings($zip);
    $mapaHojas = xlsx_hoja_nombre_a_archivo($zip);
    return [$zip, $shared, $mapaHojas];
}

function normaliza($v): ?string {
    $v = trim((string) $v);
    return $v === '' ? null : $v;
}

// ---------------- Sedes: helper para mapear nombre libre -> sede_id, creando si falta ----------------

$cacheSedes = [];
function sede_id_o_crear(PDO $pdo, ?string $nombreLibre, bool $dryRun, array &$cacheSedes, array &$stats): ?int {
    if (!$nombreLibre) return null;
    $norm = mb_strtoupper(trim($nombreLibre));
    if (isset($cacheSedes[$norm])) return $cacheSedes[$norm];

    $stmt = $pdo->prepare("SELECT id FROM sedes WHERE UPPER(nombre) = ?");
    $stmt->execute([$norm]);
    $id = $stmt->fetchColumn();
    if ($id) { $cacheSedes[$norm] = (int) $id; return (int) $id; }

    // Coincidencia parcial (ej. "Santa Fe Medellín 1" vs "SANTA FE MEDELLIN")
    $stmtTodas = $pdo->query("SELECT id, nombre FROM sedes");
    foreach ($stmtTodas->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $sNorm = mb_strtoupper($s['nombre']);
        if (str_contains($norm, $sNorm) || str_contains($sNorm, $norm)) {
            $cacheSedes[$norm] = (int) $s['id'];
            return (int) $s['id'];
        }
    }

    $stats['sedes_creadas']++;
    if ($dryRun) { $cacheSedes[$norm] = -1; return -1; }
    $pdo->prepare("INSERT INTO sedes (nombre, estado) VALUES (?, 'ACTIVO')")->execute([$nombreLibre]);
    $id = (int) $pdo->lastInsertId();
    $cacheSedes[$norm] = $id;
    return $id;
}

$stats = ['equipos_importados' => 0, 'equipos_omitidos' => 0, 'credenciales_importadas' => 0, 'credenciales_omitidas' => 0, 'sedes_creadas' => 0];

// ================= 1) INVENTARIO_MAESTRO_NAVISSI.xlsx =================
if (!file_exists($archivoInventario)) {
    fwrite(STDERR, "No existe: {$archivoInventario}\n");
    exit(1);
}
[$zipInv, $sharedInv, $hojasInv] = xlsx_abrir($archivoInventario);

// ---- INVENTARIO_GENERAL -> tabla inventario ----
if (!empty($hojasInv['INVENTARIO_GENERAL'])) {
    $filas = xlsx_leer_hoja($zipInv, $hojasInv['INVENTARIO_GENERAL'], $sharedInv);
    $encabezado = array_map('trim', $filas[0] ?? []);
    $idx = array_flip($encabezado);
    // ID SERIAL PLACA NOMBRE_USUARIO SEDE AREA CARGO TIPO MARCA REFERENCIA SO PROCESADOR MEMORIA ALMACENAMIENTO ESTADO FUENTE FECHA_ACTUALIZACION
    for ($i = 1; $i < count($filas); $i++) {
        $f = $filas[$i];
        $serial = normaliza($f[$idx['SERIAL']] ?? null);
        $sedeNombre = normaliza($f[$idx['SEDE']] ?? null);
        if (!$serial || !$sedeNombre) { $stats['equipos_omitidos']++; continue; }

        $sedeId = sede_id_o_crear($pdo, $sedeNombre, $dryRun, $cacheSedes, $stats);
        $datos = [
            'serial' => $serial,
            'placa' => normaliza($f[$idx['PLACA']] ?? null),
            'asignado_a' => normaliza($f[$idx['NOMBRE_USUARIO']] ?? null),
            'sede_id' => $sedeId > 0 ? $sedeId : null,
            'area' => normaliza($f[$idx['AREA']] ?? null),
            'cargo' => normaliza($f[$idx['CARGO']] ?? null),
            'tipo' => normaliza($f[$idx['TIPO']] ?? null),
            'marca' => normaliza($f[$idx['MARCA']] ?? null),
            'modelo' => normaliza($f[$idx['REFERENCIA']] ?? null),
            'sistema_operativo' => normaliza($f[$idx['SO']] ?? null),
            'procesador' => normaliza($f[$idx['PROCESADOR']] ?? null),
            'memoria' => normaliza($f[$idx['MEMORIA']] ?? null),
            'almacenamiento' => normaliza($f[$idx['ALMACENAMIENTO']] ?? null),
            'estado' => normaliza($f[$idx['ESTADO']] ?? null) ?: 'ACTIVO',
            'fuente' => normaliza($f[$idx['FUENTE']] ?? null) ?: 'MASTER_TI_2026',
        ];

        $stats['equipos_importados']++;
        if ($dryRun) continue;

        $existe = $pdo->prepare("SELECT id FROM inventario WHERE serial = ?");
        $existe->execute([$serial]);
        if ($existeId = $existe->fetchColumn()) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
            $stmt = $pdo->prepare("UPDATE inventario SET {$set}, actualizado_en = CURRENT_TIMESTAMP WHERE id = :id");
            $datos['id'] = $existeId;
            $stmt->execute($datos);
        } else {
            $cols = implode(', ', array_keys($datos));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
            $pdo->prepare("INSERT INTO inventario ({$cols}) VALUES ({$ph})")->execute($datos);
        }
    }
}

// ---- CREDENCIALES_365 -> tabla credenciales ----
if (!empty($hojasInv['CREDENCIALES_365'])) {
    $filas = xlsx_leer_hoja($zipInv, $hojasInv['CREDENCIALES_365'], $sharedInv);
    $encabezado = array_map('trim', $filas[0] ?? []);
    $idx = array_flip($encabezado);
    for ($i = 1; $i < count($filas); $i++) {
        $f = $filas[$i];
        $usuario = normaliza($f[$idx['USUARIO']] ?? null);
        $clave = normaliza($f[$idx['CONTRASEÑA']] ?? null);
        if (!$usuario) { $stats['credenciales_omitidas']++; continue; }
        $stats['credenciales_importadas']++;
        if ($dryRun) continue;
        $existe = $pdo->prepare("SELECT id FROM credenciales WHERE sistema = 'Microsoft 365' AND usuario = ?");
        $existe->execute([$usuario]);
        if (!$existe->fetchColumn()) {
            $pdo->prepare("INSERT INTO credenciales (nombre, sistema, usuario, contrasena, categoria, estado, origen) VALUES (?,?,?,?,?,?,?)")
                ->execute([$usuario, 'Microsoft 365', $usuario, $clave, 'O365', 'ACTIVO', normaliza($f[$idx['FUENTE']] ?? null) ?: 'MASTER_TI_2026']);
        }
    }
}

// ---- USUARIOS (POS/ERP) -> tabla credenciales ----
if (!empty($hojasInv['USUARIOS'])) {
    $filas = xlsx_leer_hoja($zipInv, $hojasInv['USUARIOS'], $sharedInv);
    $encabezado = array_map('trim', $filas[0] ?? []);
    $idx = array_flip($encabezado);
    for ($i = 1; $i < count($filas); $i++) {
        $f = $filas[$i];
        $usuario = normaliza($f[$idx['USUARIO']] ?? null);
        $sistema = normaliza($f[$idx['SISTEMA']] ?? null);
        if (!$usuario || !$sistema) { $stats['credenciales_omitidas']++; continue; }
        $sedeNombre = normaliza($f[$idx['SEDE']] ?? null);
        $sedeId = $sedeNombre ? sede_id_o_crear($pdo, $sedeNombre, $dryRun, $cacheSedes, $stats) : null;
        $stats['credenciales_importadas']++;
        if ($dryRun) continue;
        $existe = $pdo->prepare("SELECT id FROM credenciales WHERE sistema = ? AND usuario = ?");
        $existe->execute([$sistema, $usuario]);
        if (!$existe->fetchColumn()) {
            $pdo->prepare("INSERT INTO credenciales (nombre, sede_id, sistema, usuario, contrasena, categoria, estado, origen) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([
                    normaliza($f[$idx['NOMBRE']] ?? null) ?: $usuario,
                    $sedeId > 0 ? $sedeId : null, $sistema, $usuario,
                    normaliza($f[$idx['CONTRASEÑA']] ?? null),
                    normaliza($f[$idx['AREA_CARGO']] ?? null) ?: 'SIESA',
                    normaliza($f[$idx['ESTADO']] ?? null) ?: 'ACTIVO',
                    normaliza($f[$idx['FUENTE']] ?? null) ?: 'MASTER_TI_2026',
                ]);
        }
    }
}
$zipInv->close();

// ================= 2) MAESTRO_TI_GRUPO10Z.xlsx: WIFI y CORREOS =================
if (file_exists($archivoMaestroTI)) {
    [$zipTI, $sharedTI, $hojasTI] = xlsx_abrir($archivoMaestroTI);

    if (!empty($hojasTI['WIFI'])) {
        $filas = xlsx_leer_hoja($zipTI, $hojasTI['WIFI'], $sharedTI);
        $encabezado = array_map('trim', $filas[0] ?? []);
        $idx = array_flip($encabezado);
        for ($i = 1; $i < count($filas); $i++) {
            $f = $filas[$i];
            $red = normaliza($f[$idx['RED']] ?? null);
            if (!$red) { $stats['credenciales_omitidas']++; continue; }
            $sedeNombre = normaliza($f[$idx['TIENDA_SEDE']] ?? null);
            $sedeId = $sedeNombre ? sede_id_o_crear($pdo, $sedeNombre, $dryRun, $cacheSedes, $stats) : null;
            $stats['credenciales_importadas']++;
            if ($dryRun) continue;
            $existe = $pdo->prepare("SELECT id FROM credenciales WHERE sistema = 'WIFI' AND usuario = ?");
            $existe->execute([$red]);
            if (!$existe->fetchColumn()) {
                $pdo->prepare("INSERT INTO credenciales (nombre, sede_id, sistema, usuario, contrasena, categoria, estado, origen) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$red, $sedeId > 0 ? $sedeId : null, 'WIFI', $red, normaliza($f[$idx['CONTRASEÑA']] ?? null),
                        normaliza($f[$idx['TIPO']] ?? null) ?: 'WIFI', 'ACTIVO', 'MASTER_TI_2026']);
            }
        }
    }

    if (!empty($hojasTI['CORREOS'])) {
        $filas = xlsx_leer_hoja($zipTI, $hojasTI['CORREOS'], $sharedTI);
        $encabezado = array_map('trim', $filas[0] ?? []);
        $idx = array_flip($encabezado);
        for ($i = 1; $i < count($filas); $i++) {
            $f = $filas[$i];
            $correo = normaliza($f[$idx['Dirección de Correo'] ?? -1] ?? null);
            if (!$correo) { $stats['credenciales_omitidas']++; continue; }
            $stats['credenciales_importadas']++;
            if ($dryRun) continue;
            $existe = $pdo->prepare("SELECT id FROM credenciales WHERE sistema = 'Correo O365' AND usuario = ?");
            $existe->execute([$correo]);
            if (!$existe->fetchColumn()) {
                $pdo->prepare("INSERT INTO credenciales (nombre, sistema, usuario, contrasena, categoria, estado, origen) VALUES (?,?,?,?,?,?,?)")
                    ->execute([
                        normaliza($f[$idx['Usuario'] ?? -1] ?? null) ?: $correo, 'Correo O365', $correo,
                        normaliza($f[$idx['Contraseña'] ?? -1] ?? null), 'CORREO', 'ACTIVO', 'MASTER_TI_2026',
                    ]);
            }
        }
    }
    $zipTI->close();
}

// ---------------- Resumen ----------------
echo ($dryRun ? "=== MODO PRUEBA (dry-run), no se escribió nada ===\n" : "=== IMPORTACIÓN REAL COMPLETADA ===\n");
foreach ($stats as $k => $v) { echo str_pad($k, 25) . ": {$v}\n"; }
