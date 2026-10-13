[CmdletBinding()]
param([string]$Proyecto = '')
$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($Proyecto)) { $Proyecto = Join-Path $PSScriptRoot '..\..' }
$proyectoLocal = [IO.Path]::GetFullPath($Proyecto)
$runtimeFirma = Join-Path $proyectoLocal '.local\localhost'
$jarFirma = Join-Path $proyectoLocal 'resources\bin\hubdigital-pdf-signature.jar'
$claveMotor = Join-Path $runtimeFirma 'signature-worker.key'
New-Item -ItemType Directory -Path $runtimeFirma -Force | Out-Null
if (Get-NetTCPConnection -LocalPort 17655 -State Listen -ErrorAction SilentlyContinue) {
    throw 'El puerto local 17655 ya está ocupado. No se detienen ni reemplazan procesos existentes.'
}
$aclClave = New-Object Security.AccessControl.FileSecurity
$aclClave.SetAccessRuleProtection($true, $false)
$identidadLocal = [Security.Principal.WindowsIdentity]::GetCurrent().User
$aclClave.AddAccessRule((New-Object Security.AccessControl.FileSystemAccessRule($identidadLocal, 'FullControl', 'Allow')))
$aclClave.AddAccessRule((New-Object Security.AccessControl.FileSystemAccessRule('SYSTEM', 'FullControl', 'Allow')))
# El archivo se crea vacío y se protege antes de escribir la clave aleatoria.
[IO.File]::WriteAllBytes($claveMotor, [byte[]]@())
# Aplicar sólo la DACL evita solicitar el privilegio de auditoría de Set-Acl.
([IO.FileInfo]$claveMotor).SetAccessControl($aclClave)
$bytesClave = New-Object byte[] 32
$azarFirma = [Security.Cryptography.RandomNumberGenerator]::Create()
try { $azarFirma.GetBytes($bytesClave); [IO.File]::WriteAllBytes($claveMotor, $bytesClave) }
finally { $azarFirma.Dispose(); [Array]::Clear($bytesClave, 0, $bytesClave.Length) }
$temporalFirma = Join-Path $proyectoLocal 'storage\app\private\tmp\document-processing'
$entornoLocal = [IO.File]::ReadAllText((Join-Path $proyectoLocal '.env'))
$configTemporal = [regex]::Match($entornoLocal, '(?m)^HUBDIGITAL_TEMPORARY_DIRECTORY=(.+)$')
if ($configTemporal.Success) { $temporalFirma = $configTemporal.Groups[1].Value.Trim().Trim('"').Trim("'") }
$limitesFirma = @{
    FIRMA_MAX_PAGES = @{ Propiedad = 'max_pages'; Valor = 40 }
    FIRMA_MAX_PAGE_POINTS = @{ Propiedad = 'max_page_points'; Valor = 1440 }
    FIRMA_MAX_RENDER_PIXELS = @{ Propiedad = 'max_render_pixels'; Valor = 100000000 }
}
$opcionesLimites = @('-Dhubdigital.pdf.render_dpi=110')
foreach ($nombreLimite in $limitesFirma.Keys) {
    $limite = $limitesFirma[$nombreLimite]
    $valorEntorno = [Environment]::GetEnvironmentVariable($nombreLimite, 'Process')
    if ([string]::IsNullOrWhiteSpace($valorEntorno)) {
        $configLimite = [regex]::Match($entornoLocal, '(?m)^' + $nombreLimite + '=(.+)$')
        if ($configLimite.Success) { $valorEntorno = $configLimite.Groups[1].Value.Trim().Trim('"').Trim("'") }
    }
    if (-not [string]::IsNullOrWhiteSpace($valorEntorno)) {
        if ($valorEntorno -notmatch '^\d+$') { throw "El limite $nombreLimite debe ser un entero." }
        $limite.Valor = [long]$valorEntorno
    }
    $opcionesLimites += '-Dhubdigital.pdf.' + $limite.Propiedad + '=' + $limite.Valor
}
New-Item -ItemType Directory -Path $temporalFirma -Force | Out-Null
$confianzaAnterior = $env:HUBDIGITAL_SIGNATURE_TRUST_DIR
try {
    $env:HUBDIGITAL_SIGNATURE_TRUST_DIR = Join-Path $proyectoLocal 'resources\signature-trust'
    $argumentosFirma = @(
        '-Djava.awt.headless=true', ('-Djava.io.tmpdir="' + $temporalFirma + '"'),
        '-Dhubdigital.revocation.timeout=2', '-Dcom.sun.security.ocsp.timeout=2', '-Dcom.sun.security.crl.timeout=2',
        '-Dsun.net.client.defaultConnectTimeout=2000', '-Dsun.net.client.defaultReadTimeout=2000',
        '-Xms24m', '-Xmx160m', '-XX:+UseSerialGC', '-XX:ActiveProcessorCount=2'
    ) + $opcionesLimites + @('-jar', ('"' + $jarFirma + '"'), 'serve-local', ('"' + $claveMotor + '"'))
    $procesoFirma = Start-Process -FilePath (Get-Command java.exe -ErrorAction Stop).Source -WindowStyle Hidden -WorkingDirectory $proyectoLocal -ArgumentList $argumentosFirma -RedirectStandardOutput (Join-Path $runtimeFirma 'signature-worker.stdout.log') -RedirectStandardError (Join-Path $runtimeFirma 'signature-worker.stderr.log') -PassThru
    $procesoFirma.Id | Set-Content -LiteralPath (Join-Path $runtimeFirma 'signature-worker.pid') -Encoding ASCII
    Write-Output ('Motor local iniciado, PID ' + $procesoFirma.Id + '. Esperar LOCAL_SIGNATURE_READY en signature-worker.stdout.log.')
} finally {
    if ($null -eq $confianzaAnterior) { Remove-Item Env:HUBDIGITAL_SIGNATURE_TRUST_DIR -ErrorAction SilentlyContinue }
    else { $env:HUBDIGITAL_SIGNATURE_TRUST_DIR = $confianzaAnterior }
}
