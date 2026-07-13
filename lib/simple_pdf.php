<?php
/**
 * Generador de PDF minimalista, 100% PHP puro (sin Composer, sin librerias
 * externas, sin GD/Imagick) - escribe directamente la sintaxis PDF 1.4 usando
 * las fuentes estandar (Helvetica), que todo lector de PDF trae incluidas.
 * Pensado para documentos de texto simples (certificados, actas) - no soporta
 * imagenes ni tablas complejas, pero funciona igual con o sin internet.
 */
class SimplePDF {
    private array $lineas = [];
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
        $objetos[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::ANCHO . " " . self::ALTO . "] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>";
        $objetos[4] = "<< /Length {$streamLen} >>\nstream\n{$contenido}\nendstream";
        $objetos[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objetos[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

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
}
