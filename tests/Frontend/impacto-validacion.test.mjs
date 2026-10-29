import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, mkdirSync, writeFileSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import impact from '../../scripts/deployment/impacto-validacion.cjs';
import browser from '../../scripts/deployment/base-navegador.cjs';
import {readFileSync} from 'node:fs';
import {spawnSync} from 'node:child_process';

const now = Date.parse('2026-10-28T20:00:00Z');
const source = 'Modules/CatalogoPublico/app/Servicio.php';
const inventorySource = 'Modules/InventarioGestionColeccion/app/Servicio.php';
const js = 'resources/js/portal-map-model.js';
const tests = ['tests/Feature/PortalCatalogoTest.php', 'tests/Feature/DepositosPortalTest.php',
    'tests/Feature/Auth/AuthenticationTest.php', 'Modules/InventarioGestionColeccion/tests/Unit/ServicioTest.php',
    'tests/Frontend/portal-map-model.test.mjs', 'tests/Frontend/manual-password.test.mjs', 'tests/Frontend/consumer.test.mjs'];
const original = Object.fromEntries([source, inventorySource, js, 'README.md', ...tests,
    'Modules/CatalogoPublico/tests/Behat/Features/catalogo.feature',
    'Modules/GestionPrestamosRecepciones/tests/Behat/Features/depositos.feature'].map(f => [f, 'a'.repeat(64)]));
const runtime = {per_root: Object.fromEntries(['vendor', 'jar', 'build1', 'build2', 'build3', 'build4'].map(k => [k, 'x']))};
const baseline = {version: 1, full_test_coverage: true, root: '/workspace', head: 'a'.repeat(40),
    completed_at: new Date(now - 1000).toISOString(), policy_sha256: impact.policyHash, sources: original, runtime, environment: 'env'};
const plan = (files, overrides = {}) => impact.selectPlan({files, baseline, runtime, root: '/workspace', head: 'b'.repeat(40),
    environment: 'env', ancestor: true, now, ...overrides});

test('el cuerpo DOM focal exige su recibo MCP propio sin seleccionar los nueve recorridos', () => {
    const p=plan({...original,'tests/Browser/contratos-ui-focales.mjs':'nuevo'});
    assert.deepEqual(p.pendientes,[]);
    assert.equal(p.paquete.navegador,false);
    p.ui_focal={cases:['laboratorio']};
    assert.throws(()=>impact.assertCoverage(p),/UI focal laboratorio/);
});

test('falta de base, linaje incompatible o fecha invalida detienen sin ejecutar todo', () => {
    for (const base of [null, {...baseline, full_test_coverage: false}, {...baseline, completed_at: 'invalida'}]) {
        assert.throws(() => plan(original, {baseline: base}), /base|fecha/);
    }
    assert.throws(() => plan(original, {ancestor: false}), /base/);
    assert.equal(plan(original, {force: true, baseline: null}).integral, true);
});

test('antiguedad, politica, entorno y numero de cambios nunca fuerzan integral', () => {
    const p = plan({...original, ...Object.fromEntries(Array.from({length: 40}, (_, i) => [`docs/nota-${i}.md`, 'nueva']))},
        {baseline: {...baseline, completed_at: new Date(now - 365 * 86400000).toISOString(), policy_sha256: 'politica-anterior'}, environment: 'otro'});
    assert.equal(p.integral, false);
    assert.deepEqual(p.agente.node_tests, []);
    assert.deepEqual(p.paquete.pruebas_php, []);
    assert.equal(p.paquete.postgres, false);
});

