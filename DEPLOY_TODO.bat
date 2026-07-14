@echo off
REM ============================================================
REM  DEPLOY_TODO.bat - Un clic: sube TODO lo que tengas en esta
REM  carpeta a GitHub y lo despliega en grupo10z.com.co.
REM
REM  Sube TODO lo que este pendiente en el proyecto, sin
REM  distincion - revisa antes de correrlo si no quieres subir
REM  algo que no hayas terminado de probar.
REM ============================================================

cd /d "%~dp0"

echo ============================================
echo   1/3 - Revisando cambios pendientes...
echo ============================================
git status --short
echo.

set /p CONTINUAR="Vas a subir TODO lo anterior a GitHub y al hosting. Escribe SI para continuar: "
if /i not "%CONTINUAR%"=="SI" (
    echo Cancelado. No se subio nada.
    pause
    exit /b 0
)

echo.
echo ============================================
echo   2/3 - Commit y push a GitHub...
echo ============================================
git add -A
for /f "tokens=1-3 delims=/ " %%a in ("%date%") do set FECHA=%%a-%%b-%%c
set HORA=%time:~0,5%
git commit -m "Deploy automatico %FECHA% %HORA%"
if errorlevel 1 (
    echo No habia cambios nuevos para commitear, sigo con el deploy igual.
)
git push origin main
if errorlevel 1 (
    echo.
    echo ERROR: el push a GitHub fallo. Revisa tu conexion o credenciales.
    pause
    exit /b 1
)

echo.
echo ============================================
echo   3/3 - Desplegando en grupo10z.com.co...
echo ============================================
powershell -ExecutionPolicy Bypass -File "%~dp0DEPLOY_HOSTING.ps1"

echo.
echo ============================================
echo   Listo. Verifica en https://grupo10z.com.co
echo ============================================
pause
