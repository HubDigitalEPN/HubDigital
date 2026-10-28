const fs = require('node:fs');
const path = require('node:path');
const {createHash, randomUUID} = require('node:crypto');
const {spawnSync} = require('node:child_process');
const {hashRuntime} = require('./evidencia-agente.cjs');
const sectors = require('./bases-sectores-agente.cjs');
const browserCases = require('./base-navegador.cjs');
const packageSectors = require('./bases-sectores-paquete.cjs');
const policy = JSON.parse(fs.readFileSync(path.join(__dirname, 'plan-validacion.json'), 'utf8'));
const digest = value => createHash('sha256').update(value).digest('hex');
const config = policy.impacto;
const frontendProjects = ['.', ...Object.values(config.modulos).map(m => `Modules/${m}`)];
const policyHash = digest(JSON.stringify(policy) + fs.readFileSync(__filename, 'utf8'));
const phpSuites = /<testsuites>([\s\S]*?)<\/testsuites>/.exec(fs.readFileSync(path.resolve(__dirname, '../..', 'phpunit.xml'), 'utf8'))?.[1];
if (!phpSuites) throw new Error('No se pudo identificar el inventario PHP configurado.');
const phpDirectories = [...phpSuites.matchAll(/<directory(?:\s+[^>]*)?>([^<]+)<\/directory>/g)].map(m => m[1].trim().replaceAll('\\', '/').replace(/\/$/, '') + '/');
if (!phpDirectories.length || phpDirectories.some(p => p.startsWith('/') || p.split('/').includes('..'))) throw new Error('Directorios PHP no compatibles con la seleccion segura.');
const critical = /(^|\/)(AGENTS\.md|composer\.(json|lock)|package(-lock)?\.json|phpunit\.xml|behat\.php|modules_statuses\.json)$|^(scripts|deploy|tools|bootstrap|routes|config)\/|(^|\/)(routes|config|migrations|Providers|Policies|Middleware)\/|ServiceProvider\.php$|(^|\/)(vite|postcss|tailwind)\.config\.|^app\/(Models\/User|Enums\/RolUsuario|Http\/Middleware|Providers|Support\/(Botones|DescargasCatalogo))|^tests\/(bootstrap\.php|Pest\.php|.*TestCase\.php|Support\/|Fixtures\/|Concerns\/|Feature\/Auth\/)/i;
const phpTest = file => phpDirectories.some(prefix => file.startsWith(prefix)) && file.endsWith('Test.php');
const nodeTest = file => /^tests\/Frontend\/.*\.test\.mjs$/.test(file);

