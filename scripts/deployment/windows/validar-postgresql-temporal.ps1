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
$basesAisladas = [Collections.Generic.List[string]]::new()
$prefijoPruebas = 'hd_pkg_' + [Guid]::NewGuid().ToString('N').Substring(0, 12)
$raizProyecto = (Resolve-Path -LiteralPath $Proyecto).Path
$raizPruebas = Join-Path $raizProyecto ('.local\validation\' + $prefijoPruebas)
$procesosPruebas = 2
try {
    $memoriaLibre = (Get-CimInstance Win32_OperatingSystem -ErrorAction Stop).FreePhysicalMemory * 1KB
    if ([Environment]::ProcessorCount -ge 8 -and $memoriaLibre -ge 4GB) { $procesosPruebas = 4 }
} catch { # Sin inventario fiable se conservan dos procesos acotados.
}

function Assert-DirectorioPruebas {
    $esperado = [IO.Path]::GetFullPath((Join-Path $raizProyecto ('.local\validation\' + $prefijoPruebas)))
    $resuelto = [IO.Path]::GetFullPath($raizPruebas)
    if ($resuelto -ne $esperado -or $prefijoPruebas -notmatch '^hd_pkg_[a-f0-9]{12}$') {
        throw 'El directorio temporal de pruebas no pertenece al proyecto.'
    }
    $actual = $resuelto
    while ($actual -and $actual.Length -ge $raizProyecto.Length) {
        if (Test-Path -LiteralPath $actual) {
            if ((Get-Item -LiteralPath $actual).Attributes -band [IO.FileAttributes]::ReparsePoint) {
                throw 'El directorio de pruebas no admite enlaces ni junctions.'
            }
        }
        if ($actual -eq $raizProyecto) { break }
        $actual = Split-Path -Parent $actual
    }
}

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
foreach ($programa in @('pg_isready.exe', 'psql.exe', 'createdb.exe', 'dropdb.exe', 'pg_dump.exe', 'pg_restore.exe')) {
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
        Assert-DirectorioPruebas
        New-Item -ItemType Directory -Path $raizPruebas -Force | Out-Null
        $copia = Join-Path $raizPruebas 'base.dump'
        Write-Host "Preparando $procesosPruebas bases temporales aisladas; los casos no modifican la base de localhost..." -ForegroundColor Cyan
        & (Join-Path $pgBin 'pg_dump.exe') -h 127.0.0.1 -p 5432 -U postgres -d $basePruebas -Fc -f $copia
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la copia consistente de PostgreSQL.' }
        for ($tokenPruebas = 1; $tokenPruebas -le $procesosPruebas; $tokenPruebas++) {
            $baseAislada = $prefijoPruebas + '_' + $tokenPruebas
            & (Join-Path $pgBin 'createdb.exe') -h 127.0.0.1 -p 5432 -U postgres $baseAislada
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear una base temporal nueva de pruebas.' }
            $basesAisladas.Add($baseAislada)
            & (Join-Path $pgBin 'pg_restore.exe') -h 127.0.0.1 -p 5432 -U postgres -d $baseAislada --exit-on-error --no-owner --no-privileges $copia
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo restaurar la base temporal de pruebas.' }
            $env:DB_DATABASE = $env:TEST_DB_DATABASE = $baseAislada
            & $Php artisan migrate --database=pgsql --force --no-interaction
            if ($LASTEXITCODE -ne 0) { throw 'No se pudieron aplicar las migraciones en la base temporal.' }
            # pg_dump/pg_restore no conserva las estadísticas del planificador.
            # Prepararlas antes de medir las consultas evita planes de una base
            # recién restaurada, sin ampliar el presupuesto HTTP de producción.
            & (Join-Path $pgBin 'psql.exe') -h 127.0.0.1 -p 5432 -U postgres -d $baseAislada --no-psqlrc --set=ON_ERROR_STOP=1 --command='ANALYZE;'
            if ($LASTEXITCODE -ne 0) { throw 'No se pudieron preparar las estadísticas de la base temporal.' }
            foreach ($carpeta in @('app\private','framework\cache\data','framework\sessions','framework\views','logs','document-processing')) {
                New-Item -ItemType Directory -Path (Join-Path $raizPruebas ("worker-$tokenPruebas\" + $carpeta)) -Force | Out-Null
            }
        }
        Remove-Item -LiteralPath $copia -Force
        $env:DB_DATABASE = $env:TEST_DB_DATABASE = $basesAisladas[0]

        # Node necesita el ejecutable nativo: php en PATH puede ser un shim .cmd.
        # Se resuelve dentro del comando central, sin invocar un shell desde Node.
        $phpNativo = ((& $Php -r 'echo PHP_BINARY;') -join '').Trim()
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $phpNativo -PathType Leaf)) {
            throw 'No se pudo resolver el ejecutable nativo PHP para la prueba del navegador.'
        }
        foreach ($contrato in @('tests/Browser/portal-livewire.mjs', 'tests/Browser/portal-fixture.php')) {
            if (-not (Test-Path -LiteralPath (Join-Path $Proyecto $contrato) -PathType Leaf)) {
                throw "Falta la prueba obligatoria de navegador del portal: $contrato"
            }
        }
        if (-not (Get-Command 'node.exe' -ErrorAction SilentlyContinue)) { throw 'Falta Node para verificar tooltip y Livewire en el navegador real.' }
        Write-Host 'Probando tooltip durante actualizaciones Livewire y recorridos del portal en Opera Neon real...' -ForegroundColor Cyan
        & node.exe tests/Browser/portal-livewire.mjs $phpNativo
        if ($LASTEXITCODE -ne 0) { throw 'Fallo la validacion real de tooltip/Livewire o del portal. No se publica main ni se crea el paquete OCI.' }

        $env:HUBDIGITAL_TEST_DATABASE_PREFIX = $prefijoPruebas
        $env:HUBDIGITAL_TEST_RUNTIME_DIR = $raizPruebas
        Write-Host "Ejecutando la suite PHP completa en $procesosPruebas procesos con bases y almacenamiento independientes..." -ForegroundColor Cyan
        & $Php artisan test --parallel --processes=$procesosPruebas --without-databases --exclude-group=bootstrap-inicial
        $codigoPruebas = $LASTEXITCODE
    }
    finally { Pop-Location }
}
finally {
    $falloLimpieza = $false
    # También recoge bases derivadas que una versión del runner pueda crear.
    # La identidad aleatoria pertenece exclusivamente a esta ejecución.
    $derivadas = & (Join-Path $pgBin 'psql.exe') -h 127.0.0.1 -p 5432 -U postgres -d postgres -tAc "SELECT datname FROM pg_database WHERE datname LIKE '${prefijoPruebas}\_%' ESCAPE '\';"
    if ($LASTEXITCODE -ne 0) { $falloLimpieza = $true }
    foreach ($derivada in $derivadas) {
        if ($derivada -match ('^' + [regex]::Escape($prefijoPruebas) + '_[1-4](?:_test_[1-4])?$') -and -not $basesAisladas.Contains($derivada)) {
            $basesAisladas.Add($derivada)
        }
    }
    foreach ($baseAislada in $basesAisladas) {
        if ($baseAislada -notmatch ('^' + [regex]::Escape($prefijoPruebas) + '_[1-4](?:_test_[1-4])?$') -or $baseAislada -eq $basePruebas) {
            $falloLimpieza = $true
            Write-Warning 'Se impidió retirar una base que no fue creada por este comando.'
            continue
        }
        & (Join-Path $pgBin 'dropdb.exe') -h 127.0.0.1 -p 5432 -U postgres $baseAislada
        if ($LASTEXITCODE -ne 0) { $falloLimpieza = $true; Write-Warning "No se pudo retirar la base temporal $baseAislada." }
    }
    if (Test-Path -LiteralPath $raizPruebas) {
        Assert-DirectorioPruebas
        $enlaces = Get-ChildItem -LiteralPath $raizPruebas -Force -Recurse | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }
        if ($enlaces) { throw 'No se retira un directorio temporal que contiene enlaces inesperados.' }
        Remove-Item -LiteralPath $raizPruebas -Recurse -Force
    }
    foreach ($nombre in @('HUBDIGITAL_TEST_DATABASE_PREFIX','HUBDIGITAL_TEST_RUNTIME_DIR')) { Remove-Item "Env:$nombre" -ErrorAction SilentlyContinue }
    foreach ($nombre in @('PGPASSWORD','DB_PASSWORD','TEST_DB_PASSWORD','APP_KEY')) { Remove-Item "Env:$nombre" -ErrorAction SilentlyContinue }
    $clave = $null
    if ($postgresIniciadoPorPaquete) {
        Invoke-ServicioElevado -Accion stop
        Start-Sleep -Seconds 2
        Assert-PuertoCerrado
    } elseif ($postgresYaActivo) {
        Write-Host 'PostgreSQL ya estaba encendido y permanece encendido.' -ForegroundColor Green
    }
    if ($falloLimpieza) { throw 'La limpieza de las bases temporales de pruebas no terminó correctamente.' }
}

if ($codigoPruebas -ne 0) { throw "Las pruebas PostgreSQL fallaron (codigo $codigoPruebas)." }
