<#
.SYNOPSIS
Valida, publica en Git y crea un paquete de despliegue OCI para HubDigital.

Publica exclusivamente en main de HubDigitalEPN/HubDigital. Antes de publicar
exige todas las pruebas y compilaciones. Las opciones de omision se conservan
para mostrar un error claro: ya no pueden producir un paquete para main.
Solicita un token de HubDigital de forma oculta, o usa HUBDIGITAL_GITHUB_TOKEN
si ya existe en el entorno. No consulta el gestor global de credenciales de Git.

.EXAMPLE
crear-paquete-oci -DescripcionCambio "actualiza solicitudes de deposito"
#>
[CmdletBinding()]
param(
    [string]$Proyecto,
    [string]$Destino,
    [string]$DescripcionCambio,
    [switch]$OmitirCompilacion,
    [switch]$OmitirPruebasPHP,
    [Alias('OmitirPruebasPostgreSQL', 'SoloFrontend')]
    [switch]$SinPostgres
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$directorioScript = Split-Path -Parent $MyInvocation.MyCommand.Path
$raizRepositorio = Split-Path -Parent (Split-Path -Parent $directorioScript)
if ([string]::IsNullOrWhiteSpace($Proyecto)) { $Proyecto = $raizRepositorio }

function Invoke-Comando {
    param(
        [Parameter(Mandatory)] [string]$Programa,
        [Parameter(Mandatory)] [string[]]$Argumentos,
        [Parameter(Mandatory)] [string]$Descripcion,
        [string]$DirectorioTrabajo
    )

    Write-Host "`n==> $Descripcion" -ForegroundColor Cyan
    if ($DirectorioTrabajo) { Push-Location $DirectorioTrabajo }
    try {
        # .gitattributes ya fija LF. En Windows, Git avisa por cada archivo CRLF
        # aunque la conversion sea correcta; ese ruido puede saturar la consola.
        # PowerShell desenvuelve un array de un elemento al salir de `if`.
        # Mantener string[] evita que el splatting divida una ruta en letras.
        [string[]]$argumentosEjecucion = @($Argumentos)
        if ([IO.Path]::GetFileNameWithoutExtension($Programa) -eq 'git') {
            $argumentosEjecucion = @('-c', 'core.safecrlf=false') + $argumentosEjecucion
        }
        & $Programa @argumentosEjecucion
        if ($LASTEXITCODE -ne 0) { throw "Fallo: $Descripcion (codigo $LASTEXITCODE)." }
    }
    finally {
        if ($DirectorioTrabajo) { Pop-Location }
    }
}

function Get-TokenGitHubHubDigital {
    $token = $env:HUBDIGITAL_GITHUB_TOKEN
    if ([string]::IsNullOrWhiteSpace($token)) {
        if (-not [Environment]::UserInteractive) {
            throw 'Falta HUBDIGITAL_GITHUB_TOKEN. Ejecute el paquete interactivamente o entregue el token en esa variable de entorno.'
        }
        $tokenSeguro = Read-Host 'Token GitHub con escritura en HubDigitalEPN/HubDigital' -AsSecureString
        $puntero = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($tokenSeguro)
        try {
            $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($puntero)
        }
        finally {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($puntero)
        }
    }
    if ([string]::IsNullOrWhiteSpace($token)) { throw 'El token GitHub de HubDigital no puede estar vacio.' }
    return $token.Trim()
}

function Invoke-GitOficial {
    param(
        [Parameter(Mandatory)] [string[]]$Argumentos,
        [Parameter(Mandatory)] [string]$Descripcion
    )

    # Solo estas operaciones de origin reciben la credencial de HubDigital.
    # La configuracion de Git Credential Manager de otros repositorios no se toca.
    $nombres = @('GIT_CONFIG_COUNT', 'GIT_CONFIG_KEY_0', 'GIT_CONFIG_VALUE_0',
        'GIT_TERMINAL_PROMPT', 'GIT_TRACE_REDACT')
    $anteriores = @{}
    foreach ($nombre in $nombres) {
        $anteriores[$nombre] = [Environment]::GetEnvironmentVariable($nombre, 'Process')
    }
    try {
        $env:GIT_CONFIG_COUNT = '1'
        $env:GIT_CONFIG_KEY_0 = "http.$($repositorioGit).git.extraHeader"
        $env:GIT_CONFIG_VALUE_0 = "AUTHORIZATION: basic $autorizacionGit"
        $env:GIT_TERMINAL_PROMPT = '0'
        $env:GIT_TRACE_REDACT = '1'
        # El valor vacio en -c desactiva los helpers heredados. En PowerShell
        # 5.1, una variable de entorno vacia se elimina y no sirve para esto.
        Invoke-Comando -Programa 'git.exe' -Argumentos (@('-c', 'credential.helper=') + $Argumentos) -Descripcion $Descripcion
    }
    finally {
        foreach ($nombre in $nombres) {
            [Environment]::SetEnvironmentVariable($nombre, $anteriores[$nombre], 'Process')
        }
    }
}

function Convertir-NombreSeguro {
    param([Parameter(Mandatory)] [string]$Texto)

    $normalizado = $Texto.Normalize([Text.NormalizationForm]::FormD)
    $sinAcentos = -join ($normalizado.ToCharArray() | Where-Object {
        [Globalization.CharUnicodeInfo]::GetUnicodeCategory($_) -ne [Globalization.UnicodeCategory]::NonSpacingMark
    })
    $seguro = ($sinAcentos.Normalize([Text.NormalizationForm]::FormC) -replace '[^A-Za-z0-9._-]+', '-').Trim('-', '.', '_')
    return $seguro.ToLowerInvariant()
}

function Invoke-TarConProgreso {
    param(
        [Parameter(Mandatory)] [string[]]$Argumentos,
        [Parameter(Mandatory)] [string]$ArchivoSalida
    )

    Write-Host "`n==> Creando bundle fuente filtrado" -ForegroundColor Cyan
    Write-Host 'La compresion puede tardar varios minutos. Se mostrara actividad cada 5 segundos.'
    $argumentosNativos = $Argumentos | ForEach-Object {
        if ($_ -match '[\s"]') { '"' + $_.Replace('"', '\"') + '"' } else { $_ }
    }
    $inicioProceso = [Diagnostics.ProcessStartInfo]::new()
    $inicioProceso.FileName = (Get-Command 'tar.exe').Source
    $inicioProceso.Arguments = $argumentosNativos -join ' '
    $inicioProceso.UseShellExecute = $false
    $proceso = [Diagnostics.Process]::new()
    $proceso.StartInfo = $inicioProceso
    if (-not $proceso.Start()) { throw 'No se pudo iniciar tar.exe.' }

    $inicio = Get-Date
    while (-not $proceso.WaitForExit(5000)) {
        $transcurrido = (Get-Date) - $inicio
        $tamanoActual = if (Test-Path -LiteralPath $ArchivoSalida) {
            [Math]::Round((Get-Item -LiteralPath $ArchivoSalida).Length / 1MB, 2)
        } else { 0 }
        Write-Host ('  Trabajando... tiempo {0:hh\:mm\:ss} | paquete {1:N2} MiB' -f $transcurrido, $tamanoActual)
        $proceso.Refresh()
    }

    $proceso.WaitForExit()
    $codigoSalida = $proceso.ExitCode
    $proceso.Dispose()
    if ($codigoSalida -ne 0) {
        if (Test-Path -LiteralPath $ArchivoSalida) { Remove-Item -LiteralPath $ArchivoSalida -Force }
        throw "Fallo la creacion del paquete (codigo $codigoSalida)."
    }
}

function Get-SalidaGit {
    param([Parameter(Mandatory)] [string[]]$Argumentos)

    # Git emite las rutas en UTF-8. Windows PowerShell 5.1 las decodifica con
    # la codificacion de consola, que puede ser OEM y alterar tildes o simbolos.
    $codificacionAnterior = [Console]::OutputEncoding
    try {
        [Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)
        $salida = @(& git.exe -c core.safecrlf=false -c core.quotepath=false -C $Proyecto @Argumentos)
        if ($LASTEXITCODE -ne 0) { throw "Fallo git $($Argumentos -join ' ')." }
        return $salida
    }
    finally {
        [Console]::OutputEncoding = $codificacionAnterior
    }
}

function Get-HuellasCodigo {
    $rutasCodigo = @(Get-SalidaGit -Argumentos @('-c', 'core.quotepath=false', 'ls-files', '--cached', '--others', '--exclude-standard') | Sort-Object -Unique)
    foreach ($rutaCodigo in $rutasCodigo) {
        $archivoCodigo = Join-Path $Proyecto $rutaCodigo
        if (Test-Path -LiteralPath $archivoCodigo -PathType Leaf) {
            $huellaCodigo = (Get-FileHash -LiteralPath $archivoCodigo -Algorithm SHA256).Hash.ToLowerInvariant()
            "$huellaCodigo  $rutaCodigo"
        } else {
            "eliminado  $rutaCodigo"
        }
    }
}

$Proyecto = [IO.Path]::GetFullPath($Proyecto)
if ([string]::IsNullOrWhiteSpace($Destino)) { $Destino = Join-Path $Proyecto 'artifacts\oci' }
$Destino = [IO.Path]::GetFullPath($Destino)
if (-not (Test-Path -LiteralPath (Join-Path $Proyecto 'artisan') -PathType Leaf)) {
    throw "No se encontro el proyecto Laravel en: $Proyecto"
}
foreach ($programa in @('git.exe', 'tar.exe')) {
    if (-not (Get-Command $programa -ErrorAction SilentlyContinue)) { throw "No se encontro $programa en PATH." }
}

while ([string]::IsNullOrWhiteSpace($DescripcionCambio)) {
    $DescripcionCambio = Read-Host 'Describe el cambio; se usara para el commit y el paquete'
}
$DescripcionCambio = $DescripcionCambio.Trim() -replace '(?i)^HT:\s*', ''
if ([string]::IsNullOrWhiteSpace($DescripcionCambio)) { throw 'La descripcion del cambio no puede quedar vacia.' }
$nombreSeguro = Convertir-NombreSeguro -Texto $DescripcionCambio
if ([string]::IsNullOrWhiteSpace($nombreSeguro)) { throw 'La descripcion debe contener al menos una letra o un numero.' }
$mensajeCommit = "HT: $DescripcionCambio"
Write-Host "`nCommit previsto:  $mensajeCommit" -ForegroundColor Yellow
Write-Host "Nombre base:      $nombreSeguro" -ForegroundColor Yellow

$rama = (Get-SalidaGit -Argumentos @('symbolic-ref', '--short', 'HEAD') | Select-Object -First 1).Trim()
$remoto = 'origin'
$ramaRemota = 'main'
$repositorioGit = 'https://github.com/HubDigitalEPN/HubDigital'
$upstream = "$remoto/$ramaRemota"
$urlRemota = (Get-SalidaGit -Argumentos @('remote', 'get-url', $remoto) | Select-Object -First 1).Trim()
$urlPush = (Get-SalidaGit -Argumentos @('remote', 'get-url', '--push', $remoto) | Select-Object -First 1).Trim()
if ($urlRemota -ne "$repositorioGit.git" -or $urlPush -ne "$repositorioGit.git") {
    throw "El destino oficial debe ser $repositorioGit.git en origin. No se publico en otro repositorio."
}
if ($OmitirCompilacion -or $OmitirPruebasPHP -or $SinPostgres) {
    throw 'Publicar en main exige compilacion Java/Vite y pruebas PHP/PostgreSQL y Gherkin completas. No se admiten opciones de omision.'
}

Write-Host "`n==> Comprobando acceso de escritura al repositorio oficial antes de las pruebas" -ForegroundColor Cyan
$tokenGitHub = Get-TokenGitHubHubDigital
Remove-Item Env:HUBDIGITAL_GITHUB_TOKEN -ErrorAction SilentlyContinue
$cabecerasGitHub = @{
    Authorization = "Bearer $tokenGitHub"
    Accept = 'application/vnd.github+json'
}
try {
    $cuentaGitHub = Invoke-RestMethod -Uri 'https://api.github.com/user' -Headers $cabecerasGitHub -ErrorAction Stop
    $repositorioOficial = Invoke-RestMethod -Uri 'https://api.github.com/repos/HubDigitalEPN/HubDigital' -Headers $cabecerasGitHub -ErrorAction Stop
}
catch {
    throw 'No se pudo autenticar el token o consultar HubDigitalEPN/HubDigital. Compruebe que el token siga vigente y tenga acceso al repositorio oficial.'
}
if ($repositorioOficial.full_name -ne 'HubDigitalEPN/HubDigital') {
    throw 'El token GitHub no corresponde al repositorio oficial HubDigitalEPN/HubDigital.'
}
$autorizacionGit = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("$($cuentaGitHub.login):$tokenGitHub"))
$tokenGitHub = $null
$cabecerasGitHub = $null

