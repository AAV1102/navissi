# Registra el servidor RustDesk para que arranque SOLO cada vez que este equipo
# se encienda (sin que nadie tenga que abrir nada). Ejecutar UNA sola vez,
# como Administrador (clic derecho -> Ejecutar con PowerShell como administrador).

$carpeta = $PSScriptRoot
$accion = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -File `"$carpeta\iniciar_servidor.ps1`"" -WorkingDirectory $carpeta
$disparador = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$configuracion = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask -TaskName "NAVISSI - Servidor RustDesk" -Action $accion -Trigger $disparador -Principal $principal -Settings $configuracion -Force

Write-Output "Listo. El servidor RustDesk arrancara automaticamente cada vez que este equipo se encienda."
Write-Output "Para arrancarlo ahora mismo sin reiniciar: Start-ScheduledTask -TaskName 'NAVISSI - Servidor RustDesk'"