test('documentacion aislada reutiliza suites sin ocultar las auditorias ni la integridad', () => {
    const p = plan({...original, 'README.md': 'b'.repeat(64)});
    assert.equal(p.integral, false);
    assert.equal(p.paquete.postgres, false);
    assert.equal(p.paquete.gherkin, false);
    assert.deepEqual(p.agente.frontend, []);
    assert.deepEqual(p.agente.node_tests, []);
    assert.equal(p.agente.composer, false);
    assert.ok(p.siempre.includes('auditoria-composer'));
    assert.ok(p.siempre.includes('auditoria-npm-cuatro-proyectos'));
    assert.ok(p.siempre.includes('integridad-identidad-artefacto'));
});

test('una funcion selecciona solo sus contratos propios, sin consumidores transitivos ni PHP comun', () => {
    const own = 'tests/Feature/PortalCatalogoTest.php';
    const p = plan({...original, [source]: 'b'.repeat(64)}, {dependencies: {portal: ['inventario']}, fileDependencies: {
        [source]: [own, inventorySource], [inventorySource]: ['Modules/InventarioGestionColeccion/tests/Unit/ServicioTest.php']}});
    assert.equal(p.integral, false);
    assert.deepEqual(p.areas, ['portal']);
    assert.deepEqual(p.paquete.pruebas_php, [own]);
    assert.ok(p.cobertura.php.reutilizar.includes('tests/Feature/DepositosPortalTest.php'));
    assert.deepEqual(p.paquete.gherkin_paths, []);
    assert.equal(p.agente.java_test, false);
    assert.equal(p.paquete.python, false);
    assert.equal(p.paquete.pdf, false);
    assert.equal(p.paquete.navegador, false);
});

test('cambios criticos e incertidumbre no se convierten en una suite integral automatica', () => {
    for (const file of ['composer.lock', 'app/Models/User.php', 'config/botones.php',
        'Modules/CatalogoPublico/database/migrations/nueva.php', 'Modules/CatalogoPublico/app/Permisos/Acceso.php', 'app/Support/SinClasificacion.php']) {
        const p = plan({...original, [file]: 'nuevo'}, {uncertain: [file]});
        assert.equal(p.integral, false, file);
        assert.deepEqual(p.paquete.pruebas_php, []);
        if (file.endsWith('.php')) assert.throws(() => impact.assertCoverage(p), /Falta delimitar cobertura/);
    }
    assert.equal(plan(original, {force: true}).integral, true);
    const p = plan({...original, [source]: 'b'}, {uncertain: [source]});
    assert.equal(p.integral, false);
    assert.throws(() => impact.assertCoverage(p), /Falta delimitar cobertura/);
});

test('una diferencia de artefactos no autoriza repetir pruebas funcionales ajenas al cambio', () => {
    for (const key of Object.keys(runtime.per_root)) {
        const current = {per_root: {...runtime.per_root, [key]: 'cambio'}};
        const p = plan(original, {runtime: current});
        assert.equal(p.integral, false);
        assert.deepEqual(p.paquete.pruebas_php, []);
        assert.deepEqual(p.agente.node_tests, []);
    }
});

test('un componente no hereda dependencias de servicios distintos del mismo modulo', () => {
    const broad = {portal: ['inventario'], inventario: ['depositos'], depositos: ['portal']};
    const isolated = plan({...original, [js]: 'b'}, {dependencies: broad, fileDependencies: {[js]: ['tests/Frontend/consumer.test.mjs']}});
    assert.equal(isolated.integral, false);
    assert.deepEqual(isolated.areas, ['portal']);
    assert.ok(isolated.agente.node_tests.includes('tests/Frontend/consumer.test.mjs'));
    const php = plan({...original, [source]: 'b'}, {dependencies: broad, fileDependencies: {[source]: ['app/Support/Puente.php'],
        'app/Support/Puente.php': [inventorySource], [inventorySource]: ['Modules/InventarioGestionColeccion/tests/Unit/ServicioTest.php']}});
    assert.equal(php.integral, false);
    assert.deepEqual(php.areas, ['portal']);
    assert.deepEqual(php.paquete.pruebas_php, []);
});

