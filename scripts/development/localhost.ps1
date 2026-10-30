[CmdletBinding()]
param(
    [ValidateSet('iniciar', 'detener', 'estado')][string]$Accion = 'iniciar',
    [string]$BaseDatos = 'hubdigital',
    [string]$UsuarioBD = 'postgres',
    [switch]$SinVite
)

$ErrorActionPreference = 'Stop'
$raizLocal = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$runtimeLocal = Join-Path $raizLocal '.local\localhost'
$archivoEstadoLocal = Join-Path $runtimeLocal 'procesos.clixml'
$routerLocal = Join-Path $raizLocal 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
$viteLocal = Join-Path $raizLocal 'node_modules\vite\bin\vite.js'
$pythonETELocal = Join-Path $raizLocal '.local\ete-venv\Scripts\python.exe'
$servidorETELocal = Join-Path $raizLocal 'resources\python\portal_ete\server.py'

function Get-ProcesoLocal($Entrada) {
    $procesoLocal = Get-Process -Id $Entrada.Id -ErrorAction SilentlyContinue
    if (!$procesoLocal) { return $null }
    if ($procesoLocal.StartTime.ToUniversalTime().Ticks -ne $Entrada.Inicio) { return $null }
    $detalleLocal = Get-CimInstance Win32_Process -Filter "ProcessId = $($Entrada.Id)"
    if (!$detalleLocal.CommandLine -or !$detalleLocal.CommandLine.Contains($Entrada.Ruta)) { return $null }
    return $procesoLocal
}

$estadoLocal = if (Test-Path -LiteralPath $archivoEstadoLocal) { Import-Clixml -LiteralPath $archivoEstadoLocal } else { @() }
if ($Accion -eq 'estado') {
    foreach ($entradaLocal in $estadoLocal) {
        Write-Host "$($entradaLocal.Nombre): $(if (Get-ProcesoLocal $entradaLocal) { 'encendido' } else { 'apagado' })"
    }
    if (!$estadoLocal) { Write-Host 'Localhost apagado.' }
    exit
}
if ($Accion -eq 'detener') {
    foreach ($entradaLocal in $estadoLocal) {
        $procesoLocal = Get-ProcesoLocal $entradaLocal
        if ($procesoLocal) { Stop-Process -Id $procesoLocal.Id }
    }
    if (Test-Path -LiteralPath $archivoEstadoLocal) { Remove-Item -LiteralPath $archivoEstadoLocal }
    $hotLocal = Join-Path $raizLocal 'public\hot'
    if ((Test-Path -LiteralPath $hotLocal) -and ([IO.File]::ReadAllText($hotLocal).Trim() -match '^http://localhost:5173/?$')) {
        Remove-Item -LiteralPath $hotLocal
    }
    Write-Host 'Localhost apagado; PostgreSQL conserva su estado.'
    exit
}

if (@($estadoLocal | Where-Object { Get-ProcesoLocal $_ }).Count -gt 0) {
    Write-Host 'Ya hay procesos de localhost encendidos. Usa -Accion estado o -Accion detener.'
    exit
}
$puertosLocal = if ($SinVite) { @(8000, 8001) } else { @(8000, 8001, 5173) }
foreach ($puertoLocal in $puertosLocal) {
    if (Get-NetTCPConnection -State Listen -LocalPort $puertoLocal -ErrorAction SilentlyContinue) {
        throw "El puerto $puertoLocal está ocupado. Este comando no detiene procesos ajenos."
    }
}
$phpLocal = Get-Command php.exe -ErrorAction SilentlyContinue
if ($phpLocal) { $ejecutablePhpLocal = $phpLocal.Source }
else {
    $shimLocal = Get-Command php.cmd -ErrorAction Stop
    $rutaShimLocal = [IO.File]::ReadAllText($shimLocal.Source)
    if ($rutaShimLocal -notmatch '"([^"\r\n]+php\.exe)"') { throw 'No se encontró el ejecutable nativo de PHP.' }
    $ejecutablePhpLocal = $Matches[1]
}
$ejecutableNodeLocal = (Get-Command node.exe -ErrorAction Stop).Source
foreach ($rutaLocal in @($ejecutablePhpLocal, $routerLocal, $viteLocal, $pythonETELocal, $servidorETELocal)) {
    if (!(Test-Path -LiteralPath $rutaLocal -PathType Leaf)) { throw "Falta el archivo necesario: $rutaLocal" }
}
New-Item -ItemType Directory -Path $runtimeLocal -Force | Out-Null

