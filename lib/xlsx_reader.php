<?php
/**
 * Lector de XLSX sin dependencias externas (sin Composer / PhpSpreadsheet).
 * Requiere las extensiones estándar de PHP: zip y simplexml/dom.
 *
 * Uso:
 *   $sheets = xlsx_read_all_sheets('/ruta/archivo.xlsx');
 *   $sheets['NOMBRE_HOJA'] es un array de filas; cada fila es un array indexado
 *   desde 0 por posición de columna (A=0, B=1, ...), con huecos en null.
 */

function xlsx_col_to_index($colLetters) {
    $col = 0;
    $len = strlen($colLetters);
    for ($i = 0; $i < $len; $i++) {
        $col = $col * 26 + (ord($colLetters[$i]) - 64);
    }
    return $col - 1;
}

function xlsx_read_all_sheets($path) {
    if (!file_exists($path)) {
        throw new Exception("Archivo no encontrado: {$path}");
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception("No se pudo abrir el XLSX: {$path}");
    }

    // 1. Shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = simplexml_load_string($ssXml);
        foreach ($ss->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string) $si->t;
            } else {
                // texto con formato mixto (varios <r><t>)
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // 2. Mapeo nombre de hoja -> r:id
    $wbXml = $zip->getFromName('xl/workbook.xml');
    $wb = simplexml_load_string($wbXml);
    $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $sheetNameToRid = [];
    foreach ($wb->sheets->sheet as $sheet) {
        $attrs = $sheet->attributes('r', true);
        $rid = (string) $attrs['id'];
        $name = (string) $sheet->attributes()['name'];
        $sheetNameToRid[$name] = $rid;
    }

    // 3. Mapeo r:id -> archivo físico dentro del zip
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $rels = simplexml_load_string($relsXml);
    $ridToTarget = [];
    foreach ($rels->Relationship as $rel) {
        $ridToTarget[(string) $rel['Id']] = (string) $rel['Target'];
    }

    $result = [];
    foreach ($sheetNameToRid as $name => $rid) {
        if (!isset($ridToTarget[$rid])) continue;
        $target = $ridToTarget[$rid];
        $target = ltrim($target, '/');
        if (strpos($target, 'worksheets/') === 0) {
            $target = 'xl/' . $target;
        } elseif (strpos($target, 'xl/') !== 0) {
            $target = 'xl/' . $target;
        }
        $sheetXml = $zip->getFromName($target);
        if ($sheetXml === false) continue;

        $rowsOut = [];
        $sx = simplexml_load_string($sheetXml);
        if (!isset($sx->sheetData->row)) {
            $result[$name] = [];
            continue;
        }
        foreach ($sx->sheetData->row as $row) {
            $rowArr = [];
            $maxIdx = -1;
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r']; // e.g. "C5"
                preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
                $colIdx = $m ? xlsx_col_to_index($m[1]) : count($cells);
                $type = (string) $c['t'];
                $value = null;
                if (isset($c->v)) {
                    $raw = (string) $c->v;
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $raw] ?? null;
                    } elseif ($type === 'str' || $type === 'inlineStr') {
                        $value = $raw;
                    } else {
                        $value = is_numeric($raw) ? $raw + 0 : $raw;
                    }
                } elseif (isset($c->is->t)) {
                    $value = (string) $c->is->t;
                }
                $cells[$colIdx] = $value;
                if ($colIdx > $maxIdx) $maxIdx = $colIdx;
            }
            for ($i = 0; $i <= $maxIdx; $i++) {
                $rowArr[$i] = $cells[$i] ?? null;
            }
            $rowsOut[] = $rowArr;
        }
        $result[$name] = $rowsOut;
    }

    $zip->close();
    return $result;
}

/**
 * Convierte filas crudas (array indexado) en filas asociativas usando la
 * primera fila como encabezado. $skipRows permite saltar filas de título antes
 * del encabezado real (por defecto 0).
 */
function xlsx_rows_to_assoc($rows, $skipRows = 0) {
    if (count($rows) <= $skipRows) return [];
    $header = $rows[$skipRows];
    $header = array_map(function ($h) {
        return $h !== null ? trim((string) $h) : '';
    }, $header);

    $out = [];
    for ($i = $skipRows + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $assoc = [];
        $hasData = false;
        foreach ($header as $idx => $colName) {
            if ($colName === '') continue;
            $val = $row[$idx] ?? null;
            if ($val !== null && $val !== '') $hasData = true;
            $assoc[$colName] = $val;
        }
        if ($hasData) $out[] = $assoc;
    }
    return $out;
}
