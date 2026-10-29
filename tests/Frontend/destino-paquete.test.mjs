import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, mkdirSync, writeFileSync, existsSync, rmSync, symlinkSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {createHash} from 'node:crypto';
import destinations from '../../scripts/deployment/destino-paquete.cjs';

function fixture(t, temporary = true) {
    const parent = mkdtempSync(path.join(tmpdir(), 'hubdigital-destino-'));
    t.after(() => rmSync(parent, {recursive: true, force: true}));
    const root = path.join(parent, 'HubDigital'), id = 'cambio-20261028-120000';
    const directory = path.join(root, temporary ? '.local/deployment/oci-transfer' : 'artifacts/oci', id);
    mkdirSync(directory, {recursive: true});
    writeFileSync(path.join(parent, 'ssh-key-2026-09-18.key'), 'clave-ficticia-no-autentica');
    const names = [`${id}-AMBOS.tar.gz`, `${id}-DEV.sha256`, `${id}-DEV-cloudshell-vm.sh`,
        `${id}-DEV-hubdigital.env`, `${id}-PROD-produccion-vm.sh`, `${id}-INSTRUCCIONES-CLOUD-SHELL.txt`];
    for (const name of names) writeFileSync(path.join(directory, name), `contenido sintetico de ${name}\n`);
    return {root, directory, id, names};
}

test('W es local, LD apunta solo a DEV y LP solo a PROD; rechaza destinos desconocidos', () => {
    assert.deepEqual(destinations.target('/proyecto', 'W'), {code: 'W', local: true});
    assert.equal(destinations.target('/proyecto', 'ld').host, 'ubuntu@129.153.23.57');
    assert.equal(destinations.target('/proyecto', 'LP').host, 'ubuntu@150.136.219.112');
    assert.throws(() => destinations.target('/proyecto', 'otro'), /Destino invalido/);
});

test('LD recibe AMBOS y los tres DEV; LP recibe exclusivamente AMBOS y PROD', t => {
    const f = fixture(t);
    const dev = destinations.selectedFiles(f.directory, 'LD');
    const prod = destinations.selectedFiles(f.directory, 'LP');
    assert.deepEqual(dev.names, f.names.slice(0, 4));
    assert.deepEqual(prod.names, [f.names[0], f.names[4]]);
    assert.equal(prod.names.some(n => n.includes('-DEV') || n.endsWith('.env')), false);
    assert.equal(dev.remote, `/home/ubuntu/TEMP-BORRAR/${f.id}`);
});

test('no transfiere paquetes ambiguos, vacios o con un archivo ausente', t => {
    const f = fixture(t);
    writeFileSync(path.join(f.directory, 'otro-AMBOS.tar.gz'), 'otro');
    assert.throws(() => destinations.selectedFiles(f.directory, 'LD'), /exactamente un paquete/);
    rmSync(path.join(f.directory, 'otro-AMBOS.tar.gz'));
    writeFileSync(path.join(f.directory, f.names[3]), '');
    assert.throws(() => destinations.selectedFiles(f.directory, 'LD'), /invalido/);
    rmSync(path.join(f.directory, f.names[3]));
    assert.throws(() => destinations.selectedFiles(f.directory, 'LD'), /ENOENT/);
});

test('W no accede a SSH; una copia W solicitada por error se rechaza', async t => {
    const f = fixture(t);
    let called = false;
    await destinations.preflight(f.root, 'W', () => { called = true; });
    await assert.rejects(destinations.transfer(f.root, f.directory, 'W'), /conserva/);
    assert.equal(called, false);
    assert.equal(existsSync(f.directory), true);
});

test('copia exactamente los seleccionados y verifica cada hash antes de eliminar el temporal local', async t => {
    const f = fixture(t), calls = [];
    await destinations.transfer(f.root, f.directory, 'LD', {log() {}, execute: async (program, args, options) => {
        calls.push({program, args, options});
        assert.equal(existsSync(f.directory), true, 'Se elimino antes de verificar la recepcion');
    }});
    assert.deepEqual(calls.map(c => c.program), ['ssh.exe', 'scp.exe', 'ssh.exe']);
    assert.equal(calls[1].args.at(-1), `ubuntu@129.153.23.57:/home/ubuntu/TEMP-BORRAR/${f.id}/`);
    const uploaded = calls[1].args.filter(a => a.startsWith(f.directory));
    assert.deepEqual(uploaded.map(a => path.basename(a)), f.names.slice(0, 4));
    for (const name of f.names.slice(0, 4)) {
        const hash = createHash('sha256').update(`contenido sintetico de ${name}\n`).digest('hex');
        assert.ok(calls[2].options.input.includes(`${hash}  ${name}`));
    }
    assert.equal(calls[1].options.timeout, 600000);
    assert.equal(calls[2].options.timeout, 30000);
    assert.equal(existsSync(f.directory), false);
});

for (const failed of [1, 2, 3]) test(`si falla la etapa ${failed}, conserva todos los archivos y no avanza`, async t => {
    const f = fixture(t);
    let calls = 0;
    await assert.rejects(destinations.transfer(f.root, f.directory, 'LD', {log() {}, execute: async () => {
        if (++calls === failed) throw new Error('fallo sintetico');
    }}), /fallo sintetico/);
    assert.equal(calls, failed);
    for (const name of f.names) assert.equal(existsSync(path.join(f.directory, name)), true);
});

test('una copia desde artifacts/oci conserva el paquete original aun despues del exito', async t => {
    const f = fixture(t, false);
    await destinations.transfer(f.root, f.directory, 'LD', {log() {}, execute: async () => {}});
    for (const name of f.names) assert.equal(existsSync(path.join(f.directory, name)), true);
});

test('la limpieza no elimina archivos ajenos ni sigue enlaces del directorio temporal', t => {
    const f = fixture(t);
    writeFileSync(path.join(f.directory, 'ajeno.txt'), 'conservar');
    destinations.removeLocalTemporary(f.root, f.directory, f.id);
    assert.equal(existsSync(path.join(f.directory, 'ajeno.txt')), true);
    const alias = path.join(f.root, '.local/deployment/oci-transfer', 'enlace');
    symlinkSync(f.directory, alias, process.platform === 'win32' ? 'junction' : 'dir');
    destinations.removeLocalTemporary(f.root, alias, 'enlace');
    assert.equal(existsSync(path.join(f.directory, 'ajeno.txt')), true);
});

test('SSH exige la identidad del entorno y la huella conocida, sin agente ni preguntas interactivas', () => {
    const args = destinations.sshOptions('clave');
    for (const option of ['IdentitiesOnly=yes', 'IdentityAgent=none', 'BatchMode=yes', 'StrictHostKeyChecking=yes', 'ConnectTimeout=10']) assert.ok(args.includes(option));
});

test('una operacion bloqueada termina al agotar su plazo, sin reintentos', async () => {
    await assert.rejects(destinations.run(process.execPath, ['-e', 'setInterval(() => {}, 1000)'],
        {timeout: 100, log() {}, label: 'operacion sintetica'}), /plazo agotado/);
});
