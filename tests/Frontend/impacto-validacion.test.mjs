import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, mkdirSync, writeFileSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import impact from '../../scripts/deployment/impacto-validacion.cjs';

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

test('sin una base integral aprobada, vigente y de la misma linea se ejecuta todo', () => {
    for (const base of [null, {...baseline, full_test_coverage: false}, {...baseline, policy_sha256: 'otro'},
        {...baseline, completed_at: new Date(now - 8 * 86400000).toISOString()}, {...baseline, completed_at: 'invalida'}]) {
        const p = plan(original, {baseline: base});
        assert.equal(p.integral, true);
        assert.equal(p.agente.frontend.length, 4);
        assert.equal(p.cobertura.php.reutilizar.length, 0);
    }
    assert.equal(plan(original, {ancestor: false}).integral, true);
    assert.equal(plan(original, {environment: 'otra'}).integral, true);
    assert.equal(plan(original, {environment: 'incompleto:hash', baseline: {...baseline, environment: 'incompleto:hash'}}).integral, true);
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

test('incluye consumidores indirectos y contratos comunes sin ejecutar otro dominio no relacionado', () => {
    const p = plan({...original, [source]: 'b'.repeat(64)}, {dependencies: {portal: ['inventario']}});
    assert.equal(p.integral, false);
    assert.deepEqual(p.areas, ['inventario', 'portal']);
    assert.ok(p.paquete.pruebas_php.includes('tests/Feature/Auth/AuthenticationTest.php'));
    assert.ok(p.paquete.pruebas_php.includes('Modules/InventarioGestionColeccion/tests/Unit/ServicioTest.php'));
    assert.ok(!p.paquete.pruebas_php.includes('tests/Feature/DepositosPortalTest.php'));
    assert.ok(p.cobertura.php.reutilizar.includes('tests/Feature/DepositosPortalTest.php'));
    assert.ok(p.paquete.gherkin_paths.every(f => f.includes('CatalogoPublico')));
});

test('la cadena transitiva amplia, el archivo desconocido y los cambios criticos obligan a integral', () => {
    assert.equal(plan({...original, [source]: 'b'}, {dependencies: {portal: ['inventario'], inventario: ['depositos']}}).integral, true);
    for (const file of ['composer.lock', 'app/Models/User.php', 'config/botones.php',
        'Modules/CatalogoPublico/database/migrations/nueva.php', 'Modules/CatalogoPublico/app/Permisos/Acceso.php', 'app/Support/SinClasificacion.php']) {
        assert.equal(plan({...original, [file]: 'nuevo'}).integral, true, file);
    }
    assert.equal(plan(original, {force: true}).integral, true);
    assert.equal(plan({...original, [source]: 'b'}, {uncertain: [source]}).integral, true);
    assert.equal(plan({...original, [source]: 'b'}, {uncertain: ['Modules/CatalogoPublico/tests/Behat/Contexts/BaseContext.php']}).integral, false);
});

test('cambiar vendor o un artefacto invalida la reutilizacion', () => {
    for (const key of Object.keys(runtime.per_root)) {
        const current = {per_root: {...runtime.per_root, [key]: 'cambio'}};
        assert.equal(plan(original, {runtime: current}).integral, true);
        assert.equal(plan(original, {runtime: current, approvedRuntime: current}).integral, false);
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
    assert.deepEqual(php.areas, ['inventario', 'portal']);
});

test('con mapa por archivo no ejecuta pruebas ajenas del mismo modulo', () => {
    const related = 'Modules/InventarioGestionColeccion/tests/Unit/ServicioTest.php';
    const p = plan({...original, [source]: 'b'}, {fileDependencies: {[source]: ['app/Support/Puente.php'], 'app/Support/Puente.php': [related]}});
    assert.equal(p.integral, false);
    assert.deepEqual(p.paquete.pruebas_php, [related]);
    assert.ok(p.cobertura.php.reutilizar.includes('tests/Feature/PortalCatalogoTest.php'));
    assert.ok(p.cobertura.node.reutilizar.includes('tests/Frontend/portal-map-model.test.mjs'));
});

test('compara cambios acumulados con la base integral e incluye eliminaciones y consumidores JavaScript', () => {
    const p = plan({...original, [source]: 'b', [inventorySource]: 'c', [js]: 'eliminado'}, {
        nodeDependencies: {[js]: ['resources/js/portal-puente.js'], 'resources/js/portal-puente.js': ['tests/Frontend/consumer.test.mjs']},
    });
    assert.equal(p.integral, false);
    assert.deepEqual(p.changes.sort(), [source, inventorySource, js].sort());
    assert.ok(p.agente.node_tests.includes('tests/Frontend/consumer.test.mjs'));
    const crossBuild = plan({...original, [js]: 'b'}, {nodeDependencies: {[js]: ['Modules/InventarioGestionColeccion/resources/assets/js/app.js']}});
    assert.ok(crossBuild.agente.frontend.includes('Modules/InventarioGestionColeccion'));
    const combined = [...p.cobertura.php.ejecutar, ...p.cobertura.php.reutilizar];
    assert.equal(new Set(combined).size, combined.length);
    assert.deepEqual(combined.sort(), tests.filter(f => f.endsWith('.php')).sort());
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
