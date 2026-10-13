[CmdletBinding()]
param([string]$Proyecto = '', [switch]$Consumir)
$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($Proyecto)) { $Proyecto = Join-Path $PSScriptRoot '..\..' }
$proyectoCola = [IO.Path]::GetFullPath($Proyecto)
$runtimeCola = Join-Path $proyectoCola '.local\localhost'
New-Item -ItemType Directory -Path $runtimeCola -Force | Out-Null
$phpCola = Get-Command php.exe -ErrorAction Stop
if ($Consumir) {
    Set-Location -LiteralPath $proyectoCola
    while ($true) {
        & $phpCola.Source artisan queue:work r2_signatures --queue=r2-signatures --tries=0 --sleep=1 --timeout=300 --memory=192
        Start-Sleep -Seconds 2
    }
}
$procesoCola = Start-Process -FilePath 'powershell.exe' -ArgumentList @(
    '-NoLogo', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', ('"' + $PSCommandPath + '"'),
    '-Proyecto', ('"' + $proyectoCola + '"'), '-Consumir'
) -WorkingDirectory $proyectoCola -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeCola 'r2-signatures.stdout.log') -RedirectStandardError (Join-Path $runtimeCola 'r2-signatures.stderr.log') -PassThru
$procesoCola.Id | Set-Content -LiteralPath (Join-Path $runtimeCola 'r2-signatures.pid') -Encoding ASCII
Write-Output ('Consumidor local exclusivo r2-signatures iniciado, PID ' + $procesoCola.Id + '.')