Invoke-GitOficial -Argumentos @('-C', $Proyecto, 'fetch', $remoto, 'refs/heads/main:refs/remotes/origin/main') -Descripcion "Actualizando $upstream sin borrar ramas"
& git.exe -C $Proyecto merge-base --is-ancestor $upstream HEAD
if ($LASTEXITCODE -ne 0) {
    throw 'main contiene trabajo que no esta en esta rama. Integralo en la rama de trabajo antes de reintentar; no se sobrescribira main.'
}
$estadoInicial = @(Get-SalidaGit -Argumentos @('status', '--porcelain=v1', '--untracked-files=all'))
if ($rama -eq 'main' -and $estadoInicial) {
    throw 'Los cambios deben desarrollarse en una rama nueva. Conservamos todos los archivos; prepara esa rama antes de empaquetar.'
}
& git.exe -C $Proyecto show-ref --verify --quiet refs/heads/main
$mainExiste = $LASTEXITCODE -eq 0
$mainAnterior = $null
if ($mainExiste) {
    $mainAnterior = (Get-SalidaGit -Argumentos @('rev-parse', 'refs/heads/main') | Select-Object -First 1).Trim()
    & git.exe -C $Proyecto merge-base --is-ancestor $mainAnterior HEAD
    if ($LASTEXITCODE -ne 0) { throw 'main local contiene trabajo distinto. No se movera ni se publicara hasta integrarlo en esta rama.' }
}

Invoke-GitOficial -Argumentos @('-C', $Proyecto, 'push', '--dry-run', $remoto, 'HEAD:refs/heads/main') -Descripcion 'Comprobando que origin/main acepta esta cuenta sin publicar cambios'
Write-Host "Cuenta GitHub con escritura comprobada para HubDigital: $($cuentaGitHub.login)" -ForegroundColor Green

$archivosRastreadosCambiados = @(Get-SalidaGit -Argumentos @('diff', '--name-only', $upstream))
$archivosNuevos = @(Get-SalidaGit -Argumentos @('ls-files', '--others', '--exclude-standard'))
$archivosCambiados = @(($archivosRastreadosCambiados + $archivosNuevos) | Sort-Object -Unique)
$rutasProhibidas = @($archivosCambiados | Where-Object {
    $_ -match '(^|/)\.env($|\.(?!example$))' -or $_ -match '(^|/)\.[^/]*-' -or $_ -match '\.(key|pem|p12|pfx|pass)$'
})
if ($rutasProhibidas) {
    throw "Hay archivos sensibles no ignorados. No se agrego nada a Git: $($rutasProhibidas -join ', ')"
}

# El flujo Pest de deposito firma y verifica con el certificado real autorizado.
# Confirmar los insumos antes de instalar dependencias, sin imprimir la clave ni el P12.
$directorioCertificadoPruebas = Join-Path $Proyecto '.local\secrets'
$certificadoPruebas = Join-Path $directorioCertificadoPruebas 'HERNAN AUGUSTO TROYA PROANO 1707514251-241025084137.p12'
$credencialesPruebas = Join-Path $directorioCertificadoPruebas 'credencialesp12 .txt'
if (-not (Test-Path -LiteralPath $certificadoPruebas -PathType Leaf) -or
    -not (Test-Path -LiteralPath $credencialesPruebas -PathType Leaf)) {
    throw 'Falta el P12 real autorizado o su archivo de credenciales en .local/secrets. La suite Pest no puede validar la firma de deposito.'
}
$textoCredencialesPruebas = [System.IO.File]::ReadAllText($credencialesPruebas)
if ((Get-Item -LiteralPath $certificadoPruebas).Length -eq 0 -or
    $textoCredencialesPruebas -notmatch '(?im)^\s*clave\s+\S+\s*$') {
    throw 'El P12 real o la linea de clave de sus credenciales locales esta vacia o ilegible.'
}
$textoCredencialesPruebas = $null

