import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, mkdtempSync, writeFileSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
import sectors from '../../scripts/deployment/bases-sectores-agente.cjs';
import evidence from '../../scripts/deployment/evidencia-agente.cjs';

const now = Date.parse('2026-10-28T21:00:00Z');
const state = {environment: 'env', sources: {'composer.json': 'php', 'composer.lock': 'lock',
    'tools/pdf-signature/pom.xml': 'pom', 'tools/pdf-signature/src/Firmador.java': 'java',
    'resources/js/portal-map-model.js': 'javascript', 'scripts/deployment/impacto-validacion.cjs': 'selector'},
    core: readFileSync(new URL('../../scripts/deployment/crear-paquete-oci-core.ps1', import.meta.url), 'utf8'),
    runtime: {sha256: 'runtime', per_root: {vendor: 'vendor', 'resources/bin/hubdigital-pdf-signature.jar': 'jar',
        'public/build': 'root', 'public/build-catalogopublico': 'portal', 'public/build-gestionprestamosrecepciones': 'depositos',
        'public/build-inventariogestioncoleccion': 'inventario'}}};
const original = sectors.inputs(state);
const base = {version: 1, environment: 'env', sectors: Object.fromEntries(Object.entries(original).map(([id, value]) =>
    [id, {...value, completed_at: new Date(now - 1000).toISOString()}]))};

test('cambiar el selector exige Node y conserva Java, Composer y los cuatro builds realmente aprobados', () => {
    const current = sectors.inputs({...state, sources: {...state.sources, 'scripts/deployment/impacto-validacion.cjs': 'selector nuevo'}});
    const reused = sectors.compatible(base, current, now);
    assert.ok(!reused['javascript-contratos']);
    assert.ok(reused['java-compilacion']);
    assert.ok(reused['java-criptografia']);
    assert.ok(reused['php-dependencias']);
    assert.equal(Object.keys(reused).filter(k => k.startsWith('frontend-compilacion:')).length, 4);
});

test('cambiar Java, su JAR o su comando invalida su base sin usar solamente la fecha', () => {
    for (const changed of [
        {...state, sources: {...state.sources, 'tools/pdf-signature/src/Firmador.java': 'java nuevo'}},
        {...state, runtime: {...state.runtime, per_root: {...state.runtime.per_root, 'resources/bin/hubdigital-pdf-signature.jar': 'jar nuevo'}}},
        {...state, core: state.core.replace("'-Xmx160m'", "'-Xmx161m'")},
    ]) assert.ok(!sectors.compatible(base, sectors.inputs(changed), now)['java-criptografia']);
    assert.equal(Object.keys(sectors.compatible(base, original, now + 8 * 86400000)).length, 0);
    assert.equal(Object.keys(sectors.compatible(base, sectors.inputs({...state, environment: 'otro'}), now)).length, 0);
});

test('una interfaz visual no invalida Java y una base parcial no acredita sectores ausentes', () => {
    const ui = sectors.inputs({...state, sources: {...state.sources, 'resources/js/portal-map-model.js': 'mapa nuevo'}});
    assert.ok(sectors.compatible(base, ui, now)['java-criptografia']);
    assert.ok(!sectors.compatible(base, ui, now)['frontend-compilacion:.']);
    const partial = {...base, sectors: {'java-criptografia': base.sectors['java-criptografia']}};
    assert.deepEqual(Object.keys(sectors.compatible(partial, original, now)), ['java-criptografia']);
    assert.deepEqual(sectors.compatible(null, original, now), {});
});

function withEnvironment(action) {
    const root = mkdtempSync(path.join(tmpdir(), 'hub-sector-env-'));
    const probe = program => ({status: 0, stdout: program === 'php.exe' ? '8.4.10|C:\\PHP\\php.exe' : program === 'where.exe' ? 'C:\\Java\\bin\\java.exe\r\n' : '',
        stderr: program === 'java.exe' ? 'java version "26.0.2"\r\n' : ''});
    try { writeFileSync(path.join(root, '.env'), 'APP_ENV=local\n'); action(root, probe); }
    finally {
        assert.equal(path.dirname(path.resolve(root)), path.resolve(tmpdir()));
        assert.ok(path.basename(root).startsWith('hub-sector-env-'));
        rmSync(root, {recursive: true, force: true});
    }
}

