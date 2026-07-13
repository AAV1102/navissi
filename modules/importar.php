<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/layout.php';
requiere_roles(['ADMIN', 'TI'], '../');
require_once __DIR__ . '/../lib/xlsx_reader.php';
$pdo = db();
$resultado = null;
$error = null;

function reg_pendiente(PDO $pdo, $archivo, $hoja, $fila, $motivo, $datos) {
    $stmt = $pdo->prepare("INSERT INTO importaciones_log (archivo, hoja, fila, motivo, datos) VALUES (?,?,?,?,?)");
    $stmt->execute([$archivo, $hoja, $fila, $motivo, json_encode($datos, JSON_UNESCAPED_UNICODE)]);
}

function importar_inventario_general(PDO $pdo, $sheets, $archivo, &$stats) {
    if (!isset($sheets['INVENTARIO_GENERAL'])) return;
    $filas = xlsx_rows_to_assoc($sheets['INVENTARIO_GENERAL']);
    $num = 1;
    foreach ($filas as $f) {
        $num++;
        $serial = limpio($f['SERIAL'] ?? null);
        $sede = limpio($f['SEDE'] ?? null);
        if (!$serial) { reg_pendiente($pdo, $archivo, 'INVENTARIO_GENERAL', $num, 'Falta SERIAL', $f); $stats['omitidos']++; continue; }
        if (!$sede) { reg_pendiente($pdo, $archivo, 'INVENTARIO_GENERAL', $num, 'Falta SEDE', $f); $stats['omitidos']++; continue; }
        $sedeId = sede_id_por_nombre($pdo, $sede);
        $datos = [
            'serial' => $serial, 'placa' => limpio($f['PLACA'] ?? null),
            'asignado_a' => limpio($f['NOMBRE_USUARIO'] ?? null), 'sede_id' => $sedeId,
            'area' => limpio($f['AREA'] ?? null), 'cargo' => limpio($f['CARGO'] ?? null),
            'tipo' => limpio($f['TIPO'] ?? null), 'marca' => limpio($f['MARCA'] ?? null),
            'modelo' => limpio($f['REFERENCIA'] ?? null), 'sistema_operativo' => limpio($f['SO'] ?? null),
            'procesador' => limpio($f['PROCESADOR'] ?? null), 'memoria' => limpio($f['MEMORIA'] ?? null),
            'almacenamiento' => limpio($f['ALMACENAMIENTO'] ?? null),
            'estado' => limpio($f['ESTADO'] ?? null) ?: 'ACTIVO', 'fuente' => $archivo,
        ];
        $stmt = $pdo->prepare("SELECT id FROM inventario WHERE serial = ?");
        $stmt->execute([$serial]);
        $ex = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ex) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
            $stmt = $pdo->prepare("UPDATE inventario SET {$set} WHERE id = :id");
            $datos['id'] = $ex['id'];
            $stmt->execute($datos);
            $stats['actualizados']++;
        } else {
            $cols = implode(', ', array_keys($datos));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
            $pdo->prepare("INSERT INTO inventario ({$cols}) VALUES ({$ph})")->execute($datos);
            $stats['importados']++;
        }
    }
}

function importar_credenciales_generico(PDO $pdo, $filas, $archivo, $hoja, callable $mapear, &$stats) {
    $num = 1;
    foreach ($filas as $f) {
        $num++;
        $d = $mapear($f);
        if (!$d['sistema'] || !$d['usuario']) { reg_pendiente($pdo, $archivo, $hoja, $num, 'Falta SISTEMA o USUARIO', $f); $stats['omitidos']++; continue; }
        $sedeId = $d['sede'] ? sede_id_por_nombre($pdo, $d['sede'], false) : null;
        $datos = ['nombre'=>$d['nombre'],'sede_id'=>$sedeId,'sistema'=>$d['sistema'],'usuario'=>$d['usuario'],
            'contrasena'=>secreto_cifrar($d['contrasena']),'categoria'=>$d['categoria'],'estado'=>'ACTIVO','origen'=>"{$archivo} - {$hoja}"];
        $stmt = $pdo->prepare("SELECT id FROM credenciales WHERE sistema=? AND usuario=? AND (sede_id=? OR (sede_id IS NULL AND ? IS NULL))");
        $stmt->execute([$d['sistema'], $d['usuario'], $sedeId, $sedeId]);
        $ex = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ex) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
            $stmt = $pdo->prepare("UPDATE credenciales SET {$set} WHERE id = :id");
            $datos['id'] = $ex['id'];
            $stmt->execute($datos);
            $stats['actualizados']++;
        } else {
            $cols = implode(', ', array_keys($datos));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
            $pdo->prepare("INSERT INTO credenciales ({$cols}) VALUES ({$ph})")->execute($datos);
            $stats['importados']++;
        }
    }
}

