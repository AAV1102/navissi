# NAVISSI - Reportar Problema (1 clic, sin nada tecnico)
# El empleado solo escribe que le pasa. El script detecta el equipo solo
# (mismo serial que usa el agente de inventario) y arma el ticket completo.
# Si la IA lo resuelve al instante, se lo muestra ahi mismo.
#
# USO: powershell -ExecutionPolicy Bypass -File reportar_problema.ps1 -Servidor "http://IP-DEL-SERVIDOR:8099"

param([string]$Servidor = "http://127.0.0.1:8099", [string]$TokenFile = "$env:ProgramData\NAVISSI\agent.token")

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Add-Type -AssemblyName Microsoft.VisualBasic

$bios = Get-CimInstance -ClassName Win32_BIOS
$cs = Get-CimInstance -ClassName Win32_ComputerSystem
$form = New-Object System.Windows.Forms.Form
$form.Text = 'NAVISSI - Mesa de Ayuda'
$form.Size = New-Object System.Drawing.Size(570,430)
$form.StartPosition = 'CenterScreen'; $form.FormBorderStyle = 'FixedDialog'; $form.MaximizeBox = $false
$titulo = New-Object System.Windows.Forms.Label
$titulo.Text = '¿Cómo podemos ayudarte?'; $titulo.Font = New-Object System.Drawing.Font('Segoe UI',16,[System.Drawing.FontStyle]::Bold)
$titulo.Location = New-Object System.Drawing.Point(24,20); $titulo.AutoSize=$true; $form.Controls.Add($titulo)
$info = New-Object System.Windows.Forms.Label
$info.Text = "Usuario: $env:USERNAME    Equipo: $($env:COMPUTERNAME)    Serial: $($bios.SerialNumber)"
$info.Location=New-Object System.Drawing.Point(27,62); $info.Size=New-Object System.Drawing.Size(510,35); $form.Controls.Add($info)
$label = New-Object System.Windows.Forms.Label
$label.Text='Describe lo que ocurre. El equipo y tu usuario se asociarán automáticamente al ticket:'
$label.Location=New-Object System.Drawing.Point(27,105); $label.Size=New-Object System.Drawing.Size(505,40); $form.Controls.Add($label)
$texto=New-Object System.Windows.Forms.TextBox
$texto.Multiline=$true; $texto.ScrollBars='Vertical'; $texto.Font=New-Object System.Drawing.Font('Segoe UI',11)
$texto.Location=New-Object System.Drawing.Point(30,145); $texto.Size=New-Object System.Drawing.Size(490,135); $form.Controls.Add($texto)
$estado=New-Object System.Windows.Forms.Label
$estado.Text='La IA hará el diagnóstico inicial y, si es necesario, lo asignará al área correcta.'
$estado.Location=New-Object System.Drawing.Point(30,292); $estado.Size=New-Object System.Drawing.Size(490,35); $form.Controls.Add($estado)
$enviar=New-Object System.Windows.Forms.Button
$enviar.Text='Crear ticket'; $enviar.Location=New-Object System.Drawing.Point(365,335); $enviar.Size=New-Object System.Drawing.Size(155,38); $enviar.DialogResult=[System.Windows.Forms.DialogResult]::OK; $form.AcceptButton=$enviar; $form.Controls.Add($enviar)
$cancelar=New-Object System.Windows.Forms.Button
$cancelar.Text='Cancelar'; $cancelar.Location=New-Object System.Drawing.Point(250,335); $cancelar.Size=New-Object System.Drawing.Size(100,38); $cancelar.DialogResult=[System.Windows.Forms.DialogResult]::Cancel; $form.CancelButton=$cancelar; $form.Controls.Add($cancelar)
$resultado=$form.ShowDialog()
$descripcion=$texto.Text

if ($resultado -ne [System.Windows.Forms.DialogResult]::OK -or [string]::IsNullOrWhiteSpace($descripcion)) {
    exit
}
$serialAfectado = [Microsoft.VisualBasic.Interaction]::InputBox("Si el problema es de ESTE equipo, deja vacío.`nSi es de otro equipo, escribe su serial o placa (opcional):", "Equipo afectado", "")

$payload = @{
    serial          = if ([string]::IsNullOrWhiteSpace($serialAfectado)) { $bios.SerialNumber } else { $serialAfectado.Trim() }
    usuario_windows = $env:USERNAME
    descripcion     = $descripcion
} | ConvertTo-Json

try {
    $token = if (Test-Path $TokenFile) { (Get-Content $TokenFile -Raw).Trim() } else { "" }
    if (-not $token) { throw "Falta la credencial del agente en $TokenFile" }
    $resp = Invoke-RestMethod -Uri "$Servidor/api_reportar_problema.php" -Method Post -Body $payload -Headers @{Authorization="Bearer $token"} -ContentType "application/json; charset=utf-8"
    if ($resp.resuelto) {
        [System.Windows.Forms.MessageBox]::Show("Ticket #$($resp.ticket_id)`n`n$($resp.mensaje)", "Solución encontrada por NAVISSI", "OK", "Information") | Out-Null
    } else {
        $tecnico = if ($resp.asignado_a) { "`nAsignado a: $($resp.asignado_a)" } else { '' }
        [System.Windows.Forms.MessageBox]::Show("Ticket #$($resp.ticket_id)`nEstado: $($resp.estado)$tecnico`n`n$($resp.mensaje)", "Solicitud registrada", "OK", "Information") | Out-Null
    }
} catch {
    [System.Windows.Forms.MessageBox]::Show("No se pudo enviar el reporte. Avisa a TI directamente. ($($_.Exception.Message))", "Error", "OK", "Warning") | Out-Null
}
