[CmdletBinding()]
param([string]$Proyecto = '')
$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($Proyecto)) { $Proyecto = Join-Path $PSScriptRoot '..\..' }
$proyectoLocal = [IO.Path]::GetFullPath($Proyecto)
$runtimeFirma = Join-Path $proyectoLocal '.local\localhost'
$jarFirma = Join-Path $proyectoLocal 'resources\bin\hubdigital-pdf-signature.jar'
$claveMotor = Join-Path $runtimeFirma 'signature-worker.key'
New-Item -ItemType Directory -Path $runtimeFirma -Force | Out-Null
function Wait-MotorFirma([int]$IdMotorFirma) {
    $limitePreparacionFirma = (Get-Date).AddSeconds(90)
    do {
        if (!(Get-Process -Id $IdMotorFirma -ErrorAction SilentlyContinue)) {
            throw 'El motor local de firma terminó antes de prepararse.'
        }
        # Start-Process mantiene el log abierto mientras redirige stdout.
        $salidaFirma = Get-Content -LiteralPath (Join-Path $runtimeFirma 'signature-worker.stdout.log') -Raw -ErrorAction SilentlyContinue
        if ($salidaFirma -match '(?m)^LOCAL_SIGNATURE_READY\s*$') { return }
        Start-Sleep -Milliseconds 100
    } while ((Get-Date) -lt $limitePreparacionFirma)
    throw 'El motor local no quedó preparado en 90 segundos.'
}
$escuchasFirma = @(Get-NetTCPConnection -LocalPort 17655 -State Listen -ErrorAction SilentlyContinue)
if ($escuchasFirma) {
    $propietariosFirma = @($escuchasFirma.OwningProcess | Sort-Object -Unique)
    $motorExistente = if ($propietariosFirma.Count -eq 1) { Get-CimInstance Win32_Process -Filter "ProcessId = $($propietariosFirma[0])" }
    if (@($escuchasFirma | Where-Object { $_.LocalAddress -notin @('127.0.0.1', '::1') }).Count -gt 0 -or
        !$motorExistente -or !$motorExistente.CommandLine.Contains($jarFirma) -or
        !$motorExistente.CommandLine.Contains($claveMotor) -or !$motorExistente.CommandLine.Contains('serve-local') -or
        !(Test-Path -LiteralPath $claveMotor -PathType Leaf) -or (Get-Item -LiteralPath $claveMotor).Length -ne 32) {
        throw 'El puerto 17655 pertenece a otro proceso o configuración. No se reemplaza.'
    }
    if ((Get-Item -LiteralPath $jarFirma).LastWriteTimeUtc -gt $motorExistente.CreationDate.ToUniversalTime()) {
        # El cliente exige el hash del JAR activo. Un proceso anterior al build
        # provocaría el respaldo CLI frío aunque siga escuchando en loopback.
        Stop-Process -Id $motorExistente.ProcessId -ErrorAction Stop
        Wait-Process -Id $motorExistente.ProcessId -Timeout 10 -ErrorAction SilentlyContinue
        Write-Output 'Motor local anterior al build detenido.'
    } else {
        Wait-MotorFirma -IdMotorFirma $motorExistente.ProcessId
        $motorExistente.ProcessId | Set-Content -LiteralPath (Join-Path $runtimeFirma 'signature-worker.pid') -Encoding ASCII
        Write-Output ('Motor local preparado reutilizado, PID ' + $motorExistente.ProcessId + '.')
        return
    }
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
    try { Wait-MotorFirma -IdMotorFirma $procesoFirma.Id }
    catch {
        Stop-Process -Id $procesoFirma.Id
        throw
    }
    # Oracle javapath puede lanzar otro java.exe. Administrar el propietario
    # real del puerto evita dejar un motor huérfano al detener localhost.
    $puertoPreparado = Get-NetTCPConnection -LocalPort 17655 -State Listen -ErrorAction Stop |
        Where-Object LocalAddress -eq '127.0.0.1' | Select-Object -First 1
    $motorReal = if ($puertoPreparado) { Get-CimInstance Win32_Process -Filter "ProcessId = $($puertoPreparado.OwningProcess)" }
    if (!$motorReal -or !$motorReal.CommandLine.Contains($jarFirma) -or
        !$motorReal.CommandLine.Contains($claveMotor) -or !$motorReal.CommandLine.Contains('serve-local')) {
        throw 'No se pudo identificar el proceso real del motor local.'
    }
    $motorReal.ProcessId | Set-Content -LiteralPath (Join-Path $runtimeFirma 'signature-worker.pid') -Encoding ASCII
    Write-Output ('Motor local preparado, PID ' + $motorReal.ProcessId + '.')
} finally {
    if ($null -eq $confianzaAnterior) { Remove-Item Env:HUBDIGITAL_SIGNATURE_TRUST_DIR -ErrorAction SilentlyContinue }
    else { $env:HUBDIGITAL_SIGNATURE_TRUST_DIR = $confianzaAnterior }
}
