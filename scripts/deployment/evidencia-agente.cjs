const fs = require('node:fs');
const fsp = require('node:fs/promises');
const path = require('node:path');
const {createHash, randomUUID} = require('node:crypto');
const {spawnSync} = require('node:child_process');

const MAX_AGE_MS = 24 * 60 * 60 * 1000;
const runtimeRoots = ['vendor', 'resources/bin/hubdigital-pdf-signature.jar',
    'public/build', 'public/build-catalogopublico', 'public/build-gestionprestamosrecepciones',
    'public/build-inventariogestioncoleccion'];
const digest = value => createHash('sha256').update(value).digest('hex');
const plan = JSON.parse(fs.readFileSync(path.join(__dirname, 'plan-validacion.json'), 'utf8'));
const stages = plan.controles.filter(c => c.responsable === 'agente').map(c => c.id).sort();
if (plan.version !== 1 || plan.controles.length !== 20 || stages.length !== 8 ||
    new Set(plan.controles.map(c => c.id)).size !== 20 ||
    plan.controles.filter(c => c.responsable === 'paquete').length !== 12) throw new Error('Plan de validacion incompatible con el reparto 40/60.');
const fail = message => { throw new Error(`${message} Ejecute scripts/deployment/validar-cambios-agente.ps1 sobre los cambios actuales.`); };

async function hashRuntime(root, roots = runtimeRoots) {
    const files = [];
    async function walk(relative) {
        const absolute = path.resolve(root, relative);
        const inside = path.relative(root, absolute);
        if (inside === '..' || inside.startsWith('..' + path.sep) || path.isAbsolute(inside)) fail('Runtime fuera del proyecto.');
        const info = await fsp.lstat(absolute);
        if (info.isSymbolicLink()) fail(`El runtime contiene un enlace: ${relative}.`);
        if (info.isDirectory()) await directory(relative);
        else if (info.isFile()) files.push(relative);
        else fail(`Runtime no regular: ${relative}.`);
    }
    async function directory(relative) {
        const entries = await fsp.readdir(path.join(root, relative), {withFileTypes: true});
        for (const entry of entries) {
            const name = path.join(relative, entry.name);
            if (entry.isSymbolicLink()) fail(`El runtime contiene un enlace: ${name}.`);
            if (entry.isDirectory()) await directory(name);
            else if (entry.isFile()) files.push(name);
            else fail(`Runtime no regular: ${name}.`);
        }
    }
    for (const relative of roots) {
        const count = files.length;
        await walk(relative);
        if (count === files.length) fail(`Runtime vacio: ${relative}.`);
    }
    if (files.length === 0) fail('Runtime vacio.');
    files.sort();
    const hashes = new Array(files.length);
    let next = 0;
    async function consume() {
        while (next < files.length) {
            const index = next++;
            const hash = createHash('sha256');
            for await (const chunk of fs.createReadStream(path.join(root, files[index]))) hash.update(chunk);
            hashes[index] = `${hash.digest('hex')}  ${files[index].split(path.sep).join('/')}`;
        }
    }
    await Promise.all(Array.from({length: 4}, consume));
    return {sha256: digest(hashes.join('\n')), files: files.length, roots};
}

function createEvidence(snapshot, now = Date.now()) {
    return {version: 1, completed_at: new Date(now).toISOString(),
        stages: [...stages], plan_sha256: digest(JSON.stringify(plan)), ...snapshot};
}

function validateEvidence(evidence, current, now = Date.now()) {
    if (evidence?.version !== 1 || !Number.isFinite(Date.parse(evidence.completed_at))) fail('Evidencia del agente invalida.');
    const age = now - Date.parse(evidence.completed_at);
    if (age < -60000 || age > MAX_AGE_MS) fail('La evidencia del agente vencio o tiene una fecha futura.');
    if (evidence.plan_sha256 !== digest(JSON.stringify(plan)) ||
        JSON.stringify(evidence.stages) !== JSON.stringify(stages)) fail('La evidencia no acredita todos los controles del 40 %.');
    for (const key of ['root', 'head', 'source_sha256']) {
        if (evidence[key] !== current[key]) fail(`La evidencia no corresponde al codigo actual (${key}).`);
    }
    if (JSON.stringify(evidence.runtime) !== JSON.stringify(current.runtime)) fail('Los builds o dependencias PHP cambiaron despues de las pruebas.');
    return evidence;
}

