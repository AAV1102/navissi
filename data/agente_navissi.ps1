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
$logDir = "$env:ProgramData\NAVISSI"; if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Force -Path $logDir | Out-Null }
$logFile = "$logDir\agente.log"
function Log($texto) { $texto | Tee-Object -FilePath $logFile -Append }
if (-not $agentToken) { Log "ERROR: falta la credencial del agente en $TokenFile. Ejecuta el instalador NAVISSI nuevamente."; exit 1 }
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

# Programas instalados de verdad: se leen las mismas claves de registro que usa
# el Panel de control > Programas y caracteristicas (32 y 64 bits), no requiere
# admin. Se descartan entradas sin nombre (parches/componentes del sistema que
# tambien viven ahi) para quedarse solo con software real.
$softwareInstalado = @()
try {
    $rutasUninstall = @(
        'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*',
        'HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*',
        'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*'
    )
    $vistos = @{}
    foreach ($ruta in $rutasUninstall) {
        Get-ItemProperty -Path $ruta -ErrorAction SilentlyContinue | ForEach-Object {
            $nombre = $_.DisplayName
            if ($nombre -and -not $vistos.ContainsKey($nombre)) {
                $vistos[$nombre] = $true
                $softwareInstalado += @{ nombre = $nombre; version = $_.DisplayVersion; editor = $_.Publisher }
            }
        }
    }
} catch {
    Write-Output "AVISO: no se pudo leer el software instalado: $($_.Exception.Message)"
}

