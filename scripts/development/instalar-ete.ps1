$ErrorActionPreference = 'Stop'
$raizETE = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$entornoETE = Join-Path $raizETE '.local\ete-venv'
$pythonETE = Join-Path $entornoETE 'Scripts\python.exe'
if (!(Test-Path -LiteralPath $pythonETE)) {
    & python -m venv $entornoETE
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el entorno Python de ETE.' }
}
& $pythonETE -m pip install -r (Join-Path $raizETE 'resources\python\portal_ete\requirements.txt')
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron instalar las dependencias de ETE.' }
Write-Host 'ETE instalado en el entorno aislado de localhost.'
