<?php
/**
 * Logica de importacion reutilizable, compartida entre la subida manual
 * (modules/importar.php) y el vigilante de carpeta automatico
 * (api_importador_carpeta.php). Mismo motor, dos formas de activarlo.
 */
require_once __DIR__ . '/xlsx_reader.php';

function iu_reg_pendiente(PDO $pdo, $archivo, $hoja, $fila, $motivo, $datos) {
    $pdo->prepare("INSERT INTO importaciones_log (archivo, hoja, fila, motivo, datos) VALUES (?,?,?,?,?)")
        ->execute([$archivo, $hoja, $fila, $motivo, json_encode($datos, JSON_UNESCAPED_UNICODE)]);
}

/** Adivina a que modulo pertenece una hoja de Excel por su nombre. */
function iu_detectar_destino(string $nombreHoja): ?string {
    $h = mb_strtoupper($nombreHoja);
    if (str_contains($h, 'INVENT') || str_contains($h, 'EQUIPO') || str_contains($h, 'ACTIVO')) return 'inventario';
    if (str_contains($h, 'CRED') || str_contains($h, 'USUARI') || str_contains($h, 'WIFI') || str_contains($h, 'CORREO') || str_contains($h, 'CLAVE') || str_contains($h, 'CONTRASE')) return 'credenciales';
    if (str_contains($h, 'EMPLEA') || str_contains($h, 'PERSONAL') || str_contains($h, 'NOMINA')) return 'empleados';
    return null;
}

/**
 * Importa una hoja de Excel ya leida (array de filas crudas de xlsx_reader)
 * hacia el destino indicado ('inventario' | 'credenciales' | 'empleados'),
 * usando el mismo emparejamiento flexible de columnas (varios nombres
 * posibles por campo) que ya usaba el importador manual.
 */
function iu_importar_hoja(PDO $pdo, array $hojaCruda, string $nombreArchivo, string $hoja, string $destino, array &$stats): void {
    $filas = xlsx_rows_to_assoc($hojaCruda);
    $get = function ($f, $keys) {
        foreach ($keys as $k) if (!empty($f[$k])) return limpio($f[$k]);
        return null;
    };

    if ($destino === 'inventario') {
        iu_importar_inventario($pdo, $filas, $nombreArchivo, $hoja, $stats, $get);
    } elseif ($destino === 'credenciales') {
        $num = 1;
        foreach ($filas as $f) {
            $num++;
            $sistema = $get($f, ['SISTEMA']) ?: 'OTRO';
            $usuario = $get($f, ['USUARIO', 'Dirección de Correo', 'RED', 'Correo', 'Usuario']);
            if (!$usuario) { iu_reg_pendiente($pdo, $nombreArchivo, $hoja, $num, 'Falta USUARIO', $f); $stats['omitidos']++; continue; }
            $sede = $get($f, ['SEDE', 'TIENDA', 'TIENDA_SEDE']);
            $sedeId = $sede ? sede_id_por_nombre($pdo, $sede, false) : null;
            $datos = [
                'nombre' => $get($f, ['NOMBRE', 'Usuario', 'RESPONSABLE']), 'sede_id' => $sedeId, 'sistema' => $sistema,
                'usuario' => $usuario, 'contrasena' => $get($f, ['CONTRASEÑA', 'Contraseña']),
                'categoria' => $get($f, ['AREA_CARGO', 'Departamento', 'TIPO']), 'estado' => 'ACTIVO',
                'origen' => "Carpeta vigilada - {$nombreArchivo}/{$hoja}",
            ];
            $datos['contrasena'] = secreto_cifrar($datos['contrasena']);
            $stmt = $pdo->prepare("SELECT id FROM credenciales WHERE sistema=? AND usuario=? AND (sede_id=? OR (sede_id IS NULL AND ? IS NULL))");
            $stmt->execute([$sistema, $usuario, $sedeId, $sedeId]);
            $ex = $stmt->fetchColumn();
            if ($ex) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
                $pdo->prepare("UPDATE credenciales SET {$set} WHERE id = :id")->execute($datos + ['id' => $ex]);
                $stats['actualizados']++;
            } else {
                $cols = implode(', ', array_keys($datos));
                $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
                $pdo->prepare("INSERT INTO credenciales ({$cols}) VALUES ({$ph})")->execute($datos);
                $stats['importados']++;
            }
        }
    } elseif ($destino === 'empleados') {
        $num = 1;
        foreach ($filas as $f) {
            $num++;
            $nombres = $get($f, ['NOMBRES', 'NOMBRE', 'NOMBRES_Y_APELLIDOS']);
            if (!$nombres) { iu_reg_pendiente($pdo, $nombreArchivo, $hoja, $num, 'Falta NOMBRE', $f); $stats['omitidos']++; continue; }
            $documento = $get($f, ['DOCUMENTO', 'CC', 'N°_CEDULA']);
            $sede = $get($f, ['SEDE', 'TIENDA']);
            $sedeId = $sede ? sede_id_por_nombre($pdo, $sede) : null;
            $existente = $documento ? $pdo->query("SELECT id FROM empleados WHERE documento = " . $pdo->quote($documento))->fetchColumn() : false;
            if ($existente) {
                $stats['omitidos']++; // ya existe: la carpeta vigilada no pisa datos de RRHH ya cargados, solo agrega nuevos
                continue;
            }
            $pdo->prepare("INSERT INTO empleados (documento, nombres, cargo, area, sede_id, email, estado) VALUES (?,?,?,?,?,?,?)")
                ->execute([$documento, $nombres, $get($f, ['CARGO']), $get($f, ['AREA', 'DEPENDENCIA']), $sedeId,
                    $get($f, ['EMAIL', 'CORREO ELECTRONICO', 'CORREO_ELECTRONICO']), 'ACTIVO']);
            $stats['importados']++;
        }
    }
}

