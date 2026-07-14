param(
    [string]$PhpPath = "C:\Mesa de Ayuda\NAVISSI-PORTATIL\php\php.exe",
    [string]$ProjectRoot = "C:\Mesa de Ayuda\NAVISSI-INVENTARIO",
    [switch]$Remove
)

$ErrorActionPreference = 'Stop'
$taskName = 'NAVISSI - Automatizaciones Fase 3'

if ($Remove) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Output "Tarea eliminada: $taskName"
    exit 0
}

$scriptPath = Join-Path $ProjectRoot 'scripts\ejecutar_automatizaciones.php'
if (-not (Test-Path -LiteralPath $PhpPath)) { throw "No existe PHP en $PhpPath" }
if (-not (Test-Path -LiteralPath $scriptPath)) { throw "No existe el ejecutor en $scriptPath" }

$action = New-ScheduledTaskAction -Execute $PhpPath -Argument ('"{0}"' -f $scriptPath) -WorkingDirectory $ProjectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 15) -RepetitionDuration (New-TimeSpan -Days 3650)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -RestartCount 2 -RestartInterval (New-TimeSpan -Minutes 2)
$description = 'Ejecuta aperturas, cierres, SLA y notificaciones NAVISSI cada 15 minutos.'
$mode = 'SYSTEM'
try {
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description $description -Force | Out-Null
} catch [Microsoft.Management.Infrastructure.CimException] {
    if ($_.Exception.Message -notmatch 'denegado|denied|0x80070005') { throw }
    $mode = 'USUARIO_INTERACTIVO'
    $currentUser = "$env:USERDOMAIN\$env:USERNAME"
    $principal = New-ScheduledTaskPrincipal -UserId $currentUser -LogonType Interactive -RunLevel Limited
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description "$description Modo contingencia: requiere sesión iniciada." -Force | Out-Null
}
Start-ScheduledTask -TaskName $taskName
Write-Output "Tarea instalada y ejecutada: $taskName ($mode)"
