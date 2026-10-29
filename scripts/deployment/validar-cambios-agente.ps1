<#
.SYNOPSIS
Ejecuta el 30 % obligatorio del agente en localhost y sella su evidencia.
No solicita token, registra commits, publica ni genera paquetes OCI.
#>
[CmdletBinding()]
param([string]$Proyecto, [switch]$Integral, [switch]$SoloDestinoPaquete, [switch]$SoloPreparacionActivacion, [switch]$SoloSelectorPaquete)

$ErrorActionPreference = 'Stop'
if ($SoloSelectorPaquete) {
    if ($Integral -or $SoloDestinoPaquete -or $SoloPreparacionActivacion) { throw 'Seleccione un solo alcance focalizado, sin -Integral.' }
    & node.exe --test (Join-Path $PSScriptRoot '..\..\tests\Frontend\impacto-validacion.test.mjs')
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Host 'SELECTOR_PAQUETE_OK: solo seleccion y avance de la base local; sin suites del sistema, Git ni VM.'
    exit 0
}
if ($SoloPreparacionActivacion) {
    if ($Integral -or $SoloDestinoPaquete) { throw 'Seleccione un solo alcance focalizado, sin -Integral.' }
    & node.exe --test (Join-Path $PSScriptRoot '..\..\tests\Frontend\preparacion-activacion.test.mjs')
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Host 'PREPARACION_ACTIVACION_OK: pruebas focalizadas locales; sin activar VM ni acreditar el 30 % completo.'
    exit 0
}
if ($SoloDestinoPaquete) {
    if ($Integral) { throw '-SoloDestinoPaquete no admite -Integral.' }
    # Contrato nuevo del destino, aislado de Laravel, Java, builds y las VM.
    # No invalida ni emite un recibo general del 30 %.
    & node.exe --test (Join-Path $PSScriptRoot '..\..\tests\Frontend\destino-paquete.test.mjs')
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Host 'DESTINO_PAQUETE_OK: contratos focalizados; sin acreditar el 30 % completo ni acceder a las VM.'
    exit 0
}
& (Join-Path $PSScriptRoot 'crear-paquete-oci-core.ps1') -Proyecto $Proyecto -ValidacionAgente -Integral:$Integral
exit $LASTEXITCODE
