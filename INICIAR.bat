@echo off
chcp 65001 >nul
title NAVISSI Inventario
color 0A
cls

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
echo Servidor local:  http://127.0.0.1:8099
echo Desde otro equipo de la misma red: http://%COMPUTERNAME%:8099 (o la IP de este PC)
echo (deja esta ventana abierta mientras usas el software)
echo.
start "" http://127.0.0.1:8099/index.php
"C:\xampp\php\windowsXamppPhp\php.exe" -c "C:\xampp\php\windowsXamppPhp\php.ini" -S 0.0.0.0:8099 -t "%~dp0" "%~dp0router.php"
pause
