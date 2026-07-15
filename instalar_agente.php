<?php
// Genera un instalador .bat de un clic para el Agente de Inventario: lo descarga,
// lo corre una vez, y programa la tarea de Windows automaticamente - sin que el
// tecnico tenga que abrir el Programador de tareas a mano.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/agente_auth.php';
requiere_login('');
if (!tiene_rol(['ADMIN', 'TI'])) { http_response_code(403); exit('No autorizado.'); }

$base = rtrim(navissi_url_publica(), '/');
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
    echo Se necesitan permisos de Windows para instalar el agente. Se solicitaran automaticamente...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b 1
)

set "SERVIDOR=<?= $base ?>"
set "DESTINO=%ProgramData%\NAVISSI"
set "SCRIPT=%DESTINO%\agente_navissi.ps1"
set "REPORTAR=%DESTINO%\reportar_problema.ps1"
set "TOKENFILE=%DESTINO%\agent.token"

<?php if ($sedeBatch): ?>
set "SEDE=<?= $sedeBatch ?>"
<?php else: ?>
set /p SEDE=Nombre de la sede/tienda de este equipo (ej. Molinos):
<?php endif; ?>

if not exist "%DESTINO%" mkdir "%DESTINO%"
powershell -NoProfile -Command "$d=[Environment]::ExpandEnvironmentVariables('%DESTINO%');$f=[Environment]::ExpandEnvironmentVariables('%TOKENFILE%');New-Item -ItemType Directory -Force -Path $d -ErrorAction Stop | Out-Null;[IO.File]::WriteAllText($f,'<?= $tokenAgente ?>',[Text.Encoding]::ASCII)"
if errorlevel 1 goto :credential_error
icacls "%TOKENFILE%" /inheritance:r /grant:r *S-1-5-18:F *S-1-5-32-544:F >nul 2>&1
powershell -NoProfile -Command "if(-not (Test-Path -LiteralPath '%TOKENFILE%')){exit 1}"
if errorlevel 1 goto :credential_error
goto credential_ready
:credential_error
echo ERROR: no se pudo crear la credencial local del agente.
echo Ejecuta este instalador haciendo clic derecho: Ejecutar como administrador.
pause
exit /b 1
:credential_ready

echo.
echo [1/3] Descargando el agente desde %SERVIDOR% ...
powershell -NoProfile -Command "[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12;Invoke-WebRequest -UseBasicParsing -Uri '%SERVIDOR%/descargar_agente_archivo.php?archivo=inventario^&token=<?= $tokenAgente ?>' -OutFile '%SCRIPT%'"
if errorlevel 1 goto :download_error
powershell -NoProfile -Command "[Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12;Invoke-WebRequest -UseBasicParsing -Uri '%SERVIDOR%/descargar_agente_archivo.php?archivo=reportar^&token=<?= $tokenAgente ?>' -OutFile '%REPORTAR%'"
if errorlevel 1 goto :download_error
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
schtasks /create /tn "NAVISSI Agente Inventario (cada 5 minutos)" /tr "powershell -ExecutionPolicy Bypass -Command \"& '%SCRIPT%' <?= $psArgsPlantilla ?>\"" /sc minute /mo 5 /ru SYSTEM /rl highest /f >nul 2>&1
if errorlevel 1 goto :task_error
powershell -NoProfile -Command "$w=New-Object -ComObject WScript.Shell;$s=$w.CreateShortcut([Environment]::GetFolderPath('CommonDesktopDirectory')+'\Reportar problema a NAVISSI.lnk');$s.TargetPath='powershell.exe';$s.Arguments='-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"%REPORTAR%\" -Servidor \"%SERVIDOR%\"';$s.WorkingDirectory='%DESTINO%';$s.Description='Crear un ticket asociado automáticamente a este equipo';$s.Save()"

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

:download_error
echo ERROR: no se pudieron descargar todos los componentes del agente.
echo Comprueba internet, fecha/hora de Windows y vuelve a descargar un instalador nuevo.
pause
exit /b 1

:task_error
echo ERROR: Windows no pudo crear las tareas programadas.
echo Verifica que el instalador se ejecuto como administrador.
pause
exit /b 1
