# Despliegue masivo para PCs de empleados: instala/configura RustDesk apuntando
# al servidor propio de NAVISSI, reporta el inventario, y deja una Tarea
# Programada para que esto se repita solo (cada inicio de sesion) sin volver
# a tocar el equipo. Pensado para copiar por red, USB, GPO o una herramienta
# de despliegue remoto (PDQ, Intune, etc.) y correr una sola vez por PC.
#
# USO (una sola vez por equipo, como Administrador):
#   powershell -ExecutionPolicy Bypass -File desplegar_en_pc_empleado.ps1 -Servidor "http://IP-NAVISSI:8099" -Sede "Molinos" -RustDeskServidor "192.168.99.64" -RustDeskClave "eIHag4WIjOdz1upKpwNdYx5R2UU9YOvVTSSeIDdOSvA="

param(
    [Parameter(Mandatory = $true)][string]$Servidor,
    [Parameter(Mandatory = $true)][string]$Sede,
    [Parameter(Mandatory = $true)][string]$RustDeskServidor,
    [Parameter(Mandatory = $true)][string]$RustDeskClave,
    [string]$RustDeskInstaladorLocal = ""
)

$carpeta = $PSScriptRoot
$agente = "$carpeta\agente_navissi.ps1"

Write-Output "1/2 - Instalando RustDesk y reportando inventario de este equipo..."
& $agente -Servidor $Servidor -Sede $Sede -InstalarRustDesk -RustDeskServidor $RustDeskServidor -RustDeskClave $RustDeskClave -RustDeskInstaladorLocal $RustDeskInstaladorLocal

Write-Output "2/2 - Registrando Tarea Programada para que el reporte se repita solo cada inicio de sesion..."
try {
    $accion = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -File `"$agente`" -Servidor `"$Servidor`" -Sede `"$Sede`" -RustDeskServidor `"$RustDeskServidor`" -RustDeskClave `"$RustDeskClave`""
    $disparador = New-ScheduledTaskTrigger -AtLogOn
    $configuracion = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
    Register-ScheduledTask -TaskName "NAVISSI - Agente de Inventario" -Action $accion -Trigger $disparador -Settings $configuracion -Force | Out-Null
    Write-Output "Listo. Este equipo quedo dado de alta en NAVISSI y con control remoto disponible."
} catch {
    Write-Output "AVISO: no se pudo registrar la Tarea Programada (necesita permisos de administrador): $($_.Exception.Message)"
    Write-Output "El inventario y RustDesk ya quedaron configurados; solo falta repetir el reporte manualmente cada tanto, o pedirle a TI que registre la tarea."
}
