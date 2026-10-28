const fs = require('node:fs');
const path = require('node:path');
const {createHash, randomUUID} = require('node:crypto');
const {spawnSync} = require('node:child_process');
const hash = value => createHash('sha256').update(value).digest('hex');
const MAX_AGE = 7 * 86400000;
const commands = {ete: 'python -m unittest discover -s tests/Python -p test_*.py',
    pdf: 'php deploy/oracle/scripts/verify-deposit-pdf.php --medir-tiempo-local'};

function inputs({sector, sources, runtime, environment}) {
    if (!commands[sector]) throw new Error('Sector del paquete desconocido.');
    const relevant = sector === 'ete'
        ? file => /^(resources\/python\/portal_ete\/|tests\/Python\/)/.test(file) || file === 'scripts/development/instalar-ete.ps1'
        : file => /^composer\.(json|lock)$/.test(file) || file.startsWith('tools/pdf-signature/') ||
            file === 'deploy/oracle/scripts/verify-deposit-pdf.php' ||
            (/\.php$/.test(file) && !file.endsWith('.blade.php') &&
                /^(app\/|bootstrap\/|config\/|routes\/|Modules\/GestionPrestamosRecepciones\/app\/(Application|Domain|Infrastructure)\/|Modules\/[^/]+\/app\/Providers\/)/.test(file));
    const selected = Object.keys(sources).sort().filter(relevant).map(file => [file, sources[file]]);
    if (!selected.length || !environment || String(environment).startsWith('incompleto:')) throw new Error('Sector sin entradas o entorno identificados.');
    const outputs = sector === 'ete' ? runtime?.ete : [runtime?.per_root?.vendor, runtime?.per_root?.['resources/bin/hubdigital-pdf-signature.jar']];
    if (!outputs || (Array.isArray(outputs) && outputs.some(value => !value))) throw new Error('Sector sin runtime compatible.');
    return {input_sha256: hash(JSON.stringify([1, sector, commands[sector], environment, selected])),
        output_sha256: hash(JSON.stringify(outputs))};
}

function compatible(previous, current, now = Date.now()) {
    const age = now - Date.parse(previous?.completed_at);
    return previous?.version === 1 && Number.isFinite(age) && age >= -60000 && age <= MAX_AGE &&
        previous.input_sha256 === current.input_sha256 && previous.output_sha256 === current.output_sha256;
}

function atomicWrite(filename, value) {
    fs.mkdirSync(path.dirname(filename), {recursive: true});
    const temporary = filename + '.' + randomUUID() + '.tmp';
    try { fs.writeFileSync(temporary, JSON.stringify(value, null, 2) + '\n', {mode: 0o600}); fs.renameSync(temporary, filename); }
    finally { fs.rmSync(temporary, {force: true}); }
}

function git(root, args) {
    const result = spawnSync('git.exe', ['-C', root, ...args], {encoding: 'utf8', windowsHide: true, maxBuffer: 32 * 1024 * 1024});
    if (result.status !== 0) throw new Error('No se pudo identificar la linea de fuentes del sector.');
    return result.stdout.trim();
}

function sourceInventory(root) {
    const result = {};
    for (const file of git(root, ['ls-files', '-z', '--cached', '--others', '--exclude-standard']).split('\0').filter(Boolean)) {
        const filename = path.resolve(root, file), relative = path.relative(root, filename);
        if (relative === '..' || relative.startsWith('..' + path.sep) || path.isAbsolute(relative)) throw new Error('Fuente fuera del proyecto.');
        // Solo estos archivos participan en ambos contratos. Los nuevos y las
        // eliminaciones siguen incluidos; no se depende de git diff HEAD.
        if (!/\.php$/.test(file) && !/^(resources\/python\/portal_ete\/|tests\/Python\/|tools\/pdf-signature\/)/.test(file) &&
            !/^composer\.(json|lock)$/.test(file) && file !== 'scripts/development/instalar-ete.ps1') continue;
        try {
            const info = fs.lstatSync(filename);
            if (info.isSymbolicLink() || !info.isFile()) throw new Error('Fuente no regular.');
            result[file] = hash(fs.readFileSync(filename));
        } catch (error) { if (error.code !== 'ENOENT') throw error; result[file] = 'eliminado'; }
    }
    return result;
}