function importar_licencias(PDO $pdo, $sheets, $archivo, &$stats) {
    if (!isset($sheets['LICENCIAS_O365'])) return;
    $filas = xlsx_rows_to_assoc($sheets['LICENCIAS_O365']);
    foreach ($filas as $f) {
        $prov = limpio($f['PROVEEDOR'] ?? null) ?: limpio($f['LICENCIAS'] ?? null);
        $cant = $f['CANT'] ?? null;
        $valorMes = (float) ($f['V_MES'] ?? 0);
        // La hoja origen trae celdas sueltas de una hoja de cálculo (TRM, notas, etc.)
        // mezcladas con la tabla real. Solo se importa una fila si tiene proveedor,
        // cantidad entera razonable y un valor mensual mayor a cero.
        if (!$prov || !is_numeric($cant) || $cant < 1 || $cant > 500 || $valorMes <= 0) {
            reg_pendiente($pdo, $archivo, 'LICENCIAS_O365', 0, 'Fila descartada por no ser una licencia válida (dato suelto de la hoja origen)', $f);
            continue;
        }
        $datos = ['proveedor'=>$prov,'tipo'=>'Office365','cantidad'=>(int)$cant,
            'valor_mes'=>$valorMes,'valor_anual'=>(float)($f['V_ANUAL'] ?? 0),'observaciones'=>$archivo];
        $stmt = $pdo->prepare("SELECT id FROM licencias WHERE proveedor = ?");
        $stmt->execute([$prov]);
        $ex = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ex) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
            $stmt = $pdo->prepare("UPDATE licencias SET {$set} WHERE id = :id");
            $datos['id'] = $ex['id'];
            $stmt->execute($datos);
            $stats['actualizados']++;
        } else {
            $cols = implode(', ', array_keys($datos));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
            $pdo->prepare("INSERT INTO licencias ({$cols}) VALUES ({$ph})")->execute($datos);
            $stats['importados']++;
        }
    }
}

