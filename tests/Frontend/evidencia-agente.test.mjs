import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, mkdirSync, readFileSync, writeFileSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import evidence from '../../scripts/deployment/evidencia-agente.cjs';

const now = Date.parse('2026-10-28T12:00:00Z');
const current = {root: '/workspace', head: 'a'.repeat(40), source_sha256: 'b'.repeat(64),
    impact_sha256: 'd'.repeat(64),
    runtime: {sha256: 'c'.repeat(64), files: 20, roots: ['vendor', 'public/build']}};

test('preparar sincroniza el plan con el runtime construido antes de sellarlo, sin recalcular huellas en crear o verificar', () => {
    const source = readFileSync(new URL('../../scripts/deployment/evidencia-agente.cjs', import.meta.url), 'utf8');
    const snapshot = source.slice(source.indexOf('async function snapshot('), source.indexOf('async function main('));
    assert.match(snapshot, /snapshot\(root, \{prepare = false\} = \{\}\)/);
    assert.equal([...snapshot.matchAll(/await hashRuntime\(root\)/g)].length, 1, 'No repetir el recorrido de vendor/builds.');
    const runtime = snapshot.indexOf('await hashRuntime(root)'), sources = snapshot.indexOf('const sources =');
    const refresh = snapshot.indexOf("require('./impacto-validacion.cjs').refreshRuntimePlan("), seal = snapshot.indexOf('impact_sha256:');
    assert.ok(runtime >= 0 && runtime < sources && sources < refresh && refresh < seal);
    assert.match(snapshot, /if \(prepare\) \{\s*impact = require\('[^']+'\)\.refreshRuntimePlan\(\{root, head: git\.stdout\.trim\(\), files: sources,\s*runtime, environment: sector_environment, plan: impact\}\);\s*atomicWrite\(impactPath, impact\);\s*\}/);
    assert.equal([...source.matchAll(/refreshRuntimePlan\(/g)].length, 1);
    assert.match(source, /await snapshot\(root, \{prepare: action === 'preparar'\}\)/);
});

test('el paquete acepta solamente evidencia completa del codigo y runtime que se probaron', () => {
    const receipt = evidence.createEvidence(current, now);
    assert.equal(evidence.validateEvidence(receipt, structuredClone(current), now), receipt);
    for (const key of ['root', 'head', 'source_sha256', 'runtime', 'impact_sha256']) {
        assert.throws(() => evidence.validateEvidence(receipt, {...current, [key]: 'changed'}, now));
    }
    assert.throws(() => evidence.validateEvidence({...receipt, stages: receipt.stages.slice(1)}, current, now));
    assert.throws(() => evidence.validateEvidence({...receipt, plan_sha256: 'changed'}, current, now));
});

test('el reparto 30/70 rechaza los recibos del antiguo bloque del 40 por ciento', () => {
    const receipt = evidence.createEvidence(current, now);
    assert.equal(receipt.stages.length, 6);
    assert.ok(!receipt.stages.includes('laravel-arranque'));
    assert.ok(!receipt.stages.includes('postgres-integracion'));
    assert.ok(!receipt.stages.includes('ete-runtime'));
    assert.ok(receipt.stages.includes('java-criptografia'));
    const legacy = {...receipt, stages: [...receipt.stages.filter(stage => stage !== 'java-criptografia'),
        'ete-runtime', 'laravel-arranque', 'postgres-integracion'].sort()};
    assert.throws(() => evidence.validateEvidence(legacy, current, now), /30 %/);
});

test('la evidencia ausente, malformada, futura o de mas de 24 horas bloquea el empaquetado', () => {
    const receipt = evidence.createEvidence(current, now);
    for (const invalid of [null, {}, {...receipt, version: 99}, {...receipt, completed_at: 'invalid'}]) {
        assert.throws(() => evidence.validateEvidence(invalid, current, now));
    }
    assert.throws(() => evidence.validateEvidence(receipt, current, now + evidence.MAX_AGE_MS + 1));
    assert.throws(() => evidence.validateEvidence(receipt, current, now - 60001));
});

test('un cambio durante las pruebas impide sellar evidencia y un estado pendiente nunca acredita exito', () => {
    const prepared = {phase: 'pendiente', started_at: new Date(now).toISOString(), ...current};
    evidence.validatePrepared(prepared, current, now);
    for (const key of ['root', 'head', 'source_sha256', 'runtime', 'impact_sha256']) {
        assert.throws(() => evidence.validatePrepared(prepared, {...current, [key]: 'changed'}, now));
    }
    assert.throws(() => evidence.validatePrepared(null, current, now));
    assert.throws(() => evidence.validateEvidence(prepared, current, now));
});

test('alterar, agregar o retirar un archivo de vendor o de los builds invalida la huella', async () => {
    const root = mkdtempSync(path.join(tmpdir(), 'hub-evidence-'));
    try {
        mkdirSync(path.join(root, 'vendor'));
        mkdirSync(path.join(root, 'build'));
        writeFileSync(path.join(root, 'vendor', 'dependency.php'), 'original');
        writeFileSync(path.join(root, 'build', 'app.js'), 'compiled');
        const roots = ['vendor', 'build'];
        const original = await evidence.hashRuntime(root, roots);
        writeFileSync(path.join(root, 'vendor', 'dependency.php'), 'altered');
        assert.notEqual((await evidence.hashRuntime(root, roots)).sha256, original.sha256);
        writeFileSync(path.join(root, 'vendor', 'dependency.php'), 'original');
        writeFileSync(path.join(root, 'build', 'extra.js'), 'extra');
        assert.notEqual((await evidence.hashRuntime(root, roots)).sha256, original.sha256);
        rmSync(path.join(root, 'build', 'extra.js'));
        assert.deepEqual(await evidence.hashRuntime(root, roots), original);
        rmSync(path.join(root, 'build', 'app.js'));
        await assert.rejects(evidence.hashRuntime(root, roots), /Runtime vacio: build/);
        await assert.rejects(evidence.hashRuntime(root, ['../outside']));
    } finally {
        assert.equal(path.dirname(path.resolve(root)), path.resolve(tmpdir()));
        assert.ok(path.basename(root).startsWith('hub-evidence-'));
        rmSync(root, {recursive: true, force: true});
    }
});
