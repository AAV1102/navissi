param(
    [ValidateSet('Source','Hosting','DryRun')]
    [string]$Mode = 'Source'
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
Set-Location $repo

function Fail([string]$Message) {
    Write-Error "PRE-FLIGHT BLOQUEADO: $Message"
    exit 1
}

$tracked = @(git ls-files)
if ($LASTEXITCODE -ne 0 -or $tracked.Count -eq 0) { Fail 'no se pudo leer el manifiesto Git' }
$forbiddenTracked = @($tracked | Where-Object { $_ -notin @('.env.example','.env.deploy.example') -and (($_ -match '(^|/)(\.env|private/|data/.*\.(sqlite|db|json)$)') -or ($_ -match '(^|/)\.env\.' )) })
if ($forbiddenTracked.Count -gt 0) { Fail ("hay archivos sensibles versionados: " + ($forbiddenTracked -join ', ')) }

$configPath = Join-Path $repo '.env.deploy.local'
if ($Mode -eq 'Hosting') {
    if (-not (Test-Path -LiteralPath $configPath)) { Fail 'falta .env.deploy.local; use .env.deploy.example y coloque credenciales rotadas' }
    $config = Get-Content -LiteralPath $configPath -ErrorAction Stop
    foreach ($key in @('NAVISSI_FTP_HOST','NAVISSI_FTP_USER','NAVISSI_FTP_PASS','NAVISSI_SITE_HOST')) {
        $line = $config | Where-Object { $_ -match "^$key=" } | Select-Object -First 1
        if (-not $line -or [string]::IsNullOrWhiteSpace(($line -split '=',2)[1]) -or $line -match 'CAMBIAR|usuario-ftp') { Fail "valor inválido o ausente: $key" }
    }
}

$status = @(git status --porcelain)
if ($Mode -eq 'Hosting' -and $status.Count -gt 0) { Fail 'hay cambios locales sin commit; no se desplegará un estado incompleto' }
if (-not (Select-String -Path (Join-Path $repo 'DEPLOY_HOSTING.ps1') -Pattern 'git ls-files' -Quiet)) { Fail 'el script de hosting no usa manifiesto versionado' }
if (Select-String -Path (Join-Path $repo 'DEPLOY_HOSTING.ps1') -Pattern '\-k' -Quiet) { Fail 'el script de hosting desactiva la validación TLS; renueve el certificado del proveedor antes de desplegar' }

Write-Host "Pre-flight OK ($Mode): $($tracked.Count) archivos versionados; datos locales fuera del paquete." -ForegroundColor Green
if ($Mode -eq 'DryRun') { $tracked | Select-Object -First 20 | ForEach-Object { Write-Host "  $_" } }
