import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, mkdtempSync, writeFileSync, mkdirSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
import browser from '../../scripts/deployment/base-navegador.cjs';

const root = path.resolve(fileURLToPath(new URL('../..', import.meta.url)));
const runner = readFileSync(path.join(root, 'tests/Browser/portal-livewire.mjs'), 'utf8').replaceAll('\r\n', '\n');
const layout = JSON.parse(readFileSync(path.join(root, 'tests/Browser/portal-casos.json'), 'utf8'));
const now = Date.parse('2026-10-28T22:00:00Z');
function state(extra = {}) {
    const texts = {'tests/Browser/portal-livewire.mjs': runner, 'tests/Browser/portal-casos.json': JSON.stringify(layout),
        'tests/Browser/portal-fixture.php': '<?php // fixture', ...extra};
    return {root: '/workspace', contents: texts, environment: 'env', runtime: {per_root: {vendor: 'vendor'}},
        sources: Object.fromEntries(Object.entries(texts).map(([f, t]) => [f, browser.hash(t)]))};
}
function entries(current, target = '/workspace') {
    return Object.fromEntries(browser.ids.map(id => [id, {root: target, status: 'passed', approved_at: new Date(now - 1000).toISOString(),
        inputs: current[id].values, input_sha256: current[id].sha256, provenance: {type: 'case-run-success'}}]));
}
test('los nueve cuerpos conservan literalmente sus aserciones y el runner se puede interpretar sin lanzar Neon', () => {
    const parts = browser.runnerParts(runner);
    for (const id of browser.ids) {
        const body = browser.preservedBody(parts.bodies[id]);
        assert.equal(browser.hash(body), layout.cases[id].body_sha256, id);
        assert.equal(browser.assertionStatements(body).length, layout.cases[id].assertions, id);
    }
    const syntax = spawnSync(process.execPath, ['--check', path.join(root, 'tests/Browser/portal-livewire.mjs')], {encoding: 'utf8', windowsHide: true});
    assert.equal(syntax.status, 0, syntax.stderr);
});
test('sin impacto reutiliza los nueve casos y mantiene sus fechas reales sin acreditar Pest', () => {
    const current = browser.inputs(state());
    const plan = browser.select({root: '/workspace', entries: entries(current), current, now});
    assert.deepEqual(plan.ejecutar, []); assert.equal(plan.reutilizar.length, 9);
    assert.equal(plan.evidencia['tooltip-livewire'].approved_at, new Date(now - 1000).toISOString());
    assert.equal(plan.full_test_coverage, undefined);
});