test('no usa pruebas indirectas como sustituto de cobertura propia del cambio', () => {
    const related = 'Modules/InventarioGestionColeccion/tests/Unit/ServicioTest.php';
    const p = plan({...original, [source]: 'b'}, {fileDependencies: {[source]: ['app/Support/Puente.php'], 'app/Support/Puente.php': [related]}});
    assert.equal(p.integral, false);
    assert.deepEqual(p.paquete.pruebas_php, []);
    assert.throws(() => impact.assertCoverage(p), /Falta delimitar cobertura/);
    assert.ok(p.cobertura.php.reutilizar.includes('tests/Feature/PortalCatalogoTest.php'));
    assert.ok(p.cobertura.node.reutilizar.includes('tests/Frontend/portal-map-model.test.mjs'));
});

test('compara cambios acumulados, incluye eliminaciones y no expande a consumidores JavaScript', () => {
    const p = plan({...original, [source]: 'b', [inventorySource]: 'c', [js]: 'eliminado'}, {
        nodeDependencies: {[js]: ['resources/js/portal-puente.js'], 'resources/js/portal-puente.js': ['tests/Frontend/consumer.test.mjs']},
    });
    assert.equal(p.integral, false);
    assert.deepEqual(p.changes.sort(), [source, inventorySource, js].sort());
    assert.deepEqual(p.agente.node_tests, []);
    const crossBuild = plan({...original, [js]: 'b'}, {nodeDependencies: {[js]: ['Modules/InventarioGestionColeccion/resources/assets/js/app.js']}});
    assert.deepEqual(crossBuild.agente.frontend, ['.']);
    const combined = [...p.cobertura.php.ejecutar, ...p.cobertura.php.reutilizar];
    assert.equal(new Set(combined).size, combined.length);
    assert.deepEqual(combined.sort(), tests.filter(f => f.endsWith('.php')).sort());
});

test('cada frontend reconstruye solo el proyecto de los recursos modificados', () => {
    const file = 'Modules/InventarioGestionColeccion/resources/css/app.css';
    const p = plan({...original, [file]: 'css'});
    assert.deepEqual(p.agente.frontend, ['Modules/InventarioGestionColeccion']);
    assert.equal(p.paquete.postgres, false);
    assert.equal(p.paquete.gherkin, false);
    assert.equal(p.agente.composer, false);
});

test('Python y Gherkin seleccionan archivos propios, sin incluir todos los del modulo', () => {
    const python = 'tests/Python/test_portal_ete.py', feature = 'Modules/CatalogoPublico/tests/Behat/Features/catalogo.feature';
    const p = plan({...original, [python]: 'nuevo', [feature]: 'nuevo'});
    assert.deepEqual(p.paquete.python_tests, [python]);
    assert.deepEqual(p.paquete.gherkin_paths, [feature]);
    assert.equal(p.paquete.ete, true);
});

test('el cambio aprobado pasa a ser la siguiente base sin reexaminar cambios anteriores', () => {
    const first = {...original, 'README.md': 'cambio-aprobado'};
    const p = plan(first);
    const next = impact.approvedBase({plan: p, previous: baseline, files: first, runtime, environment: 'env', root: '/workspace', head: baseline.head,
        completedAt: new Date(now).toISOString()});
    assert.equal(next.approval_kind, 'incremental');
    assert.equal(next.integral_approved_at, baseline.completed_at);
    assert.deepEqual(next.approved_changes, ['README.md']);
    assert.deepEqual(plan(first, {baseline: next}).changes, []);
    const second = plan({...first, [js]: 'cambio-nuevo'}, {baseline: next, fileDependencies: {[js]: ['tests/Frontend/consumer.test.mjs']}});
    assert.deepEqual(second.changes, [js]);
    assert.deepEqual(second.agente.node_tests, ['tests/Frontend/consumer.test.mjs']);
    assert.equal(baseline.sources['README.md'], original['README.md']);
});