$headAntesValidacion = (Get-SalidaGit -Argumentos @('rev-parse', 'HEAD') | Select-Object -First 1).Trim()
$huellasAntesValidacion = @(Get-HuellasCodigo)

Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'diff', $upstream, '--check') -Descripcion 'Validando espacios, conflictos y formato basico del diff'

$archivosJson = @($archivosCambiados | Where-Object { $_ -match '\.json$' })
if ($archivosJson) {
    if (-not (Get-Command 'node.exe' -ErrorAction SilentlyContinue)) {
        throw 'No se encontro node.exe en PATH para validar los archivos JSON.'
    }
    # package-lock.json contiene una clave raiz vacia valida que ConvertFrom-Json
    # no puede representar como propiedad en Windows PowerShell.
    # Usar un archivo evita que Windows PowerShell altere las comillas de node -e.
    $rutaValidadorJson = Join-Path $directorioScript 'validar-json.cjs'
    if (-not (Test-Path -LiteralPath $rutaValidadorJson -PathType Leaf)) {
        throw "No se encontro el validador JSON: $rutaValidadorJson"
    }
    Write-Host "`n==> Validando archivos JSON" -ForegroundColor Cyan
    foreach ($archivo in $archivosJson) {
        $rutaJson = Join-Path $Proyecto $archivo
        if (Test-Path -LiteralPath $rutaJson -PathType Leaf) {
            & node.exe $rutaValidadorJson $rutaJson
            if ($LASTEXITCODE -ne 0) { throw "JSON invalido: $archivo (codigo $LASTEXITCODE)." }
            Write-Host "  OK $archivo"
        }
    }
}

$composer = Get-Command 'composer' -ErrorAction SilentlyContinue
if (-not $composer) {
    $composerAlternativo = Join-Path $env:LOCALAPPDATA 'Microsoft\WindowsApps\composer.cmd'
    if (Test-Path -LiteralPath $composerAlternativo -PathType Leaf) {
        $composer = Get-Item -LiteralPath $composerAlternativo
    }
}
if (-not $composer) { throw 'No se encontro Composer. Ejecute composer --version para revisar su instalacion.' }
$composerPrograma = if ($composer.PSObject.Properties['Source'] -and $composer.Source) { $composer.Source } else { $composer.FullName }
Invoke-Comando -Programa $composerPrograma -Argumentos @('validate', '--no-check-publish') -Descripcion 'Validando composer.json y composer.lock' -DirectorioTrabajo $Proyecto
Invoke-Comando -Programa $composerPrograma -Argumentos @('install', '--no-interaction', '--prefer-dist', '--no-progress') -Descripcion 'Sincronizando dependencias PHP desde composer.lock' -DirectorioTrabajo $Proyecto

# Las pruebas de carga PDF usan el inspector Java: debe estar compilado antes de la suite PHP.
$maven = Get-Command 'mvn.cmd' -ErrorAction SilentlyContinue
if (-not $maven) { throw 'No se encontro Maven para compilar el validador criptografico Java.' }
Invoke-Comando -Programa $maven.Source -Argumentos @('-q', '-f', 'tools/pdf-signature/pom.xml', '-DskipTests', 'package') -Descripcion 'Compilando firmador y validador criptografico Java' -DirectorioTrabajo $Proyecto
Write-Host 'OK Maven: firmador y validador Java compilados.' -ForegroundColor Green
$destinoJar = Join-Path $Proyecto 'resources/bin/hubdigital-pdf-signature.jar'
New-Item -ItemType Directory -Path (Split-Path -Parent $destinoJar) -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $Proyecto 'tools/pdf-signature/target/pdf-signature-1.0.0.jar') -Destination $destinoJar -Force
Invoke-Comando -Programa 'java.exe' -Argumentos @('-jar', $destinoJar, 'selftest') -Descripcion 'Probando firma valida, firma alterada y PDF sin firma con Java' -DirectorioTrabajo $Proyecto
Write-Host 'OK Java: firma valida, alteracion, ausencia de firma y seguridad PDF comprobadas.' -ForegroundColor Green

$suitePostgresCompletada = $false
$suiteGherkinCompletada = $false
$solucionPdfComprobada = $false
$php = Get-Command 'php' -ErrorAction SilentlyContinue
if (-not $php) {
    $phpAlternativo = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
    if (Test-Path -LiteralPath $phpAlternativo -PathType Leaf) {
        $php = Get-Item -LiteralPath $phpAlternativo
    }
}
$phpPrograma = if ($php -and $php.PSObject.Properties['Source'] -and $php.Source) { $php.Source } elseif ($php) { $php.FullName } else { $null }
$archivosPhp = @($archivosCambiados | Where-Object { $_ -match '\.php$' -and $_ -notmatch '\.blade\.php$' })
if ($php) {
    Invoke-Comando -Programa $phpPrograma -Argumentos @('deploy/oracle/scripts/verify-migration-classes.php') -Descripcion 'Comprobando clases de migraciones sin ejecutar cambios en la base' -DirectorioTrabajo $Proyecto
    if ($archivosPhp) {
        Write-Host "`n==> Validando sintaxis PHP" -ForegroundColor Cyan
        foreach ($archivo in $archivosPhp) {
            $rutaPhp = Join-Path $Proyecto $archivo
            if (Test-Path -LiteralPath $rutaPhp -PathType Leaf) {
                & $phpPrograma -l $rutaPhp | Out-Host
                if ($LASTEXITCODE -ne 0) { throw "Sintaxis PHP invalida: $archivo" }
            }
        }
    }
    if (-not $OmitirPruebasPHP) {
        Invoke-Comando -Programa $phpPrograma -Argumentos @('artisan', 'about', '--only=environment') -Descripcion 'Validando el arranque de Laravel' -DirectorioTrabajo $Proyecto

        $ejecutableBehat = Join-Path $Proyecto 'vendor/bin/behat'
        if (-not (Test-Path -LiteralPath $ejecutableBehat -PathType Leaf)) {
            throw 'Falta Behat: no se puede publicar en main ni crear el paquete sin validar Gherkin.'
        }
        # Los mismos escenarios activos que GitHub. --strict rechaza fallos,
        # pasos pendientes o sin definir antes del commit y del archivo OCI.
        Invoke-Comando -Programa $phpPrograma -Argumentos @(
            $ejecutableBehat, '--profile=default', '--no-interaction', '--tags=@listo', '--strict'
        ) -Descripcion 'Validando escenarios de negocio Gherkin con Behat' -DirectorioTrabajo $Proyecto
        $suiteGherkinCompletada = $true
        Write-Host 'OK Gherkin: escenarios activos completados con Behat.' -ForegroundColor Green

        if ($SinPostgres) {
            Write-Host "`n==> Suite PostgreSQL omitida mediante -SinPostgres" -ForegroundColor Yellow
        } else {
            $validadorPostgres = Join-Path $raizRepositorio 'scripts\deployment\windows\validar-postgresql-temporal.ps1'
            if (-not (Test-Path -LiteralPath $validadorPostgres -PathType Leaf)) { throw "No se encontro el validador PostgreSQL: $validadorPostgres" }
            Invoke-Comando -Programa 'powershell.exe' -Argumentos @(
                '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $validadorPostgres,
                '-Proyecto', $Proyecto, '-Php', $phpPrograma
            ) -Descripcion 'Validando con PostgreSQL temporal y apagado automatico'
            $suitePostgresCompletada = $true
            Invoke-Comando -Programa $phpPrograma -Argumentos @('deploy/oracle/scripts/verify-deposit-pdf.php') -Descripcion 'Comprobando admision PDF y firma integrada PHP/Java' -DirectorioTrabajo $Proyecto
            $solucionPdfComprobada = $true
        }

    }
} else {
    throw 'No se encontro PHP: main solo se publica despues de validar PHP/PostgreSQL.'
}

if (-not $OmitirCompilacion) {
    if (-not (Get-Command 'npm.cmd' -ErrorAction SilentlyContinue)) { throw 'No se encontro npm.cmd en PATH.' }
    Invoke-Comando -Programa 'npm.cmd' -Argumentos @('ci') -Descripcion 'Instalando dependencias frontend exactas desde package-lock.json' -DirectorioTrabajo $Proyecto
    Invoke-Comando -Programa 'npm.cmd' -Argumentos @('run', 'build') -Descripcion 'Compilando y validando JavaScript, CSS y Tailwind con Vite' -DirectorioTrabajo $Proyecto
}

