[CmdletBinding()]
param(
    [Parameter(Mandatory)] [string]$Proyecto,
    [Parameter(Mandatory)] [string]$Php
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$servicio = 'hubdigital-postgresql-16'
$pgBin = 'C:\Program Files\PostgreSQL\16\bin'
$raizRepositorio = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $PSScriptRoot))
$archivoClave = Join-Path $raizRepositorio '.local\secrets\postgres-test-password.clixml'
$basePruebas = 'hubdigital'
$postgresYaActivo = (Get-Service -Name $servicio -ErrorAction Stop).Status -eq 'Running'
$postgresIniciadoPorPaquete = $false

function Invoke-ServicioElevado {
    param([Parameter(Mandatory)] [ValidateSet('start', 'stop')] [string]$Accion)

    $comando = if ($Accion -eq 'start') {
        "Start-Service -Name '$servicio'"
    } else {
        "Stop-Service -Name '$servicio' -Force"
    }
    Write-Host "Se solicitara UAC para $Accion PostgreSQL de pruebas." -ForegroundColor Yellow
    $proceso = Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList @('-NoProfile', '-Command', $comando) -WindowStyle Hidden -Wait -PassThru
    if ($proceso.ExitCode -ne 0) { throw "No se pudo $Accion PostgreSQL (codigo $($proceso.ExitCode))." }
}

function Assert-PuertoCerrado {
    $listener = Get-NetTCPConnection -LocalPort 5432 -State Listen -ErrorAction SilentlyContinue
    if ($listener) { throw 'SEGURIDAD: PostgreSQL fue detenido, pero el puerto 5432 continua escuchando.' }
    Write-Host 'PostgreSQL apagado; puerto 5432 cerrado.' -ForegroundColor Green
}

if (-not (Test-Path -LiteralPath $archivoClave -PathType Leaf)) { throw "No existe la credencial cifrada: $archivoClave" }
foreach ($programa in @('pg_isready.exe', 'psql.exe', 'createdb.exe')) {
    if (-not (Test-Path -LiteralPath (Join-Path $pgBin $programa) -PathType Leaf)) { throw "No se encontro $programa en PostgreSQL 16." }
}

# La misma instancia puede estar iniciada mediante pg_ctl sin servicio Windows activo.
# No iniciar ni detener el servicio por el simple hecho de usar esa instancia.
# La contraseña y la base siguen comprobándose después mediante psql y la suite completa.
if (-not $postgresYaActivo) {
    & (Join-Path $pgBin 'pg_isready.exe') -h 127.0.0.1 -p 5432 -U postgres *> $null
    $postgresYaActivo = $LASTEXITCODE -eq 0
}

$segura = Import-Clixml -LiteralPath $archivoClave
$puntero = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura)
try { $clave = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($puntero) }
finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($puntero) }

$codigoPruebas = 1
try {
    if (-not $postgresYaActivo) {
        Invoke-ServicioElevado -Accion start
        $postgresIniciadoPorPaquete = $true
    }
    $listo = $false
    for ($intento = 0; $intento -lt 30; $intento++) {
        & (Join-Path $pgBin 'pg_isready.exe') -h 127.0.0.1 -p 5432 -U postgres *> $null
        if ($LASTEXITCODE -eq 0) { $listo = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $listo) { throw 'PostgreSQL no quedo listo en 30 segundos.' }

    $env:PGPASSWORD = $clave
    $existe = & (Join-Path $pgBin 'psql.exe') -h 127.0.0.1 -p 5432 -U postgres -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$basePruebas';"
    if ($LASTEXITCODE -ne 0) { throw "No se pudo consultar la base local $basePruebas." }
    if ([string]::IsNullOrWhiteSpace([string]$existe)) {
        & (Join-Path $pgBin 'createdb.exe') -h 127.0.0.1 -p 5432 -U postgres $basePruebas
        if ($LASTEXITCODE -ne 0) { throw "No se pudo crear la base local $basePruebas." }
    }

    $env:APP_ENV = 'testing'
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = '5432'
    $env:DB_DATABASE = $basePruebas
    $env:DB_USERNAME = 'postgres'
    $env:DB_PASSWORD = $clave
    $env:DB_SSLMODE = 'prefer'
    $env:TEST_DB_HOST = '127.0.0.1'
    $env:TEST_DB_PORT = '5432'
    $env:TEST_DB_DATABASE = $basePruebas
    $env:TEST_DB_USERNAME = 'postgres'
    $env:TEST_DB_PASSWORD = $clave
    $lineaClave = Get-Content -LiteralPath (Join-Path $Proyecto '.env') |
        Where-Object { $_.StartsWith('APP_KEY=') } |
        Select-Object -First 1
    if ([string]::IsNullOrWhiteSpace($lineaClave)) { throw 'Falta APP_KEY en el .env local.' }
    $env:APP_KEY = $lineaClave.Substring('APP_KEY='.Length).Trim('"')

    Push-Location $Proyecto
    try {
        Write-Host "Aplicando migraciones pendientes en la base local unica $basePruebas..." -ForegroundColor Cyan
        & $Php artisan migrate --database=pgsql --force --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'No se pudieron aplicar las migraciones locales.' }

        Write-Host 'Ejecutando la suite PHP con PostgreSQL local (sin bootstrap inicial)...' -ForegroundColor Cyan
        & $Php artisan test --exclude-group=bootstrap-inicial
        $codigoPruebas = $LASTEXITCODE
    }
    finally { Pop-Location }
}
finally {
    foreach ($nombre in @('PGPASSWORD','DB_PASSWORD','TEST_DB_PASSWORD','APP_KEY')) { Remove-Item "Env:$nombre" -ErrorAction SilentlyContinue }
    $clave = $null
    if ($postgresIniciadoPorPaquete) {
        Invoke-ServicioElevado -Accion stop
        Start-Sleep -Seconds 2
        Assert-PuertoCerrado
    } elseif ($postgresYaActivo) {
        Write-Host 'PostgreSQL ya estaba encendido y permanece encendido.' -ForegroundColor Green
    }
}

if ($codigoPruebas -ne 0) { throw "Las pruebas PostgreSQL fallaron (codigo $codigoPruebas)." }