test('no incorpora un cambio sin cobertura ni sobrescribe una base alterada durante las pruebas', () => {
    const files = {...original, [source]: 'sin-cobertura'};
    const args = {plan: plan(files), previous: baseline, files, runtime, environment: 'env', root: '/workspace', head: baseline.head, completedAt: new Date(now).toISOString()};
    assert.throws(() => impact.approvedBase(args), /Falta delimitar cobertura/);
    assert.equal(baseline.sources[source], original[source]);
    const p = plan({...original, 'README.md': 'nota'});
    assert.throws(() => impact.approvedBase({...args, plan: p, previous: {...baseline, head: 'c'.repeat(40)}}), /base aprobada cambio/);
});

test('la antiguedad de UI intacta no abre Neon ni repite sus nueve recorridos', () => {
    const current = Object.fromEntries(browser.ids.map(id => [id, {sha256: id, values: {entorno: 'env'}}]));
    const entries = Object.fromEntries(browser.ids.map(id => [id, {root: '/workspace', status: 'passed', input_sha256: id,
        inputs: current[id].values, approved_at: new Date(now - 365 * 86400000).toISOString(), provenance: {type: 'case-run-success'}}]));
    const result = browser.select({root: '/workspace', entries, current, now, onlyCases: []});
    assert.deepEqual(result.ejecutar, []);
    assert.equal(result.reutilizar.length, 9);
    assert.equal(result.evidencia.fotografias.approved_at, entries.fotografias.approved_at);
    current.fotografias.sha256 = 'foto-nueva';
    const changed = browser.select({root: '/workspace', entries, current, now, onlyCases: ['fotografias']});
    assert.deepEqual(changed.ejecutar, ['fotografias']);
});

test('el plan real de UI y PDF no reintroduce suites fuera del cambio de fotografias', () => {
    const temporary = mkdtempSync(path.join(tmpdir(), 'hub-impact-ui-'));
    try {
        const runner = browser.ids.map(id => `    // CASO ${id} INICIO\n    assert.ok(true);\n    // CASO ${id} FIN\n`).join('');
        const content = {'tests/Browser/portal-livewire.mjs': runner,
            'tests/Browser/portal-casos.json': JSON.stringify({cases: Object.fromEntries(browser.ids.map(id => [id, {}]))}),
            'tests/Browser/portal-fixture.php': '<?php // fixture', 'composer.lock': '{}', 'resources/js/portal-photo-model.js': 'export const photo=1;'};
        for (const [file, text] of Object.entries(content)) {
            mkdirSync(path.dirname(path.join(temporary, file)), {recursive: true}); writeFileSync(path.join(temporary, file), text);
        }
        const files = Object.fromEntries(Object.entries(content).map(([file, text]) => [file, browser.hash(text)]));
        const outputs = {per_root: {...runtime.per_root, 'resources/bin/hubdigital-pdf-signature.jar': 'jar'}};
        const prior = browser.inputs({root: temporary, sources: files, runtime: outputs, environment: 'env'});
        const cases = Object.fromEntries(browser.ids.map(id => [id, {root: temporary, status: 'passed', approved_at: baseline.completed_at,
            input_sha256: prior[id].sha256, inputs: prior[id].values, provenance: {type: 'case-run-success'}}]));
        const basePath = path.join(temporary, '.local/validation/base-navegador.json'); mkdirSync(path.dirname(basePath), {recursive: true});
        writeFileSync(basePath, JSON.stringify({version: 1, root: temporary, cases}));
        const changed = {...files, 'resources/js/portal-photo-model.js': browser.hash('export const photo=2;')};
        writeFileSync(path.join(temporary, 'resources/js/portal-photo-model.js'), 'export const photo=2;');
        const p = plan(changed, {root: temporary, baseline: {...baseline, root: temporary, sources: files}});
        impact.refreshRuntimePlan({root: temporary, head: baseline.head, files: changed, runtime: outputs, environment: 'env', plan: p});
        assert.deepEqual(p.navegador.ejecutar, ['fotografias']);
        assert.equal(p.paquete.pdf, false);
        assert.equal(p.paquete.python, false);
        assert.deepEqual(p.paquete.pruebas_php, []);
        assert.deepEqual(p.pendientes, []);
        // El verificador respeta el mismo alcance y no vuelve al grafo transitivo.
        browser.verify(temporary, p.navegador, {sources: changed, runtime: outputs, environment: 'env',
            dependencies: {'resources/js/portal-photo-model.js': ['resources/js/portal-search.js']}});
    } finally { assert.equal(path.dirname(temporary), path.resolve(tmpdir())); assert.ok(path.basename(temporary).startsWith('hub-impact-ui-')); rmSync(temporary, {recursive: true, force: true}); }
});