$requeridos = @(
    'vendor/autoload.php', 'vendor/livewire/flux/dist/manifest.json',
    'public/build/manifest.json', 'deploy/oracle/scripts/stage-linux-candidate.sh',
    'deploy/oracle/scripts/verify-source-identity.sh',
    'deploy/oracle/scripts/verify-deposit-pdf.php',
    'bootstrap/app.php', 'bootstrap/providers.php', 'bootstrap/cache/.gitignore',
    'composer.json', 'composer.lock', 'modules_statuses.json',
    'resources/bin/hubdigital-pdf-signature.jar',
    'resources/data/ecuador-localidades-inec.json',
    'database/migrations/2026_09_28_000001_create_localidades_ecuador_catalogo.php',
    'app/Support/CatalogoLocalidadesEcuador.php',
    'app/Livewire/Administracion/LocalidadesEcuadorCatalogo.php',
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/FirmaPdfJava.php',
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Storage/ValidacionPreviaDocumentoDeposito.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000001_create_portal_chat_knowledge.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000002_enhance_portal_chat.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000003_chat_language_examples.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000004_chat_paraphrase_vocabulary.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000005_chat_confidence_observations.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000006_chat_quality_and_versions.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000007_chat_node_drafts.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000008_chat_train_vocabulary.php',
    'Modules/CatalogoPublico/database/migrations/2026_09_26_000009_chat_trace.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/ConocimientoPortal.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/TextoChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/ConversacionBasica.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/CorpusChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/ConsultaCatalogoPublico.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/RankingIntencionesChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/SenalesIntencionChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/DetectorEntidadesChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/ContextoChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/AnaliticaChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/EvaluadorCorpusChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/TransferenciaConocimientoChat.php',
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/AsistentePortal.php',
    'Modules/CatalogoPublico/app/Presentation/Http/Controllers/ChatBotWidget.php',
    'Modules/CatalogoPublico/app/Presentation/Http/Controllers/AdministrarAsistente.php',
    'routes/console.php',
    'Modules/CatalogoPublico/resources/views/livewire/chat-bot-widget.blade.php',
    'Modules/CatalogoPublico/resources/views/livewire/administrar-asistente.blade.php',
    'config/chatbot.php',
    'deploy/oracle/scripts/verify-portal-chat.php',
    'deploy/oracle/scripts/verify-migration-classes.php'
)
foreach ($ruta in $requeridos) {
    if (-not (Test-Path -LiteralPath (Join-Path $Proyecto $ruta) -PathType Leaf)) { throw "Falta un archivo requerido para el paquete: $ruta" }
}

$headDespuesValidacion = (Get-SalidaGit -Argumentos @('rev-parse', 'HEAD') | Select-Object -First 1).Trim()
$huellasDespuesValidacion = @(Get-HuellasCodigo)
if ($headAntesValidacion -ne $headDespuesValidacion -or
    [string]::Join("`n", $huellasAntesValidacion) -ne [string]::Join("`n", $huellasDespuesValidacion)) {
    throw 'El codigo cambio durante las pruebas o compilaciones. main no se publica: vuelve a validar la version actual.'
}

if (-not $suitePostgresCompletada -or -not $suiteGherkinCompletada -or -not $solucionPdfComprobada) {
    throw 'No se completaron las validaciones PHP/PostgreSQL, Gherkin y PDF obligatorias. main no se publica y no se crea el paquete.'
}

$estadoAntesCommit = @(Get-SalidaGit -Argumentos @('status', '--porcelain=v1', '--untracked-files=all'))
if ($estadoAntesCommit) {
    Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'add', '-A') -Descripcion 'Agregando cambios a Git'
    Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'diff', '--cached', '--check') -Descripcion 'Validando el contenido preparado para commit'
    Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'commit', '-m', $mensajeCommit) -Descripcion "Creando commit: $mensajeCommit"
} else {
    Write-Host "`n==> Git no tiene cambios nuevos; no se crea un commit vacio." -ForegroundColor Yellow
}

$estadoDespuesCommit = @(Get-SalidaGit -Argumentos @('status', '--porcelain=v1', '--untracked-files=all'))
if ($estadoDespuesCommit) { throw 'Quedaron cambios fuera del commit. Se detiene antes de publicar para no crear un paquete distinto de Git.' }

$commit = (Get-SalidaGit -Argumentos @('rev-parse', 'HEAD') | Select-Object -First 1).Trim()
Invoke-GitOficial -Argumentos @('-C', $Proyecto, 'push', $remoto, 'HEAD:refs/heads/main') -Descripcion "Publicando el trabajo validado en $upstream sin force"
Invoke-GitOficial -Argumentos @('-C', $Proyecto, 'fetch', $remoto, 'refs/heads/main:refs/remotes/origin/main') -Descripcion 'Verificando el commit publicado'
$commitRemoto = (Get-SalidaGit -Argumentos @('rev-parse', $upstream) | Select-Object -First 1).Trim()
if ($commit -ne $commitRemoto) { throw 'El commit local y el remoto no coinciden. No se crea el paquete.' }
if ($rama -ne 'main') {
    if ($mainExiste) {
        Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'update-ref', 'refs/heads/main', $commit, $mainAnterior) -Descripcion 'Avanzando main local conservando su historial'
    } else {
        Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'branch', 'main', $commit) -Descripcion 'Creando main local desde el trabajo publicado'
    }
}
Invoke-Comando -Programa 'git.exe' -Argumentos @('-C', $Proyecto, 'branch', '--set-upstream-to=origin/main', 'main') -Descripcion 'Vinculando main local al repositorio oficial'


New-Item -ItemType Directory -Path $Destino -Force | Out-Null
$marcaTiempo = (Get-Date).ToString('yyyyMMdd-HHmmss')
$identificadorPaquete = "$nombreSeguro-$marcaTiempo"
$directorioPaquete = Join-Path $Destino $identificadorPaquete
if (Test-Path -LiteralPath $directorioPaquete) {
    throw "Ya existe el directorio de salida del paquete: $directorioPaquete"
}
New-Item -ItemType Directory -Path $directorioPaquete | Out-Null

$nombre = "$identificadorPaquete.tar.gz"
$nombreScriptTransferencia = "$identificadorPaquete-cloudshell-vm.sh"
$nombreKitCloudShell = "$identificadorPaquete-cloudshell-upload.tar.gz"
$nombreInstruccionesCloudShell = "$identificadorPaquete-INSTRUCCIONES-CLOUD-SHELL.txt"
$nombreEntornoOci = "$identificadorPaquete-hubdigital.env"
$paquete = Join-Path $directorioPaquete $nombre
$suma = "$paquete.sha256"
$scriptTransferencia = Join-Path $directorioPaquete $nombreScriptTransferencia
$kitCloudShell = Join-Path $directorioPaquete $nombreKitCloudShell
$instruccionesCloudShell = Join-Path $directorioPaquete $nombreInstruccionesCloudShell
$entornoOci = Join-Path $directorioPaquete $nombreEntornoOci
foreach ($salida in @($paquete, $suma, $scriptTransferencia, $kitCloudShell, $instruccionesCloudShell, $entornoOci)) {
    if (Test-Path -LiteralPath $salida) { throw "Ya existe un archivo de salida: $salida" }
}

$incluir = @(
    'app', 'config', 'database', 'lang', 'Modules', 'public', 'resources', 'routes', 'vendor', 'deploy',
    'scripts/depositos',
    'bootstrap/app.php', 'bootstrap/providers.php', 'bootstrap/cache/.gitignore',
    'artisan', 'composer.json', 'composer.lock', 'modules_statuses.json'
)
$argumentosTar = @(
    '-czf', $paquete,
    '--exclude=.git', '--exclude=.env', '--exclude=.env.*', '--exclude=.*-*',
    '--exclude=.ai', '--exclude=.agents', '--exclude=.tools', '--exclude=.local',
    '--exclude=artifacts', '--exclude=docs', '--exclude=tests', '--exclude=postman', '--exclude=docker',
    '--exclude=node_modules', '--exclude=public/hot', '--exclude=public/storage',
    '--exclude=Modules/*/tests', '--exclude=Modules/*/tests/*',
    '--exclude=*.key', '--exclude=*.pem', '--exclude=*.p12', '--exclude=*.pfx', '--exclude=*.pass',
    '-C', $Proyecto
) + $incluir