test('la evidencia generada en Codex se acepta desde CMD/PowerShell con otro PATH si resuelven las mismas herramientas', () => {
    withEnvironment((root, probe) => {
        const agent = sectors.identity(root, {probe, env: {PATH: 'C:\\Codex\\tmp;C:\\PHP;C:\\Java\\bin', JAVA_HOME: 'C:\\Java', PWD: 'C:\\proyecto', TERM_PROGRAM: 'codex'}});
        const manual = sectors.identity(root, {probe, env: {PATH: 'C:\\PHP;C:\\Java\\bin;C:\\Windows', JAVA_HOME: 'C:\\Java', PWD: 'C:\\padre', TERM_PROGRAM: 'vscode'}});
        assert.notEqual(agent.legacy, manual.legacy);
        assert.equal(agent.fingerprint, manual.fingerprint);
        const snapshot = value => ({root, head: 'a'.repeat(40), source_sha256: 'b'.repeat(64), impact_sha256: 'c'.repeat(64), runtime: state.runtime,
            sector_environment: value.fingerprint, sector_environment_details: value.components, sector_inputs: sectors.inputs({...state, environment: value.fingerprint})});
        const approved = evidence.createEvidence(snapshot(agent), now);
        assert.equal(evidence.validateEvidence(approved, snapshot(manual), now), approved);
    });
});

test('otro PHP efectivo, otro JDK o configuracion local diferente siguen invalidando los sectores', () => {
    withEnvironment((root, probe) => {
        const normal = sectors.identity(root, {probe, env: {PATH: 'normal', JAVA_HOME: 'C:\\Java'}});
        const changedPhp = sectors.identity(root, {env: {PATH: 'otro', JAVA_HOME: 'C:\\Java'}, probe: (program, ...args) => program === 'php.exe'
            ? {status: 0, stdout: '8.5.0|C:\\PHP85\\php.exe', stderr: ''} : probe(program, ...args)});
        const changedJava = sectors.identity(root, {probe, env: {PATH: 'normal', JAVA_HOME: 'C:\\JavaNuevo'}});
        writeFileSync(path.join(root, '.env'), 'APP_ENV=testing\n');
        const changedConfig = sectors.identity(root, {probe, env: {PATH: 'normal', JAVA_HOME: 'C:\\Java'}});
        for (const changed of [changedPhp, changedJava, changedConfig]) {
            assert.notEqual(normal.fingerprint, changed.fingerprint);
            const approved = {...base, environment: normal.fingerprint, sectors: Object.fromEntries(Object.entries(sectors.inputs({...state, environment: normal.fingerprint}))
                .map(([id, value]) => [id, {...value, completed_at: new Date(now).toISOString()}]))};
            assert.deepEqual(sectors.compatible(approved, sectors.inputs({...state, environment: changed.fingerprint}), now), {});
        }
    });
});

test('no reutiliza una identidad incompleta al no poder resolver Java', () => {
    withEnvironment((root, probe) => {
        const missing = sectors.identity(root, {env: {}, probe: (program, ...args) => program === 'where.exe' ? {status: 1, stdout: '', stderr: ''} : probe(program, ...args)});
        assert.match(missing.fingerprint, /^incompleto:/);
        assert.deepEqual(sectors.compatible({...base, environment: missing.fingerprint}, original, now), {});
    });
});

test('procesos Windows reales conservan la identidad al retirar rutas temporales y cambiar de directorio', {skip: process.platform !== 'win32'}, () => {
    const root = fileURLToPath(new URL('../../', import.meta.url));
    const module = fileURLToPath(new URL('../../scripts/deployment/bases-sectores-agente.cjs', import.meta.url));
    const script = 'const s=require(process.argv[1]);process.stdout.write(JSON.stringify(s.identity(process.argv[2])));';
    const originalPath = process.env.PATH || process.env.Path || '';
    const manualPath = path.join(root, '.local', 'ruta-ausente-de-lanzador') + ';' + originalPath.split(';').filter(p => !/[\\/]\.codex[\\/]tmp[\\/]/i.test(p)).join(';');
    const manualEnv = {...Object.fromEntries(Object.entries(process.env).filter(([key]) => key.toLowerCase() !== 'path')), Path: manualPath};
    const execute = (cwd, env) => {
        const child = spawnSync(process.execPath, ['-e', script, module, root], {cwd, env, encoding: 'utf8', windowsHide: true, timeout: 30000});
        assert.equal(child.status, 0, child.stderr);
        return JSON.parse(child.stdout);
    };
    const agent = execute(root, process.env), manual = execute(path.dirname(root), manualEnv);
    assert.match(agent.fingerprint, /^v2:/);
    assert.notEqual(agent.legacy, manual.legacy);
    assert.equal(agent.fingerprint, manual.fingerprint);
});
