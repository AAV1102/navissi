<#
    Despliega NAVISSI-INVENTARIO al hosting real (FreeHosting / grupo10z.com.co).
    Empaqueta el proyecto (sin IMAGENES, rustdesk-server, Dockerfile, logs), lo sube por FTP
    y lo descomprime en el servidor con un script PHP temporal que se autoelimina.

    Uso:  clic derecho > Ejecutar con PowerShell   (o)   powershell -ExecutionPolicy Bypass -File DEPLOY_HOSTING.ps1
#>

$ErrorActionPreference = 'Stop'

$FtpHost  = 'panel.freehosting.com'
$FtpUser  = 'aav@grupo10z.com.co'
$FtpPass  = 'Danna0827**'
$SiteHost = 'grupo10z.com.co'
$SiteIp   = '195.201.179.80'   # usado solo para verificar el resultado tras subir

$src   = $PSScriptRoot
$stage = "$env:TEMP\navissi_stage_$(Get-Random)"
$zipPath = "$env:TEMP\navissi_deploy_$(Get-Random).zip"

Write-Host "1/5 Empaquetando archivos..." -ForegroundColor Cyan
New-Item -ItemType Directory -Path $stage | Out-Null
$excludeDirs  = @('IMAGENES', 'rustdesk-server', '.git')
$excludeFiles = @('Dockerfile', 'docker-compose.yml')
Get-ChildItem -Path $src -Force | Where-Object {
    $excludeDirs -notcontains $_.Name -and $excludeFiles -notcontains $_.Name -and $_.Name -ne 'DEPLOY_HOSTING.ps1'
} | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination (Join-Path $stage $_.Name) -Recurse -Force
}
# No subir la base de datos local ni logs: el hosting tiene su propia BD viva, no la pisamos
Remove-Item -Path (Join-Path $stage 'data\navissi.sqlite') -ErrorAction SilentlyContinue
Remove-Item -Path (Join-Path $stage 'data\n8n_err.log') -ErrorAction SilentlyContinue
Remove-Item -Path (Join-Path $stage 'data\n8n_out.log') -ErrorAction SilentlyContinue
Get-ChildItem -Path (Join-Path $stage 'data') -Filter '~$*' -Force -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue

Write-Host "2/5 Comprimiendo..." -ForegroundColor Cyan
Compress-Archive -Path "$stage\*" -DestinationPath $zipPath -CompressionLevel Optimal -Force

Write-Host "3/5 Subiendo por FTP (esto puede tardar 1-2 min)..." -ForegroundColor Cyan
$zipName = Split-Path $zipPath -Leaf
$prevEap = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
& curl.exe -s --ssl-reqd -k -u "${FtpUser}:${FtpPass}" -T "$zipPath" "ftp://$FtpHost/public_html/$zipName" *>$null
$ErrorActionPreference = $prevEap

$unzipScript = @"
<?php
header('Content-Type: text/plain; charset=utf-8');
`$zipFile = __DIR__ . '/$zipName';
if (!file_exists(`$zipFile)) { die('ERROR: no se encontro el zip'); }
`$zip = new ZipArchive();
if (`$zip->open(`$zipFile) !== true) { die('ERROR abriendo zip'); }
`$zip->extractTo(__DIR__);
`$zip->close();
unlink(`$zipFile);
unlink(__FILE__);
echo 'OK: desplegado correctamente.';
"@
$unzipPath = "$env:TEMP\_deploy_unzip_$(Get-Random).php"
Set-Content -Path $unzipPath -Value $unzipScript -Encoding UTF8
$unzipName = Split-Path $unzipPath -Leaf

Write-Host "4/5 Extrayendo en el servidor..." -ForegroundColor Cyan
$prevEap = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
& curl.exe -s --ssl-reqd -k -u "${FtpUser}:${FtpPass}" -T "$unzipPath" "ftp://$FtpHost/public_html/$unzipName" *>$null
$resultado = & curl.exe -s -H "Host: $SiteHost" "http://$SiteIp/$unzipName"
Write-Host $resultado

# Red de seguridad: si por lo que sea el auto-borrado del zip/script en el servidor
# fallo, lo forzamos por FTP para no dejar basura ni el zip completo publicamente accesible.
& curl.exe -s --ssl-reqd -k -u "${FtpUser}:${FtpPass}" -Q "DELE public_html/$zipName" "ftp://$FtpHost/public_html/" *>$null
& curl.exe -s --ssl-reqd -k -u "${FtpUser}:${FtpPass}" -Q "DELE public_html/$unzipName" "ftp://$FtpHost/public_html/" *>$null
$ErrorActionPreference = $prevEap

Write-Host "5/5 Limpiando temporales locales..." -ForegroundColor Cyan
Remove-Item -Path $stage -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item -Path $zipPath -Force -ErrorAction SilentlyContinue
Remove-Item -Path $unzipPath -Force -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "Listo. Verifica en https://$SiteHost" -ForegroundColor Green
