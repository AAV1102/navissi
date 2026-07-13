<?php
/**
 * Generador de PDF minimalista, 100% PHP puro (sin Composer, sin librerias
 * externas) - escribe directamente la sintaxis PDF 1.4 usando las fuentes
 * estandar (Helvetica). Soporta texto e imagenes JPEG embebidas (para firmas
 * digitales) sin necesitar GD/Imagick: el JPEG ya viene codificado desde el
 * navegador (canvas.toDataURL('image/jpeg')), aqui solo se leen sus
 * dimensiones del propio header JPEG y se incrusta tal cual (DCTDecode).
 */
class SimplePDF {
    private array $lineas = [];
    private array $imagenes = []; // ['datos' => bytes, 'ancho_px' => int, 'alto_px' => int]
    private float $y = 780;
    private const ANCHO = 612; // Carta (8.5x11 in) en puntos
    private const ALTO = 792;
    private const MARGEN = 60;

    private function escape(string $t): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->aLatin1($t));
    }

    /** El PDF estandar (sin fuentes embebidas) solo soporta Latin-1, no UTF-8. */
    private function aLatin1(string $t): string {
        return @mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8') ?: $t;
    }

    public function titulo(string $texto, int $tamano = 18): void {
        $this->y -= $tamano + 6;
        $this->lineas[] = sprintf('BT /F2 %d Tf %d %.1f Td (%s) Tj ET', $tamano, self::MARGEN, $this->y, $this->escape($texto));
    }

    public function parrafo(string $texto, int $tamano = 11, int $interlineado = 16): void {
        $anchoUtil = self::ANCHO - 2 * self::MARGEN;
        $maxCaracteres = (int) ($anchoUtil / ($tamano * 0.52));
        foreach (explode("\n", $texto) as $bloque) {
            foreach ($this->envolver($bloque, $maxCaracteres) as $linea) {
                $this->y -= $interlineado;
                if ($this->y < self::MARGEN) { $this->y = self::ALTO - self::MARGEN; }
                $this->lineas[] = sprintf('BT /F1 %d Tf %d %.1f Td (%s) Tj ET', $tamano, self::MARGEN, $this->y, $this->escape($linea));
            }
        }
    }

    public function espacio(int $puntos = 14): void { $this->y -= $puntos; }

    public function linea(): void {
        $this->y -= 4;
        $this->lineas[] = sprintf('%d %.1f m %d %.1f l S', self::MARGEN, $this->y, self::ANCHO - self::MARGEN, $this->y);
    }

    /**
     * Incrusta una firma/imagen JPEG (acepta data URL "data:image/jpeg;base64,..."
     * o bytes crudos ya decodificados). $anchoPt es el ancho deseado en puntos
     * en la pagina; el alto se calcula guardando la proporción real de la imagen.
     */
    public function imagenJpeg(string $jpegDataUrlOBytes, float $anchoPt = 140): bool {
        $bytes = $jpegDataUrlOBytes;
        if (str_starts_with($jpegDataUrlOBytes, 'data:image')) {
            $partes = explode(',', $jpegDataUrlOBytes, 2);
            if (count($partes) < 2) return false;
            $bytes = base64_decode($partes[1]);
        }
        if (!$bytes || substr($bytes, 0, 2) !== "\xFF\xD8") return false; // no es JPEG valido

        $dim = $this->leerDimensionesJpeg($bytes);
        if (!$dim) return false;
        [$anchoPx, $altoPx] = $dim;

        $altoPt = $anchoPt * ($altoPx / $anchoPx);
        $this->y -= $altoPt + 6;
        if ($this->y < self::MARGEN) { $this->y = self::ALTO - self::MARGEN - $altoPt; }

        $idxImg = count($this->imagenes) + 1;
        $this->imagenes[] = ['datos' => $bytes, 'ancho_px' => $anchoPx, 'alto_px' => $altoPx];
        $this->lineas[] = sprintf('q %.1f 0 0 %.1f %d %.1f cm /Img%d Do Q', $anchoPt, $altoPt, self::MARGEN, $this->y, $idxImg);
        return true;
    }

    /** Lee ancho/alto en pixeles directo de los marcadores SOF del JPEG (sin GD). */
    private function leerDimensionesJpeg(string $bytes): ?array {
        $len = strlen($bytes);
        $i = 2; // saltar SOI (0xFFD8)
        while ($i < $len - 8) {
            if (ord($bytes[$i]) !== 0xFF) { $i++; continue; }
            $marcador = ord($bytes[$i + 1]);
            // Marcadores SOF (inicio de frame) que traen las dimensiones.
            if (($marcador >= 0xC0 && $marcador <= 0xC3) || ($marcador >= 0xC5 && $marcador <= 0xC7)
                || ($marcador >= 0xC9 && $marcador <= 0xCB) || ($marcador >= 0xCD && $marcador <= 0xCF)) {
                $alto = (ord($bytes[$i + 5]) << 8) | ord($bytes[$i + 6]);
                $ancho = (ord($bytes[$i + 7]) << 8) | ord($bytes[$i + 8]);
                return [$ancho, $alto];
            }
            $segmentoLen = (ord($bytes[$i + 2]) << 8) | ord($bytes[$i + 3]);
            $i += 2 + $segmentoLen;
        }
        return null;
    }

    private function envolver(string $texto, int $maxCaracteres): array {
        if ($maxCaracteres < 1) $maxCaracteres = 40;
        return $this->aLatin1($texto) === '' ? [''] : $this->wordwrap_seguro($texto, $maxCaracteres);
    }

    private function wordwrap_seguro(string $texto, int $ancho): array {
        $envuelto = wordwrap($texto, $ancho, "\n", true);
        return explode("\n", $envuelto);
    }

    /** Genera el binario PDF completo y lo envía directo al navegador. */
    public function salida(string $nombreArchivo, bool $descargar = false): void {
        $contenido = implode("\n", $this->lineas);
        $streamLen = strlen($contenido);

        $objetos = [];
        $objetos[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objetos[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objetos[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::ANCHO . " " . self::ALTO . "] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> /XObject << " . $this->xobjectDict() . " >> >> /Contents 4 0 R >>";
        $objetos[4] = "<< /Length {$streamLen} >>\nstream\n{$contenido}\nendstream";
        $objetos[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objetos[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $numObjBin = 7;
        $binarios = []; // num => bytes crudos (para no forzar utf8/latin1 sobre ellos)
        foreach ($this->imagenes as $img) {
            $objetos[$numObjBin] = "<< /Type /XObject /Subtype /Image /Width {$img['ancho_px']} /Height {$img['alto_px']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($img['datos']) . " >>\nstream\n" . $img['datos'] . "\nendstream";
            $numObjBin++;
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $num => $cuerpo) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$cuerpo}\nendobj\n";
        }
        $xrefStart = strlen($pdf);
        $totalObjs = count($objetos) + 1;
        $pdf .= "xref\n0 {$totalObjs}\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objetos); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    private function xobjectDict(): string {
        $partes = [];
        foreach (array_keys($this->imagenes) as $idx) { $partes[] = "/Img" . ($idx + 1) . " " . (7 + $idx) . " 0 R"; }
        return implode(' ', $partes);
    }
}
