import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../..', import.meta.url));
const validator = 'scripts/deployment/windows/validar-postgresql-temporal.ps1';

test('el validador OCI prepara PHP y PDF sin invocar Opera Neon', () => {
    const command = String.raw`
$ErrorActionPreference = 'Stop'
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile(
    (Resolve-Path $env:HUBDIGITAL_QA_POSTGRES_VALIDATOR).Path, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw ($parseErrors | Out-String) }
$copies = $ast.Find({param($n) $n -is [System.Management.Automation.Language.IfStatementAst] -and
    $n.Clauses[0].Item1.Extent.Text -eq '$validarPdf -or $validarPhp'}, $true)
if (!$copies) { throw 'Falta la preparacion aislada de PHP y PDF.' }
if ($ast.Extent.Text -match 'portal-livewire|validarNavegador|paquete\.navegador|Opera Neon') {
    throw 'El validador OCI aun contiene un recorrido de navegador.'
}
$core = [System.Management.Automation.Language.Parser]::ParseFile(
    (Resolve-Path 'scripts/deployment/crear-paquete-oci-core.ps1').Path, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw ($parseErrors | Out-String) }
if ($core.Extent.Text -match 'portal-livewire|suiteNavegadorCompletada|paquete\.navegador|ui_focal|vendor/bin/behat|FlujoDepositoPersistenciaE2ETest') {
    throw 'El comando OCI aun requiere una prueba funcional del sistema.'
}
if ($core.Extent.Text -notmatch 'vendor/bin/pest' -or $core.Extent.Text -notmatch 'verify-deposit-pdf\.php') {
    throw 'Faltan los controles técnicos PHP o PDF.'
}
$tecnico = $ast.Find({param($n) $n -is [System.Management.Automation.Language.IfStatementAst] -and
    $n.Clauses[0].Item1.Extent.Text -eq '$ContratosTecnicos'}, $true)
if ($core.Extent.Text -notmatch "'-ContratosTecnicos'" -or !$tecnico -or
    $tecnico.Clauses[0].Item2.Extent.Text -notmatch '--schema-only' -or
    $tecnico.Clauses[0].Item2.Extent.Text -notmatch '--table=public.migrations') {
    throw 'La integración PostgreSQL requiere el modo técnico sin copiar datos de usuarios.'
}
if ($core.Extent.Text -notmatch '\$Destino = ''W''' -or $core.Extent.Text -notmatch 'Guardar-RespaldoFuentes -Raiz \$Proyecto') {
    throw 'Falta el respaldo automatico o el destino local predeterminado.'
}
[void][System.Management.Automation.Language.Parser]::ParseFile(
    (Resolve-Path 'scripts/development/localhost.ps1').Path, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw ($parseErrors | Out-String) }
$patron = $ast.Find({param($n) $n -is [System.Management.Automation.Language.AssignmentStatementAst] -and
    $n.Left.Extent.Text -eq '$patronBaseAislada'}, $true)
$prefijoPruebas = 'hd_pkg_0123456789ab'
. ([ScriptBlock]::Create($patron.Extent.Text))
if (($prefijoPruebas + '_1') -notmatch $patronBaseAislada -or
    ($prefijoPruebas + '_1_test_2') -notmatch $patronBaseAislada -or
    ($prefijoPruebas + '_browser') -match $patronBaseAislada -or
    'hubdigital' -match $patronBaseAislada -or 'hd_pkg_abcdef012345_1' -match $patronBaseAislada) {
    throw 'La limpieza no esta limitada a las bases PHP de esta ejecucion.'
}
`;
    const encoded = Buffer.from(command, 'utf16le').toString('base64');
    const result = spawnSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-EncodedCommand', encoded], {
        cwd: root, encoding: 'utf8', windowsHide: true, env: { ...process.env, HUBDIGITAL_QA_POSTGRES_VALIDATOR: validator },
    });
    assert.equal(result.status, 0, result.stderr);
});

test('el ZIP conserva los bytes de las fuentes y excluye documentos y pruebas', () => {
    const command = String.raw`
$ErrorActionPreference = 'Stop'
$tokens = $null
$errors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile(
    (Resolve-Path 'scripts/deployment/crear-paquete-oci-core.ps1').Path, [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw ($errors | Out-String) }
$fn = $ast.Find({param($n) $n -is [System.Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -eq 'Guardar-RespaldoFuentes'}, $true)
. ([ScriptBlock]::Create($fn.Extent.Text))
$temp = Join-Path ([IO.Path]::GetTempPath()) ('hd-codigo-zip-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp | Out-Null
try {
    Set-Content -LiteralPath (Join-Path $temp 'app.php') -Value '<?php return 123;' -Encoding Ascii
    Set-Content -LiteralPath (Join-Path $temp 'package-lock.json') -Value '{}' -Encoding Ascii
    Set-Content -LiteralPath (Join-Path $temp 'AccesoTest.php') -Value 'prueba' -Encoding Ascii
    Set-Content -LiteralPath (Join-Path $temp 'manual.pdf') -Value 'documento' -Encoding Ascii
    $archivos = @('app.php','package-lock.json','AccesoTest.php','manual.pdf')
    $manifest = @(foreach ($nombre in $archivos) { (Get-FileHash -LiteralPath (Join-Path $temp $nombre)).Hash.ToLowerInvariant() + '  ' + $nombre })
    Guardar-RespaldoFuentes -Raiz $temp -Huellas $manifest -Commit ('1' * 40)
    $carpeta = Join-Path $temp '.local/backups/codigo-fuente'
    $archivo = @(Get-ChildItem -LiteralPath $carpeta -Filter '*.zip')
    if ($archivo.Count -ne 1) { throw 'El paquete debe dejar un solo ZIP por ejecucion.' }
    $zip = [IO.Compression.ZipFile]::OpenRead($archivo[0].FullName)
    try {
        if (($zip.Entries.FullName -join ',') -ne 'app.php,package-lock.json') { throw 'El ZIP incluye archivos ajenos a la fuente.' }
    } finally { $zip.Dispose() }
    $manifest[0] = ('0' * 64) + '  app.php'
    $rechazo = $false
    try { Guardar-RespaldoFuentes -Raiz $temp -Huellas $manifest -Commit ('1' * 40) }
    catch { $rechazo = $true }
    if (!$rechazo) { throw 'Se acepto una huella incorrecta.' }
    if (@(Get-ChildItem -LiteralPath $carpeta).Count -ne 1) { throw 'Quedaron respaldos sin verificar.' }
} finally {
    $resuelto = (Resolve-Path -LiteralPath $temp).Path
    if ([IO.Path]::GetDirectoryName($resuelto) -eq [IO.Path]::GetTempPath().TrimEnd('\') -and [IO.Path]::GetFileName($resuelto) -like 'hd-codigo-zip-*') {
        Remove-Item -LiteralPath $resuelto -Recurse -Force
    }
}
`;
    const encoded = Buffer.from(command, 'utf16le').toString('base64');
    const result = spawnSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-EncodedCommand', encoded], {
        cwd: root, encoding: 'utf8', windowsHide: true,
    });
    assert.equal(result.status, 0, result.stderr);
});