function importar_datos_pdv(PDO $pdo, $archivo, &$stats) {
    if (!file_exists($archivo)) return;
    $sheets = xlsx_read_all_sheets($archivo);
    $nombreArchivo = basename($archivo);

    if (isset($sheets['CONTRASEÑAS CORREOS'])) {
        importar_credenciales_generico($pdo, xlsx_rows_to_assoc($sheets['CONTRASEÑAS CORREOS']), $nombreArchivo, 'CONTRASEÑAS CORREOS',
            fn($f) => ['nombre'=>null,'sede'=>null,'sistema'=>'CORREO','usuario'=>limpio($f['Correo'] ?? null),
                'contrasena'=>limpio($f['Contraseña'] ?? null),'categoria'=>limpio($f['Departamento'] ?? null)], $stats);
    }

    if (isset($sheets['WI FI '])) {
        importar_credenciales_generico($pdo, xlsx_rows_to_assoc($sheets['WI FI ']), $nombreArchivo, 'WI FI',
            fn($f) => ['nombre'=>limpio($f['Nombre de RED'] ?? null),'sede'=>null,'sistema'=>'WIFI',
                'usuario'=>limpio($f['Nombre de RED'] ?? null),'contrasena'=>limpio($f['Contraseña'] ?? null),'categoria'=>null], $stats);
    }

    if (isset($sheets['SPOTIFY PDV'])) {
        importar_credenciales_generico($pdo, xlsx_rows_to_assoc($sheets['SPOTIFY PDV']), $nombreArchivo, 'SPOTIFY PDV',
            function ($f) {
                $get = function ($keys) use ($f) { foreach ($keys as $k) if (!empty($f[$k])) return limpio($f[$k]); return null; };
                return ['nombre'=>null,'sede'=>$get(['SEDE', 'SEDE ']),'sistema'=>'SPOTIFY',
                    'usuario'=>$get(['coCORREO ELECTRONICO', 'CORREO ELECTRONICO', 'coCORREO ELECTRONICO ']),'contrasena'=>$get(['CONTRASEÑA', 'CONTRASEÑA ']),'categoria'=>null];
            }, $stats);
    }

    // SERVIDOR: formato de 3 filas sueltas (nombre / usuario-id / contraseña), no es una tabla.
    if (isset($sheets['SERVIDOR'])) {
        $filas = $sheets['SERVIDOR'];
        $nombre = $filas[0][0] ?? null;
        $usuario = $filas[1][0] ?? null;
        $clave = $filas[2][0] ?? null;
        if ($nombre && $usuario && $clave) {
            $datos = ['nombre'=>limpio($nombre),'sede_id'=>null,'sistema'=>'SERVIDOR','usuario'=>limpio($usuario),
                'contrasena'=>secreto_cifrar(limpio($clave)),'categoria'=>'Acceso remoto','estado'=>'ACTIVO','origen'=>"{$nombreArchivo} - SERVIDOR"];
            $stmt = $pdo->prepare("SELECT id FROM credenciales WHERE sistema='SERVIDOR' AND usuario=? AND sede_id IS NULL");
            $stmt->execute([$datos['usuario']]);
            $ex = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ex) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE credenciales SET {$set} WHERE id = :id");
                $datos['id'] = $ex['id']; $stmt->execute($datos); $stats['actualizados']++;
            } else {
                $cols = implode(', ', array_keys($datos)); $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO credenciales ({$cols}) VALUES ({$ph})")->execute($datos); $stats['importados']++;
            }
        }
    }

    // DVR: usuario/contraseña de la central de cámaras (fila "usuario:" / fila "pass:" en columna B).
    if (isset($sheets['CONTRASEÑAS DVR'])) {
        $filas = $sheets['CONTRASEÑAS DVR'];
        $usuario = null; $clave = null;
        foreach ($filas as $row) {
            $etiqueta = trim((string) ($row[0] ?? ''));
            if (stripos($etiqueta, 'usuario') === 0) $usuario = limpio($row[1] ?? null);
            if (stripos($etiqueta, 'pass') === 0) $clave = limpio($row[1] ?? null);
        }
        if ($usuario && $clave) {
            $datos = ['nombre'=>'DVR Cámaras Sede Principal','sede_id'=>sede_id_por_nombre($pdo,'PRINCIPAL - MEDELLIN',false),
                'sistema'=>'DVR','usuario'=>$usuario,'contrasena'=>secreto_cifrar($clave),'categoria'=>'Cámaras','estado'=>'ACTIVO','origen'=>"{$nombreArchivo} - CONTRASEÑAS DVR"];
            $stmt = $pdo->prepare("SELECT id FROM credenciales WHERE sistema='DVR' AND usuario=?");
            $stmt->execute([$usuario]);
            $ex = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ex) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $stmt = $pdo->prepare("UPDATE credenciales SET {$set} WHERE id = :id");
                $datos['id'] = $ex['id']; $stmt->execute($datos); $stats['actualizados']++;
            } else {
                $cols = implode(', ', array_keys($datos)); $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO credenciales ({$cols}) VALUES ({$ph})")->execute($datos); $stats['importados']++;
            }
        }
    }
}