function owner(file) {
    for (const [area, module] of Object.entries(config.modulos)) if (file.startsWith(`Modules/${module}/`)) return area;
    if (/^resources\/(js\/portal-|python\/portal_ete\/)|^public\/images\/taxonomia\//.test(file)) return 'portal';
    if (/^tests\/(Feature|Unit|Regression)\/(Portal|Catalogo|Exportacion|ReferenciasFotograficas|VisibilidadCuratorial|ImportacionCatalogo|PerfilIpt)/.test(file) || /^tests\/Frontend\/portal-/.test(file)) return 'portal';
    if (/^tests\/(Feature|Unit)\/(Depositos|FlujoDeposito|Solicitud|OperacionesDocumentales|EnviarSolicitud|Analizador|TextoOficio|AlmacenamientoDepositos|ColaFirmas|DetalleValidacionFirma|AsistenteDocumental)/.test(file)) return 'depositos';
    return null;
}

function changedFiles(previous, current) {
    return [...new Set([...Object.keys(previous), ...Object.keys(current)])].filter(f => previous[f] !== current[f]).sort();
}

function closure(start, consumers) {
    const result = new Set(start), pending = [...start];
    while (pending.length) for (const consumer of consumers[pending.shift()] || []) {
        if (!result.has(consumer)) { result.add(consumer); pending.push(consumer); }
    }
    return result;
}

function inventoryHash(files) {
    return digest(Object.keys(files).sort().map(f => `${files[f]}  ${f}`).join('\n') + '\n');
}

function selectPlan({files, baseline, runtime, approvedRuntime = baseline?.runtime, root, head, environment, dependencies = {}, nodeDependencies = {}, fileDependencies, uncertain = [], ancestor = false, force = false, now = Date.now()}) {
    const allFiles = Object.keys(files).filter(f => files[f] !== 'eliminado').sort();
    const allPhp = allFiles.filter(phpTest), allNode = allFiles.filter(nodeTest);
    const changes = changedFiles(baseline?.sources || {}, files);
    let reason = '', integral = true, areas = new Set();
    if (force) reason = 'Validacion integral solicitada expresamente.';
    else if (!baseline || baseline.version !== 1 || baseline.full_test_coverage !== true || baseline.root !== root || baseline.policy_sha256 !== policyHash || !ancestor) reason = 'No existe una base integral aprobada y compatible en esta linea de trabajo.';
    else if (!Number.isFinite(Date.parse(baseline.completed_at)) || now - Date.parse(baseline.completed_at) < -60000 || now - Date.parse(baseline.completed_at) > config.base_max_dias * 86400000) reason = 'La base integral vencio o tiene fecha invalida.';
    else if (String(environment).startsWith('incompleto:') || baseline.environment !== environment) reason = 'Cambio el entorno de validacion o no se pudo identificar completo.';
    else if (!runtime?.per_root || Object.keys(approvedRuntime?.per_root || {}).length !== 6 || Object.entries(approvedRuntime.per_root).some(([k, v]) => runtime.per_root[k] !== v)) reason = 'Los artefactos o vendor no tienen evidencia compatible; se reconstruyen y comprueban todos.';
    else if (changes.length > config.max_archivos_selectivos) reason = `Hay ${changes.length} archivos cambiados acumulados; se supera el umbral selectivo.`;
    else if (changes.some(f => critical.test(f) || /(^|\/)(Auth|Authentication|Authorization|Policies|Permissions|Roles|Permisos)\/|(^|\/)(Rol|Role|Permission|Permiso|BotonesRbac|Autenticacion)[^/]*\.(php|js)$/i.test(f))) reason = 'Cambios criticos: autenticacion, permisos, dependencias, migraciones o infraestructura compartida.';
    else {
        const unknown = changes.filter(f => !/\.md$/i.test(f) && !owner(f));
        if (unknown.length) reason = `Impacto sin clasificar: ${unknown.slice(0, 3).join(', ')}.`;
        else {
            const impactedNodes = closure(changes, fileDependencies || nodeDependencies);
            areas = fileDependencies ? new Set([...impactedNodes].map(owner).filter(Boolean)) : closure(changes.map(owner).filter(Boolean), dependencies);
            if (uncertain.some(f => changes.includes(f) || (!/(^|\/)tests\//.test(f) && (impactedNodes.has(f) || (/\.php$/.test(f) && areas.has(owner(f))))))) reason = 'Una dependencia dinamica impide acotar el impacto con confianza.';
            else if (areas.size > config.max_areas_selectivas) reason = 'El impacto directo e indirecto alcanza varios dominios del sistema.';
            else { integral = false; reason = 'Cambio acotado y consumidores directos e indirectos identificados.'; }
        }
    }
    if (integral) areas = new Set(Object.keys(config.modulos));
    const nodeConsumers = closure(changes, fileDependencies || nodeDependencies);
    let selectedNode = integral ? allNode : allNode.filter(f => changes.includes(f) || nodeConsumers.has(f) || (!fileDependencies && areas.has(owner(f))));
    let selectedPhp = integral ? allPhp : fileDependencies ? allPhp.filter(f => changes.includes(f) || nodeConsumers.has(f))
        : areas.size ? allPhp.filter(f => !owner(f) || areas.has(owner(f))) : [];
    // Un cambio funcional sin cobertura mapeada nunca queda validado como documentación.
    if (!integral && changes.some(f => !/\.md$/i.test(f)) && selectedNode.length + selectedPhp.length === 0) {
        integral = true; reason = 'No se encontro cobertura mapeada para el cambio funcional.';
        areas = new Set(Object.keys(config.modulos)); selectedNode = allNode; selectedPhp = allPhp;
    }
    const frontend = integral ? frontendProjects : changes.some(f => /\.(blade\.php|css|scss)$/.test(f)) ? frontendProjects
        : frontendProjects.filter(project => [...nodeConsumers].some(f => project === '.' ? /^resources\/js\//.test(f) : f.startsWith(project + '/resources/')));
    const java = integral || areas.has('depositos');
    const browser = integral || areas.has('portal');
    const pdf = integral || areas.has('depositos');
    const selectedFeatures = integral ? allFiles.filter(f => /\/tests\/Behat\/.*\.feature$/.test(f))
        : allFiles.filter(f => /\/tests\/Behat\/.*\.feature$/.test(f) && areas.has(owner(f)));
    return {version: 1, root, head, source_sha256: inventoryHash(files), policy_sha256: policyHash,
        generated_at: new Date(now).toISOString(), integral, forced_integral: force, reason, changes, areas: [...areas].sort(),
        baseline_sha256: baseline ? digest(JSON.stringify(baseline)) : null,
        agente: {composer: integral || changes.some(f => /\.php$/.test(f)), java_build: integral, java_test: java,
            frontend, node_tests: selectedNode},
        paquete: {ete: integral || areas.has('portal'), python: integral || areas.has('portal'),
            postgres: integral || selectedPhp.length > 0 || browser || pdf, pdf, navegador: browser,
            pruebas_php: selectedPhp, gherkin: integral || selectedFeatures.length > 0, gherkin_paths: selectedFeatures},
        cobertura: {php: {ejecutar: selectedPhp, reutilizar: allPhp.filter(f => !selectedPhp.includes(f))},
            node: {ejecutar: selectedNode, reutilizar: allNode.filter(f => !selectedNode.includes(f))},
            gherkin: {ejecutar: selectedFeatures, reutilizar: allFiles.filter(f => /\/tests\/Behat\/.*\.feature$/.test(f) && !selectedFeatures.includes(f))},
            integraciones: {java: java ? 'ejecutar' : 'reutilizar', python: browser ? 'ejecutar' : 'reutilizar',
                pdf: pdf ? 'ejecutar' : 'reutilizar', navegador: browser ? 'ejecutar' : 'reutilizar'},
            builds: {ejecutar: frontend, reutilizar: frontendProjects.filter(p => !frontend.includes(p)),
                java: integral ? 'ejecutar' : 'reutilizar'}},
        dependencias: {modulos: dependencies, consumidores_js: nodeDependencies, consumidores_archivos: fileDependencies || {}, inciertas: uncertain},
        siempre: ['auditoria-composer', 'auditoria-npm-cuatro-proyectos', 'diff-json-sintaxis', 'integridad-identidad-artefacto']};
}

function git(root, args) {
    const result = spawnSync('git.exe', ['-C', root, ...args], {encoding: 'utf8', windowsHide: true, maxBuffer: 32 * 1024 * 1024});
    if (result.status !== 0) throw new Error('No se pudo identificar la linea de trabajo con Git.');
    return result.stdout.trim();
}

function sources(root) {
    const result = spawnSync(process.execPath, [path.join(__dirname, 'huellas-codigo.cjs'), root], {encoding: 'utf8', windowsHide: true, maxBuffer: 32 * 1024 * 1024});
    if (result.status !== 0) throw new Error('No se pudieron identificar las fuentes.');
    const files = {};
    for (const line of result.stdout.trimEnd().split('\n')) {
        const separator = line.indexOf('  ');
        if (separator < 1) throw new Error('Inventario de fuentes invalido.');
        files[line.slice(separator + 2)] = line.slice(0, separator);
    }
    return files;
}

function graph(root, files) {
    const dependencies = {}, nodeDependencies = {}, fileDependencies = {}, uncertain = [], phpRecords = [], classes = new Map();
    const all = new Set(Object.keys(files));
    const add = (map, dependency, consumer) => { if (!map[dependency]) map[dependency] = []; if (!map[dependency].includes(consumer)) map[dependency].push(consumer); };
    for (const file of all) {
        if (files[file] === 'eliminado' || !/\.(php|js|mjs|cjs|css|scss)$/.test(file)) continue;
        const text = fs.readFileSync(path.join(root, file), 'utf8');
        const area = owner(file);
        if (area && /\.php$/.test(file)) {
            for (const match of text.matchAll(/Modules\\+([A-Za-z0-9_]+)/g)) {
                const imported = Object.keys(config.modulos).find(k => config.modulos[k] === match[1]);
                if (imported && imported !== area) add(dependencies, imported, area);
            }
            for (const [imported, module] of Object.entries(config.modulos)) {
                if (imported !== area && text.toLowerCase().includes(module.toLowerCase() + '::')) add(dependencies, imported, area);
            }
            if (/(?<![:>\w])\b(app|resolve|call_user_func)\s*\(\s*\$/.test(text) || /->make\s*\(\s*\$/.test(text) || /\bnew\s+\$/.test(text)) uncertain.push(file);
        }
        if (/\.php$/.test(file)) {
            const namespace = /\bnamespace\s+([A-Za-z0-9_\\]+)\s*;/.exec(text)?.[1];
            const name = /\b(?:class|interface|trait|enum)\s+([A-Za-z0-9_]+)/.exec(text)?.[1];
            const refs = new Set([...text.matchAll(/\b(?:App|Modules)(?:\\+[A-Za-z_][A-Za-z0-9_]*)+/g)].map(m => m[0].replace(/\\+/g, '\\')));
            const aliases = {};
            for (const use of text.matchAll(/\buse\s+((?:App|Modules)\\[A-Za-z0-9_\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;/g)) aliases[use[2] || use[1].split('\\').at(-1)] = use[1];
            for (const group of text.matchAll(/\buse\s+((?:App|Modules)\\[A-Za-z0-9_\\]+)\s*\{([^}]+)\}\s*;/g)) {
                for (const item of group[2].split(',')) {
                    const [target, alias] = item.trim().split(/\s+as\s+/i), full = group[1] + target;
                    refs.add(full); aliases[alias || target.split('\\').at(-1)] = full;
                }
            }
            refs.delete(namespace);
            const areas = new Set([...refs].flatMap(ref => Object.keys(config.modulos).filter(k => ref.startsWith(`Modules\\${config.modulos[k]}\\`))));
            const record = {file, area, namespace, refs, aliases, text, areas, provider: /Providers\/|ServiceProvider\.php$/.test(file)};
            phpRecords.push(record);
            if (namespace && name) classes.set(namespace + '\\' + name, record);
            // Las vistas son dependencias de quien las renderiza o incluye.
            for (const match of text.matchAll(/(?:\bview\s*\(|@(?:include|extends|each|component)\s*\()\s*['"]([A-Za-z0-9_.:-]+)['"]/g)) {
                const parts = match[1].split('::');
                const module = parts.length === 2 ? Object.values(config.modulos).find(m => m.toLowerCase() === parts[0].toLowerCase()) : null;
                const view = (module ? `Modules/${module}/resources/views/` : 'resources/views/') + parts.at(-1).replaceAll('.', '/') + '.blade.php';
                if (all.has(view)) add(fileDependencies, view, file);
                else uncertain.push(file);
            }
            if (/\bview\s*\(\s*\$/.test(text)) uncertain.push(file);
        }
        if (!/\.(js|mjs|cjs|css|scss)$/.test(file)) continue;
        for (const match of text.matchAll(/(?:from\s*|import\s*\(?\s*|require\s*\(\s*|new\s+URL\s*\(\s*)['"](\.[^'"\n]+)['"]/g)) {
            const relative = path.posix.normalize(path.posix.join(path.posix.dirname(file), match[1]));
            const target = [relative, relative + '.js', relative + '.mjs', relative + '.cjs', relative + '/index.js'].find(p => all.has(p));
            if (target) add(nodeDependencies, target, file);
            // Los imports de vendor estan vinculados por la huella completa de
            // vendor y se reconstruyen al cambiar. No son fuentes sin clasificar.
            else if (!relative.startsWith('vendor/') || ![relative, relative + '.js'].some(p => fs.existsSync(path.join(root, p)))) uncertain.push(file);
        }
        if (/\bimport\s*\(\s*[^'"\s]/.test(text)) uncertain.push(file);
    }
    // Referencias a clases concretas, aliases de namespace y clases vecinas.
    // El mapa por modulo se conserva como diagnostico, pero un componente no
    // hereda todas las conexiones de los demas componentes de su modulo.
    const resolveClass = (token, record) => {
        const name = token.replace(/^\\/, ''), [first, ...rest] = name.split('\\');
        return classes.get(name) || classes.get(record.aliases[first] + (rest.length ? '\\' + rest.join('\\') : '')) || classes.get(record.namespace + '\\' + name);
    };
    for (const record of phpRecords) {
        for (const [name, dependency] of classes) {
            if (record.file === dependency.file) continue;
            const localName = name.slice(name.lastIndexOf('\\') + 1);
            const sameNamespace = record.namespace && dependency.namespace === record.namespace && new RegExp(`\\b${localName}\\b`).test(record.text);
            if (sameNamespace || [...record.refs].some(ref => name === ref || name.startsWith(ref + '\\'))) add(fileDependencies, dependency.file, record.file);
        }
        for (const declaration of record.text.matchAll(/\b(?:implements|extends)\s+([A-Za-z_\\][A-Za-z0-9_\\]*(?:\s*,\s*[A-Za-z_\\][A-Za-z0-9_\\]*)*)/g)) {
            for (const token of declaration[1].split(',').map(t => t.trim())) {
                const contract = resolveClass(token, record);
                if (contract) add(fileDependencies, record.file, contract.file);
            }
        }
        // Vincular cada par real, sin conectar entre si todos los servicios
        // importados por el mismo provider.
        if (record.provider) {
            const calls = [...record.text.matchAll(/->(?:bind|singleton|scoped)\s*\(/g)];
            const bindings = [...record.text.matchAll(/->(?:bind|singleton|scoped)\s*\(\s*(\\?[A-Za-z_][A-Za-z0-9_\\]*)::class\s*(?:,\s*(\\?[A-Za-z_][A-Za-z0-9_\\]*)::class\s*)?\)/g)];
            if (calls.length !== bindings.length) uncertain.push(record.file);
            for (const binding of bindings) {
                if (!binding[2]) continue;
                const contract = resolveClass(binding[1], record), implementation = resolveClass(binding[2], record);
                if (contract && implementation) add(fileDependencies, implementation.file, contract.file);
                else uncertain.push(record.file);
            }
        }
    }
    for (const [dependency, consumers] of Object.entries(nodeDependencies)) for (const consumer of consumers) add(fileDependencies, dependency, consumer);
    // Propaga también puentes App\\* entre módulos y los contratos registrados
    // juntos en providers. La unión conservadora puede ampliar la selección.
    let changed = true;
    while (changed) {
        changed = false;
        for (const record of phpRecords) {
            for (const ref of record.refs) for (const area of classes.get(ref)?.areas || []) {
                if (!record.areas.has(area)) { record.areas.add(area); changed = true; }
            }
            if (record.provider) for (const ref of record.refs) {
                const contract = classes.get(ref);
                if (contract && ref.startsWith('App\\')) for (const area of record.areas) {
                    if (!contract.areas.has(area)) { contract.areas.add(area); changed = true; }
                }
            }
        }
    }
    for (const record of phpRecords) if (record.area) for (const dependency of record.areas) {
        if (dependency !== record.area) add(dependencies, dependency, record.area);
    }
    return {dependencies, nodeDependencies, fileDependencies, uncertain: [...new Set(uncertain)]};
}

function environment(root) {
    return sectors.environment(root);
}

function writeJson(filename, value) {
    fs.mkdirSync(path.dirname(filename), {recursive: true});
    const temp = `${filename}.${randomUUID()}.tmp`;
    try { fs.writeFileSync(temp, JSON.stringify(value, null, 2) + '\n', {mode: 0o600}); fs.renameSync(temp, filename); }
    finally { fs.rmSync(temp, {force: true}); }
}

function refreshRuntimePlan({root, head, files, runtime, environment, plan}) {
    const force = plan.forced_integral === true;
    plan.navegador = browserCases.loadPlan({root, sources: files, runtime, environment,
        dependencies: plan.dependencias.consumidores_archivos, force});
    plan.paquete.navegador_casos = plan.navegador.ejecutar;
    plan.paquete.navegador = plan.navegador.ejecutar.length > 0;
    try {
        const pdfState = {root, head, ...packageSectors.inputs({sector: 'pdf', sources: files, runtime, environment})};
        const pdfDecision = packageSectors.select(root, 'pdf', pdfState, {force});
        plan.sectores_paquete = {pdf: {execute: pdfDecision.execute, inputs: pdfState,
            evidence: !pdfDecision.execute ? pdfDecision.previous : null}};
        plan.paquete.pdf = pdfDecision.execute;
    } catch {
        // Antes de construir puede faltar vendor o el JAR. Preparar la evidencia
        // vuelve a seleccionar con los artefactos reales, antes de las pruebas.
        plan.sectores_paquete = {pdf: {execute: true, inputs: null, evidence: null}};
        plan.paquete.pdf = true;
    }
    plan.paquete.postgres = plan.integral || plan.paquete.pruebas_php.length > 0 || plan.paquete.navegador || plan.paquete.pdf;
    plan.cobertura.integraciones.pdf = plan.paquete.pdf ? 'ejecutar' : 'reutilizar';
    plan.cobertura.navegador = {ejecutar: plan.navegador.ejecutar, reutilizar: plan.navegador.reutilizar,
        motivos: plan.navegador.motivos, evidencia: plan.navegador.evidencia};
    plan.cobertura.integraciones.navegador = plan.paquete.navegador ? 'ejecutar-casos-seleccionados' : 'reutilizar';
    return plan;
}

async function main() {
    const [action, project, option] = process.argv.slice(2);
    if (!['generar', 'verificar', 'acreditar'].includes(action) || !project) throw new Error('Uso: impacto-validacion.cjs generar|verificar|acreditar PROYECTO [integral]');
    const root = fs.realpathSync(project), directory = path.join(root, '.local', 'validation');
    const planPath = path.join(directory, 'plan-impacto.json'), basePath = path.join(directory, 'base-integral.json');
    const reportPath = path.join(directory, 'ultima-validacion.json');
    const files = sources(root), head = git(root, ['rev-parse', 'HEAD']);
    if (action === 'generar') {
        let baseline = null, runtime = null, ancestor = false, approvedRuntime;
        const sectorBase = sectors.load(root, head);
        try { baseline = JSON.parse(fs.readFileSync(basePath, 'utf8')); } catch { /* Sin recibo valido se exige integral. */ }
        if (baseline?.version === 1 && /^[a-f0-9]{40}$/.test(baseline.head || '')) {
            ancestor = spawnSync('git.exe', ['-C', root, 'merge-base', '--is-ancestor', baseline.head, head], {windowsHide: true}).status === 0;
            try { runtime = await hashRuntime(root); } catch { /* Runtime ausente obliga a reconstruccion integral. */ }
            try {
                const last = JSON.parse(fs.readFileSync(reportPath, 'utf8'));
                if (last.root === root && last.base_integral_sha256 === digest(JSON.stringify(baseline)) && last.policy_sha256 === policyHash &&
                    /^[a-f0-9]{40}$/.test(last.head) && spawnSync('git.exe', ['-C', root, 'merge-base', '--is-ancestor', last.head, head], {windowsHide: true}).status === 0) approvedRuntime = last.runtime;
            } catch { /* Sin recibo compatible, solo se admite el runtime integral. */ }
        }
        const sectorEnvironment = sectors.identity(root);
        const plan = selectPlan({files, root, head, baseline, runtime, approvedRuntime: approvedRuntime || baseline?.runtime,
            ancestor, environment: sectorEnvironment.fingerprint, force: option === 'integral', ...graph(root, files)});
        if (sectorBase && option !== 'integral') {
            try {
                runtime ||= await hashRuntime(root);
                const ownState = {sources: files, runtime, environment: sectorEnvironment.fingerprint,
                    core: fs.readFileSync(path.join(root, 'scripts/deployment/crear-paquete-oci-core.ps1'), 'utf8')};
                const ownInputs = sectors.inputs(ownState);
                const reusable = sectors.compatible(sectorBase, ownInputs);
                if (!sectorEnvironment.fingerprint.startsWith('incompleto:') && sectorBase.environment === sectorEnvironment.legacy) {
                    Object.assign(reusable, sectors.compatible(sectorBase, sectors.inputs({...ownState, environment: sectorEnvironment.legacy})));
                }
                plan.agente.evidencia_reutilizada = reusable;
                if (reusable['php-dependencias']) plan.agente.composer = false;
                if (reusable['java-compilacion']) plan.agente.java_build = false;
                if (reusable['java-criptografia'] && !plan.agente.java_build) plan.agente.java_test = false;
                plan.agente.frontend = plan.agente.frontend.filter(project => !reusable[`frontend-compilacion:${project}`] || !reusable[`frontend-dependencias:${project}`]);
                plan.agente.frontend_deps = plan.agente.frontend.filter(project => !reusable[`frontend-dependencias:${project}`]);
                if (reusable['javascript-contratos'] && !plan.agente.composer && !plan.agente.java_build && !plan.agente.java_test && plan.agente.frontend.length === 0) { plan.agente.node_tests = []; plan.agente.node_reutilizado = true; }
                plan.cobertura.node = {ejecutar: plan.agente.node_tests, reutilizar: Object.keys(files).filter(nodeTest).filter(f => !plan.agente.node_tests.includes(f))};
                plan.cobertura.builds = {ejecutar: plan.agente.frontend, reutilizar: frontendProjects.filter(p => !plan.agente.frontend.includes(p)), java: plan.agente.java_build ? 'ejecutar' : 'reutilizar'};
                plan.cobertura.integraciones.java = plan.agente.java_test ? 'ejecutar' : 'reutilizar';
            } catch { /* Runtime incompleto: conservar el alcance integral calculado. */ }
        }
        if (!runtime) { try { runtime = await hashRuntime(root); } catch { runtime = {per_root: {}}; } }
        refreshRuntimePlan({root, head, files, runtime, environment: sectorEnvironment.fingerprint, plan});
        writeJson(planPath, plan);
        console.log(`VALIDACION ${plan.integral ? 'INTEGRAL' : 'POR IMPACTO'}: ${plan.reason}`);
        console.log(`Cambios acumulados: ${plan.changes.length}; areas: ${plan.areas.join(', ') || 'documentacion'}.`);
        console.log(`PHP: ejecutar ${plan.cobertura.php.ejecutar.length}, reutilizar ${plan.cobertura.php.reutilizar.length}; Node: ejecutar ${plan.cobertura.node.ejecutar.length}, reutilizar ${plan.cobertura.node.reutilizar.length}.`);
        console.log(`Sectores del agente con evidencia propia compatible: ${Object.keys(plan.agente.evidencia_reutilizada || {}).join(', ') || 'ninguno'}.`);
        console.log(`Navegador por casos: ejecutar ${plan.navegador.ejecutar.length}, reutilizar ${plan.navegador.reutilizar.length}; base independiente de Pest.`);
        for (const id of browserCases.ids) console.log(`  ${plan.navegador.ejecutar.includes(id) ? 'EJECUTAR' : 'REUTILIZAR'} ${id}: ${plan.navegador.motivos[id]}`);
        console.log(`Detalle y dependencias: ${planPath}`);
    } else {
        const plan = JSON.parse(fs.readFileSync(planPath, 'utf8'));
        if (plan.version !== 1 || plan.root !== root || plan.source_sha256 !== inventoryHash(files) || plan.policy_sha256 !== policyHash) throw new Error('El plan de impacto no corresponde a las fuentes y reglas actuales. Repetir el bloque del agente.');
        if (!plan.navegador) throw new Error('Falta la seleccion independiente del navegador. Repetir el bloque del agente.');
        const verifiedRuntime = await hashRuntime(root), verifiedEnvironment = environment(root);
        browserCases.verify(root, plan.navegador, {sources: files, runtime: verifiedRuntime, environment: verifiedEnvironment,
            dependencies: plan.dependencias.consumidores_archivos});
        const pdfState = {root, head, ...packageSectors.inputs({sector: 'pdf', sources: files, runtime: verifiedRuntime, environment: verifiedEnvironment})};
        if (!plan.sectores_paquete?.pdf || ['root', 'input_sha256', 'output_sha256'].some(key => pdfState[key] !== plan.sectores_paquete.pdf.inputs?.[key])) throw new Error('Cambiaron los insumos PDF. Repetir el bloque del agente.');
        if (!plan.paquete.pdf && packageSectors.select(root, 'pdf', pdfState).execute) throw new Error('La base PDF reutilizada cambio o vencio. Repetir el bloque del agente.');
        if (option === 'integral' && !plan.integral) throw new Error('Para forzar integral, ejecutar primero validar-cambios-agente.ps1 -Integral.');
        if (!plan.integral) {
            const base = JSON.parse(fs.readFileSync(basePath, 'utf8'));
            if (digest(JSON.stringify(base)) !== plan.baseline_sha256 || Date.now() - Date.parse(base.completed_at) > config.base_max_dias * 86400000 || environment(root) !== base.environment) throw new Error('La base reutilizada cambio o vencio. Repetir la validacion del agente.');
        }
        if (action === 'acreditar') {
            const agent = JSON.parse(fs.readFileSync(path.join(directory, 'evidencia-agente.json'), 'utf8'));
            if (agent.source_sha256 !== plan.source_sha256 || agent.impact_sha256 !== digest(JSON.stringify(plan))) throw new Error('La evidencia del agente no corresponde al plan terminado.');
            const runtime = verifiedRuntime;
            if (JSON.stringify(runtime) !== JSON.stringify(agent.runtime)) throw new Error('El runtime cambio durante la validacion del paquete.');
            const report = {version: 1, completed_at: new Date().toISOString(), root, head, integral: plan.integral, reason: plan.reason,
                cobertura: plan.cobertura, sectores_paquete: packageSectors.load(root).sectors, siempre: plan.siempre, policy_sha256: policyHash, runtime, plan_sha256: digest(JSON.stringify(plan)), base_integral_sha256: plan.baseline_sha256};
            if (plan.integral) {
                const base = {version: 1, completed_at: report.completed_at, full_test_coverage: true, root, head,
                    policy_sha256: policyHash, environment: environment(root), sources: files, runtime};
                writeJson(basePath, base); report.base_integral_sha256 = digest(JSON.stringify(base));
            }
            writeJson(reportPath, report);
            console.log(`VALIDACION ACREDITADA: ${plan.integral ? 'integral; nueva base aprobada' : 'por impacto; se conserva la base integral'}.`);
        } else console.log(`PLAN_IMPACTO_OK=${plan.integral ? 'integral' : 'selectivo'}; auditorias e integridad siempre obligatorias.`);
    }
}

module.exports = {selectPlan, changedFiles, closure, inventoryHash, policyHash, config, owner, graph, refreshRuntimePlan};
if (require.main === module) main().catch(error => { console.error(error.message); process.exitCode = 1; });