# Reinicio pendiente real: se revisan las mismas claves de registro que usa
# Windows Update / WSUS para saber si un equipo necesita reiniciar - no es un
# valor inventado, es exactamente lo que Windows ya sabe internamente.
$reinicioPendiente = $false
try {
    if (Test-Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Component Based Servicing\RebootPending') { $reinicioPendiente = $true }
    if (Test-Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\WindowsUpdate\Auto Update\RebootRequired') { $reinicioPendiente = $true }
    $pendingRename = Get-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager' -Name 'PendingFileRenameOperations' -ErrorAction SilentlyContinue
    if ($pendingRename) { $reinicioPendiente = $true }
} catch {
    Write-Output "AVISO: no se pudo verificar reinicio pendiente: $($_.Exception.Message)"
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
    hostname           = $env:COMPUTERNAME
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
    reinicio_pendiente = if ($reinicioPendiente) { 1 } else { 0 }
    parches            = $parches
    software           = $softwareInstalado
} | ConvertTo-Json -Depth 4

$reporteExitoso = $false
try {
    $resp = Invoke-RestMethod -Uri "$Servidor/api_agente.php" -Method Post -Headers $headersAgente -Body $payload -ContentType "application/json; charset=utf-8"
    Write-Output "OK: $($resp.accion) - id $($resp.id) - RustDesk ID: $rustdeskId - Parches reportados: $($parches.Count) - Programas reportados: $($softwareInstalado.Count)"
    $reporteExitoso = $true
} catch {
    Log "ERROR enviando el reporte: $($_.Exception.Message)"
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

# winget (Windows Package Manager) esta empaquetado como app de Microsoft
# Store y normalmente solo aparece en el PATH del usuario que inicio sesion -
# como el agente corre como SYSTEM (tarea programada), se busca el .exe real
# directamente dentro de WindowsApps en vez de asumir que "winget" esta en el
# PATH. Si no aparece (equipo viejo sin App Installer actualizado), se avisa
# con un error claro en vez de fallar en silencio.
function Buscar-Winget {
    $candidato = Get-ChildItem "$env:ProgramFiles\WindowsApps\Microsoft.DesktopAppInstaller_*_x64__8wekyb3d8bbwe\winget.exe" -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName
    if (-not $candidato) { $candidato = (Get-Command winget.exe -ErrorAction SilentlyContinue).Source }
    return $candidato
}

# --- Órdenes centralizadas autorizadas desde NAVISSI ---
try {
    $ordenes = Invoke-RestMethod -Uri "$Servidor/api_agente_ordenes.php" -Method Post -Headers $headersAgente -Body (@{accion='consultar';serial=$bios.SerialNumber}|ConvertTo-Json) -ContentType 'application/json; charset=utf-8'
    foreach($orden in @($ordenes.ordenes)) {
        $estado='COMPLETADA';$resultado='';$errorOrden='';
        try {
            if($orden.tipo -eq 'WINDOWS_UPDATE') {
                $session=New-Object -ComObject Microsoft.Update.Session;$search=$session.CreateUpdateSearcher().Search("IsInstalled=0 and Type='Software'");$updates=$search.Updates
                if($updates.Count -gt 0){$down=$session.CreateUpdateDownloader();$down.Updates=$updates;$null=$down.Download();$inst=$session.CreateUpdateInstaller();$inst.Updates=$updates;$r=$inst.Install();$resultado="Actualizaciones instaladas: $($updates.Count). Resultado: $($r.ResultCode)"}else{$resultado='No había actualizaciones pendientes.'}
            } elseif($orden.tipo -eq 'INSTALLER_URL' -and $orden.parametros.url -match '^https://') {
                $tmp="$env:TEMP\navissi_orden_$($orden.id).exe";$argsInst=if($orden.parametros.argumentos){$orden.parametros.argumentos}else{'/quiet'};Invoke-WebRequest -UseBasicParsing -Uri $orden.parametros.url -OutFile $tmp;Start-Process -FilePath $tmp -ArgumentList $argsInst -Wait;Remove-Item $tmp -Force -ErrorAction SilentlyContinue;$resultado='Instalador ejecutado correctamente.'
            } elseif($orden.tipo -eq 'UNINSTALL_SOFTWARE' -and $orden.parametros.nombre) {
                # Busca el programa por su nombre exacto en las mismas claves de
                # Uninstall que se leen para el inventario de software, y usa el
                # UninstallString real que Windows ya conoce para ese programa -
                # no se inventa ningun comando, se reutiliza el que el propio
                # instalador del programa registro.
                $nombreBuscado = $orden.parametros.nombre
                $entrada = $null
                foreach ($ruta in @('HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*','HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*','HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*')) {
                    $entrada = Get-ItemProperty -Path $ruta -ErrorAction SilentlyContinue | Where-Object { $_.DisplayName -eq $nombreBuscado } | Select-Object -First 1
                    if ($entrada) { break }
                }
                if (-not $entrada -or -not $entrada.UninstallString) { throw "No se encontro '$nombreBuscado' instalado en este equipo (puede que ya se haya desinstalado)." }
                $cmdDesinstalar = $entrada.UninstallString
                if ($cmdDesinstalar -match 'msiexec') {
                    $codigoProducto = if ($cmdDesinstalar -match '(\{[0-9A-Fa-f\-]+\})') { $matches[1] } else { $null }
                    if (-not $codigoProducto) { throw 'No se pudo identificar el codigo del producto MSI.' }
                    Start-Process -FilePath 'msiexec.exe' -ArgumentList "/X$codigoProducto /quiet /norestart" -Wait
                } else {
                    Start-Process -FilePath 'cmd.exe' -ArgumentList "/c `"$cmdDesinstalar`" /S /silent /quiet /verysilent" -Wait
                }
                $resultado = "Se envio la desinstalacion de '$nombreBuscado'. Verifica en el proximo reporte si desaparecio del listado."
            } elseif($orden.tipo -eq 'INSTALL_WINGET' -and $orden.parametros.id_winget) {
                $winget = Buscar-Winget
                if (-not $winget) { throw 'winget (App Installer) no esta disponible en este equipo. Instala "App Installer" desde Microsoft Store o actualizalo.' }
                $idPaquete = $orden.parametros.id_winget
                $salida = & $winget install --id $idPaquete --exact --silent --accept-package-agreements --accept-source-agreements --disable-interactivity 2>&1 | Out-String
                if ($LASTEXITCODE -ne 0 -and $LASTEXITCODE -ne $null) { throw "winget devolvio error ($LASTEXITCODE): $salida" }
                $resultado = "Instalado con winget ('$idPaquete'). Salida: " + ($salida.Trim() -replace '\s+', ' ').Substring(0, [Math]::Min(300, $salida.Trim().Length))
            } elseif($orden.tipo -eq 'UPGRADE_WINGET') {
                $winget = Buscar-Winget
                if (-not $winget) { throw 'winget (App Installer) no esta disponible en este equipo. Instala "App Installer" desde Microsoft Store o actualizalo.' }
                $idPaquete = $orden.parametros.id_winget
                $argsWinget = if ($idPaquete) { @('upgrade','--id',$idPaquete,'--exact') } else { @('upgrade','--all') }
                $argsWinget += @('--silent','--accept-package-agreements','--accept-source-agreements','--disable-interactivity')
                $salida = & $winget @argsWinget 2>&1 | Out-String
                if ($LASTEXITCODE -ne 0 -and $LASTEXITCODE -ne $null) { throw "winget devolvio error ($LASTEXITCODE): $salida" }
                $resultado = "Actualizado con winget" + $(if ($idPaquete) { " ('$idPaquete')" } else { " (todos los paquetes)" }) + ". Salida: " + ($salida.Trim() -replace '\s+', ' ').Substring(0, [Math]::Min(300, $salida.Trim().Length))
            } elseif($orden.tipo -eq 'ACTIVATE_WINDOWS' -and $orden.parametros.clave -match '^([A-Z0-9]{5}-){4}[A-Z0-9]{5}$') {
                # slmgr es el activador oficial de Windows - se usa la clave de
                # producto tal cual, sin construir ni adivinar nada mas.
                $claveW = $orden.parametros.clave
                $salidaIpk = & cscript.exe //nologo "$env:SystemRoot\System32\slmgr.vbs" /ipk $claveW 2>&1 | Out-String
                $salidaAto = & cscript.exe //nologo "$env:SystemRoot\System32\slmgr.vbs" /ato 2>&1 | Out-String
                $resultado = "Activacion de Windows: $($salidaAto.Trim())"
            } elseif($orden.tipo -eq 'ACTIVATE_OFFICE' -and $orden.parametros.clave -match '^([A-Z0-9]{5}-){4}[A-Z0-9]{5}$') {
                # ospp.vbs es el activador oficial de Office - su ruta cambia segun
                # version/arquitectura, se busca la instalacion real en vez de
                # asumir una ruta fija.
                $ospp = Get-ChildItem "$env:ProgramFiles\Microsoft Office\Office*\ospp.vbs", "${env:ProgramFiles(x86)}\Microsoft Office\Office*\ospp.vbs" -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
                if (-not $ospp) { throw 'No se encontro ospp.vbs - Office no parece estar instalado en este equipo o es una version sin activacion por volumen.' }
                $claveO = $orden.parametros.clave
                $salidaIpk = & cscript.exe //nologo $ospp /inpkey:$claveO 2>&1 | Out-String
                $salidaAto = & cscript.exe //nologo $ospp /act 2>&1 | Out-String
                $resultado = "Activacion de Office: $($salidaAto.Trim())"
            } else { throw 'Tipo de orden no permitido.' }
        } catch { $estado='FALLIDA';$errorOrden=$_.Exception.Message }
        $body=@{accion='resultado';serial=$bios.SerialNumber;orden_id=$orden.id;estado=$estado;resultado=$resultado;error=$errorOrden}|ConvertTo-Json
        Invoke-RestMethod -Uri "$Servidor/api_agente_ordenes.php" -Method Post -Headers $headersAgente -Body $body -ContentType 'application/json; charset=utf-8' | Out-Null
    }
} catch { Write-Output "AVISO: no se pudieron consultar órdenes: $($_.Exception.Message)" }

if (-not $reporteExitoso) { exit 1 }
exit 0
