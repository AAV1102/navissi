<?php
// Genera un instalador .bat de un clic para el Agente de Inventario: lo descarga,
// lo corre una vez, y programa la tarea de Windows automaticamente - sin que el
// tecnico tenga que abrir el Programador de tareas a mano.
require_once __DIR__ . '/config.php';
requiere_login('');
if (!tiene_rol(['ADMIN', 'TI'])) { http_response_code(403); exit('No autorizado.'); }

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$sede = limpio($_GET['sede'] ?? null);

$serverPubKeyPath = __DIR__ . '/rustdesk-server/id_ed25519.pub';
$rustdeskClave = file_exists($serverPubKeyPath) ? trim(file_get_contents($serverPubKeyPath)) : null;
$rustdeskServidor = $rustdeskClave ? gethostbyname(gethostname()) : null;

header('Content-Type: application/octet-stream; charset=utf-8');
header('Content-Disposition: attachment; filename="instalar_agente_navissi.bat"');
?>
@echo off
setlocal EnableDelayedExpansion
title Instalador del Agente NAVISSI
echo ============================================
echo   Instalador del Agente de Inventario NAVISSI
echo ============================================
echo.

set "SERVIDOR=<?= $base ?>"
set "DESTINO=%ProgramData%\NAVISSI"
set "SCRIPT=%DESTINO%\agente_navissi.ps1"

<?php if ($sede): ?>
set "SEDE=<?= $sede ?>"
<?php else: ?>
set /p SEDE=Nombre de la sede/tienda de este equipo (ej. Molinos):
<?php endif; ?>

if not exist "%DESTINO%" mkdir "%DESTINO%"

echo.
echo [1/3] Descargando el agente desde %SERVIDOR% ...
powershell -Command "Invoke-WebRequest -Uri '%SERVIDOR%/data/agente_navissi.ps1' -OutFile '%SCRIPT%'"
if not exist "%SCRIPT%" (
    echo ERROR: no se pudo descargar el agente. Revisa la conexion a internet/red y vuelve a intentar.
    pause
    exit /b 1
)

<?php
$psArgsPlantilla = $rustdeskClave
    ? "-Servidor '%SERVIDOR%' -Sede '%SEDE%' -InstalarRustDesk -RustDeskServidor '{$rustdeskServidor}' -RustDeskClave '{$rustdeskClave}'"
    : "-Servidor '%SERVIDOR%' -Sede '%SEDE%'";
?>
echo [2/3] Ejecutando el agente por primera vez<?= $rustdeskClave ? ' (incluye control remoto)' : '' ?> ...
powershell -ExecutionPolicy Bypass -Command "& '%SCRIPT%' <?= $psArgsPlantilla ?>"

echo [3/3] Programando la tarea automatica (se ejecuta cada vez que alguien inicia sesion) ...
schtasks /create /tn "NAVISSI Agente Inventario" /tr "powershell -ExecutionPolicy Bypass -Command \"& '%SCRIPT%' <?= $psArgsPlantilla ?>\"" /sc onlogon /rl highest /f >nul 2>&1
schtasks /create /tn "NAVISSI Agente Inventario (diario)" /tr "powershell -ExecutionPolicy Bypass -Command \"& '%SCRIPT%' <?= $psArgsPlantilla ?>\"" /sc daily /st 09:00 /rl highest /f >nul 2>&1

echo.
echo ============================================
echo   Listo. El agente ya reporto este equipo y
echo   quedo programado para reportarse solo.
echo ============================================
pause
