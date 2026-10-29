import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, mkdtempSync, mkdirSync, writeFileSync, existsSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';

const deploy = readFileSync(new URL('../../deploy/oracle/scripts/deploy-release.sh', import.meta.url), 'utf8');
const prod = readFileSync(new URL('../../scripts/deployment/produccion-vm.sh.template', import.meta.url), 'utf8');
function block(source, marker) {
    const parts = source.split(`# BEGIN ${marker}`);
    assert.equal(parts.length, 2);
    const end = parts[1].indexOf(`# END ${marker}`);
    assert.ok(end > 0);
    return parts[1].slice(0, end);
}
const activate = block(deploy, 'AUTOMATIC ACTIVATION');
const activateProd = block(prod, 'PROD AUTOMATIC ACTIVATION');
const bash = process.env.HUBDIGITAL_BASH || (process.platform === 'win32'
    ? path.join(process.env.ProgramFiles || 'C:/Program Files', 'Git/bin/bash.exe') : 'bash');

function fixture(t) {
    const root = mkdtempSync(path.join(tmpdir(), 'hubdigital-preparacion-'));
    t.after(() => rmSync(root, {recursive: true, force: true}));
    const release = path.join(root, 'release'), backup = path.join(root, 'backup');
    mkdirSync(path.join(release, 'deploy/oracle/scripts'), {recursive: true});
    mkdirSync(backup);
    const called = path.join(root, 'activaciones.txt');
    writeFileSync(path.join(release, 'deploy/oracle/scripts/activate-release.sh'),
        '#!/usr/bin/env bash\nprintf "%s\\n" "$1" >> "$QA_CALLED"\nexit "${QA_ACTIVATION_EXIT:-0}"\n', {mode: 0o755});
    writeFileSync(path.join(backup, 'operar-produccion.sh'),
        '#!/usr/bin/env bash\ntest -f "$QA_BACKUP/PREPARED_OK" || exit 99\nprintf "%s\\n" "$1" >> "$QA_CALLED"\nexit "${QA_ACTIVATION_EXIT:-0}"\n', {mode: 0o755});
    const unix = p => p.replaceAll('\\', '/');
    const env = {...process.env, QA_RELEASE: unix(release), QA_BACKUP: unix(backup), QA_CALLED: unix(called), QA_ID: '0123456789abcdef',
        HUBDIGITAL_DEFER_ACTIVATION: '', QA_ACTIVATION_EXIT: '0', QA_STATE: 'validado_en_mantenimiento'};
    return {root, backup, called, env};
}

function execute(f, body, env = {}) {
    const result = spawnSync(bash, ['--noprofile', '--norc', '-c',
        'set -euo pipefail\nrelease_dir="$QA_RELEASE"\nrelease_id="$QA_ID"\nbackup="$QA_BACKUP"\nstate_file=estado-sintetico.json\ndefer_activation="${HUBDIGITAL_DEFER_ACTIVATION:-0}"\njq() { printf "%s\\n" "$QA_STATE"; }\n' + body],
        {encoding: 'utf8', windowsHide: true, timeout: 5000, env: {...f.env, ...env}});
    assert.ifError(result.error);
    return result;
}

for (const flag of ['', '0']) test(`DEV activa automaticamente una sola vez con el mismo ID (opcion ${flag || 'predeterminada'})`, t => {
    const f = fixture(t);
    const r = execute(f, activate, {HUBDIGITAL_DEFER_ACTIVATION: flag});
    assert.equal(r.status, 0, r.stderr);
    assert.equal(readFileSync(f.called, 'utf8'), f.env.QA_ID + '\n');
});

test('el paso interno de PROD difiere la activacion hasta que su asistente registre la preparacion', t => {
    const f = fixture(t);
    const r = execute(f, activate, {HUBDIGITAL_DEFER_ACTIVATION: '1'});
    assert.equal(r.status, 0, r.stderr);
    assert.equal(existsSync(f.called), false);
});

for (const status of [41, 65]) test(`un fallo previo de preparacion (${status}) bloquea la activacion`, t => {
    const f = fixture(t);
    const r = execute(f, `(exit ${status})\n${activate}`);
    assert.equal(r.status, status, r.stderr);
    assert.equal(existsSync(f.called), false);
});

test('DEV propaga el error de activacion sin repetirla ni declarar exito', t => {
    const f = fixture(t);
    const r = execute(f, activate, {QA_ACTIVATION_EXIT: '23'});
    assert.equal(r.status, 23, r.stderr);
    assert.equal(readFileSync(f.called, 'utf8'), f.env.QA_ID + '\n');
});

test('PROD con estado aprobado crea PREPARED_OK y encadena exactamente una activacion completa', t => {
    const f = fixture(t);
    const r = execute(f, activateProd);
    assert.equal(r.status, 0, r.stderr);
    assert.equal(existsSync(path.join(f.backup, 'PREPARED_OK')), true);
    assert.equal(readFileSync(f.called, 'utf8'), 'activar\n');
});

test('PROD con preparacion no aprobada conserva el bloqueo y no activa', t => {
    const f = fixture(t);
    const r = execute(f, activateProd, {QA_STATE: 'incompleto'});
    assert.notEqual(r.status, 0);
    assert.equal(existsSync(path.join(f.backup, 'PREPARED_OK')), false);
    assert.equal(existsSync(f.called), false);
});

test('PROD conserva el error de su activacion completa y no entra en un bucle', t => {
    const f = fixture(t);
    const r = execute(f, activateProd, {QA_ACTIVATION_EXIT: '29'});
    assert.equal(r.status, 29, r.stderr);
    assert.equal(readFileSync(f.called, 'utf8'), 'activar\n');
});
