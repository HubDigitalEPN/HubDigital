param([string]$ConfiguracionPkcs11)

$ErrorActionPreference = 'Stop'
$jarToken = Join-Path $PSScriptRoot 'hubdigital-pdf-signature.jar'
$origenToken = (Get-Content -LiteralPath (Join-Path $PSScriptRoot 'ORIGEN.txt') -Raw).Trim()
if (-not (Get-Command java.exe -ErrorAction SilentlyContinue)) { throw 'Instala Java 17 o posterior, de la misma arquitectura que el controlador del token.' }
if (-not $ConfiguracionPkcs11) {
    Add-Type -AssemblyName System.Windows.Forms
    $selectorToken = New-Object System.Windows.Forms.OpenFileDialog
    $selectorToken.Title = 'Selecciona la biblioteca PKCS#11 instalada por el fabricante del token'
    $selectorToken.Filter = 'Biblioteca PKCS#11 (*.dll)|*.dll'
    if ($selectorToken.ShowDialog() -ne 'OK') { exit 0 }
    $bibliotecaToken = $selectorToken.FileName.Replace('\', '/')
    $ConfiguracionPkcs11 = Join-Path $PSScriptRoot 'token-pkcs11.cfg'
    $textoConfigToken = 'name = HubDigitalUsb' + [Environment]::NewLine + 'library = "' + $bibliotecaToken + '"' + [Environment]::NewLine + 'slotListIndex = 0' + [Environment]::NewLine
    [IO.File]::WriteAllText($ConfiguracionPkcs11, $textoConfigToken, (New-Object Text.UTF8Encoding($false)))
}
& java.exe '-Djava.awt.headless=true' '-cp' $jarToken 'org.hubdigital.signatures.TokenUsbBridge' $ConfiguracionPkcs11 $origenToken
exit $LASTEXITCODE
