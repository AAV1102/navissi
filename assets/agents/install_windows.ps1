# WorkManager ERP - Agente Multiplataforma (Windows)
param(
    [string]$ServerUrl = "http://localhost/Workmanager-ERP/api/agent-endpoint.php",
    [string]$AgentId = ""
)

function Send-AgentData {
    param(
        [string]$ServerUrl,
        [string]$AgentId,
        [string]$Type,
        $Content
    )

    $payload = @{
        agent_id = $AgentId
        data_type = $Type
        data_content = ($Content | ConvertTo-Json -Compress)
        timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    } | ConvertTo-Json -Compress

    Invoke-RestMethod -Uri $ServerUrl -Method Post -Body $payload -ContentType "application/json" | Out-Null
}

if ([string]::IsNullOrWhiteSpace($AgentId)) {
    $mac = (Get-CimInstance Win32_NetworkAdapterConfiguration | Where-Object { $_.IPAddress } | Select-Object -First 1).MACAddress
    $AgentId = ($env:COMPUTERNAME + "-" + ($mac -replace "[:\-]", "")).ToLower()
}

try {
    $computerInfo = Get-ComputerInfo
    $bios = Get-CimInstance Win32_BIOS
    $disk = Get-CimInstance Win32_LogicalDisk | Where-Object { $_.DeviceID -eq "C:\" }
    $net = Get-NetIPAddress | Where-Object { $_.AddressFamily -eq "IPv4" -and $_.InterfaceAlias -notlike "*Loopback*" } | Select-Object -First 1
    $macAddress = (Get-CimInstance Win32_NetworkAdapterConfiguration | Where-Object { $_.IPAddress -contains $net.IPAddress }).MACAddress

    $systemInfo = @{
        hostname = $env:COMPUTERNAME
        os_type = $computerInfo.OsName
        os_version = $computerInfo.OsVersion
        architecture = $computerInfo.OsArchitecture
        ip_address = $net.IPAddress
        mac_address = $macAddress
        serial = $bios.SerialNumber
        user = $env:USERNAME
        ram = [math]::Round($computerInfo.CsTotalPhysicalMemory / 1GB, 0).ToString() + " GB"
        disk = [math]::Round($disk.Size / 1GB, 0).ToString() + " GB"
        agent_version = "1.0.0"
    }
    Send-AgentData -ServerUrl $ServerUrl -AgentId $AgentId -Type "system_info" -Content $systemInfo

    $hardwareInfo = @{
        cpu_name = $computerInfo.CsProcessors.Name
        cpu_cores = $computerInfo.CsNumberOfLogicalProcessors
        total_ram = [math]::Round($computerInfo.CsTotalPhysicalMemory / 1GB, 2)
        disks = @(
            @{ name = $disk.DeviceID; size_gb = [math]::Round($disk.Size / 1GB, 2); free_gb = [math]::Round($disk.FreeSpace / 1GB, 2) }
        )
    }
    Send-AgentData -ServerUrl $ServerUrl -AgentId $AgentId -Type "hardware_info" -Content $hardwareInfo

    $software = @()
    $paths = @(
        "HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*",
        "HKLM:\Software\Wow6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*"
    )
    foreach ($p in $paths) {
        Get-ItemProperty $p -ErrorAction SilentlyContinue | ForEach-Object {
            if ($_.DisplayName) {
                $software += @{ name = $_.DisplayName; version = $_.DisplayVersion; vendor = $_.Publisher; type = "application" }
            }
        }
    }
    if ($software.Count -gt 200) { $software = $software | Select-Object -First 200 }
    Send-AgentData -ServerUrl $ServerUrl -AgentId $AgentId -Type "software_info" -Content $software

    $netInfo = @()
    $ips = Get-NetIPAddress | Where-Object { $_.AddressFamily -eq "IPv4" -and $_.InterfaceAlias -notlike "*Loopback*" }
    foreach ($ip in $ips) {
        $adapter = Get-NetAdapter -InterfaceIndex $ip.InterfaceIndex -ErrorAction SilentlyContinue
        $netInfo += @{ name = $ip.InterfaceAlias; ip = $ip.IPAddress; mac = $adapter.MacAddress; type = "Ethernet"; status = $adapter.Status }
    }
    Send-AgentData -ServerUrl $ServerUrl -AgentId $AgentId -Type "network_info" -Content $netInfo

    $licenses = @()
    foreach ($item in ($software | Select-Object -First 50)) {
        $licenses += @{ product_name = $item.name; license_type = "software"; status = "installed" }
    }
    Send-AgentData -ServerUrl $ServerUrl -AgentId $AgentId -Type "licenses_info" -Content $licenses

    Write-Host "Reporte enviado correctamente" -ForegroundColor Green
} catch {
    Write-Host "Error enviando reporte: $($_.Exception.Message)" -ForegroundColor Red
}
