# Agente de inventario NAVISSI - recolecta datos reales del equipo y los envia
# al software. Se ejecuta manualmente o programado (Tarea Programada de Windows,
# ej. cada inicio de sesion o cada 24h). No instala nada (excepto RustDesk si se
# pide con -InstalarRustDesk), no requiere admin para el reporte de inventario.
#
# USO:
#   powershell -ExecutionPolicy Bypass -File agente_navissi.ps1 -Servidor "http://IP-DEL-SERVIDOR:8099" -Sede "Molinos"
#   powershell -ExecutionPolicy Bypass -File agente_navissi.ps1 -Servidor "http://IP-DEL-SERVIDOR:8099" -Sede "Molinos" `
#       -InstalarRustDesk -RustDeskServidor "192.168.99.64" -RustDeskClave "eIHag4WIjOdz1upKpwNdYx5R2UU9YOvVTSSeIDdOSvA=" `
#       -RustDeskInstaladorLocal "\\SISTEMAS-PC\NAVISSI\rustdesk-server\instaladores\rustdesk-cliente-windows.exe"

param(
    [string]$Servidor = "http://127.0.0.1:8099",
    [string]$Sede = "",
    [string]$TokenFile = "$env:ProgramData\NAVISSI\agent.token",
    [switch]$InstalarRustDesk,
    [string]$RustDeskServidor = "",
    [string]$RustDeskClave = "",
    [string]$RustDeskInstaladorLocal = "",
    [switch]$EscanearRed
)
$agentToken = if (Test-Path $TokenFile) { (Get-Content $TokenFile -Raw).Trim() } else { "" }
if (-not $agentToken) { Write-Output "ERROR: falta la credencial del agente. Descarga un instalador nuevo desde NAVISSI."; exit 1 }
$headersAgente = @{ Authorization = "Bearer $agentToken" }

$cs = Get-CimInstance -ClassName Win32_ComputerSystem
$bios = Get-CimInstance -ClassName Win32_BIOS
$os = Get-CimInstance -ClassName Win32_OperatingSystem
$cpu = Get-CimInstance -ClassName Win32_Processor | Select-Object -First 1
$discos = Get-CimInstance -ClassName Win32_LogicalDisk -Filter "DriveType=3"
$ramBytes = $cs.TotalPhysicalMemory

$almacenamientoTotal = ($discos | Measure-Object -Property Size -Sum).Sum
$almacenamientoGB = if ($almacenamientoTotal) { [math]::Round($almacenamientoTotal / 1GB) } else { 0 }
$ramGB = [math]::Round($ramBytes / 1GB)

$ipLocal = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.InterfaceAlias -notmatch 'Loopback|vEthernet|Virtual' -and $_.IPAddress -notlike '169.254.*' } |
    Select-Object -First 1 -ExpandProperty IPAddress)

# --- RustDesk: instalación opcional (control remoto tipo Zoho Assist / AnyDesk) ---
if ($InstalarRustDesk -and -not (Test-Path "$env:ProgramFiles\RustDesk\rustdesk.exe")) {
    try {
        $tmp = "$env:TEMP\rustdesk-setup.exe"
        if ($RustDeskInstaladorLocal -and (Test-Path $RustDeskInstaladorLocal)) {
            Copy-Item $RustDeskInstaladorLocal $tmp -Force
        } else {
            Invoke-WebRequest -Uri "https://github.com/rustdesk/rustdesk/releases/download/1.4.9/rustdesk-1.4.9-x86_64.exe" -OutFile $tmp
        }
        Start-Process -FilePath $tmp -ArgumentList "--silent-install" -Wait
        Start-Sleep -Seconds 2
    } catch {
        Write-Output "AVISO: no se pudo instalar RustDesk automáticamente: $($_.Exception.Message)"
    }
}

# Si se indicó un servidor propio de RustDesk (self-hosted), edita directamente su
# configuración (rendezvous_server + key) para que el equipo se registre ahí en vez
# del servidor público de RustDesk. El flag --config de la CLI no persiste esto de
# forma confiable, por eso se edita el archivo TOML directamente.
$rustdeskExe = "$env:ProgramFiles\RustDesk\rustdesk.exe"
$rustdeskId = ""
$rustdeskPassword = ""
if (Test-Path $rustdeskExe) {
    if ($RustDeskServidor) {
        Get-Process rustdesk -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 1
        $cfgPath2 = "$env:APPDATA\RustDesk\config\RustDesk2.toml"
        New-Item -ItemType Directory -Force -Path (Split-Path $cfgPath2) | Out-Null
        $contenido = if (Test-Path $cfgPath2) { Get-Content $cfgPath2 -Raw } else { "rendezvous_server = ''`n`n[options]`n" }
        if ($contenido -match "rendezvous_server = '.*?'") {
            $contenido = $contenido -replace "rendezvous_server = '.*?'", "rendezvous_server = '$RustDeskServidor'"
        } else {
            $contenido = "rendezvous_server = '$RustDeskServidor'`n" + $contenido
        }
        if ($RustDeskClave) {
            if ($contenido -notmatch "\[options\]") { $contenido += "`n[options]`n" }
            if ($contenido -match "(?m)^key = ") {
                $contenido = $contenido -replace "(?m)^key = '.*?'", "key = '$RustDeskClave'"
            } else {
                $contenido = $contenido -replace "\[options\]", "[options]`nkey = '$RustDeskClave'"
            }
        }
        Set-Content -Path $cfgPath2 -Value $contenido -NoNewline
    }
    # Se usa redireccion a archivo (no captura de pipeline) porque rustdesk.exe
    # se comunica con la instancia ya corriendo via IPC y no siempre entrega su
    # salida al capturar la tuberia directamente.
    try {
        $outId = "$env:TEMP\navissi_rd_id.txt"
        Start-Process -FilePath $rustdeskExe -ArgumentList "--get-id" -NoNewWindow -Wait -RedirectStandardOutput $outId -ErrorAction Stop
        if (Test-Path $outId) { $rustdeskId = (Get-Content $outId -Raw).Trim(); Remove-Item $outId -Force -ErrorAction SilentlyContinue }
    } catch { $rustdeskId = "" }
    try {
        $outPw = "$env:TEMP\navissi_rd_pw.txt"
        Start-Process -FilePath $rustdeskExe -ArgumentList "--get-unattended-password" -NoNewWindow -Wait -RedirectStandardOutput $outPw -ErrorAction Stop
        if (Test-Path $outPw) { $rustdeskPassword = (Get-Content $outPw -Raw).Trim(); Remove-Item $outPw -Force -ErrorAction SilentlyContinue }
    } catch { $rustdeskPassword = "" }
    if (-not (Get-Process rustdesk -ErrorAction SilentlyContinue)) {
        Start-Process -FilePath $rustdeskExe -WindowStyle Minimized
    }
}