# El bundle transporta la identidad de main incluso cuando OCI no tiene .git.
$fuenteManifest = Join-Path $Proyecto 'SOURCE-MANIFEST.sha256'
$fuenteMetadata = Join-Path $Proyecto 'SOURCE-METADATA.json'
$rutasFuente = @(Get-SalidaGit -Argumentos @('-c', 'core.quotepath=false', 'ls-files') | Where-Object {
    $ruta = $_
    $permitida = @($incluir | Where-Object { $ruta -eq $_ -or $ruta.StartsWith("$_/") }).Count -gt 0
    $permitida -and $ruta -notmatch '(^|/)(tests|docs|postman|docker|node_modules)(/|$)' -and
        $ruta -notmatch '(^|/)\.(git|ai|agents|tools|local)(/|$)' -and
        $ruta -notmatch '\.(key|pem|p12|pfx|pass)$'
} | Sort-Object)
$lineasFuente = @($rutasFuente | ForEach-Object {
    $huella = (Get-FileHash -LiteralPath (Join-Path $Proyecto $_) -Algorithm SHA256).Hash.ToLowerInvariant()
    "$huella  $_"
})
[IO.File]::WriteAllText($fuenteManifest, (($lineasFuente -join "`n") + "`n"), [Text.UTF8Encoding]::new($false))
$metadataFuente = [ordered]@{
    format_version = 1
    repository = $repositorioGit
    git_branch = 'main'
    git_commit = $commit
    git_tree = (Get-SalidaGit -Argumentos @('rev-parse', ($commit + '^{tree}')) | Select-Object -First 1).Trim()
    source_manifest_sha256 = (Get-FileHash -LiteralPath $fuenteManifest -Algorithm SHA256).Hash.ToLowerInvariant()
    java_jar_sha256 = (Get-FileHash -LiteralPath $destinoJar -Algorithm SHA256).Hash.ToLowerInvariant()
    frontend_manifest_sha256 = (Get-FileHash -LiteralPath (Join-Path $Proyecto 'public/build/manifest.json') -Algorithm SHA256).Hash.ToLowerInvariant()
}
[IO.File]::WriteAllText($fuenteMetadata, (($metadataFuente | ConvertTo-Json -Depth 4) + "`n"), [Text.UTF8Encoding]::new($false))
$argumentosTar += @('SOURCE-METADATA.json', 'SOURCE-MANIFEST.sha256')
$requeridos += @('SOURCE-METADATA.json', 'SOURCE-MANIFEST.sha256')