function hashEteRuntime(root) {
    const entries = [];
    function walk(relative) {
        const filename = path.join(root, relative), info = fs.lstatSync(filename);
        if (info.isSymbolicLink()) throw new Error('El runtime ETE contiene un enlace.');
        if (info.isDirectory()) for (const entry of fs.readdirSync(filename)) {
            if (entry !== '__pycache__' && !entry.endsWith('.pyc')) walk(path.join(relative, entry));
        }
        else if (info.isFile()) entries.push([relative.split(path.sep).join('/'), hash(fs.readFileSync(filename))]);
        else throw new Error('Runtime ETE no regular.');
    }
    walk('.local/ete-venv');
    if (!entries.length) throw new Error('Runtime ETE vacio.');
    return hash(JSON.stringify(entries.sort(([a], [b]) => a.localeCompare(b))));
}

function snapshot(root, sector) {
    const sources = sourceInventory(root), head = git(root, ['rev-parse', 'HEAD']);
    let environment, runtime;
    if (sector === 'ete') {
        const python = path.join(root, '.local/ete-venv/Scripts/python.exe');
        const result = spawnSync(python, ['-c', 'import json,sys; print(json.dumps([sys.version,sys.executable,sys._base_executable]))'],
            {encoding: 'utf8', windowsHide: true});
        if (result.status !== 0) throw new Error('Falta el runtime ETE identificado.');
        const details = JSON.parse(result.stdout);
        environment = hash(JSON.stringify([process.platform, process.arch, details, hash(fs.readFileSync(details[2])),
            ...['PYTHONHOME', 'PYTHONPATH', 'QT_QPA_PLATFORM', 'QT_PLUGIN_PATH'].map(key => process.env[key] || '')]));
        runtime = {ete: hashEteRuntime(root)};
    } else if (sector === 'pdf') {
        // El punto de entrada del paquete ya contrasto esta evidencia con todo
        // vendor y el JAR. No rehashar vendor por cada comprobacion de 600 ms.
        const evidence = JSON.parse(fs.readFileSync(path.join(root, '.local/validation/evidencia-agente.json'), 'utf8'));
        const age = Date.now() - Date.parse(evidence.completed_at);
        if (evidence.root !== root || evidence.head !== head || !Number.isFinite(age) || age < -60000 || age > 86400000) throw new Error('Falta evidencia vigente del runtime PDF.');
        environment = evidence.sector_environment;
        runtime = evidence.runtime;
    } else throw new Error('Sector del paquete desconocido.');
    return {root, head, ...inputs({sector, sources, runtime, environment})};
}

function basePath(root) { return path.join(root, '.local/validation/base-sectores-paquete.json'); }
function load(root) {
    try {
        const base = JSON.parse(fs.readFileSync(basePath(root), 'utf8'));
        return base.version === 1 && base.root === root && base.sectors && typeof base.sectors === 'object' ? base : {version: 1, root, sectors: {}};
    } catch { return {version: 1, root, sectors: {}}; }
}

