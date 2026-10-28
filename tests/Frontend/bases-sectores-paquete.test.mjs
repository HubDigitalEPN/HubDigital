import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, readFileSync, rmSync, writeFileSync, mkdirSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {tmpdir} from 'node:os';
import path from 'node:path';
import sectors from '../../scripts/deployment/bases-sectores-paquete.cjs';

const now = Date.parse('2026-10-28T21:00:00Z');
const state = {environment: 'entorno-real', sources: {
    'resources/python/portal_ete/server.py': 'python', 'tests/Python/test_portal_ete.py': 'contratos',
    'scripts/development/instalar-ete.ps1': 'instalador', 'composer.lock': 'lock',
    'deploy/oracle/scripts/verify-deposit-pdf.php': 'verificador',
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Storage/ValidadorPdfDeposito.php': 'adaptador',
    'Modules/CatalogoPublico/resources/views/livewire/catalogo.blade.php': 'vista'},
    runtime: {ete: 'venv-completo', per_root: {vendor: 'vendor-completo', 'resources/bin/hubdigital-pdf-signature.jar': 'jar'}}};
const current = sector => sectors.inputs({...state, sector});
const approved = sector => ({version: 1, ...current(sector), completed_at: new Date(now - 1000).toISOString()});

test('las bases ETE/PDF omiten el sector sin cambios, no una fecha o un nombre de familia', () => {
    for (const sector of ['ete', 'pdf']) {
        assert.equal(sectors.compatible(approved(sector), current(sector), now), true);
        assert.equal(sectors.compatible(approved(sector), current(sector), now + 8 * 86400000), false);
        assert.equal(sectors.compatible({...approved(sector), completed_at: 'fecha-falsa'}, current(sector), now), false);
        assert.equal(sectors.compatible(null, current(sector), now), false);
    }
});

test('texto del portal no repite Python o PDF; sus entradas, dependencias y artefactos si', () => {
    const ui = {...state, sources: {...state.sources, 'Modules/CatalogoPublico/resources/views/livewire/catalogo.blade.php': 'nuevo texto'}};
    for (const sector of ['ete', 'pdf']) assert.equal(sectors.compatible(approved(sector), sectors.inputs({...ui, sector}), now), true);
    const changes = [
        ['ete', {...state, sources: {...state.sources, 'resources/python/portal_ete/server.py': 'nuevo render'}}],
        ['ete', {...state, sources: {...state.sources, 'tests/Python/test_nuevo.py': 'caso nuevo'}}],
        ['ete', {...state, runtime: {...state.runtime, ete: 'venv modificado'}}],
        ['pdf', {...state, runtime: {...state.runtime, per_root: {...state.runtime.per_root, vendor: 'vendor modificado'}}}],
        ['pdf', {...state, runtime: {...state.runtime, per_root: {...state.runtime.per_root, 'resources/bin/hubdigital-pdf-signature.jar': 'otro jar'}}}],
        ['pdf', {...state, sources: {...state.sources, 'Modules/GestionPrestamosRecepciones/app/Infrastructure/Storage/ValidadorPdfDeposito.php': 'eliminado'}}],
    ];
    for (const [sector, changed] of changes) assert.equal(sectors.compatible(approved(sector), sectors.inputs({...changed, sector}), now), false);
    assert.throws(() => sectors.inputs({...state, sector: 'pdf', runtime: {}}), /runtime/);
    assert.throws(() => sectors.inputs({...state, sector: 'ete', environment: 'incompleto:algo'}), /entorno/);
});

function withRoot(action) {
    const root = mkdtempSync(path.join(tmpdir(), 'hub-package-sector-'));
    try { action(root); }
    finally {
        assert.equal(path.dirname(root), path.resolve(tmpdir()));
        assert.ok(path.basename(root).startsWith('hub-package-sector-'));
        rmSync(root, {recursive: true, force: true});
    }
}

test('un sector aprobado sobrevive a cancelaciones posteriores sin acreditar Pest ni una base integral', () => withRoot(root => {
    const identity = {root, head: 'a'.repeat(40), ...current('ete')};
    assert.throws(() => sectors.accredit(root, 'ete', identity, now));
    sectors.prepare(root, 'ete', identity, now);
    const result = sectors.accredit(root, 'ete', identity, now + 1000);
    sectors.prepare(root, 'pdf', {root, head: identity.head, ...current('pdf')}, now + 2000);
    const base = sectors.load(root);
    assert.deepEqual(Object.keys(base.sectors), ['ete']);
    assert.equal(base.sectors.ete.completed_at, result.completed_at);
    assert.equal(base.full_test_coverage, undefined);
    assert.equal(base.sectors['php-pest'], undefined);
}));

test('un reintento invalida su sector y rechaza insumos modificados durante la prueba', () => withRoot(root => {
    const identity = {root, head: 'a'.repeat(40), ...current('pdf')};
    sectors.prepare(root, 'pdf', identity, now);
    sectors.accredit(root, 'pdf', identity, now + 1000);
    sectors.prepare(root, 'pdf', identity, now + 2000);
    assert.equal(sectors.load(root).sectors.pdf, undefined);
    assert.ok(sectors.load(root).invalidated.pdf);
    assert.throws(() => sectors.accredit(root, 'pdf', {...identity, output_sha256: 'otro jar'}, now + 3000), /cambiaron/);
    assert.equal(sectors.load(root).sectors.pdf, undefined);
}));

test('un PDF cancelado al revalidar no resucita la aprobacion historica anterior', () => withRoot(root => {
    const directory = path.join(root, '.local/validation');
    mkdirSync(directory, {recursive: true});
    const head = 'a'.repeat(40), source_sha256 = 'b'.repeat(64);
    const transcript = 'OK solución PDF depósitos: verificacion real anterior.\n';
    writeFileSync(path.join(directory, 'avance-paquete-cancelado-20261028.txt'), transcript);
    writeFileSync(path.join(directory, 'base-navegador-origen.json'), JSON.stringify({root, head, source_sha256, ...state}));
    writeFileSync(path.join(directory, 'avance-paquete-cancelado-20261028.json'), JSON.stringify({
        fuentes: {head, source_sha256, sha256: createHash('sha256').update(transcript).digest('hex')},
        observado_antes_de: new Date(now - 1000).toISOString(), aprobados: [{control: 'pdf-integracion', resultado: 'OK real'}]}));
    const identity = {root, head, ...current('pdf')};
    assert.ok(sectors.select(root, 'pdf', identity, {now}).previous);
    sectors.prepare(root, 'pdf', identity, now);
    assert.equal(sectors.select(root, 'pdf', identity, {now: now + 1000}).previous, null);
}));

test('el comando central conserva las once pruebas Python y acredita ETE inmediatamente despues de aprobarlas', () => {
    const core = readFileSync(new URL('../../scripts/deployment/crear-paquete-oci-core.ps1', import.meta.url), 'utf8');
    const selection = core.indexOf("'seleccionar-ete'");
    const prepare = core.indexOf("'preparar-ete'");
    const suite = core.indexOf("@('-m', 'unittest', 'discover', '-s', 'tests/Python', '-p', 'test_*.py')");
    const approved = core.indexOf("'acreditar-ete'");
    assert.ok(selection >= 0 && prepare > selection && suite > prepare && approved > suite);
    assert.equal(sectors.commands.pdf, 'php deploy/oracle/scripts/verify-deposit-pdf.php --medir-tiempo-local');
});
