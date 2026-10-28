const fs = require('node:fs');
const path = require('node:path');
const {createHash, randomUUID} = require('node:crypto');
const {spawnSync} = require('node:child_process');
const hash = value => createHash('sha256').update(value).digest('hex');
const MAX_AGE = 7 * 86400000;
const projects = {'.': 'public/build', 'Modules/CatalogoPublico': 'public/build-catalogopublico',
    'Modules/GestionPrestamosRecepciones': 'public/build-gestionprestamosrecepciones',
    'Modules/InventarioGestionColeccion': 'public/build-inventariogestioncoleccion'};
const families = ['php-dependencias', 'java-compilacion', 'frontend-dependencias', 'frontend-compilacion', 'java-criptografia', 'javascript-contratos'].sort();

function identity(root, {env = process.env, probe = spawnSync} = {}) {
    const options = {encoding: 'utf8', windowsHide: true, env};
    const php = probe('php.exe', ['-r', "echo PHP_VERSION, '|', PHP_BINARY;"], options);
    const java = probe('java.exe', ['-version'], options);
    const javaLocation = probe('where.exe', ['java.exe'], options);
    const canonical = value => {
        if (!value) return '';
        let result = value.trim();
        try { result = fs.realpathSync.native(result); } catch { /* La comprobacion de herramienta decide si la identidad es completa. */ }
        return process.platform === 'win32' ? path.win32.normalize(result).toLowerCase() : result;
    };
    const envFile = path.join(root, '.env');
    const envHash = fs.existsSync(envFile) ? hash(fs.readFileSync(envFile)) : 'sin-env';
    const [phpVersion, phpBinary] = String(php.stdout || '').trim().split('|');
    const javaBinary = canonical(String(javaLocation.stdout || '').split(/\r?\n/).find(Boolean) || '');
    const javaHome = env.JAVA_HOME ? canonical(env.JAVA_HOME) : (javaBinary ? path.dirname(path.dirname(javaBinary)) : '');
    const complete = php.status === 0 && java.status === 0 && javaLocation.status === 0 && !!phpVersion && !!phpBinary && !!javaBinary;
    const components = {version: 2, platform: process.platform, arch: process.arch,
        node: {version: process.version, binary: canonical(process.execPath)},
        php: {version: phpVersion || '', binary: canonical(phpBinary || '')},
        java: {version: String(java.stdout || '') + String(java.stderr || ''), binary: javaBinary, maven_home: javaHome,
            options_sha256: hash(JSON.stringify([env.JAVA_TOOL_OPTIONS || '', env.JDK_JAVA_OPTIONS || '', env._JAVA_OPTIONS || '']))},
        config_sha256: envHash};
    // PATH solo participa en resolver ejecutables. No se firma el texto del
    // lanzador: Codex, CMD y PowerShell pueden agregar carpetas irrelevantes.
    const fingerprint = (complete ? 'v2:' : 'incompleto:') + hash(JSON.stringify(components));
    // Permite conservar una aprobacion anterior UNICAMENTE si tambien coincide
    // toda la huella estricta antigua en este proceso, no solo el codigo.
    const legacy = (php.status === 0 && java.status === 0 ? '' : 'incompleto:') + hash([
        process.version, process.platform, process.arch, env.PATH || env.Path || '', env.JAVA_HOME || '',
        php.status === 0 ? php.stdout : 'php-no-identificado', java.status === 0 ? java.stdout + java.stderr : 'java-no-identificado',
        envHash].join('\n'));
    return {fingerprint, legacy, components};
}

function environment(root) { return identity(root).fingerprint; }

