# NAVISSI - Reportar Problema (1 clic, sin nada tecnico)
# El empleado solo escribe que le pasa. El script detecta el equipo solo
# (mismo serial que usa el agente de inventario) y arma el ticket completo.
# Si la IA lo resuelve al instante, se lo muestra ahi mismo.
#
# USO: powershell -ExecutionPolicy Bypass -File reportar_problema.ps1 -Servidor "http://IP-DEL-SERVIDOR:8099"

param([string]$Servidor = "http://127.0.0.1:8099", [string]$TokenFile = "$env:ProgramData\NAVISSI\agent.token")

Add-Type -AssemblyName Microsoft.VisualBasic
Add-Type -AssemblyName System.Windows.Forms

$descripcion = [Microsoft.VisualBasic.Interaction]::InputBox(
    "Cuentanos que te esta pasando, con tus propias palabras (ejemplo: 'no me conecta a internet', 'la pantalla se queda pegada'):",
    "NAVISSI - Reportar un problema",
    ""
)

if ([string]::IsNullOrWhiteSpace($descripcion)) {
    exit
}

$bios = Get-CimInstance -ClassName Win32_BIOS

$payload = @{
    serial          = $bios.SerialNumber
    usuario_windows = $env:USERNAME
    descripcion     = $descripcion
} | ConvertTo-Json

try {
    $token = if (Test-Path $TokenFile) { (Get-Content $TokenFile -Raw).Trim() } else { "" }
    if (-not $token) { throw "Falta la credencial del agente en $TokenFile" }
    $resp = Invoke-RestMethod -Uri "$Servidor/api_reportar_problema.php" -Method Post -Body $payload -Headers @{Authorization="Bearer $token"} -ContentType "application/json; charset=utf-8"
    if ($resp.resuelto) {
        [System.Windows.Forms.MessageBox]::Show($resp.mensaje, "Listo, ya tenemos la solucion", "OK", "Information") | Out-Null
    } else {
        [System.Windows.Forms.MessageBox]::Show($resp.mensaje, "Quedo reportado a TI", "OK", "Information") | Out-Null
    }
} catch {
    [System.Windows.Forms.MessageBox]::Show("No se pudo enviar el reporte. Avisa a TI directamente. ($($_.Exception.Message))", "Error", "OK", "Warning") | Out-Null
}
