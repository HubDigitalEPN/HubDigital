<#
.SYNOPSIS
Ejecuta el 40 % obligatorio del agente en localhost y sella su evidencia.
No solicita token, registra commits, publica ni genera paquetes OCI.
#>
[CmdletBinding()]
param([string]$Proyecto)

$ErrorActionPreference = 'Stop'
& (Join-Path $PSScriptRoot 'crear-paquete-oci-core.ps1') -Proyecto $Proyecto -ValidacionAgente
exit $LASTEXITCODE
