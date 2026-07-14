@echo off
setlocal EnableExtensions EnableDelayedExpansion

REM NAVISSI - despliegue completo a GitHub y hosting.
REM Credenciales: .env.deploy.local (nunca se versiona).
REM El paquete del hosting se construye exclusivamente con git ls-files.

cd /d "%~dp0"
set "ROOT=%~dp0"
set "PREFLIGHT=%ROOT%scripts\preflight_deploy.ps1"
set "HOSTING=%ROOT%DEPLOY_HOSTING.ps1"

echo ============================================================
echo   NAVISSI - DEPLOY COMPLETO (GitHub + hosting)
echo ============================================================
echo Ruta: %CD%
echo.

where git >nul 2>&1
if errorlevel 1 goto :fail_git
where powershell >nul 2>&1
if errorlevel 1 goto :fail_powershell
if not exist "%PREFLIGHT%" goto :fail_preflight_file
if not exist "%HOSTING%" goto :fail_hosting_file

echo [1/5] Estado actual del repositorio
git status -sb
if errorlevel 1 goto :fail_git_status
echo.

echo [2/5] Validando paquete y archivos sensibles
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%PREFLIGHT%" -Mode Source
if errorlevel 1 goto :fail_preflight
echo.

echo [3/5] Guardando cambios en GitHub
git add -A
if errorlevel 1 goto :fail_git_add

git diff --cached --quiet
if errorlevel 1 (
    for /f "usebackq delims=" %%D in (`powershell.exe -NoLogo -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd HH:mm:ss'"`) do set "DEPLOY_DATE=%%D"
    git commit -m "Deploy NAVISSI !DEPLOY_DATE!"
    if errorlevel 1 goto :fail_git_commit
) else (
    echo No hay cambios nuevos; se sincronizara igualmente el commit actual.
)

git push origin main
if errorlevel 1 goto :fail_git_push
echo GitHub actualizado correctamente.
echo.

echo [4/5] Publicando en hosting
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%HOSTING%"
if errorlevel 1 goto :fail_hosting
echo.

echo [5/5] Deploy terminado
echo GitHub: https://github.com/AAV1102/navissi
echo Hosting: revisar NAVISSI_SITE_HOST en .env.deploy.local
echo El paquete incluyo todos los archivos versionados y excluyo secretos/datos locales.
echo.
echo RESULTADO: OK
exit /b 0

:fail_git
echo ERROR: Git no esta instalado o no esta en PATH.
goto :fail
:fail_powershell
echo ERROR: PowerShell no esta disponible.
goto :fail
:fail_preflight_file
echo ERROR: falta scripts\preflight_deploy.ps1.
goto :fail
:fail_hosting_file
echo ERROR: falta DEPLOY_HOSTING.ps1.
goto :fail
:fail_git_status
echo ERROR: no se pudo leer el estado del repositorio.
goto :fail
:fail_preflight
echo ERROR: el pre-flight bloqueo la publicacion. No se hizo commit ni deploy.
goto :fail
:fail_git_add
echo ERROR: no se pudieron preparar los cambios.
goto :fail
:fail_git_commit
echo ERROR: fallo el commit de GitHub.
goto :fail
:fail_git_push
echo ERROR: fallo el push a GitHub. El hosting no se ejecuto.
goto :fail
:fail_hosting
echo ERROR: fallo el deploy al hosting. GitHub puede haber quedado actualizado.
goto :fail

:fail
echo.
echo RESULTADO: ERROR
exit /b 1