$dependenciasDesarrolloRetiradas = $false
try {
    Invoke-Comando -Programa $composerPrograma -Argumentos @(
        'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--prefer-dist', '--no-progress'
    ) -Descripcion 'Preparando dependencias PHP exclusivas de produccion' -DirectorioTrabajo $Proyecto
    $dependenciasDesarrolloRetiradas = $true

    $commitActual = (Get-SalidaGit -Argumentos @('rev-parse', 'HEAD') | Select-Object -First 1).Trim()
    if ($commitActual -ne $commit -or @(Get-SalidaGit -Argumentos @('status', '--porcelain=v1', '--untracked-files=all')).Count -gt 0) {
        throw 'El trabajo cambio durante el empaquetado. No se crea un paquete distinto del commit publicado.'
    }
    foreach ($linea in $lineasFuente) {
        $rutaFuente = $linea.Substring(66)
        if ((Get-FileHash -LiteralPath (Join-Path $Proyecto $rutaFuente) -Algorithm SHA256).Hash.ToLowerInvariant() -ne $linea.Substring(0, 64)) {
            throw "El archivo cambio durante el empaquetado: $rutaFuente. No se crea el bundle."
        }
    }
    Invoke-TarConProgreso -Argumentos $argumentosTar -ArchivoSalida $paquete

    Write-Host "`n==> Validando contenido y exclusiones" -ForegroundColor Cyan
    $contenido = & tar.exe -tzf $paquete
    if ($LASTEXITCODE -ne 0 -or -not $contenido) { throw 'El paquete se creo, pero no pudo volver a leerse.' }
    $prohibidos = @($contenido | Where-Object {
        $_ -match '(^|/)\.git(/|$)' -or $_ -match '(^|/)\.env($|\.(?!example$))' -or
        $_ -match '(^|/)\.[^/]*-' -or $_ -match '(^|/)\.(ai|agents|tools|local)(/|$)' -or
        $_ -match '(^|/)(artifacts|docs|tests|postman|docker)(/|$)' -or
        $_ -match '\.(key|pem|p12|pfx|pass)$' -or
        $_ -match '(^|/)node_modules(/|$)' -or
        $_ -match '(^|/)vendor/(phpunit|pestphp)(/|$)'
    })
    if ($prohibidos) {
        Remove-Item -LiteralPath $paquete -Force
        throw "El paquete contenia rutas prohibidas: $($prohibidos -join ', ')"
    }
    $faltantesPaquete = @($requeridos | Where-Object { $contenido -notcontains $_ })
    if ($faltantesPaquete) {
        Remove-Item -LiteralPath $paquete -Force
        throw "El paquete no contiene archivos runtime obligatorios: $($faltantesPaquete -join ', ')"
    }

    $archivoEntornoLocal = Join-Path $Proyecto '.env'
    if (-not (Test-Path -LiteralPath $archivoEntornoLocal -PathType Leaf)) {
        throw 'Falta el .env local requerido para generar el entorno OCI completo.'
    }
    $valoresEntorno = @{}
    foreach ($linea in Get-Content -LiteralPath $archivoEntornoLocal) {
        if ($linea -match '^([^#=]+)=(.*)$') {
            $valoresEntorno[$matches[1].Trim()] = $matches[2].Trim()
        }
    }
    $clavesOciRequeridas = @(
        'APP_KEY', 'DEPOSIT_STORAGE_DRIVER', 'DEPOSIT_STORAGE_REQUIRE_REMOTE',
        'DEPOSIT_STORAGE_VERIFY_AFTER_WRITE', 'DEPOSIT_STORAGE_MAX_OBJECT_BYTES',
        'R2_ACCOUNT_ID', 'R2_BUCKET', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY',
        'R2_ENDPOINT', 'TURNSTILE_ENABLED', 'TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET',
        'TURNSTILE_EXPECTED_HOSTNAME', 'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT',
        'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS',
        'SEED_BOOTSTRAP_DEPOSITANTE', 'BOOTSTRAP_DEPOSITANTE_EMAIL',
        'BOOTSTRAP_DEPOSITANTE_PASSWORD'
    )
    foreach ($clave in $clavesOciRequeridas) {
        if ([string]::IsNullOrWhiteSpace($valoresEntorno[$clave])) {
            throw "El .env local no tiene un valor para $clave; no se genero un kit incompleto."
        }
    }
    if ($valoresEntorno['DEPOSIT_STORAGE_DRIVER'] -ne 'r2' -or
        $valoresEntorno['DEPOSIT_STORAGE_REQUIRE_REMOTE'] -ne 'true' -or
        $valoresEntorno['DEPOSIT_STORAGE_VERIFY_AFTER_WRITE'] -ne 'true') {
        throw 'OCI exige R2 remoto y verificacion posterior a escritura.'
    }
    $endpointEsperado = "https://$($valoresEntorno['R2_ACCOUNT_ID']).r2.cloudflarestorage.com"
    if ($valoresEntorno['R2_ENDPOINT'].TrimEnd('/') -ne $endpointEsperado) {
        throw "R2_ENDPOINT no corresponde a R2_ACCOUNT_ID: se esperaba $endpointEsperado"
    }
    if ($valoresEntorno['TURNSTILE_EXPECTED_HOSTNAME'] -ne 'dev.labinvepn.org') {
        throw 'TURNSTILE_EXPECTED_HOSTNAME debe ser dev.labinvepn.org para OCI.'
    }
    if ($valoresEntorno['MAIL_MAILER'] -ne 'smtp' -or
        $valoresEntorno['MAIL_HOST'] -ne 'smtp.zoho.com' -or
        $valoresEntorno['MAIL_PORT'] -ne '587' -or
        $valoresEntorno['MAIL_USERNAME'] -ne 'hubdigital.epn@kintiflow.com' -or
        $valoresEntorno['MAIL_FROM_ADDRESS'] -ne 'hubdigital.epn@kintiflow.com') {
        throw 'El correo OCI debe usar SMTP Zoho con hubdigital.epn@kintiflow.com y puerto 587.'
    }
    $bytesPassword = [byte[]]::new(36)
    $generadorAleatorio = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generadorAleatorio.GetBytes($bytesPassword)
    }
    finally {
        $generadorAleatorio.Dispose()
    }
    $passwordPostgres = [Convert]::ToBase64String($bytesPassword).TrimEnd('=').Replace('+', '-').Replace('/', '_')

    $vapidPublica = $valoresEntorno['VAPID_PUBLIC_KEY']
    $vapidPrivada = $valoresEntorno['VAPID_PRIVATE_KEY']
    if ([string]::IsNullOrWhiteSpace($vapidPublica) -or [string]::IsNullOrWhiteSpace($vapidPrivada)) {
        $ecdsa = [Security.Cryptography.ECDsa]::Create([Security.Cryptography.ECCurve]::NamedCurves.nistP256)
        try {
            $parametros = $ecdsa.ExportParameters($true)
            $publicaBytes = [byte[]]::new(65)
            $publicaBytes[0] = 4
            [Array]::Copy($parametros.Q.X, 0, $publicaBytes, 1, 32)
            [Array]::Copy($parametros.Q.Y, 0, $publicaBytes, 33, 32)
            $vapidPublica = [Convert]::ToBase64String($publicaBytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')
            $vapidPrivada = [Convert]::ToBase64String($parametros.D).TrimEnd('=').Replace('+', '-').Replace('/', '_')
        }
        finally {
            $ecdsa.Dispose()
        }
    }

    $plantillaEntorno = Join-Path $Proyecto 'deploy\oracle\env\hubdigital.env.example'
    $contenidoEntornoOci = [IO.File]::ReadAllText($plantillaEntorno)
    $reemplazosEntorno = [ordered]@{
        '<APP_KEY_ORIGINAL>' = $valoresEntorno['APP_KEY']
        '<POSTGRES_PASSWORD>' = $passwordPostgres
        '<R2_ACCOUNT_ID>' = $valoresEntorno['R2_ACCOUNT_ID']
        '<R2_BUCKET>' = $valoresEntorno['R2_BUCKET']
        '<R2_ACCESS_KEY_ID>' = $valoresEntorno['R2_ACCESS_KEY_ID']
        '<R2_SECRET_ACCESS_KEY>' = $valoresEntorno['R2_SECRET_ACCESS_KEY']
        '<VAPID_PUBLIC_KEY>' = $vapidPublica
        '<VAPID_PRIVATE_KEY>' = $vapidPrivada
        '<turnstile-site-key>' = $valoresEntorno['TURNSTILE_SITE_KEY']
        '<turnstile-secret>' = $valoresEntorno['TURNSTILE_SECRET']
        '<CONTRASENA_DE_APLICACION_ZOHO>' = $valoresEntorno['MAIL_PASSWORD']
        '<CONTRASENA_DE_LA_CUENTA>' = $valoresEntorno['BOOTSTRAP_DEPOSITANTE_PASSWORD']
    }
    foreach ($marcador in $reemplazosEntorno.Keys) {
        $contenidoEntornoOci = $contenidoEntornoOci.Replace($marcador, $reemplazosEntorno[$marcador])
    }
    if ($contenidoEntornoOci -match '<[^>]+>') {
        throw "El entorno OCI conserva un marcador sin resolver: $($matches[0])"
    }
    [IO.File]::WriteAllText(
        $entornoOci,
        ($contenidoEntornoOci.TrimEnd() + "`n"),
        [Text.UTF8Encoding]::new($false)
    )

    $contenidoScript = @'
#!/usr/bin/env bash
set -Eeuo pipefail

package_name='__PACKAGE__'
checksum_name='__CHECKSUM__'
environment_name='__ENVIRONMENT__'
kit_name="${package_name%.tar.gz}-cloudshell-upload.tar.gz"
vm_host="${VM_HOST:-129.153.23.57}"
vm_user="${VM_USER:-ubuntu}"
key_path="${SSH_KEY_PATH:-${HOME}/ssh-key-2026-09-18.key}"
self_path="$(readlink -f -- "$0")"
base_dir="$(dirname -- "${self_path}")"
stage_script='/srv/hubdigital/current/deploy/oracle/scripts/stage-linux-candidate.sh'

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

verify_transfer() {
    [[ -f "${base_dir}/${package_name}" ]] || fail "Falta ${base_dir}/${package_name}"
    [[ -f "${base_dir}/${checksum_name}" ]] || fail "Falta ${base_dir}/${checksum_name}"
    [[ -f "${base_dir}/${environment_name}" ]] || fail "Falta ${base_dir}/${environment_name}"
    (cd "${base_dir}" && sha256sum -c "${checksum_name}")
}

install_environment() {
    local source="${base_dir}/${environment_name}"
    local target='/etc/hubdigital/hubdigital.env'
    local required=(
        APP_KEY APP_ENV APP_URL DB_CONNECTION DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD
        DEPOSIT_STORAGE_DRIVER R2_ACCOUNT_ID R2_BUCKET R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY R2_ENDPOINT
        TURNSTILE_ENABLED TURNSTILE_SITE_KEY TURNSTILE_SECRET TURNSTILE_EXPECTED_HOSTNAME
        VAPID_SUBJECT VAPID_PUBLIC_KEY VAPID_PRIVATE_KEY
    )
    grep -Eq '<[^>]+>' "${source}" && fail "${environment_name} conserva marcadores sin resolver."
    for key in "${required[@]}"; do
        grep -Eq "^${key}=.+$" "${source}" || fail "Falta ${key} en ${environment_name}."
    done
    sudo install -o root -g www-data -m 0600 "${source}" "${target}"
    sudo /srv/hubdigital/current/deploy/oracle/scripts/configure-postgres.sh
    sudo rm -f -- "${source}"
    printf 'Entorno OCI completo instalado automaticamente en %s.\n' "${target}"
}

if [[ -f "${stage_script}" ]]; then
    printf 'Entorno detectado: VM OCI.\n'
    verify_transfer
    install_environment
    stage_log="/tmp/${package_name%.tar.gz}-stage.log"
    sudo "${stage_script}" "${base_dir}/${package_name}" | tee "${stage_log}"

    candidate="$(awk -F= '$1 == "candidate" {print $2}' "${stage_log}" | tail -n 1)"
    checksum="$(awk -F= '$1 == "checksum" {print $2}' "${stage_log}" | tail -n 1)"
    staging="$(awk -F= '$1 == "staging" {print $2}' "${stage_log}" | tail -n 1)"
    [[ "${candidate}" =~ ^[A-Za-z0-9._-]+\.tar\.gz$ ]] || fail 'El staging no devolvio un candidato valido.'
    [[ "${checksum}" =~ ^[0-9a-f]{64}$ ]] || fail 'El staging no devolvio un checksum valido.'
    [[ "${staging}" =~ ^/srv/hubdigital/staging/[0-9a-f]{16}$ ]] || fail 'El staging no devolvio una ruta valida.'
    release_id="${checksum:0:16}"

    rm -f -- "${base_dir}/${package_name}" "${base_dir}/${checksum_name}" "${base_dir}/${environment_name}" "${self_path}"
    printf '\nCandidato preparado correctamente.\n'
    printf 'Archivos temporales enviados a /tmp eliminados.\n'
    printf 'Registro conservado: %s\n' "${stage_log}"
    printf 'Candidato: %s\n' "${candidate}"
    printf 'Staging: %s\n' "${staging}"
    printf 'Release ID previsto: %s\n\n' "${release_id}"
    printf '\n============================================================\n'
    printf 'COPIE Y PEGUE ESTE COMANDO EN LA VM (CON MIGRACIONES):\n'
    printf '============================================================\n'
    printf 'sudo env APPLY_MIGRATIONS=1 %q %q %q\n' \
        "${staging}/deploy/oracle/scripts/deploy-release.sh" \
        "/srv/hubdigital/staging/${candidate}" \
        "/srv/hubdigital/staging/${candidate}.sha256"
    printf '============================================================\n'
    printf '\nSOLAMENTE SI EL COMANDO ANTERIOR TERMINA CON\n'
    printf '"Release preparada en mantenimiento", active la release con:\n'
    printf 'sudo %q %q\n' \
        "/srv/hubdigital/releases/${release_id}/deploy/oracle/scripts/activate-release.sh" \
        "${release_id}"
    exit 0
fi

printf 'Entorno detectado: OCI Cloud Shell.\n'
verify_transfer
[[ -f "${key_path}" ]] || fail "No se encontro la clave SSH: ${key_path}"
chmod 600 "${key_path}"
scp -i "${key_path}" \
    "${base_dir}/${package_name}" \
    "${base_dir}/${checksum_name}" \
    "${base_dir}/${environment_name}" \
    "${self_path}" \
    "${vm_user}@${vm_host}:/tmp/"

printf '\nTransferencia a la VM completada.\n'
cleanup_failed=0
for transferred_file in \
    "${base_dir}/${package_name}" \
    "${base_dir}/${checksum_name}" \
    "${base_dir}/${environment_name}" \
    "${self_path}" \
    "${HOME}/${kit_name}"; do
    if [[ -e "${transferred_file}" ]] && ! rm -f -- "${transferred_file}"; then
        printf 'ADVERTENCIA: no se pudo eliminar de Cloud Shell: %s\n' "${transferred_file}" >&2
        cleanup_failed=1
    fi
done
if [[ "${cleanup_failed}" -eq 0 ]]; then
    printf 'Limpieza Cloud Shell: OK. Paquete, entorno, checksum, script y kit eliminados.\n'
    printf 'Clave SSH conservada: %s\n' "${key_path}"
else
    printf 'Limpieza Cloud Shell: NO OK. Revise las advertencias anteriores.\n' >&2
fi
printf 'Conectate con:\nssh -i %q %q\n' "${key_path}" "${vm_user}@${vm_host}"
printf '\nDentro de la VM verifica y ejecuta:\n'
printf 'cd /tmp\nsha256sum -c %q\nbash %q\n' "${checksum_name}" "$(basename -- "${self_path}")"
'@
    $contenidoScript = $contenidoScript.Replace('__PACKAGE__', $nombre).Replace('__CHECKSUM__', [IO.Path]::GetFileName($suma)).Replace('__ENVIRONMENT__', $nombreEntornoOci)
    [IO.File]::WriteAllText($scriptTransferencia, ($contenidoScript.TrimStart() + "`n"), [Text.UTF8Encoding]::new($false))

    $hash = (Get-FileHash -LiteralPath $paquete -Algorithm SHA256).Hash.ToLowerInvariant()
    $hashScript = (Get-FileHash -LiteralPath $scriptTransferencia -Algorithm SHA256).Hash.ToLowerInvariant()
    $hashEntorno = (Get-FileHash -LiteralPath $entornoOci -Algorithm SHA256).Hash.ToLowerInvariant()
    $contenidoChecksum = "$hash  $nombre`n$hashScript  $nombreScriptTransferencia`n$hashEntorno  $nombreEntornoOci`n"
    [IO.File]::WriteAllText($suma, $contenidoChecksum, [Text.UTF8Encoding]::new($false))

    Invoke-Comando -Programa 'tar.exe' -Argumentos @(
        '-czf', $kitCloudShell, '-C', $directorioPaquete,
        $nombre, [IO.Path]::GetFileName($suma), $nombreScriptTransferencia, $nombreEntornoOci
    ) -Descripcion 'Agrupando el kit de subida unica para Cloud Shell'
    $contenidoKit = @(& tar.exe -tzf $kitCloudShell)
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo volver a leer el kit de Cloud Shell.' }
    $esperadoKit = @($nombre, [IO.Path]::GetFileName($suma), $nombreScriptTransferencia, $nombreEntornoOci) | Sort-Object
    $contenidoKitOrdenado = @($contenidoKit | Sort-Object)
    if (($contenidoKitOrdenado -join "`n") -ne ($esperadoKit -join "`n")) {
        Remove-Item -LiteralPath $kitCloudShell -Force
        throw "El kit de Cloud Shell no contiene exactamente los cuatro archivos esperados: $($contenidoKit -join ', ')"
    }
    $hashKit = (Get-FileHash -LiteralPath $kitCloudShell -Algorithm SHA256).Hash.ToLowerInvariant()
    Remove-Item -LiteralPath $entornoOci -Force

    $contenidoInstruccionesCloudShell = @"
MANUAL COMPLETO DE DESPLIEGUE OCI - HUBDIGITAL
==============================================

Paquete: $identificadorPaquete
Commit Git: $commit
Repositorio: $repositorioGit
Rama remota: $upstream
Identidad: SOURCE-METADATA.json registra el commit de main y las huellas de Java y Vite.
Kit que debe subirse: $nombreKitCloudShell
VM OCI: ubuntu@129.153.23.57
Migraciones de base de datos: se aplican en la preparacion, antes de activar

OBJETIVO
--------
Este procedimiento transfiere una release validada desde Windows a OCI Cloud
Shell, luego a la VM y finalmente la activa en https://dev.labinvepn.org.
El despliegue verifica checksums SHA-256 y aplica las migraciones pendientes
antes de activar la release.

ARCHIVOS GENERADOS EN WINDOWS
-----------------------------
La carpeta de este paquete contiene cinco archivos:

1. $nombreKitCloudShell
   Es el unico archivo que debe subir manualmente a Cloud Shell. Contiene el
   entorno OCI completo cifrado durante el transporte SSH, pero no debe
   compartirse ni conservarse despues del despliegue.
2. $nombre
   Es el paquete fuente de produccion del commit indicado de main. La preparacion Linux verifica su identidad antes de continuar.
3. $([IO.Path]::GetFileName($suma))
   Contiene los checksums SHA-256.
4. $nombreScriptTransferencia
   Automatiza Cloud Shell y el staging dentro de la VM.
5. $nombreInstruccionesCloudShell
   Es este manual; no necesita subirlo.

REQUISITOS PREVIOS
------------------
- La VM debe responder por SSH desde OCI Cloud Shell.
- La clave debe existir en:
  ~/ssh-key-2026-09-18.key
- Para comprobarla en Cloud Shell:

ls -l ~/ssh-key-2026-09-18.key

Si la clave no existe, use Cloud Shell > Menu > Upload y subala una sola vez.
Cloud Shell conserva el directorio personal entre sesiones. El script aplica
chmod 600 automaticamente antes de usar la clave. Nunca comparta, renombre,
incluya en Git ni copie la clave dentro del paquete.

PASO 1 - SUBIR EL KIT A CLOUD SHELL
-----------------------------------
En OCI Cloud Shell use Menu > Upload y seleccione solamente:

$nombreKitCloudShell

No suba por separado el paquete, checksum, entorno ni script: ya estan dentro
del kit.

PASO 2 - EXTRAER Y EJECUTAR EN CLOUD SHELL
-------------------------------------------
Copie y ejecute este bloque completo en Cloud Shell:

mkdir -p ~/hubdigital-upload
tar -xzf ~/$nombreKitCloudShell \
  -C ~/hubdigital-upload

cd ~/hubdigital-upload
bash ./$nombreScriptTransferencia

El script realiza estas acciones:
- Comprueba los checksums del paquete, entorno y del propio script.
- Aplica permisos 600 a la clave SSH.
- Copia paquete, checksum, entorno y script a /tmp de la VM mediante scp.
- Si scp termina correctamente, elimina de Cloud Shell el kit y los cuatro
  archivos extraidos. No elimina la clave SSH.
- Muestra "Limpieza Cloud Shell: OK" o explica que archivo no pudo eliminar.

Si aparece ERROR, FAILED, checksum distinto de OK o scp incompleto, detengase.
No continue con la VM hasta resolverlo.

PASO 3 - CONECTARSE A LA VM
---------------------------
Despues de la transferencia, el script mostrara este comando:

ssh -i ~/ssh-key-2026-09-18.key ubuntu@129.153.23.57

Ejecutelo desde Cloud Shell. Sabra que entro en la VM cuando el prompt comience
con ubuntu@labinvepn-dev-vnic. No copie el texto del prompt como si fuera parte
de un comando.

PASO 4 - VERIFICAR Y PREPARAR EL CANDIDATO EN LA VM
----------------------------------------------------
En una VM existente, el staging exige Java 17. Si falta,
instale el runtime PDF antes de preparar el candidato:

sudo apt-get update
sudo apt-get install -y --no-install-recommends openjdk-17-jre-headless

Dentro de la VM ejecute:

cd /tmp
sha256sum -c ${nombre}.sha256
bash ./$nombreScriptTransferencia

Que hace este paso:
- Vuelve a verificar SHA-256 dentro de la VM.
- Instala automaticamente el entorno completo como
  /etc/hubdigital/hubdigital.env con permisos 0600.
- Configura el rol y la base PostgreSQL con los valores de ese entorno.
- Extrae y construye un candidato Linux en /srv/hubdigital/staging.
- Comprueba PHP, extensiones, Composer y archivos runtime obligatorios.
- Verifica especificamente los recursos de Livewire Flux.
- No cambia current, no activa servicios y no aplica migraciones.
- Elimina de /tmp los cuatro archivos transferidos solo si el staging termina
  correctamente.

Los mensajes "Deprecated" de Composer son advertencias. El exito real se
confirma con "platform=ok" y "Candidato preparado correctamente".

PASO 5 - PREPARAR LA RELEASE Y APLICAR MIGRACIONES
--------------------------------------------
Al terminar el staging aparecera un bloque titulado:

COPIE Y PEGUE ESTE COMANDO EN LA VM (CON MIGRACIONES)

Cree y verifique un respaldo PostgreSQL antes de este paso. Copie y ejecute
exactamente el comando sudo env APPLY_MIGRATIONS=1 mostrado.
El ID, candidato y checksum se calculan dentro de la VM y por eso no pueden
escribirse anticipadamente en este manual.

APPLY_MIGRATIONS=1 aplica las migraciones pendientes despues del preflight
de PostgreSQL, R2 y temporales y antes de sembrar usuarios u optimizar la
release. El proceso verifica que no quede ninguna migracion pendiente antes
de declarar el candidato listo para activar.

Este paso valida PostgreSQL, R2, correo, temporales, colas y caches. Detiene
worker y scheduler y deja la release preparada en mantenimiento. Continue solo
si termina con:

Release preparada en mantenimiento: ID
Validaciones superadas. Puede activar ahora copiando y ejecutando:

Si aparece un error, no repita comandos ni active la release. Conserve toda la
salida para diagnosticar la causa.

PASO 6 - ACTIVAR LA RELEASE
---------------------------
El paso anterior mostrara el comando completo. Tendra esta forma:

sudo /srv/hubdigital/releases/ID/deploy/oracle/scripts/activate-release.sh ID

Use el mismo ID en la ruta y en el argumento. No use el ID de otro paquete.

La activacion comprueba automaticamente:
- La identidad e integridad de la release.
- Que current apunte a la release esperada.
- https://dev.labinvepn.org/ con HTTP 200.
- https://dev.labinvepn.org/depositos con HTTP 200.
- Nginx, PHP-FPM y el worker en estado active.
- Scheduler y Tunnel detenidos durante la validacion.
- El JAR Java de esta release: firma autentica, alterada y ausente; PDF
  con contenido activo o danado rechazado.
- El flujo PHP de depositos: numero magico y estructura PDF comprobados,
  contenido activo rechazado; PDF falso rechazado; firma sintetica valida aceptada,
  alterada rechazada y PDF sin firma detectado por el adaptador PHP/Java.

Cada comprobacion muestra OK o NO OK con la causa. Si alguna falla, el script
detiene el worker, devuelve Laravel a mantenimiento y termina con error.
El despliegue solo esta activo cuando aparece:

Verificacion final OK: release, URLs publicas, servicios, Java y admision PDF de depositos en el estado esperado.
Release activa en el origen directo: ID.

Si esta VM tenia ClamAV instalado, retire sus paquetes y firmas residuales
despues de activar esta release (la aplicacion ya no los utiliza):

sudo apt-get purge -y clamav clamav-base clamav-freshclam
sudo rm -rf -- /var/lib/clamav

PASO 7 - VERIFICACION EN EL NAVEGADOR
-------------------------------------
Abra https://dev.labinvepn.org y haga una recarga completa con Ctrl+F5.
Compruebe visualmente los cambios incluidos en este paquete.

SCHEDULER Y TUNNEL
------------------
El Scheduler ejecuta tareas Laravel programadas, como limpiar borradores y
evaluar plazos de devolucion. Permanece detenido hasta revisar sus efectos y
autorizarlo expresamente; no debe activarse como parte automatica de este flujo.

Cloudflare Tunnel es una via alternativa de entrada. Esta arquitectura publica
el origen directamente mediante Nginx y HTTPS, por lo que el servicio Tunnel
permanece detenido. El proxy DNS de Cloudflare no es lo mismo que Tunnel.

LIMPIEZA Y CONSERVACION
-----------------------
- Cloud Shell elimina automaticamente los archivos del despliegue despues de
  transferirlos; conserva la clave SSH.
- La VM elimina paquete, entorno, checksum y script de /tmp despues de un staging
  correcto.
- Despues de una activacion exitosa, la VM conserva automaticamente current y
  la release activada valida inmediatamente anterior. Elimina otras releases,
  candidatos fallidos y todo el staging antiguo.
- Windows conserva la carpeta del paquete en artifacts/oci hasta que usted la
  elimine. Puede regenerarla ejecutando crear-paquete-oci.
- La release activa dentro de /srv/hubdigital/releases NO debe eliminarse.

La limpieza automatica no toca PostgreSQL, R2, respaldos ni secretos. Para
revisar manualmente la politica sin borrar nada:

sudo /srv/hubdigital/current/deploy/oracle/scripts/cleanup-old-releases.sh --dry-run

Para aplicarla manualmente:

sudo /srv/hubdigital/current/deploy/oracle/scripts/cleanup-old-releases.sh --apply

DIAGNOSTICO SI FALLA LA ACTIVACION
----------------------------------
No use chmod 777 ni cambie current manualmente. Las migraciones se aplican
solo en el paso de preparacion indicado.
Consulte estos registros y conserve la salida:

sudo tail -n 150 /var/log/php8.4-fpm.log
sudo tail -n 150 /var/log/nginx/error.log
sudo journalctl -u php8.4-fpm.service --since "15 minutes ago" --no-pager -n 150

REGLAS IMPORTANTES
-------------------
- Copie solamente comandos, nunca los textos del prompt.
- Revise las migraciones y verifique un respaldo PostgreSQL antes del paso 5.
- No active Scheduler ni Tunnel durante este procedimiento.
- No borre la clave SSH ni la release activa.
- Ante cualquier NO OK, detengase y revise la causa antes de reintentar.
"@
    [IO.File]::WriteAllText(
        $instruccionesCloudShell,
        ($contenidoInstruccionesCloudShell.Trim() + "`r`n"),
        [Text.UTF8Encoding]::new($false)
    )
    $tamanoMiB = [Math]::Round((Get-Item -LiteralPath $paquete).Length / 1MB, 2)
}
finally {
    if ($dependenciasDesarrolloRetiradas) {
        Invoke-Comando -Programa $composerPrograma -Argumentos @(
            'install', '--no-interaction', '--prefer-dist', '--no-progress'
        ) -Descripcion 'Restaurando dependencias PHP de desarrollo locales' -DirectorioTrabajo $Proyecto
    }
}

