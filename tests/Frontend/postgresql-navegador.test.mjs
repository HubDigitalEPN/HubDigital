import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../..', import.meta.url));
const validator = 'scripts/deployment/windows/validar-postgresql-temporal.ps1';

test('el navegador prepara su esquema y su historial antes de copiar datos para Pest o PDF', () => {
    const command = String.raw`
$ErrorActionPreference = 'Stop'
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile(
    (Resolve-Path $env:HUBDIGITAL_QA_POSTGRES_VALIDATOR).Path, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw ($parseErrors | Out-String) }
$browser = $ast.Find({param($n) $n -is [System.Management.Automation.Language.IfStatementAst] -and
    $n.Clauses[0].Item1.Extent.Text -eq '$validarNavegador'}, $true)
$copies = $ast.Find({param($n) $n -is [System.Management.Automation.Language.IfStatementAst] -and
    $n.Clauses[0].Item1.Extent.Text -eq '$validarPdf -or $validarPhp'}, $true)
if (!$browser -or !$copies -or $browser.Extent.EndOffset -ge $copies.Extent.StartOffset) { throw 'Orden de las etapas inválido.' }

$patron = $ast.Find({param($n) $n -is [System.Management.Automation.Language.AssignmentStatementAst] -and
    $n.Left.Extent.Text -eq '$patronBaseAislada'}, $true)
$prefijoPruebas = 'hd_pkg_0123456789ab'
. ([ScriptBlock]::Create($patron.Extent.Text))
if ($prefijoPruebas + '_browser' -notmatch $patronBaseAislada -or 'hubdigital' -match $patronBaseAislada -or
    'hd_pkg_abcdef012345_browser' -match $patronBaseAislada) { throw 'La limpieza no está limitada a esta ejecución.' }

$etapa = [ScriptBlock]::Create(($browser.Clauses[0].Item2.Statements.Extent.Text -join [Environment]::NewLine))
$Proyecto = (Get-Location).Path
$raizPruebas = Join-Path $Proyecto '.local/validation/simulacion-node'
$basePruebas = 'hubdigital'
$pgBin = 'pg-mock'
$phpNativo = 'php-mock'
$PlanImpacto = 'plan-focal.json'
$seleccion = @{paquete = @{navegador_casos = @('modales-mapa')}}
$basesAisladas = [Collections.Generic.List[string]]::new()
$traza = [Collections.Generic.List[object]]::new()
$env:DB_URL = 'valor-previo'
$env:APP_CONFIG_CACHE = 'cache-previa'
function Join-Path($Path, $ChildPath) {
    if ($Path -eq $pgBin) { return $ChildPath }
    Microsoft.PowerShell.Management\Join-Path $Path $ChildPath
}
function createdb.exe {
    $traza.Add(@{op='crear'; args=$args})
    $global:LASTEXITCODE = 0
}
function pg_dump.exe {
    $traza.Add(@{op='exportar'; args=$args})
    $global:LASTEXITCODE = 0
}
function pg_restore.exe {
    $traza.Add(@{op='restaurar'; args=$args})
    $global:LASTEXITCODE = 0
}
function php-mock {
    $traza.Add(@{op='migrar'; args=$args; base=$env:DB_DATABASE; url=$env:DB_URL; cache=$env:APP_CONFIG_CACHE})
    $global:LASTEXITCODE = 0
}
function node.exe {
    $traza.Add(@{op='probar'; args=$args; base=$env:DB_DATABASE; url=$env:DB_URL; basePruebas=$env:TEST_DB_DATABASE})
    $global:LASTEXITCODE = 0
}
& $etapa
[pscustomobject]@{pasos=$traza; limpieza=$basesAisladas; url=$env:DB_URL; cache=$env:APP_CONFIG_CACHE} | ConvertTo-Json -Depth 5 -Compress
`;
    const encoded = Buffer.from(command, 'utf16le').toString('base64');
    const result = spawnSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-EncodedCommand', encoded], {
        cwd: root, encoding: 'utf8', windowsHide: true, env: { ...process.env, HUBDIGITAL_QA_POSTGRES_VALIDATOR: validator },
    });
    assert.equal(result.status, 0, result.stderr);
    const trace = JSON.parse(result.stdout.trim().split(/\r?\n/).at(-1));
    const browserDatabase = 'hd_pkg_0123456789ab_browser';
    assert.deepEqual(trace.pasos.map(step => step.op), ['exportar', 'exportar', 'crear', 'restaurar', 'restaurar', 'migrar', 'probar']);
    const [exportSchema, exportMigrations, create, restoreSchema, restoreMigrations, migrate, run] = trace.pasos;
    const connection = ['-h', '127.0.0.1', '-p', '5432', '-U', 'postgres'];
    const schemaArchive = exportSchema.args.at(-1);
    const migrationsArchive = exportMigrations.args.at(-1);
    assert.match(schemaArchive, /simulacion-node[\\/]browser-schema\.dump$/);
    assert.match(migrationsArchive, /simulacion-node[\\/]browser-migrations\.dump$/);
    assert.deepEqual(exportSchema.args.map(String), [...connection, '-d', 'hubdigital', '--schema-only', '-Fc', '-f', schemaArchive]);
    assert.deepEqual(exportMigrations.args.map(String), [...connection, '-d', 'hubdigital', '--data-only', '--table=public.migrations', '--strict-names', '--no-large-objects', '-Fc', '-f', migrationsArchive]);
    assert.deepEqual(create.args.map(String), [...connection, '--template=template0', browserDatabase]);
    const restore = [...connection, '-d', browserDatabase];
    const guards = ['--exit-on-error', '--no-owner', '--no-privileges'];
    assert.deepEqual(restoreSchema.args.map(String), [...restore, '--schema-only', ...guards, schemaArchive]);
    assert.deepEqual(restoreMigrations.args.map(String), [...restore, '--data-only', ...guards, migrationsArchive]);
    assert.deepEqual(migrate.args, ['artisan', 'migrate', '--database=pgsql', '--force', '--no-interaction']);
    assert.match(migrate.cache, /browser-config\.php$/);
    assert.deepEqual(run.args, ['tests/Browser/portal-livewire.mjs', 'php-mock', 'plan-focal.json']);
    for (const step of [migrate, run]) {
        assert.equal(step.base, browserDatabase);
        assert.equal(step.url, 'null');
    }
    assert.equal(run.basePruebas, browserDatabase);
    assert.deepEqual(trace.limpieza, [browserDatabase]);
    assert.equal(trace.url, 'valor-previo');
    assert.equal(trace.cache, 'cache-previa');
});
