<# Despliegue seguro al hosting. Requiere .env.deploy.local (ignorado por Git). #>
param([switch]$DryRun)
$ErrorActionPreference = 'Stop'
$src = $PSScriptRoot
if (-not $DryRun) {
    & (Join-Path $src 'scripts\preflight_deploy.ps1') -Mode Hosting
    if ($LASTEXITCODE -ne 0) { throw 'Pre-flight rechazó el despliegue.' }
}

function Read-DeployConfig([string]$Path) {
    $values = @{}
    foreach ($line in Get-Content -LiteralPath $Path) {
        if ($line -match '^\s*([A-Z0-9_]+)=(.*)$') { $values[$Matches[1]] = $Matches[2].Trim() }
    }
    return $values
}
$cfgPath = Join-Path $src '.env.deploy.local'
if (-not $DryRun -and -not (Test-Path $cfgPath)) { throw 'Falta .env.deploy.local. No se usan credenciales dentro del código.' }
$cfg = if (Test-Path $cfgPath) { Read-DeployConfig $cfgPath } else { @{} }
$required = @('NAVISSI_FTP_HOST','NAVISSI_FTP_USER','NAVISSI_FTP_PASS','NAVISSI_SITE_HOST')
foreach ($key in $required) { if (-not $DryRun -and [string]::IsNullOrWhiteSpace($cfg[$key])) { throw "Falta $key en .env.deploy.local" } }
$FtpHost = $cfg['NAVISSI_FTP_HOST']; $FtpUser = $cfg['NAVISSI_FTP_USER']; $FtpPass = $cfg['NAVISSI_FTP_PASS']; $SiteHost = $cfg['NAVISSI_SITE_HOST']

$stage = Join-Path $env:TEMP "navissi_stage_$(Get-Random)"
$zipPath = Join-Path $env:TEMP "navissi_deploy_$(Get-Random).zip"
try {
    New-Item -ItemType Directory -Path $stage | Out-Null
    Write-Host '1/5 Empaquetando solo archivos versionados...' -ForegroundColor Cyan
    $files = @(git ls-files)
    foreach ($relative in $files) {
        if ($relative -in @('DEPLOY_HOSTING.ps1','DEPLOY_TODO.bat','.env.deploy.example')) { continue }
        $source = Join-Path $src $relative
        if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { continue }
        $destination = Join-Path $stage $relative
        New-Item -ItemType Directory -Path (Split-Path $destination) -Force | Out-Null
        Copy-Item -LiteralPath $source -Destination $destination -Force
    }
    if ($DryRun) { Write-Host "DryRun OK: $($files.Count) entradas versionadas evaluadas." -ForegroundColor Green; return }
    Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $zipPath -CompressionLevel Optimal -Force
    $zipName = Split-Path $zipPath -Leaf
    $ftpUrl = "ftp://$FtpHost/public_html/"
    Write-Host '2/5 Subiendo por FTP (el hosting no tiene certificado SSL valido; ver nota en preflight_deploy.ps1)...' -ForegroundColor Cyan
    & curl.exe --fail --silent --show-error --retry 2 -u "${FtpUser}:${FtpPass}" -T $zipPath "$ftpUrl$zipName"
    if ($LASTEXITCODE -ne 0) { throw 'Falló la carga FTP del paquete.' }

    $token = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
    $unzipName = "_deploy_unzip_$([Guid]::NewGuid().ToString('N')).php"
    $unzipPath = Join-Path $env:TEMP $unzipName
    $php = @"
<?php
if ((`$_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals('$token', `$_POST['token'] ?? '')) { http_response_code(404); exit; }
`$zipFile = __DIR__ . '/$zipName';
`$zip = new ZipArchive();
if (!is_file(`$zipFile) || `$zip->open(`$zipFile) !== true) { http_response_code(500); exit('ERROR'); }
`$zip->extractTo(__DIR__); `$zip->close(); @unlink(`$zipFile); @unlink(__FILE__); echo 'OK';
"@
    Set-Content -LiteralPath $unzipPath -Value $php -Encoding UTF8
    Write-Host '3/5 Subiendo extractor temporal autenticado...' -ForegroundColor Cyan
    & curl.exe --fail --silent --show-error --retry 2 -u "${FtpUser}:${FtpPass}" -T $unzipPath "$ftpUrl$unzipName"
    if ($LASTEXITCODE -ne 0) { throw 'Falló la carga del extractor.' }
    Write-Host '4/5 Extrayendo por HTTP (el hosting no tiene HTTPS valido)...' -ForegroundColor Cyan
    $result = & curl.exe --fail --silent --show-error --retry 2 -X POST -d "token=$token" "http://$SiteHost/$unzipName"
    if ($LASTEXITCODE -ne 0 -or $result -notmatch '^OK') { throw "Falló la extracción remota: $result" }
    # El extractor remoto elimina el ZIP y su propio archivo al terminar.
    # No se ejecutan comandos DELE adicionales: algunos servidores responden
    # 550 cuando el archivo ya fue eliminado, aunque el deploy haya sido exitoso.
    Write-Host "5/5 Listo. Verifica http://$SiteHost" -ForegroundColor Green
} finally {
    Remove-Item -LiteralPath $stage -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $zipPath -Force -ErrorAction SilentlyContinue
    if ($unzipPath) { Remove-Item -LiteralPath $unzipPath -Force -ErrorAction SilentlyContinue }
}