Write-Host "`nProceso completo." -ForegroundColor Green
Write-Host "Commit:    $commit"
Write-Host "Mensaje:   $mensajeCommit"
Write-Host "Git:       $repositorioGit/tree/main"
Write-Host "Main:      $upstream sincronizado"
Write-Host "Identidad: $commit (incluida y verificada en el paquete)"
Write-Host "Directorio: $directorioPaquete"
Write-Host "Paquete:   $paquete"
Write-Host "Checksum:  $suma"
Write-Host "Script:    $scriptTransferencia"
Write-Host "Kit unico: $kitCloudShell"
Write-Host "Guia:      $instruccionesCloudShell"
Write-Host "SHA-256:   $hash"
Write-Host "SHA kit:   $hashKit"
Write-Host "Tamano:    $tamanoMiB MiB"
Write-Host 'Java PDF:  OK, compilacion Maven y autoprueba criptografica.'
Write-Host 'Gherkin:   OK, escenarios de negocio activos completados con Behat en modo estricto.'
if ($suitePostgresCompletada) {
    Write-Host 'Depositos: OK, suite PHP/PostgreSQL completada.'
} else {
    Write-Host 'Depositos: suite PHP/PostgreSQL omitida.' -ForegroundColor Yellow
}
if ($solucionPdfComprobada) {
    Write-Host 'Solucion PDF: OK, numero magico, estructura, contenido activo y firmas valida/alterada/ausente por PHP y Java.'
} else {
    Write-Host 'Solucion PDF: comprobacion integrada local omitida; se exigira al activar la release en OCI.' -ForegroundColor Yellow
}
Write-Host "`nSube solamente este archivo a OCI Cloud Shell: $nombreKitCloudShell"
Write-Host "Luego ejecuta:"
Write-Host "  mkdir -p ~/hubdigital-upload && tar -xzf ~/$nombreKitCloudShell -C ~/hubdigital-upload"
Write-Host "  cd ~/hubdigital-upload && bash ./$nombreScriptTransferencia"
Write-Host "`nEstos mismos comandos quedaron guardados en: $nombreInstruccionesCloudShell"
