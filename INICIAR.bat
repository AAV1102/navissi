@echo off
chcp 65001 >nul
title NAVISSI Inventario
color 0A
cls
cd /d "%~dp0"

REM Obtener la IPv4 que Windows usa para salir a la red. La conexion UDP no
REM envia datos; solo permite descubrir la interfaz activa sin depender del
REM idioma de la salida de ipconfig.
set "NAVISSI_IP="
for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "$s=New-Object Net.Sockets.UdpClient; try{$s.Connect('8.8.8.8',53); $s.Client.LocalEndPoint.Address.IPAddressToString}finally{$s.Dispose()}" 2^>nul`) do set "NAVISSI_IP=%%i"
if not defined NAVISSI_IP set "NAVISSI_IP=%COMPUTERNAME%"

REM La primera ejecucion crea la excepcion de entrada para equipos de la red
REM privada. Windows mostrara una sola solicitud de permiso de administrador.
netsh advfirewall firewall show rule name="NAVISSI_Inventario_8099" >nul 2>&1
if errorlevel 1 (
    echo Configurando acceso desde la red local...
    powershell -NoProfile -Command "Start-Process netsh -Verb RunAs -Wait -ArgumentList 'advfirewall firewall add rule name=NAVISSI_Inventario_8099 dir=in action=allow protocol=TCP localport=8099 profile=private'"
)

REM Si ya hay un NAVISSI corriendo (de una ventana anterior sin cerrar bien),
REM lo cerramos primero para que nunca queden dos procesos peleando el mismo
REM puerto (eso causaba que a veces no se vieran los cambios mas recientes).
for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":8099" ^| findstr "LISTENING"') do (
    taskkill /F /PID %%p >nul 2>&1
)

echo ==========================================
echo   NAVISSI INVENTARIO - Iniciando...
echo ==========================================
echo.
echo En este equipo:  http://localhost:8099
echo En la red local: http://%NAVISSI_IP%:8099
echo (deja esta ventana abierta mientras usas el software)
echo.
start "" http://localhost:8099/index.php
"C:\xampp\php\windowsXamppPhp\php.exe" -c "C:\xampp\php\windowsXamppPhp\php.ini" -S 0.0.0.0:8099 -t "%~dp0." "%~dp0router.php"
pause
