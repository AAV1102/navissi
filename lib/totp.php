<?php
/**
 * TOTP (RFC 6238 / RFC 4226) puro en PHP, sin dependencias. Compatible con
 * Microsoft Authenticator, Google Authenticator y cualquier app TOTP estándar
 * (no requiere ninguna API de Microsoft - el protocolo es un estándar abierto).
 */

function totp_generar_secreto(int $longitud = 20): string {
    $alfabetoBase32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secreto = '';
    for ($i = 0; $i < $longitud; $i++) {
        $secreto .= $alfabetoBase32[random_int(0, 31)];
    }
    return $secreto;
}

function totp_base32_decode(string $b32): string {
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $char) {
        $pos = strpos($alfabeto, $char);
        if ($pos === false) continue;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) < 8) continue;
        $bytes .= chr(bindec($byte));
    }
    return $bytes;
}

function totp_codigo_actual(string $secretoBase32, int $ventanaSegundos = 30, int $digitos = 6, int $offset = 0): string {
    $clave = totp_base32_decode($secretoBase32);
    $contador = intdiv(time(), $ventanaSegundos) + $offset;
    $contadorBin = pack('N*', 0) . pack('N*', $contador); // 8 bytes big-endian
    $hash = hash_hmac('sha1', $contadorBin, $clave, true);
    $offsetByte = ord(substr($hash, -1)) & 0x0F;
    $trozo = substr($hash, $offsetByte, 4);
    $valor = unpack('N', $trozo)[1] & 0x7FFFFFFF;
    return str_pad((string) ($valor % (10 ** $digitos)), $digitos, '0', STR_PAD_LEFT);
}

/** Verifica el código permitiendo +-1 ventana (30s) de margen por desfase de reloj. */
function totp_verificar(string $secretoBase32, string $codigoIngresado): bool {
    $codigoIngresado = trim($codigoIngresado);
    foreach ([-1, 0, 1] as $offset) {
        if (hash_equals(totp_codigo_actual($secretoBase32, 30, 6, $offset), $codigoIngresado)) {
            return true;
        }
    }
    return false;
}

function totp_uri_otpauth(string $secretoBase32, string $cuenta, string $emisor = 'NAVISSI Inventario'): string {
    return 'otpauth://totp/' . rawurlencode("{$emisor}:{$cuenta}") . '?secret=' . $secretoBase32
        . '&issuer=' . rawurlencode($emisor) . '&algorithm=SHA1&digits=6&period=30';
}
