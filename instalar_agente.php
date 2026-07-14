<?php
// Genera un instalador .bat de un clic para el Agente de Inventario: lo descarga,
// lo corre una vez, y programa la tarea de Windows automaticamente - sin que el
// tecnico tenga que abrir el Programador de tareas a mano.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/agente_auth.php';
requiere_login('');
if (!tiene_rol(['ADMIN', 'TI'])) { http_response_code(403); exit('No autorizado.'); }

$hostSeguro=preg_replace('/[^A-Za-z0-9.\-:\[\]]/','',(string)($_SERVER['HTTP_HOST']??'localhost'));
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $hostSeguro;
$sede = limpio($_GET['sede'] ?? null);
$sedeBatch=$sede?preg_replace('/[\r\n"&|<>^%!]/','',$sede):null;$pdo=db();$sedeId=$sede?sede_id_por_nombre($pdo,$sede,false):null;if($sede&&!$sedeId){http_response_code(400);exit('Sede inválida.');}$u=usuario_actual();$tokenAgente=agente_token_emitir($pdo,'Instalador '.($sede?:'sin sede').' · '.date('Y-m-d H:i'),$sedeId,$u['nombre']??null);

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

net session >nul 2>&1
if errorlevel 1 (
    echo ERROR: este instalador requiere permisos de administrador.
    echo Haz clic derecho sobre el archivo y selecciona "Ejecutar como administrador".
    pause
    exit /b 1
)

set "SERVIDOR=<?= $base ?>"
set "DESTINO=%ProgramData%\NAVISSI"
set "SCRIPT=%DESTINO%\agente_navissi.ps1"
set "TOKENFILE=%DESTINO%\agent.token"

<?php if ($sedeBatch): ?>
set "SEDE=<?= $sedeBatch ?>"
<?php else: ?>
set /p SEDE=Nombre de la sede/tienda de este equipo (ej. Molinos):
<?php endif; ?>

if not exist "%DESTINO%" mkdir "%DESTINO%"
> "%TOKENFILE%" echo <?= $tokenAgente ?>
icacls "%TOKENFILE%" /inheritance:r /grant:r "SYSTEM:F" "Administrators:F" >nul 2>&1

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
    ? "-Servidor '%SERVIDOR%' -Sede '%SEDE%' -TokenFile '%TOKENFILE%' -InstalarRustDesk -RustDeskServidor '{$rustdeskServidor}' -RustDeskClave '{$rustdeskClave}'"
    : "-Servidor '%SERVIDOR%' -Sede '%SEDE%' -TokenFile '%TOKENFILE%'";
?>
echo [2/3] Ejecutando el agente por primera vez<?= $rustdeskClave ? ' (incluye control remoto)' : '' ?> ...
powershell -ExecutionPolicy Bypass -Command "& '%SCRIPT%' <?= $psArgsPlantilla ?>"
if errorlevel 1 (
    echo ERROR: el equipo no pudo reportarse. No se crearon tareas automaticas.
    echo La credencial queda disponible para reintentar en este mismo equipo.
    pause
    exit /b 1
)

echo [3/3] Programando las tareas automaticas con la cuenta SYSTEM ...
schtasks /create /tn "NAVISSI Agente Inventario" /tr "powershell -ExecutionPolicy Bypass -Command \"& '%SCRIPT%' <?= $psArgsPlantilla ?>\"" /sc onstart /ru SYSTEM /rl highest /f >nul 2>&1
if errorlevel 1 goto :task_error
schtasks /create /tn "NAVISSI Agente Inventario (diario)" /tr "powershell -ExecutionPolicy Bypass -Command \"& '%SCRIPT%' <?= $psArgsPlantilla ?>\"" /sc daily /st 09:00 /ru SYSTEM /rl highest /f >nul 2>&1
if errorlevel 1 goto :task_error

echo.
echo ============================================
echo   Listo. El agente ya reporto este equipo y
echo   quedo programado para reportarse solo.
echo   Este instalador se eliminara para proteger
echo   la credencial incluida en su interior.
echo ============================================
pause
start "" /b cmd /c del /f /q "%~f0"
exit /b 0

:task_error
echo ERROR: Windows no pudo crear las tareas programadas.
echo Verifica que el instalador se ejecuto como administrador.
pause
exit /b 1
