# Servidor RustDesk (self-hosted, portable) para Grupo 10Z / NAVISSI.
# No requiere Docker ni instalacion: son 2 ejecutables nativos de Windows.
# Se puede copiar esta carpeta completa a cualquier PC/servidor y funciona igual.
#
# Uso normal (doble clic o):
#   powershell -ExecutionPolicy Bypass -File iniciar_servidor.ps1
#
# Para produccion real (24/7), usa registrar_tarea_programada.ps1 una sola vez
# y este script correra solo al encender el equipo, sin que nadie tenga que
# abrir nada manualmente.

$carpeta = $PSScriptRoot
Set-Location $carpeta

Write-Output "Iniciando servidor RustDesk (hbbs + hbbr) en $carpeta ..."
Write-Output "IP local de este equipo: $((Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notmatch 'Loopback|vEthernet|Virtual' -and $_.IPAddress -notlike '169.254.*' } | Select-Object -First 1).IPAddress)"

# hbbr = servidor de relay (retransmite la sesion de control remoto)
Start-Process -FilePath "$carpeta\hbbr.exe" -WorkingDirectory $carpeta -WindowStyle Minimized

# hbbs = servidor de ID (registra los equipos y hace el emparejamiento)
Start-Process -FilePath "$carpeta\hbbs.exe" -WorkingDirectory $carpeta -WindowStyle Minimized

Start-Sleep -Seconds 2
if (Test-Path "$carpeta\id_ed25519.pub") {
    $clave = Get-Content "$carpeta\id_ed25519.pub" -Raw
    Write-Output ""
    Write-Output "===================================================================="
    Write-Output "Servidor RustDesk activo."
    Write-Output "Clave publica (se necesita en CADA equipo cliente RustDesk):"
    Write-Output $clave.Trim()
    Write-Output "===================================================================="
} else {
    Write-Output "El servidor esta iniciando, la clave publica aparecera en unos segundos en id_ed25519.pub"
}