test('no conserva una aprobacion si cambia una entrada mientras el recorrido esta en curso', () => {
    const temporary = mkdtempSync(path.join(tmpdir(), 'hub-browser-stable-'));
    try {
        const file = 'resources/js/portal-photo-model.js';
        const original = state({[file]: 'export const photo = 1;'});
        for (const [relative, text] of Object.entries(original.contents)) {
            const filename = path.join(temporary, relative);
            mkdirSync(path.dirname(filename), {recursive: true}); writeFileSync(filename, text);
        }
        const plan = {inputs: browser.inputs(original)};
        browser.assertCaseUnchanged(temporary, plan, 'fotografias');
        writeFileSync(path.join(temporary, file), 'export const photo = 2;');
        assert.throws(() => browser.assertCaseUnchanged(temporary, plan, 'fotografias'), /Cambio durante el recorrido/);
    } finally {
        assert.equal(path.dirname(temporary), path.resolve(tmpdir()));
        assert.ok(path.basename(temporary).startsWith('hub-browser-stable-'));
        rmSync(temporary, {recursive: true, force: true});
    }
});
test('cambiar el selector o el empaquetado no invalida entradas funcionales ya aprobadas', () => {
    const before = browser.inputs(state({'scripts/deployment/impacto-validacion.cjs': 'anterior'}));
    const current = browser.inputs(state({'scripts/deployment/impacto-validacion.cjs': 'nuevo'}));
    assert.deepEqual(current, before);
});
test('un método aislado de tooltip no ejecuta árbol, fotos ni otros recorridos del dashboard', () => {
    const file = 'resources/js/portal-dashboard.js';
    const text = "const estado = {};\n        mostrarAyudaMapa(texto) { return texto; },\n        ocultarAyudaMapa() { return false; },\n        pintar() { return true; }\n";
    const previous = browser.inputs(state({[file]: text}));
    const current = browser.inputs(state({[file]: text.replace('return texto;', 'return texto.trim();')}));
    const plan = browser.select({root: '/workspace', entries: entries(previous), current, now});
    assert.deepEqual(plan.ejecutar, ['tooltip-livewire']);
    assert.match(plan.motivos['tooltip-livewire'], /mostrarAyudaMapa/);
});
test('el método de portadas de un controlador monolítico afecta únicamente fotografías', () => {
    const file = 'Modules/CatalogoPublico/app/Presentation/Http/Controllers/PortalCatalogo.php';
    const text = '<?php class PortalCatalogo {\n    public function render() { return 1; }\n    private function cargarPortadas() { return []; }\n}';
    const previous = browser.inputs(state({[file]: text}));
    const current = browser.inputs(state({[file]: text.replace('return [];', 'return [1];')}));
    assert.deepEqual(browser.select({root: '/workspace', entries: entries(previous), current, now}).ejecutar, ['fotografias']);
});
test('sigue consumidores indirectos y conserva hashes binarios sin convertir imágenes a texto', () => {
    const leaf = 'public/images/taxonomia/test.webp', consumer = 'resources/js/portal-search.js';
    const old = state(); old.sources[leaf] = browser.hash(Buffer.from([0xff, 0x80, 0x00]));
    old.dependencies = {[leaf]: ['resources/js/puente.js'], 'resources/js/puente.js': [consumer]};
    const previous = browser.inputs(old);
    assert.equal(previous.fotografias.values[leaf], old.sources[leaf]);
    const next = {...old, sources: {...old.sources, [leaf]: 'nuevo'}};
    const plan = browser.select({root: '/workspace', entries: entries(previous), current: browser.inputs(next), now});
    assert.deepEqual(plan.ejecutar, ['filtros-graficos', 'tooltip-livewire', 'fotografias']);
});
test('autenticación, dependencia compartida, ausencia, incertidumbre o expiración exigen casos compatibles', () => {
    const previous = browser.inputs(state());
    for (const file of ['app/Models/User.php', 'composer.lock', 'Modules/CatalogoPublico/app/ServicioSinClasificar.php']) {
        const current = browser.inputs(state({[file]: 'nuevo'}));
        assert.equal(browser.select({root: '/workspace', entries: entries(previous), current, now}).ejecutar.length, 9, file);
    }
    assert.equal(browser.select({root: '/workspace', entries: null, current: previous, now}).ejecutar.length, 9);
    assert.equal(browser.select({root: '/workspace', entries: entries(previous), current: previous, now, force: true}).ejecutar.length, 9);
    assert.equal(browser.select({root: '/workspace', entries: entries(previous), current: previous, now: now + 8 * 86400000}).ejecutar.length, 9);
});
test('fallar o cancelar nunca aprueba el caso activo; conserva los completados y las fechas reutilizadas', () => {
    const temporary = mkdtempSync(path.join(tmpdir(), 'hub-browser-receipt-'));
    try {
        const current = browser.inputs(state()), old = entries(current, temporary);
        const filename = path.join(temporary, '.local/validation/base-navegador.json'); mkdirSync(path.dirname(filename), {recursive: true});
        writeFileSync(filename, JSON.stringify({version: 1, root: temporary, cases: old}));
        const plan = {root: temporary, ejecutar: ['tooltip-livewire', 'fotografias'], inputs: current};
        const active = browser.prepareRun(temporary, plan);
        browser.approveCase(active, 'tooltip-livewire', now);
        // Cancelación antes de approveCase(fotografias): no hay aprobación inventada.
        const stored = JSON.parse(readFileSync(filename));
        assert.equal(stored.cases.fotografias, undefined);
        assert.equal(stored.cases['tooltip-livewire'].approved_at, new Date(now).toISOString());
        assert.equal(stored.cases['arbol-diversidad'].approved_at, old['arbol-diversidad'].approved_at);
        assert.throws(() => browser.approveCase(active, 'arbol-diversidad'), /no ejecutado/);
    } finally { assert.ok(path.basename(temporary).startsWith('hub-browser-receipt-')); rmSync(temporary, {recursive: true, force: true}); }
});
test('la migración solo hereda cuerpos originales completos y distingue evidencia previa de runner nuevo', () => {
    const bodies = Object.fromEntries(browser.ids.map(id => [id, `    assert.equal('${id}', '${id}');\n`]));
    const original = Object.values(bodies).join('');
    const next = browser.ids.map(id => `    // CASO ${id} INICIO\n${bodies[id]}    // CASO ${id} FIN\n`).join('');
    const ownLayout = {version: 1, original_runner_sha256: browser.hash(original), helpers: [],
        cases: Object.fromEntries(browser.ids.map((id, i) => [id, {ranges: [[i + 1, i + 1]], body_sha256: browser.hash(bodies[id])}]))};
    const current = Object.fromEntries(browser.ids.map(id => [id, {sha256: id, values: {entorno: 'env', vendor: 'vendor'}}]));
    const origin = {version: 1, root: '/workspace', scope: 'portal-browser-full', approved_at: new Date(now - 1000).toISOString(),
        confirmed_at: new Date(now).toISOString(), approval: {type: 'real-browser-success-confirmed-by-user', message: 'OK navegador: completo'},
        runner: original, runner_sha256: browser.hash(original), fixture_sha256: 'fixture', runtime: {per_root: {vendor: 'vendor'}}, environment: 'env', sources: {}};
    const args = {origin, root: '/workspace', current, runner: next, fixtureHash: 'fixture', layout: ownLayout, now};
    const inherited = browser.migration(args);
    assert.equal(Object.keys(inherited).length, 9);
    assert.equal(inherited['tooltip-livewire'].approved_at, origin.approved_at);
    assert.equal(inherited['tooltip-livewire'].provenance.new_runner_executed, false);
    assert.throws(() => browser.migration({...args, runner: next.replace(bodies.fotografias, '    if (false) {\n' + bodies.fotografias + '    }\n')}), /cuerpo.*cambió/);
    assert.deepEqual(browser.migration({...args, fixtureHash: 'otra'}), {});
});