# Parches de Windows realmente instalados (Get-HotFix es rapido y no requiere
# admin; da KB, descripcion y fecha real de instalacion - datos genuinos, no
# simulados).
$parches = @()
try {
    Get-HotFix -ErrorAction Stop | ForEach-Object {
        $parches += @{
            kb = $_.HotFixID
            descripcion = $_.Description
            tipo = if ($_.HotFixID -match '^KB\d+$') { 'SEGURIDAD' } else { 'ACTUALIZACION' }
            fecha_instalado = if ($_.InstalledOn) { $_.InstalledOn.ToString('yyyy-MM-dd') } else { $null }
        }
    }
} catch {
    Write-Output "AVISO: no se pudieron leer los parches instalados: $($_.Exception.Message)"
}

$payload = @{
    serial             = $bios.SerialNumber
    usuario_windows    = $env:USERNAME
    tipo               = if ($cs.PCSystemType -eq 2) { "PORTATIL" } else { "ESCRITORIO" }
    marca              = $cs.Manufacturer
    modelo             = $cs.Model
    sistema_operativo  = $os.Caption
    procesador         = $cpu.Name
    memoria            = "$ramGB GB"
    almacenamiento     = "$almacenamientoGB GB"
    sede               = $Sede
    ip_local           = $ipLocal
    rustdesk_id        = $rustdeskId
    rustdesk_password  = $rustdeskPassword
    parches            = $parches
} | ConvertTo-Json -Depth 4

$reporteExitoso = $false
try {
    $resp = Invoke-RestMethod -Uri "$Servidor/api_agente.php" -Method Post -Headers $headersAgente -Body $payload -ContentType "application/json; charset=utf-8"
    Write-Output "OK: $($resp.accion) - id $($resp.id) - RustDesk ID: $rustdeskId - Parches reportados: $($parches.Count)"
    $reporteExitoso = $true
} catch {
    Write-Output "ERROR enviando el reporte: $($_.Exception.Message)"
}

# --- Network Discovery: barrido real del segmento local (ping + tabla ARP) ---
# No requiere admin ni herramientas externas - usa ping.exe y arp.exe que ya
# vienen en Windows. Solo se ejecuta si se pide explicitamente con -EscanearRed
# porque tarda ~30-60s en barrer un /24 completo.
if ($EscanearRed -and $ipLocal) {
    Write-Output "Escaneando la red local (esto puede tardar hasta 1 minuto)..."
    $partesIp = $ipLocal -split '\.'
    $prefijo = "$($partesIp[0]).$($partesIp[1]).$($partesIp[2])"
    1..254 | ForEach-Object -Parallel {
        $ip = "$($using:prefijo).$_"
        if (Test-Connection -TargetName $ip -Count 1 -Quiet -TimeoutSeconds 1 -ErrorAction SilentlyContinue) { $ip }
    } -ThrottleLimit 60 | Out-Null

    Start-Sleep -Seconds 2
    $arp = arp -a | Select-String "$prefijo\.\d+\s+([0-9a-f-]{17})" -AllMatches
    $dispositivos = @()
    foreach ($linea in (arp -a)) {
        if ($linea -match "^\s*($([regex]::Escape($prefijo))\.\d+)\s+([0-9a-f-]{17})\s+(\w+)") {
            $dispositivos += @{ ip = $matches[1]; mac = $matches[2]; tipo_arp = $matches[3] }
        }
    }

    if ($dispositivos.Count -gt 0) {
        $payloadRed = @{ sede = $Sede; dispositivos = $dispositivos } | ConvertTo-Json -Depth 4
        try {
            $respRed = Invoke-RestMethod -Uri "$Servidor/api_network_discovery.php" -Method Post -Headers $headersAgente -Body $payloadRed -ContentType "application/json; charset=utf-8"
            Write-Output "Network Discovery: $($dispositivos.Count) dispositivos encontrados, $($respRed.nuevos) nuevos."
        } catch {
            Write-Output "ERROR enviando el escaneo de red: $($_.Exception.Message)"
        }
    } else {
        Write-Output "Network Discovery: no se encontraron dispositivos (revisa permisos de firewall para ping)."
    }
}

if (-not $reporteExitoso) { exit 1 }
exit 0