function migratedPdf(root) {
    try {
        const directory = path.join(root, '.local/validation');
        const origin = JSON.parse(fs.readFileSync(path.join(directory, 'base-navegador-origen.json'), 'utf8'));
        const advance = JSON.parse(fs.readFileSync(path.join(directory, 'avance-paquete-cancelado-20261028.json'), 'utf8'));
        const transcript = fs.readFileSync(path.join(directory, 'avance-paquete-cancelado-20261028.txt'));
        if (origin.root !== root || origin.source_sha256 !== advance.fuentes?.source_sha256 || origin.head !== advance.fuentes?.head ||
            hash(transcript) !== advance.fuentes?.sha256 || !advance.aprobados?.some(item => item.control === 'pdf-integracion' && item.resultado.startsWith('OK ')) ||
            !transcript.toString('utf8').includes('OK solución PDF depósitos:')) return null;
        return {version: 1, root, head: origin.head, ...inputs({sector: 'pdf', sources: origin.sources, runtime: origin.runtime, environment: origin.environment}),
            completed_at: advance.observado_antes_de, provenance: 'PDF realmente aprobado antes de cancelar Pest; transcripcion y snapshot conservados'};
    } catch { return null; }
}

function select(root, sector, current, {force = false, now = Date.now()} = {}) {
    const base = load(root);
    const previous = base.sectors?.[sector] || (sector === 'pdf' && !base.invalidated?.[sector] ? migratedPdf(root) : null);
    let ancestor = false;
    if (/^[a-f0-9]{40}$/.test(previous?.head || '')) {
        try { git(root, ['merge-base', '--is-ancestor', previous.head, current.head]); ancestor = true; } catch { /* Linaje incompatible. */ }
    }
    return {execute: force || !ancestor || !compatible(previous, current, now), previous};
}

function prepare(root, sector, current, now = Date.now()) {
    const base = load(root);
    delete base.sectors[sector];
    base.invalidated ||= {};
    base.invalidated[sector] = {started_at: new Date(now).toISOString(), head: current.head};
    atomicWrite(basePath(root), base);
    atomicWrite(path.join(root, `.local/validation/pendiente-paquete-${sector}.json`),
        {phase: 'pendiente', sector, started_at: new Date(now).toISOString(), ...current});
}

function accredit(root, sector, current, now = Date.now()) {
    const filename = path.join(root, `.local/validation/pendiente-paquete-${sector}.json`);
    const pending = JSON.parse(fs.readFileSync(filename, 'utf8'));
    const age = now - Date.parse(pending.started_at);
    if (pending.phase !== 'pendiente' || pending.sector !== sector || !Number.isFinite(age) || age < -60000 || age > 86400000 ||
        ['root', 'head', 'input_sha256', 'output_sha256'].some(key => current[key] !== pending[key])) throw new Error('Los insumos del sector cambiaron durante sus pruebas o falta el inicio.');
    const base = load(root);
    base.sectors[sector] = {version: 1, ...current, completed_at: new Date(now).toISOString()};
    if (base.invalidated) delete base.invalidated[sector];
    atomicWrite(basePath(root), base);
    fs.rmSync(filename);
    return base.sectors[sector];
}

function main() {
    const [action, rootArg, force] = process.argv.slice(2), root = path.resolve(rootArg);
    const match = /^(seleccionar|preparar|acreditar)-(ete|pdf)$/.exec(action || '');
    if (!match) throw new Error('Accion del sector desconocida.');
    const [, operation, sector] = match;
    let current;
    try { current = snapshot(root, sector); }
    catch (error) { if (operation === 'seleccionar') { process.stdout.write('EJECUTAR\n'); return; } throw error; }
    if (operation === 'seleccionar') {
        const decision = select(root, sector, current, {force: force === 'integral'});
        if (!decision.execute && !load(root).sectors[sector]) {
            const base = load(root); base.sectors[sector] = decision.previous; atomicWrite(basePath(root), base);
        }
        process.stdout.write(decision.execute ? 'EJECUTAR\n' : 'REUTILIZAR\n');
    } else if (operation === 'preparar') prepare(root, sector, current);
    else { accredit(root, sector, current); process.stdout.write(`BASE_PAQUETE_OK=${sector}\n`); }
}

if (require.main === module) { try { main(); } catch (error) { process.stderr.write(error.message + '\n'); process.exitCode = 1; } }
module.exports = {inputs, compatible, prepare, accredit, load, snapshot, select, migratedPdf, MAX_AGE, commands};