function inputs({sources, runtime, environment: env, core}) {
    const result = {};
    const sourceHash = predicate => hash(Object.keys(sources).sort().filter(predicate).map(f => `${sources[f]}  ${f}`).join('\n'));
    const invoker = /function Invoke-Comando\s*\{[\s\S]*?\r?\n\}/.exec(core)?.[0];
    if (!invoker) throw new Error('No se pudo identificar el contrato del ejecutor de validaciones.');
    const command = marker => {
        const lines = core.split(/\r?\n/).filter(line => line.includes('Invoke-Comando ') && line.includes(marker));
        if (lines.length !== 1) throw new Error(`Contrato de sector ambiguo: ${marker}`);
        return lines[0].trim().split(' -Descripcion ')[0];
    };
    const add = (id, source, runner, output = '') => {
        result[id] = {input_sha256: hash(JSON.stringify([1, env, source, hash(invoker), runner])), output_sha256: output};
    };
    add('php-dependencias', sourceHash(f => /\.php$/.test(f) || /^composer\.(json|lock)$/.test(f)), command("@('install',"), runtime.per_root.vendor);
    const javaSources = sourceHash(f => f.startsWith('tools/pdf-signature/'));
    const jar = runtime.per_root['resources/bin/hubdigital-pdf-signature.jar'];
    add('java-compilacion', javaSources, command("'-DskipTests', 'package'"), jar);
    add('java-criptografia', javaSources, command("'selftest')"), jar);
    const frontendSources = sourceHash(f => /^(resources|Modules|public|app|routes|config|bootstrap)\//.test(f) || /^(package.*\.json|vite[^/]*|postcss[^/]*|tailwind[^/]*)$/.test(f));
    for (const [project, build] of Object.entries(projects)) {
        const prefix = project === '.' ? '' : project + '/';
        const locks = sourceHash(f => f === prefix + 'package.json' || f === prefix + 'package-lock.json');
        add(`frontend-dependencias:${project}`, locks, command("@('ci',"), runtime.per_root[build]);
        add(`frontend-compilacion:${project}`, hash(frontendSources + locks + runtime.per_root.vendor), command("@('run', 'build')"), runtime.per_root[build]);
    }
    // Los contratos Node leen tambien PHP, Blade y scripts: sin un inventario
    // propio por caso se mantiene este conjunto amplio y de bajo coste.
    add('javascript-contratos', sourceHash(f => !/\.md$/i.test(f)), command("@('--test')"), runtime.sha256);
    return result;
}

function compatible(base, current, now = Date.now()) {
    const approved = {};
    if (base?.version !== 1 || String(base.environment).startsWith('incompleto:')) return approved;
    for (const [sector, value] of Object.entries(current)) {
        const previous = base.sectors?.[sector], age = now - Date.parse(previous?.completed_at);
        if (Number.isFinite(age) && age >= -60000 && age <= MAX_AGE && previous.input_sha256 === value.input_sha256 && previous.output_sha256 === value.output_sha256) approved[sector] = previous;
    }
    return approved;
}

function write(root, base) {
    const filename = path.join(root, '.local/validation/base-sectores-agente.json'), temp = filename + '.' + randomUUID() + '.tmp';
    try { fs.writeFileSync(temp, JSON.stringify(base, null, 2) + '\n', {mode: 0o600}); fs.renameSync(temp, filename); }
    finally { fs.rmSync(temp, {force: true}); }
}

function load(root, head) {
    let base;
    try { base = JSON.parse(fs.readFileSync(path.join(root, '.local/validation/base-sectores-agente.json'), 'utf8')); }
    catch {
        // Migracion unica del estado exacto conservado tras verificar la evidencia
        // real del agente. No acredita controles del paquete ni inventa fechas.
        try {
            const original = JSON.parse(fs.readFileSync(path.join(root, '.local/validation/sectores-aprobados-origen.json'), 'utf8'));
            if (original.version !== 1 || original.root !== root || JSON.stringify(original.approved_stages) !== JSON.stringify(families)) return null;
            const values = inputs(original);
            base = {version: 1, root, head: original.head, environment: original.environment,
                sectors: Object.fromEntries(Object.entries(values).map(([id, value]) => [id, {...value, completed_at: original.completed_at}]))};
            write(root, base);
        } catch { return null; }
    }
    if (base.version !== 1 || base.root !== root || !/^[a-f0-9]{40}$/.test(base.head || '') ||
        spawnSync('git.exe', ['-C', root, 'merge-base', '--is-ancestor', base.head, head], {windowsHide: true}).status !== 0) return null;
    return base;
}

function record(root, evidence, agentPlan) {
    if (!evidence.sector_inputs || JSON.stringify(evidence.stages) !== JSON.stringify(families)) throw new Error('Falta aprobacion de las seis familias para conservar bases.');
    const previous = load(root, evidence.head);
    const executed = id => id === 'php-dependencias' ? agentPlan.composer : id === 'java-compilacion' ? agentPlan.java_build :
        id === 'java-criptografia' ? agentPlan.java_test : id === 'javascript-contratos' ? agentPlan.node_tests.length > 0 :
        id.startsWith('frontend-compilacion:') ? agentPlan.frontend.includes(id.slice('frontend-compilacion:'.length)) :
        (agentPlan.frontend_deps || agentPlan.frontend).includes(id.slice('frontend-dependencias:'.length));
    const sectors = Object.fromEntries(Object.entries(evidence.sector_inputs).map(([id, value]) => [id, {...value,
        completed_at: executed(id) ? evidence.completed_at : previous?.sectors?.[id]?.completed_at || evidence.completed_at}]));
    write(root, {version: 1, root, head: evidence.head, environment: evidence.sector_environment, sectors});
}

module.exports = {inputs, compatible, load, record, environment, identity, MAX_AGE};