function importar_contactos_tiendas(PDO $pdo, $archivo, &$stats) {
    if (!file_exists($archivo)) return;
    $sheets = xlsx_read_all_sheets($archivo);
    $sheet = reset($sheets);
    $nombreArchivo = basename($archivo);

    // Alias explícitos: el nombre de tienda en este archivo no siempre coincide
    // textualmente con el nombre guardado en la tabla sedes.
    $alias = [
        'NUESTRO' => 'Nuestro Bogotá', 'BOGOTA' => 'Santa Fe Bogotá',
        'UNICENTRO' => 'Unicentro Cali', 'CHIPICHAPE' => 'Chipichape Cali',
        'BARRANQUILLA' => 'Viva Barranquilla', 'CARTAGENA' => 'Cartagena Caribe Plaza',
        'FABRICATO' => 'Fabricato', 'MOLINOS' => 'Molinos', 'PREMIUM' => 'Premium Plaza',
        'VIVA ENVIGADO' => 'Viva Envigado', 'SANTA FE DE MEDELLIN' => 'Santa Fe Medellín 1',
        'SAN NICOLAS' => 'San Nicolás 1', 'MAYORCA' => 'Mayorca', 'OUTLET' => 'Outlet',
        'WEB' => 'WEB', 'MONTERIA' => 'Montería', 'UNICO' => 'Único',
    ];

    $numFila = 0;
    foreach ($sheet as $row) {
        $numFila++;
        // Fila de datos real = la columna "#" trae un número (1,2,3...18). Cualquier otra
        // fila (título, encabezado, separador, o los bloques de "Coordinadoras"/"Calidad"
        // más abajo en la misma hoja) no tiene número ahí y se ignora automáticamente -
        // más confiable que contar filas, porque el lector de Excel omite filas vacías.
        $numero = $row[1] ?? null;
        if (!is_numeric($numero)) continue;
        $tienda = isset($row[2]) ? trim((string) $row[2]) : '';
        if ($tienda === '' || strtoupper($tienda) === 'N/A') continue;

        $clave = strtoupper($tienda);
        $nombreSede = $alias[$clave] ?? $tienda;

        // Buscar sede existente (exacta o por coincidencia parcial); si no existe, se crea -
        // esta lista viene directo de la coordinación de tiendas, es fuente autorizada.
        $stmt = $pdo->prepare("SELECT id FROM sedes WHERE nombre = ? COLLATE NOCASE");
        $stmt->execute([$nombreSede]);
        $sedeId = $stmt->fetchColumn();
        if (!$sedeId) {
            $stmt = $pdo->prepare("SELECT id FROM sedes WHERE nombre LIKE ? COLLATE NOCASE");
            $stmt->execute(['%' . $nombreSede . '%']);
            $sedeId = $stmt->fetchColumn();
        }
        if (!$sedeId) {
            $pdo->prepare("INSERT INTO sedes (nombre) VALUES (?)")->execute([$nombreSede]);
            $sedeId = $pdo->lastInsertId();
        }

        $admin = limpio($row[3] ?? null);
        $adminCel = limpio($row[4] ?? null);
        $segunda = limpio($row[5] ?? null);
        $segundaCel = limpio($row[6] ?? null);

        if (!$admin && !$segunda) {
            reg_pendiente($pdo, $nombreArchivo, 'ACTULIZACION DE DATOS', $numFila, 'Tienda sin administradora ni segunda encargada registrada', ['TIENDA' => $tienda]);
            $stats['omitidos']++;
            continue;
        }

        $pdo->prepare("UPDATE sedes SET administradora = COALESCE(?, administradora),
                administradora_celular = COALESCE(?, administradora_celular),
                segunda_encargada = COALESCE(?, segunda_encargada),
                segunda_encargada_celular = COALESCE(?, segunda_encargada_celular),
                actualizado_en = CURRENT_TIMESTAMP
            WHERE id = ?")
            ->execute([$admin, $adminCel, $segunda, $segundaCel, $sedeId]);
        $stats['actualizados']++;
    }

    // Coordinadoras por zona (filas ~29-31 del archivo): aproximación por zona geográfica
    // conocida, ya que el archivo agrupa por región y no por tienda individual.
    $zonaAntioquiaBogota = ['Nuestro Bogotá','Santa Fe Bogotá','Santa Fe Medellín 1','Santa Fe Medellín 2',
        'Viva Envigado','Premium Plaza','Molinos','Fabricato','San Nicolás 1','San Nicolás 2','Mayorca','PRINCIPAL - MEDELLIN'];
    $zonaCostaValle = ['Unicentro Cali','Chipichape Cali','Viva Barranquilla','Cartagena Caribe Plaza','Cartagena Carbe Plaza'];

    foreach ($sheet as $row) {
        if (($row[2] ?? null) === 'ANDREA GRISALES ' || trim((string)($row[2] ?? '')) === 'ANDREA GRISALES') {
            foreach ($zonaAntioquiaBogota as $nombreSede) {
                $pdo->prepare("UPDATE sedes SET coordinadora='Andrea Grisales', coordinadora_celular=?, zona=? WHERE nombre = ?")
                    ->execute([limpio($row[3] ?? null), 'MEDELLIN, BOGOTA Y RIO NEGRO', $nombreSede]);
            }
        }
        if (trim((string)($row[2] ?? '')) === 'MILENA TOBON') {
            foreach ($zonaCostaValle as $nombreSede) {
                $pdo->prepare("UPDATE sedes SET coordinadora='Milena Tobón', coordinadora_celular=?, zona=? WHERE nombre = ?")
                    ->execute([limpio($row[3] ?? null), 'COSTA Y VALLE', $nombreSede]);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'importar_maestros') {
    $stats = ['importados'=>0,'actualizados'=>0,'omitidos'=>0];
    $erroresFuentes = [];
    $rutaInv = MASTER_DIR . '\\INVENTARIO_MAESTRO_NAVISSI.xlsx';
    $rutaTI = MASTER_DIR . '\\MAESTRO_TI_GRUPO10Z.xlsx';

    // Cada fuente se importa en su propio try/catch: si una falla (p.ej. un
    // archivo bloqueado porque está abierto en Excel en ese momento), las
    // demás fuentes se siguen importando igual.
    try {
        if (file_exists($rutaInv)) {
            $sheets = xlsx_read_all_sheets($rutaInv);
            importar_inventario_general($pdo, $sheets, 'INVENTARIO_MAESTRO_NAVISSI.xlsx', $stats);
            if (isset($sheets['USUARIOS'])) {
                // Lista blanca de sedes reconocidas: una credencial de tienda solo se acepta
                // si la SEDE coincide con una sede real conocida. Esto evita que un dato mal
                // leído (p.ej. un nombre de persona en la columna equivocada) cree una "sede"
                // falsa - en vez de eso, la fila queda marcada como pendiente.
                $sedesConocidas = array_column($pdo->query("SELECT nombre FROM sedes")->fetchAll(PDO::FETCH_ASSOC), 'nombre');
                $sedesConocidasUpper = array_map('mb_strtoupper', $sedesConocidas);
                importar_credenciales_generico($pdo, xlsx_rows_to_assoc($sheets['USUARIOS']), 'INVENTARIO_MAESTRO_NAVISSI.xlsx', 'USUARIOS',
                    function ($f) use ($sedesConocidasUpper) {
                        $sede = limpio($f['SEDE'] ?? null);
                        if ($sede && $sede !== 'PRINCIPAL - MEDELLIN' && !in_array(mb_strtoupper($sede), $sedesConocidasUpper, true)) {
                            $sede = null; // sede no reconocida: no se crea una sede falsa
                        }
                        return ['nombre'=>limpio($f['NOMBRE'] ?? null),'sede'=>$sede,'sistema'=>limpio($f['SISTEMA'] ?? null),
                            'usuario'=>limpio($f['USUARIO'] ?? null),'contrasena'=>limpio($f['CONTRASEÑA'] ?? null),'categoria'=>limpio($f['AREA_CARGO'] ?? null)];
                    },
                    $stats);
            }
        } else {
            $erroresFuentes[] = "No se encontró {$rutaInv}";
        }
    } catch (Exception $ex) {
        $erroresFuentes[] = "INVENTARIO_MAESTRO_NAVISSI.xlsx: " . $ex->getMessage();
    }

    try {
        if (file_exists($rutaTI)) {
            $sheets = xlsx_read_all_sheets($rutaTI);
            if (isset($sheets['WIFI'])) {
                importar_credenciales_generico($pdo, xlsx_rows_to_assoc($sheets['WIFI']), 'MAESTRO_TI_GRUPO10Z.xlsx', 'WIFI',
                    fn($f) => ['nombre'=>limpio($f['RED'] ?? null),'sede'=>limpio($f['TIENDA_SEDE'] ?? null),'sistema'=>'WIFI',
                        'usuario'=>limpio($f['RED'] ?? null),'contrasena'=>limpio($f['CONTRASEÑA'] ?? null),'categoria'=>limpio($f['TIPO'] ?? null)],
                    $stats);
            }
            if (isset($sheets['CORREOS'])) {
                importar_credenciales_generico($pdo, xlsx_rows_to_assoc($sheets['CORREOS']), 'MAESTRO_TI_GRUPO10Z.xlsx', 'CORREOS',
                    fn($f) => ['nombre'=>limpio($f['Usuario'] ?? null),'sede'=>null,'sistema'=>'CORREO',
                        'usuario'=>limpio($f['Dirección de Correo'] ?? null),'contrasena'=>limpio($f['Contraseña'] ?? null),'categoria'=>limpio($f['Departamento'] ?? null)],
                    $stats);
            }
            importar_licencias($pdo, $sheets, 'MAESTRO_TI_GRUPO10Z.xlsx', $stats);
        }
    } catch (Exception $ex) {
        $erroresFuentes[] = "MAESTRO_TI_GRUPO10Z.xlsx: " . $ex->getMessage();
    }

    try {
        importar_datos_pdv($pdo, DATOS_PDV_PATH, $stats);
    } catch (Exception $ex) {
        $erroresFuentes[] = "DATOS PDV.xlsx: " . $ex->getMessage() . " (¿está abierto en Excel ahora mismo? Ciérralo y reintenta).";
    }

    try {
        importar_contactos_tiendas($pdo, CONTACTOS_TIENDAS_PATH, $stats);
    } catch (Exception $ex) {
        $erroresFuentes[] = "ACTUALIZAR DATOS CONTACTOS DE TIENDAS.xlsx: " . $ex->getMessage() . " (¿está abierto en Excel ahora mismo? Ciérralo y reintenta).";
    }

    $resultado = $stats;
    if ($erroresFuentes) {
        $error = implode(' | ', $erroresFuentes);
    }
}

// Carga manual de un archivo cualquiera
$hojasSubidas = null;
$archivoSubidoNombre = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir_listar') {
    if (!empty($_FILES['archivo']['tmp_name'])) {
        $tmp = __DIR__ . '/../data/tmp_' . uniqid() . '.xlsx';
        move_uploaded_file($_FILES['archivo']['tmp_name'], $tmp);
        try {
            $sheets = xlsx_read_all_sheets($tmp);
            $hojasSubidas = array_keys($sheets);
            $archivoSubidoNombre = $tmp;
        } catch (Exception $ex) {
            $error = 'No se pudo leer el archivo: ' . $ex->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'importar_hoja') {
    $tmp = $_POST['archivo_tmp'] ?? '';
    $hoja = $_POST['hoja'] ?? '';
    $destino = $_POST['destino'] ?? '';
    if (file_exists($tmp) && $hoja) {
        $sheets = xlsx_read_all_sheets($tmp);
        $filas = xlsx_rows_to_assoc($sheets[$hoja] ?? []);
        $stats = ['importados'=>0,'actualizados'=>0,'omitidos'=>0];
        $nombreArchivo = basename($tmp);
        if ($destino === 'inventario') {
            importar_inventario_general($pdo, ['INVENTARIO_GENERAL' => $sheets[$hoja]], $nombreArchivo, $stats);
        } elseif ($destino === 'credenciales') {
            importar_credenciales_generico($pdo, $filas, $nombreArchivo, $hoja, function ($f) {
                $get = function ($keys) use ($f) {
                    foreach ($keys as $k) if (!empty($f[$k])) return limpio($f[$k]);
                    return null;
                };
                return [
                    'nombre' => $get(['NOMBRE','Usuario','RESPONSABLE']),
                    'sede' => $get(['SEDE','TIENDA','TIENDA_SEDE']),
                    'sistema' => $get(['SISTEMA']) ?: 'OTRO',
                    'usuario' => $get(['USUARIO','Dirección de Correo','RED']),
                    'contrasena' => $get(['CONTRASEÑA','Contraseña']),
                    'categoria' => $get(['AREA_CARGO','Departamento','TIPO']),
                ];
            }, $stats);
        } elseif ($destino === 'empleados') {
            $num = 1;
            $get = function ($f, $keys) {
                foreach ($keys as $k) if (!empty($f[$k])) return limpio($f[$k]);
                return null;
            };
            foreach ($filas as $f) {
                $num++;
                $nombres = $get($f, ['NOMBRES', 'NOMBRE', 'NOMBRES_Y_APELLIDOS']);
                if (!$nombres) { reg_pendiente($pdo, $nombreArchivo, $hoja, $num, 'Falta NOMBRE', $f); $stats['omitidos']++; continue; }
                $sede = $get($f, ['SEDE', 'TIENDA']);
                $sedeId = $sede ? sede_id_por_nombre($pdo, $sede) : null;
                $pdo->prepare("INSERT INTO empleados (documento, nombres, cargo, area, sede_id, email, estado) VALUES (?,?,?,?,?,?,?)")
                    ->execute([
                        $get($f, ['DOCUMENTO', 'CC', 'N°_CEDULA']),
                        $nombres,
                        $get($f, ['CARGO']),
                        $get($f, ['AREA', 'DEPENDENCIA']),
                        $sedeId,
                        $get($f, ['EMAIL', 'CORREO ELECTRONICO', 'CORREO_ELECTRONICO']),
                        'ACTIVO',
                    ]);
                $stats['importados']++;
            }
        }
        @unlink($tmp);
        $resultado = $stats;
    }
}

$pendientes = $pdo->query("SELECT * FROM importaciones_log ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$totalPendientes = $pdo->query("SELECT COUNT(*) FROM importaciones_log")->fetchColumn();

// --- Importador automático de carpeta vigilada ---
require_once __DIR__ . '/../lib/importador_universal.php';
$configCarpetaPath = private_path('importador_config.json');
$configCarpeta = leer_config_json($configCarpetaPath) ?? [];
$resumenCarpeta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_carpeta') {
    $configCarpeta['ruta_carpeta'] = limpio($_POST['ruta_carpeta'] ?? null);
    guardar_config_json($configCarpetaPath, $configCarpeta);
    $msg = ['ok', 'Carpeta configurada.'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'sincronizar_carpeta') {
    $resumenCarpeta = iu_sincronizar_carpeta($pdo, $configCarpeta['ruta_carpeta'] ?? '');
}

$logCarpeta = $pdo->query("SELECT * FROM importador_carpeta_log ORDER BY procesado_en DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

layout_inicio('Importar', 'Importar', '../');
?>
<h1><?= icon('upload','icon-lg') ?> Importar datos</h1>
<p class="subtitle">Trae información desde los Excel maestros de TI 2026 o desde cualquier otro archivo .xlsx.</p>

<?php if ($error): ?><div class="msg-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($resultado): ?>
<div class="msg-ok">
    Importados: <?= $resultado['importados'] ?> — Actualizados: <?= $resultado['actualizados'] ?> — Omitidos por datos incompletos: <?= $resultado['omitidos'] ?>
    <?php if ($resultado['omitidos'] > 0): ?><br><span class="small">Las filas omitidas quedaron registradas abajo, en "Filas pendientes por completar".</span><?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h3>1. Importar los maestros de TI 2026 (un clic)</h3>
    <p class="small">Lee directamente: <code><?= e(MASTER_DIR) ?>\INVENTARIO_MAESTRO_NAVISSI.xlsx</code> y <code>MAESTRO_TI_GRUPO10Z.xlsx</code>.
    No se importa ninguna fila con datos incompletos (queda registrada abajo para que la completes en el Excel).</p>
    <form method="post">
        <input type="hidden" name="accion" value="importar_maestros">
        <button type="submit">Importar maestros de TI 2026</button>
    </form>
</div>

<div class="panel">
    <h3>2. Importar cualquier otro archivo Excel</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="subir_listar">
        <input type="file" name="archivo" accept=".xlsx" required>
        <button type="submit">Ver hojas del archivo</button>
    </form>

    <?php if ($hojasSubidas): ?>
    <form method="post" style="margin-top:14px;">
        <input type="hidden" name="accion" value="importar_hoja">
        <input type="hidden" name="archivo_tmp" value="<?= e($archivoSubidoNombre) ?>">
        <div class="grid-form">
            <div><label>Hoja a importar</label>
                <select name="hoja">
                    <?php foreach ($hojasSubidas as $h): ?><option><?= e($h) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Importar como</label>
                <select name="destino">
                    <option value="inventario">Inventario (equipos)</option>
                    <option value="credenciales">Credenciales (wifi/usuarios/correos)</option>
                    <option value="empleados">RRHH (empleados)</option>
                </select>
            </div>
        </div>
        <button type="submit">Importar esta hoja</button>
    </form>
    <?php endif; ?>
</div>

<div class="panel">
    <h3><?= icon('zap') ?> 3. Importador automático (carpeta vigilada)</h3>
    <p class="small">Deja los Excel en una carpeta fija y NAVISSI los trae solo — detecta el destino por el nombre de la hoja (una hoja llamada "INVENTARIO..." va a Inventario, "CREDENCIALES/USUARIOS/WIFI/CORREOS" a Credenciales, "EMPLEADOS/NOMINA" a RRHH). Solo procesa archivos nuevos o modificados, nunca reimporta lo mismo dos veces.</p>
    <form method="post">
        <input type="hidden" name="accion" value="guardar_carpeta">
        <div class="grid-form">
            <div style="grid-column:span 2;"><label>Ruta de la carpeta a vigilar</label>
                <input type="text" name="ruta_carpeta" value="<?= e($configCarpeta['ruta_carpeta'] ?? '') ?>" placeholder="C:\Mesa de Ayuda\NAVISSI-INVENTARIO\data\carpeta_importar">
            </div>
        </div>
        <button type="submit"><?= icon('check') ?> Guardar ruta</button>
    </form>
    <?php if (!empty($configCarpeta['ruta_carpeta'])): ?>
    <form method="post" style="margin-top:10px;">
        <input type="hidden" name="accion" value="sincronizar_carpeta">
        <button type="submit"><?= icon('zap') ?> Sincronizar ahora</button>
    </form>
    <?php if ($resumenCarpeta): ?>
    <div class="msg-<?= $resumenCarpeta['errores'] ? 'error' : 'ok' ?>" style="margin-top:10px;">
        Revisados: <?= $resumenCarpeta['archivos_revisados'] ?> archivo(s) · Procesados: <?= $resumenCarpeta['archivos_procesados'] ?> hoja(s) nueva(s)/modificada(s) ·
        Importados: <?= $resumenCarpeta['importados'] ?> · Actualizados: <?= $resumenCarpeta['actualizados'] ?> · Omitidos: <?= $resumenCarpeta['omitidos'] ?>
        <?php if ($resumenCarpeta['errores']): ?><br><?= implode('<br>', array_map('e', $resumenCarpeta['errores'])) ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <p class="small" style="margin-top:12px;">Para que se sincronice sola sin que nadie tenga que entrar aquí, agrega una Tarea Programada de Windows que corra cada cierto tiempo:</p>
    <pre style="background:#0f1720;color:#d7e3ef;padding:12px;border-radius:8px;overflow-x:auto;font-size:12px;">"C:\xampp\php\windowsXamppPhp\php.exe" "<?= e(__DIR__) ?>\..\api_importador_carpeta.php"</pre>
    <?php if ($logCarpeta): ?>
    <h3 style="font-size:13px;margin-top:16px;">Últimas sincronizaciones</h3>
    <table>
        <tr><th>Archivo</th><th>Hoja</th><th>Destino</th><th>Importados</th><th>Actualizados</th><th>Fecha</th></tr>
        <?php foreach ($logCarpeta as $l): ?>
        <tr>
            <td><?= e($l['archivo']) ?></td><td><?= e($l['hoja']) ?></td>
            <td><span class="badge badge-otro"><?= e($l['destino']) ?></span></td>
            <td><?= (int)$l['importados'] ?></td><td><?= (int)$l['actualizados'] ?></td>
            <td class="small"><?= e($l['procesado_en']) ?><?= $l['error'] ? ' — <span style="color:var(--err-fg)">' . e($l['error']) . '</span>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Filas pendientes por completar (<?= (int)$totalPendientes ?>)</h3>
    <?php if (!$pendientes): ?>
        <p class="small">No hay filas pendientes. Todo lo importado está completo.</p>
    <?php else: ?>
    <table>
        <tr><th>Archivo</th><th>Hoja</th><th>Fila</th><th>Motivo</th></tr>
        <?php foreach ($pendientes as $p): ?>
        <tr><td><?= e($p['archivo']) ?></td><td><?= e($p['hoja']) ?></td><td><?= e($p['fila']) ?></td><td><?= e($p['motivo']) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php layout_fin(); ?>