test('un fragmento desconocido del dashboard se informa pendiente y no activa nueve recorridos', () => {
    const temporary = mkdtempSync(path.join(tmpdir(), 'hub-impact-fragment-'));
    try {
        const file = 'resources/js/portal-dashboard.js';
        const before = 'const state = {};\n        mostrarAyudaMapa(texto) { return texto; },\n        ocultarAyudaMapa() { return false; },\n        pintar() { return true; }\n';
        const parts = browser.fragments(file, before);
        const entries = Object.fromEntries(browser.ids.map(id => [id, {inputs: Object.fromEntries(parts.filter(p => p.cases.includes(id)).map(p => [p.key, browser.hash(p.text)]))}]));
        mkdirSync(path.dirname(path.join(temporary, file)), {recursive: true});
        writeFileSync(path.join(temporary, file), before.replace('return texto;', 'return texto.trim();'));
        const own = impact.browserScope({root: temporary, files: {[file]: 'nuevo'}, changes: [file], entries});
        assert.deepEqual(own, {cases: ['tooltip-livewire'], pending: []});
        writeFileSync(path.join(temporary, file), before.replace('return true;', 'return false;'));
        const unknown = impact.browserScope({root: temporary, files: {[file]: 'nuevo'}, changes: [file], entries});
        assert.deepEqual(unknown.cases, []);
        assert.equal(unknown.pending.length, 1);
    } finally { assert.equal(path.dirname(temporary), path.resolve(tmpdir())); assert.ok(path.basename(temporary).startsWith('hub-impact-fragment-')); rmSync(temporary, {recursive: true, force: true}); }
});

