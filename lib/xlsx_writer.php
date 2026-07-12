<?php
/**
 * Escritor de XLSX sin dependencias externas (solo ZipArchive, estándar en PHP).
 * Uso:
 *   xlsx_write(['HOJA1' => [ ['Col1','Col2'], ['dato1','dato2'] ]], '/ruta/salida.xlsx');
 */

function xlsx_col_letter_calc($zeroBasedIndex) {
    $letter = '';
    $n = $zeroBasedIndex;
    while (true) {
        $rem = $n % 26;
        $letter = chr(65 + $rem) . $letter;
        $n = intdiv($n, 26) - 1;
        if ($n < 0) break;
    }
    return $letter;
}

function xlsx_escape($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsx_write(array $sheets, string $outPath) {
    if (file_exists($outPath)) {
        @unlink($outPath);
    }
    $zip = new ZipArchive();
    if ($zip->open($outPath, ZipArchive::CREATE) !== true) {
        throw new Exception("No se pudo crear el archivo XLSX: {$outPath}");
    }

    $sheetNames = array_keys($sheets);
    $sheetCount = count($sheetNames);

    // [Content_Types].xml
    $overrides = '';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
    }
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        $overrides .
        '</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    // _rels/.rels
    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>';
    $zip->addFromString('_rels/.rels', $rootRels);

    // xl/workbook.xml
    $sheetsXml = '';
    $i = 1;
    foreach ($sheetNames as $name) {
        $safe = xlsx_escape(substr($name, 0, 31));
        $sheetsXml .= "<sheet name=\"{$safe}\" sheetId=\"{$i}\" r:id=\"rId{$i}\"/>";
        $i++;
    }
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        "<sheets>{$sheetsXml}</sheets></workbook>";
    $zip->addFromString('xl/workbook.xml', $workbookXml);

    // xl/_rels/workbook.xml.rels
    $wbRels = '';
    $i = 1;
    foreach ($sheetNames as $name) {
        $wbRels .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
        $i++;
    }
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');

    // xl/worksheets/sheetN.xml
    $i = 1;
    foreach ($sheetNames as $name) {
        $rowsXml = '';
        $rowIdx = 1;
        foreach ($sheets[$name] as $row) {
            $cellsXml = '';
            $colIdx = 0;
            foreach ($row as $val) {
                $ref = xlsx_col_letter_calc($colIdx) . $rowIdx;
                if ($val === null || $val === '') {
                    // celda vacía, se omite
                } elseif (is_numeric($val) && !preg_match('/^0[0-9]/', (string) $val)) {
                    $cellsXml .= "<c r=\"{$ref}\"><v>" . xlsx_escape($val) . "</v></c>";
                } else {
                    $cellsXml .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . xlsx_escape($val) . "</t></is></c>";
                }
                $colIdx++;
            }
            $rowsXml .= "<row r=\"{$rowIdx}\">{$cellsXml}</row>";
            $rowIdx++;
        }
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            "<sheetData>{$rowsXml}</sheetData></worksheet>";
        $zip->addFromString("xl/worksheets/sheet{$i}.xml", $sheetXml);
        $i++;
    }

    $zip->close();
    return $outPath;
}