# Sirve la aplicación y prepara sus vistas; no migra ni compila Java/Vite ni ejecuta suites.
$variablesLocal = @{
    APP_ENV = 'local'; APP_DEBUG = 'true'; APP_URL = 'http://localhost:8000';
    APP_CONFIG_CACHE = (Join-Path $runtimeLocal 'config-sin-cache.php');
    APP_ROUTES_CACHE = (Join-Path $runtimeLocal 'routes-sin-cache.php');
    DB_CONNECTION = 'pgsql'; DB_HOST = '127.0.0.1'; DB_PORT = '5432'; DB_SSLMODE = 'prefer';
    DB_DATABASE = $BaseDatos; DB_USERNAME = $UsuarioBD;
    SESSION_DRIVER = 'file'; SESSION_DOMAIN = 'null'; SESSION_SECURE_COOKIE = 'false';
    CACHE_STORE = 'file'; QUEUE_CONNECTION = 'sync'; MAIL_MAILER = 'log'
    # Claves oficiales de prueba de Cloudflare: solo en este servidor loopback.
    # Se mantienen el widget y siteverify; no se exportan a .env ni a OCI.
    TURNSTILE_ENABLED = 'true'
    TURNSTILE_SITE_KEY = '1x00000000000000000000AA'
    TURNSTILE_SECRET = '1x0000000000000000000000000000000AA'
    # La respuesta dummy no acredita hostname ni action reales.
    TURNSTILE_EXPECTED_HOSTNAME = 'null'
    TURNSTILE_EXPECTED_ACTION = 'null'
    JAVA_SIGNATURE_WORKER_ENABLED = 'true'
    PORTAL_ETE_URL = 'http://127.0.0.1:8001'; QT_QPA_PLATFORM = 'offscreen'
}
$archivoKeyLocal = Join-Path $runtimeLocal 'app-key.txt'
if (!(Test-Path -LiteralPath $archivoKeyLocal)) {
    $bytesLocal = New-Object byte[] 32
    $azarLocal = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $azarLocal.GetBytes($bytesLocal) } finally { $azarLocal.Dispose() }
    [IO.File]::WriteAllText($archivoKeyLocal, ('base64:' + [Convert]::ToBase64String($bytesLocal)))
}
$variablesLocal.APP_KEY = [IO.File]::ReadAllText($archivoKeyLocal).Trim()
if (!$env:DB_PASSWORD) {
    $secretoLocal = Join-Path $raizLocal '.local\secrets\postgres-test-password.clixml'
    if (!(Test-Path -LiteralPath $secretoLocal)) { throw 'Configura DB_PASSWORD para la base local antes de iniciar.' }
    $seguraLocal = Import-Clixml -LiteralPath $secretoLocal
    $punteroLocal = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($seguraLocal)
    try { $variablesLocal.DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($punteroLocal) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($punteroLocal) }
}
$anterioresLocal = @{}
$iniciadosLocal = @()
try {
    foreach ($nombreLocal in $variablesLocal.Keys) {
        $anterioresLocal[$nombreLocal] = [Environment]::GetEnvironmentVariable($nombreLocal, 'Process')
        [Environment]::SetEnvironmentVariable($nombreLocal, $variablesLocal[$nombreLocal], 'Process')
    }
    # Preparar también las vistas privadas antes de la primera visita autenticada.
    & $ejecutablePhpLocal (Join-Path $raizLocal 'artisan') hub:prepare-local-views --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron preparar las vistas de localhost.' }
    & (Join-Path $PSScriptRoot 'start-signature-worker.ps1') -Proyecto $raizLocal
    $idFirmaLocal = [int]([IO.File]::ReadAllText((Join-Path $runtimeLocal 'signature-worker.pid')).Trim())
    $firmaProcesoLocal = Get-Process -Id $idFirmaLocal -ErrorAction Stop
    $iniciadosLocal += [pscustomobject]@{ Nombre = 'Firma'; Id = $firmaProcesoLocal.Id; Inicio = $firmaProcesoLocal.StartTime.ToUniversalTime().Ticks; Ruta = (Join-Path $raizLocal 'resources\bin\hubdigital-pdf-signature.jar') }
    $artisanLocal = Join-Path $raizLocal 'artisan'
    $r2ProcesoLocal = Start-Process -FilePath $ejecutablePhpLocal -ArgumentList @(('"' + $artisanLocal + '"'), 'queue:work', 'r2_signatures', '--queue=r2-signatures', '--sleep=1', '--memory=192', '--timeout=300') -WorkingDirectory $raizLocal -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeLocal 'r2-signatures.stdout.log') -RedirectStandardError (Join-Path $runtimeLocal 'r2-signatures.stderr.log')
    $iniciadosLocal += [pscustomobject]@{ Nombre = 'R2'; Id = $r2ProcesoLocal.Id; Inicio = $r2ProcesoLocal.StartTime.ToUniversalTime().Ticks; Ruta = $artisanLocal }
    $eteProcesoLocal = Start-Process -FilePath $pythonETELocal -ArgumentList @(('"' + $servidorETELocal + '"'), '--port', '8001') -WorkingDirectory $raizLocal -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeLocal 'ete.log') -RedirectStandardError (Join-Path $runtimeLocal 'ete-error.log')
    $iniciadosLocal += [pscustomobject]@{ Nombre = 'ETE'; Id = $eteProcesoLocal.Id; Inicio = $eteProcesoLocal.StartTime.ToUniversalTime().Ticks; Ruta = $servidorETELocal }
    $phpProcesoLocal = Start-Process -FilePath $ejecutablePhpLocal -ArgumentList @('-d', 'max_execution_time=180', '-d', 'opcache.enable_cli=1', '-d', 'upload_max_filesize=50M', '-d', 'post_max_size=55M', '-S', 'localhost:8000', ('"' + $routerLocal + '"')) -WorkingDirectory (Join-Path $raizLocal 'public') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeLocal 'php.log') -RedirectStandardError (Join-Path $runtimeLocal 'php-error.log')
    $iniciadosLocal += [pscustomobject]@{ Nombre = 'PHP'; Id = $phpProcesoLocal.Id; Inicio = $phpProcesoLocal.StartTime.ToUniversalTime().Ticks; Ruta = $routerLocal }
    if (!$SinVite) {
        $viteProcesoLocal = Start-Process -FilePath $ejecutableNodeLocal -ArgumentList @(('"' + $viteLocal + '"'), '--host', 'localhost', '--port', '5173', '--strictPort') -WorkingDirectory $raizLocal -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeLocal 'vite.log') -RedirectStandardError (Join-Path $runtimeLocal 'vite-error.log')
        $iniciadosLocal += [pscustomobject]@{ Nombre = 'Vite'; Id = $viteProcesoLocal.Id; Inicio = $viteProcesoLocal.StartTime.ToUniversalTime().Ticks; Ruta = $viteLocal }
    }
    $iniciadosLocal | Export-Clixml -LiteralPath $archivoEstadoLocal
    # Preparar los caminos reales de lectura antes de entregar localhost al usuario.
    # Evita que su primera navegación pague la carga fría de PHP, Blade y catálogo.
    foreach ($paginaLocal in @('/register', '/portal/catalogo?vista=mapa')) {
        $respuestaLocal = Invoke-WebRequest -Uri ('http://localhost:8000' + $paginaLocal) -UseBasicParsing -TimeoutSec 90
        if ($respuestaLocal.StatusCode -ne 200) { throw "No se pudo preparar $paginaLocal." }
        Write-Host ('Preparado ' + $paginaLocal + ': ' + $respuestaLocal.Headers['Server-Timing'])
    }
    Write-Host 'Localhost encendido: http://localhost:8000/portal/catalogo?vista=mapa'
    Write-Host 'Para apagar: .\scripts\development\localhost.ps1 -Accion detener'
} catch {
    foreach ($entradaLocal in $iniciadosLocal) {
        $procesoLocal = Get-ProcesoLocal $entradaLocal
        if ($procesoLocal) { Stop-Process -Id $procesoLocal.Id }
    }
    throw
} finally {
    foreach ($nombreLocal in $anterioresLocal.Keys) { [Environment]::SetEnvironmentVariable($nombreLocal, $anterioresLocal[$nombreLocal], 'Process') }
}