test('los scripts pasan el analisis PowerShell y solo acreditan una vez antes del commit', () => {
    const root = path.resolve(new URL('../..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
    const core = readFileSync(path.join(root, 'scripts/deployment/crear-paquete-oci-core.ps1'), 'utf8');
    const validator = readFileSync(path.join(root, 'scripts/deployment/validar-cambios-agente.ps1'), 'utf8');
    assert.match(validator, /if \(\$SoloSelectorPaquete\)/);
    const accredit = core.indexOf("'acreditar', $Proyecto");
    assert.ok(accredit > core.indexOf('if (-not $suitePostgresCompletada'));
    assert.ok(accredit < core.indexOf("'commit', '-m'"));
    assert.equal(core.match(/'acreditar', \$Proyecto/g).length, 1);
    const command = "$ErrorActionPreference = 'Stop'; foreach ($p in @('scripts/deployment/crear-paquete-oci-core.ps1', 'scripts/deployment/validar-cambios-agente.ps1')) { $tokens = $null; $errors = $null; [System.Management.Automation.Language.Parser]::ParseFile((Join-Path (Get-Location) $p), [ref]$tokens, [ref]$errors) | Out-Null; if ($errors.Count) { throw ($errors | Out-String) } }";
    const parsed = spawnSync('powershell.exe', ['-NoProfile', '-Command', command], {cwd: root, encoding: 'utf8', windowsHide: true});
    assert.equal(parsed.status, 0, parsed.stderr);
    assert.match(core, /if \(-not \$ValidacionAgente -and \$planImpacto\.paquete\.python\)/);
    assert.match(core, /if \(\$planImpacto\.paquete\.laravel\)/);
    assert.match(core, /if \(\$planImpacto\.paquete\.migraciones\)/);
    assert.match(readFileSync(path.join(root, '.gitignore'), 'utf8'), /^\/\.local\//m);
});

test('el mapa sigue imports agrupados, puentes App y dos niveles de imports relativos', () => {
    const root = mkdtempSync(path.join(tmpdir(), 'hub-impact-'));
    const files = {
        'Modules/CatalogoPublico/app/Dato.php': String.raw`<?php namespace Modules\CatalogoPublico; class Dato {}`,
        'app/Support/Puente.php': String.raw`<?php namespace App\Support; use Modules\CatalogoPublico\Dato; class Puente {}`,
        'Modules/InventarioGestionColeccion/app/Servicio.php': String.raw`<?php namespace Modules\InventarioGestionColeccion; use App\Support\{Puente}; class Servicio {}`,
        'resources/js/portal-map-model.js': 'export const x=1;',
        'resources/js/portal-puente.js': "export {x} from './portal-map-model.js';",
        'tests/Frontend/consumer.test.mjs': "import {x} from '../../resources/js/portal-puente.js';",
        'Modules/CatalogoPublico/app/Port.php': String.raw`<?php namespace Modules\CatalogoPublico; interface Port {}`,
        'Modules/CatalogoPublico/app/Adapter.php': String.raw`<?php namespace Modules\CatalogoPublico; class Adapter implements Port {}`,
        'Modules/CatalogoPublico/app/Other.php': String.raw`<?php namespace Modules\CatalogoPublico; class Other {}`,
        'Modules/CatalogoPublico/app/Providers/Provider.php': String.raw`<?php namespace Modules\CatalogoPublico\Providers; use Modules\CatalogoPublico\Port; use Modules\CatalogoPublico\Adapter as Adaptador; use Modules\CatalogoPublico\Other; class Provider { function boot() { $this->app->bind(Port::class, Adaptador::class); $this->app->scoped(Other::class); } }`,
        'Modules/CatalogoPublico/app/Renderer.php': String.raw`<?php namespace Modules\CatalogoPublico; class Renderer { function render() { return view('catalogopublico::pieza'); } }`,
        'Modules/CatalogoPublico/resources/views/pieza.blade.php': '<p>hola</p>',
    };
    try {
        for (const [file, text] of Object.entries(files)) {
            mkdirSync(path.dirname(path.join(root, file)), {recursive: true}); writeFileSync(path.join(root, file), text);
        }
        const graph = impact.graph(root, Object.fromEntries(Object.keys(files).map(f => [f, 'hash'])));
        assert.ok(graph.dependencies.portal.includes('inventario'));
        assert.ok(impact.closure([js], graph.nodeDependencies).has('tests/Frontend/consumer.test.mjs'));
        assert.ok(impact.closure(['Modules/CatalogoPublico/app/Dato.php'], graph.fileDependencies).has(inventorySource));
        assert.ok(graph.fileDependencies['Modules/CatalogoPublico/app/Adapter.php'].includes('Modules/CatalogoPublico/app/Port.php'));
        assert.ok(!graph.fileDependencies['Modules/CatalogoPublico/app/Other.php'].includes('Modules/CatalogoPublico/app/Port.php'));
        assert.ok(graph.fileDependencies['Modules/CatalogoPublico/resources/views/pieza.blade.php'].includes('Modules/CatalogoPublico/app/Renderer.php'));
    } finally {
        assert.equal(path.dirname(path.resolve(root)), path.resolve(tmpdir()));
        assert.ok(path.basename(root).startsWith('hub-impact-'));
        rmSync(root, {recursive: true, force: true});
    }
});