function validatePrepared(prepared, current, now = Date.now()) {
    const age = now - Date.parse(prepared?.started_at);
    if (!Number.isFinite(age) || age < -60000 || age > MAX_AGE_MS || prepared.phase !== 'pendiente') fail('Falta la identificacion previa de las entradas de las pruebas.');
    for (const key of ['root', 'head', 'source_sha256', 'runtime']) {
        if (JSON.stringify(prepared[key]) !== JSON.stringify(current[key])) fail(`Las entradas cambiaron mientras se ejecutaban las pruebas (${key}).`);
    }
}

function atomicWrite(filename, data) {
    fs.mkdirSync(path.dirname(filename), {recursive: true});
    const temporary = `${filename}.${randomUUID()}.tmp`;
    try {
        fs.writeFileSync(temporary, JSON.stringify(data, null, 2) + '\n', {mode: 0o600});
        fs.renameSync(temporary, filename);
    } finally { fs.rmSync(temporary, {force: true}); }
}

async function snapshot(root) {
    const code = spawnSync(process.execPath, [path.join(__dirname, 'huellas-codigo.cjs'), root],
        {encoding: 'utf8', windowsHide: true, maxBuffer: 32 * 1024 * 1024});
    if (code.status !== 0) fail('No se pudo identificar el codigo actual.');
    const git = spawnSync('git.exe', ['-C', root, 'rev-parse', 'HEAD'], {encoding: 'utf8', windowsHide: true});
    if (git.status !== 0) fail('No se pudo identificar HEAD.');
    return {root, head: git.stdout.trim(), source_sha256: digest(code.stdout), runtime: await hashRuntime(root)};
}

async function main() {
    const [action, project] = process.argv.slice(2);
    if (!['preparar', 'crear', 'verificar'].includes(action) || !project) throw new Error('Uso: evidencia-agente.cjs preparar|crear|verificar PROYECTO');
    const root = fs.realpathSync(project);
    const receipt = path.join(root, '.local', 'validation', 'evidencia-agente.json');
    const pending = path.join(root, '.local', 'validation', 'evidencia-agente-pendiente.json');
    if (action === 'verificar' && !fs.existsSync(receipt)) fail('Falta la validacion obligatoria del agente (40 %).');
    const current = await snapshot(root);
    if (action === 'preparar') {
        atomicWrite(pending, {phase: 'pendiente', started_at: new Date().toISOString(), ...current});
        console.log(`ENTRADAS_AGENTE_IDENTIFICADAS=${current.runtime.files} archivos runtime; todavia no acreditan pruebas aprobadas.`);
    } else if (action === 'crear') {
        if (!fs.existsSync(pending)) fail('No se identificaron las entradas antes de las pruebas.');
        validatePrepared(JSON.parse(fs.readFileSync(pending, 'utf8')), current);
        atomicWrite(receipt, createEvidence(current));
        fs.rmSync(pending);
        console.log(`EVIDENCIA_AGENTE_CREADA=${receipt}`);
    } else {
        validateEvidence(JSON.parse(fs.readFileSync(receipt, 'utf8')), current);
        console.log('EVIDENCIA_AGENTE_OK=40 %; codigo, vendor y builds coinciden con las pruebas aprobadas.');
    }
}

module.exports = {createEvidence, validateEvidence, validatePrepared, hashRuntime, MAX_AGE_MS};
if (require.main === module) main().catch(error => { console.error(error.message); process.exitCode = 1; });