function iu_importar_inventario(PDO $pdo, array $filas, string $nombreArchivo, string $hoja, array &$stats, callable $get): void {
    $num = 1;
    foreach ($filas as $f) {
        $num++;
        $serial = $get($f, ['SERIAL']);
        if (!$serial) { iu_reg_pendiente($pdo, $nombreArchivo, $hoja, $num, 'Falta SERIAL', $f); $stats['omitidos']++; continue; }
        $sede = $get($f, ['SEDE']);
        $sedeId = $sede ? sede_id_por_nombre($pdo, $sede) : null;
        $datos = [
            'serial' => $serial, 'placa' => $get($f, ['PLACA']), 'asignado_a' => $get($f, ['NOMBRE_USUARIO', 'ASIGNADO_A']),
            'sede_id' => $sedeId, 'area' => $get($f, ['AREA']), 'cargo' => $get($f, ['CARGO']),
            'tipo' => $get($f, ['TIPO']), 'marca' => $get($f, ['MARCA']), 'modelo' => $get($f, ['REFERENCIA', 'MODELO']),
            'sistema_operativo' => $get($f, ['SO', 'SISTEMA_OPERATIVO']), 'procesador' => $get($f, ['PROCESADOR']),
            'memoria' => $get($f, ['MEMORIA']), 'almacenamiento' => $get($f, ['ALMACENAMIENTO']),
            'estado' => $get($f, ['ESTADO']) ?: 'ACTIVO', 'fuente' => "Carpeta vigilada - {$nombreArchivo}",
        ];
        $stmt = $pdo->prepare("SELECT id FROM inventario WHERE serial = ?");
        $stmt->execute([$serial]);
        $ex = $stmt->fetchColumn();
        if ($ex) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($datos)));
            $pdo->prepare("UPDATE inventario SET {$set} WHERE id = :id")->execute($datos + ['id' => $ex]);
            $stats['actualizados']++;
        } else {
            $cols = implode(', ', array_keys($datos));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($datos)));
            $pdo->prepare("INSERT INTO inventario ({$cols}) VALUES ({$ph})")->execute($datos);
            $stats['importados']++;
        }
    }
}

/**
 * Recorre la carpeta configurada, y para cada archivo .xlsx nuevo o
 * modificado desde la ultima vez (por mtime), importa cada hoja cuyo
 * destino se pueda adivinar por el nombre. Idempotente: un archivo sin
 * cambios no se vuelve a procesar.
 */
function iu_sincronizar_carpeta(PDO $pdo, string $ruta): array {
    $resumen = ['archivos_revisados' => 0, 'archivos_procesados' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
    if (!$ruta || !is_dir($ruta)) {
        $resumen['errores'][] = "La carpeta \"{$ruta}\" no existe o no es accesible desde este servidor.";
        return $resumen;
    }

    $archivos = glob(rtrim($ruta, '\\/') . DIRECTORY_SEPARATOR . '*.xlsx');
    foreach ($archivos as $rutaArchivo) {
        $resumen['archivos_revisados']++;
        $nombreArchivo = basename($rutaArchivo);
        $mtime = filemtime($rutaArchivo);
        $tamano = filesize($rutaArchivo);

        try {
            $sheets = xlsx_read_all_sheets($rutaArchivo);
        } catch (Exception $ex) {
            $resumen['errores'][] = "{$nombreArchivo}: " . $ex->getMessage();
            continue;
        }

        foreach ($sheets as $hoja => $data) {
            $destino = iu_detectar_destino($hoja);
            if (!$destino) continue;

            $yaProcesado = $pdo->prepare("SELECT id FROM importador_carpeta_log WHERE archivo = ? AND hoja = ? AND mtime = ?");
            $yaProcesado->execute([$nombreArchivo, $hoja, $mtime]);
            if ($yaProcesado->fetchColumn()) continue; // sin cambios desde la ultima sincronizacion

            $stats = ['importados' => 0, 'actualizados' => 0, 'omitidos' => 0];
            $errorHoja = null;
            try {
                iu_importar_hoja($pdo, $data, $nombreArchivo, $hoja, $destino, $stats);
            } catch (Exception $ex) {
                $errorHoja = $ex->getMessage();
            }

            $pdo->prepare("INSERT INTO importador_carpeta_log (archivo, hoja, mtime, tamano, destino, importados, actualizados, omitidos, error)
                VALUES (?,?,?,?,?,?,?,?,?)
                ON CONFLICT(archivo, hoja, mtime) DO UPDATE SET importados=excluded.importados, actualizados=excluded.actualizados, omitidos=excluded.omitidos, error=excluded.error, procesado_en=CURRENT_TIMESTAMP")
                ->execute([$nombreArchivo, $hoja, $mtime, $tamano, $destino, $stats['importados'], $stats['actualizados'], $stats['omitidos'], $errorHoja]);

            $resumen['archivos_procesados']++;
            $resumen['importados'] += $stats['importados'];
            $resumen['actualizados'] += $stats['actualizados'];
            $resumen['omitidos'] += $stats['omitidos'];
            if ($errorHoja) $resumen['errores'][] = "{$nombreArchivo}/{$hoja}: {$errorHoja}";
        }
    }
    return $resumen;
}
