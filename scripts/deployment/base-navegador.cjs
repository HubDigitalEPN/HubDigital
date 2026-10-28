// Evidencia independiente por recorrido. No acredita Pest ni el resto del 70 %.
const fs = require('node:fs');
const path = require('node:path');
const {createHash, randomUUID} = require('node:crypto');
const ids = ['apertura-sesion', 'filtros-graficos', 'tooltip-livewire', 'arbol-diversidad', 'modales-mapa', 'tabla-paginacion', 'fotografias', 'administracion-importacion', 'pdf-siete-figuras'];
const hash = value => createHash('sha256').update(typeof value === 'string' || Buffer.isBuffer(value) ? value : JSON.stringify(value)).digest('hex');
const days = 7;
const shared = /^(composer\.(json|lock)|package(-lock)?\.json|modules_statuses\.json|bootstrap\/|config\/|routes\/|app\/(Models\/User|Enums\/Rol|Http\/Middleware|Providers|Support\/(Botones|DescargasCatalogo)))|(^|\/)(Auth|Authentication|Authorization|Policies|Permissions|Roles|Permisos|migrations|Providers|Middleware|routes|config)\/|(^|\/)(Rol|Role|Permission|Permiso|BotonesRbac)[^/]*\.php$|(^|\/)package(-lock)?\.json$/i;
const functional = /^(app|Modules|resources|public)\//;
const infrastructureOnly = /^(scripts\/deployment\/|tests\/Frontend\/|docs\/)|(^|\/)AGENTS\.md$|\.md$/i;
const labels = {
    'apertura-sesion': 'apertura, sesión, cabecera y pie', 'filtros-graficos': 'filtros, búsqueda y gráficos',
    'tooltip-livewire': 'tooltip durante morph Livewire', 'arbol-diversidad': 'árbol y diversidad',
    'modales-mapa': 'modales, mapa y registros', 'tabla-paginacion': 'tabla y paginación adaptable',
    fotografias: 'fotografías por taxón', 'administracion-importacion': 'curador e importación',
    'pdf-siete-figuras': 'PDF de siete figuras',
};
function write(filename, value) {
    fs.mkdirSync(path.dirname(filename), {recursive: true});
    const temporary = `${filename}.${randomUUID()}.tmp`;
    try { fs.writeFileSync(temporary, JSON.stringify(value, null, 2) + '\n', {mode: 0o600}); fs.renameSync(temporary, filename); }
    finally { fs.rmSync(temporary, {force: true}); }
}
function read(filename) { try { return JSON.parse(fs.readFileSync(filename, 'utf8')); } catch { return null; } }
function closure(files, graph) {
    const found = new Set(files), pending = [...found];
    while (pending.length) for (const file of graph[pending.shift()] || []) if (!found.has(file)) { found.add(file); pending.push(file); }
    return found;
}
function directCases(file) {
    if (shared.test(file)) return ids;
    if (/resources\/views\/layouts\/|resources\/views\/portal-informacion|app\/Support\/PortalCopy|resources\/js\/(auth-forms|manual-password|portal-theme)|resources\/views\/livewire\/auth\//.test(file)) return ['apertura-sesion'];
    if (/portal-dashboard-export|figura-panel|grafico-panel|figura|portal-estadisticas/.test(file)) return ['pdf-siete-figuras', 'filtros-graficos'];
    if (/portal-(chart|charts|search|phylum)|filtro-(investigacion|catalogo)|busqueda-predictiva/.test(file)) return ['filtros-graficos', 'tooltip-livewire'];
    if (/portal-(taxonomy|tree|ete|phylotree|mdd)|explorador-taxonomico|rama-arbol|nodo-arbol|Dendrograma|Diversidad|ArbolTaxonomico|portal_ete/.test(file)) return ['arbol-diversidad', 'modales-mapa'];
    if (/portal-(photo|photos)|fotografia|Fotografia|Fotografias|Referencia(Taxonomica|Visual)|Ilustracion|ayuda-taxon|galeria|images\//.test(file)) return ['fotografias'];
    if (/portal-(record|records)|tabla-registros|ficha-registro|detalle-celda|registros-taxon/.test(file)) return ['modales-mapa', 'tabla-paginacion'];
    if (/portal-map|Coordenada|DistribucionPublica/.test(file)) return ['tooltip-livewire', 'modales-mapa', 'filtros-graficos', 'pdf-siete-figuras'];
    if (/Matriz|matriz|Importa|importa|Carga|carga|inventario\/taxonomia\/importar/.test(file)) return ['administracion-importacion'];
    if (/^Modules\/GestionPrestamosRecepciones\//.test(file)) return [];
    if (/^Modules\/InventarioGestionColeccion\//.test(file)) return ['administracion-importacion'];
    // Estos archivos contienen piezas de varios casos. Una modificación sin
    // fragmento reconocible amplía el alcance de forma explícita y conservadora.
    if (/portal-dashboard\.js$|PortalCatalogo\.php$|portal-catalogo\.blade\.php$|dashboard-coleccion\.blade\.php$|resources\/(js\/app\.js|css\/(app|portal)\.css)/.test(file)) return ids;
    return null;
}
function methodCases(name) {
    if (/^(abrirFichaRegistro|cerrarFichaRegistro|fichaRegistro|ajustarRegistrosCelda)$/.test(name)) return ['modales-mapa'];
    if (/^(cargarMasRegistros|ajustarRegistrosPorPagina|ordenarRegistros|restablecerVistaRegistros|cambiarPagina)$/.test(name)) return ['tabla-paginacion', 'modales-mapa'];
    if (/^cargarPortadas$/.test(name)) return ['fotografias'];
    if (/^(seleccionarMes|seleccionarAltitud|seleccionarDecada|seleccionarProvincia|cambiarProvinciaPanel|seleccionarLocalidad|volverProvincias|aplicarBusquedaTexto|territorioPanel)$/.test(name)) return ['filtros-graficos', 'tooltip-livewire', 'pdf-siete-figuras'];
    return ids;
}
function fragments(file, text) {
    if (/resources\/js\/portal-dashboard\.js$/.test(file)) {
        const methods = [...text.matchAll(/^\s{4,}(?:async\s+)?([A-Za-z_$][\w$]*)\([^\n;]*\)\s*\{/gm)]
            .filter(m => !['if', 'for', 'while', 'switch', 'catch', 'function'].includes(m[1]));
        if (methods.length) return [{key: `${file}#estado-compartido`, text: text.slice(0, methods[0].index), cases: ids},
            ...methods.map((m, i) => ({key: `${file}#${i}-${m[1]}`, text: text.slice(m.index, methods[i + 1]?.index ?? text.length),
                cases: /^(mostrarAyudaMapa|ocultarAyudaMapa)$/.test(m[1]) ? ['tooltip-livewire'] : ids}))];
    }
    // Método completo hasta la próxima declaración de la clase. Incluye también
    // el encabezado/estado compartido: cambios fuera del método afectan a todos.
    if (!/PortalCatalogo\.php$/.test(file)) return [{key: file, text, cases: directCases(file)}];
    const matches = [...text.matchAll(/^    (?:public|private|protected)\s+(?:static\s+)?function\s+(\w+)\s*\(/gm)];
    if (!matches.length) return [{key: file, text, cases: ids}];
    return [{key: `${file}#estado-compartido`, text: text.slice(0, matches[0].index), cases: ids},
        ...matches.map((m, i) => ({key: `${file}#${m[1]}`, text: text.slice(m.index, matches[i + 1]?.index ?? text.length), cases: methodCases(m[1])}))];
}
function runnerParts(runner) {
    const bodies = {};
    let common = runner;
    for (const id of ids) {
        const start = `    // CASO ${id} INICIO\n`, end = `    // CASO ${id} FIN\n`;
        const a = runner.indexOf(start), b = runner.indexOf(end);
        if (a < 0 || b < a || runner.indexOf(start, a + 1) >= 0) throw new Error(`Falta el cuerpo único del caso ${id}.`);
        bodies[id] = runner.slice(a + start.length, b);
        common = common.replace(bodies[id], `<cuerpo:${id}>\n`);
    }
    return {bodies, common};
}
function preservedBody(body) { return body.replace(/    \/\/ PREPARACION ADICIONAL INICIO\n[\s\S]*?    \/\/ PREPARACION ADICIONAL FIN\n/g, ''); }
function inputs({root, sources, contents, environment, runtime, dependencies = {}}) {
    const readText = file => contents?.[file] ?? fs.readFileSync(path.join(root, file), 'utf8');
    const runner = readText('tests/Browser/portal-livewire.mjs').replaceAll('\r\n', '\n');
    const parts = runnerParts(runner), result = Object.fromEntries(ids.map(id => [id, {}]));
    const layout = JSON.parse(readText('tests/Browser/portal-casos.json'));
    for (const id of ids) {
        result[id]['test:cuerpo'] = hash(parts.bodies[id]); result[id]['test:preparacion'] = hash(parts.common);
        result[id]['test:contrato'] = hash(layout.cases[id]);
        result[id]['test:fixture'] = sources['tests/Browser/portal-fixture.php'];
        result[id]['entorno'] = environment; result[id]['vendor'] = runtime?.per_root?.vendor || 'ausente';
    }
    for (const file of Object.keys(sources).sort()) {
        if (infrastructureOnly.test(file) || (!functional.test(file) && !shared.test(file))) continue;
        let own = directCases(file);
        const consumers = closure([file], dependencies);
        // Los archivos de entrada del bundle y las vistas/contenedores
        // monolíticos cargan varios componentes, pero no los hacen depender
        // funcionalmente unos de otros. Sí se siguen consumidores específicos.
        const related = [...consumers].filter(f => f !== file && !/resources\/js\/app\.js$|portal-dashboard\.js$|PortalCatalogo\.php$|portal-catalogo\.blade\.php$|dashboard-coleccion\.blade\.php$/.test(f))
            .flatMap(f => directCases(f) || []);
        if (own === null) {
            own = [...new Set([...consumers].filter(f => f !== file).flatMap(f => directCases(f) || []))];
            // Un archivo de catálogo no clasificado no desaparece silenciosamente.
            if (!own.length && /^Modules\/CatalogoPublico\//.test(file)) own = ids;
            if (!own.length && /^app\//.test(file)) own = ids;
        } else own = [...new Set([...own, ...related])];
        if (!own.length) continue;
        if (sources[file] === 'eliminado') { for (const id of own) result[id][file] = 'eliminado'; continue; }
        if (!/PortalCatalogo\.php$|resources\/js\/portal-dashboard\.js$/.test(file)) {
            // Las imágenes son bytes: convertirlas a UTF-8 cambiaría su huella.
            for (const id of own) result[id][file] = sources[file];
        } else for (const fragment of fragments(file, readText(file))) for (const id of fragment.cases || own) if (own.includes(id)) result[id][fragment.key] = hash(fragment.text);
    }
    return Object.fromEntries(ids.map(id => [id, {values: result[id], sha256: hash(result[id])}]));
}
function select({root, entries, current, force = false, now = Date.now()}) {
    const ejecutar = [], reutilizar = [], motivos = {}, evidencia = {};
    for (const id of ids) {
        const previous = entries?.[id];
        let reason = '';
        if (force) reason = 'Integral solicitado: se comprueba de nuevo este caso.';
        else if (!previous || previous.root !== root || previous.status !== 'passed') reason = 'No existe base aprobada propia de este caso.';
        else if (!Number.isFinite(Date.parse(previous.approved_at)) || now - Date.parse(previous.approved_at) > days * 86400000 || now < Date.parse(previous.approved_at) - 60000) reason = 'La evidencia del caso venció o tiene fecha inválida.';
        else if (String(current[id].values.entorno).startsWith('incompleto:')) reason = 'El entorno no pudo identificarse completamente.';
        else if (previous.input_sha256 !== current[id].sha256) {
            const changes = [...new Set([...Object.keys(previous.inputs || {}), ...Object.keys(current[id].values)])].filter(key => previous.inputs?.[key] !== current[id].values[key]);
            reason = `Entradas o consumidores modificados: ${changes.slice(0, 4).join(', ')}${changes.length > 4 ? '…' : ''}.`;
            if (changes.some(f => /portal-dashboard\.js|portal-catalogo\.blade|dashboard-coleccion\.blade/.test(f))) reason += ' Componente compartido: no es seguro reducir más este recorrido.';
        }
        motivos[id] = reason || `Sin impacto; aprobado ${previous.approved_at} (${previous.provenance.type}).`;
        if (reason) ejecutar.push(id);
        else { reutilizar.push(id); evidencia[id] = {approved_at: previous.approved_at, input_sha256: previous.input_sha256, provenance: previous.provenance}; }
    }
    return {version: 1, root, ejecutar, reutilizar, motivos, evidencia, inputs: current};
}
// Conservación verificable de TODAS las aserciones, incluidas las de helpers.
function assertionStatements(text) {
    const statements = [];
    for (let index = 0; index < text.length;) {
        const match = /\bassert\.[A-Za-z]+\s*\(/g; match.lastIndex = index;
        const found = match.exec(text); if (!found) break;
        let depth = 1, quote = '', escaped = false, j = match.lastIndex;
        for (; j < text.length && depth; j++) {
            const char = text[j];
            if (quote) { if (escaped) escaped = false; else if (char === '\\') escaped = true; else if (char === quote) quote = ''; }
            else if (['"', "'", '`'].includes(char)) quote = char;
            else if (char === '(') depth++; else if (char === ')') depth--;
        }
        if (depth) throw new Error('Aserción truncada: no se migra evidencia.');
        statements.push(text.slice(found.index, j).replaceAll('\r\n', '\n')); index = j;
    }
    return statements;
}
function migration({origin, root, current, runner, fixtureHash, layout, now = Date.now()}) {
    if (!origin || origin.version !== 1 || origin.root !== root || origin.scope !== 'portal-browser-full' ||
        origin.approval?.type !== 'real-browser-success-confirmed-by-user' || !/^OK navegador:/.test(origin.approval.message || '') ||
        hash(origin.runner) !== origin.runner_sha256 || fixtureHash !== origin.fixture_sha256 ||
        !Number.isFinite(Date.parse(origin.approved_at)) || now - Date.parse(origin.approved_at) > days * 86400000) return {};
    if (!layout || layout.version !== 1 || layout.original_runner_sha256 !== origin.runner_sha256) return {};
    const originalLines = origin.runner.replaceAll('\r\n', '\n').split(/(?<=\n)/), parts = runnerParts(runner.replaceAll('\r\n', '\n'));
    for (const id of ids) {
        const expected = layout.cases[id]?.ranges.flatMap(([a, b]) => originalLines.slice(a - 1, b)
            .filter((_, i) => !layout.helpers.some(([start, end]) => i + a >= start && i + a <= end))).join('');
        if (hash(expected) !== layout.cases[id].body_sha256 || preservedBody(parts.bodies[id]) !== expected) throw new Error(`El cuerpo de ${id} cambió: no se hereda la aprobación original.`);
    }
    const oldAssertions = assertionStatements(origin.runner), nextAssertions = assertionStatements(runner);
    const counts = new Map(); for (const statement of nextAssertions) counts.set(statement, (counts.get(statement) || 0) + 1);
    for (const statement of oldAssertions) { if (!counts.get(statement)) throw new Error('La extracción no conserva todas las aserciones del recorrido aprobado.'); counts.set(statement, counts.get(statement) - 1); }
    const migrated = {};
    for (const id of ids) {
        // El nuevo setup no se declara ejecutado. Solo la cobertura original de
        // aserciones y entradas funcionales iguales hereda su aprobación real.
        const values = current[id].values;
        if (values.entorno !== origin.environment || values.vendor !== origin.runtime?.per_root?.vendor) continue;
        const compatible = Object.entries(values).every(([key, value]) => {
            if (key.startsWith('test:') || ['entorno', 'vendor'].includes(key)) return true;
            const [file, fragment] = key.split('#');
            if (!fragment) return origin.sources[file] === value;
            const text = origin.contents?.[file]; if (typeof text !== 'string') return false;
            return fragments(file, text).find(p => p.key === key) && hash(fragments(file, text).find(p => p.key === key).text) === value;
        });
        if (compatible) migrated[id] = {root, status: 'passed', approved_at: origin.approved_at,
            input_sha256: current[id].sha256, inputs: values,
            provenance: {type: 'assertions-preserved-from-confirmed-full-run', original_runner_sha256: origin.runner_sha256,
                confirmation_at: origin.confirmed_at, new_runner_executed: false, message: origin.approval.message}};
    }
    return migrated;
}
function loadPlan({root, sources, environment, runtime, dependencies, force = false, now = Date.now()}) {
    const directory = path.join(root, '.local/validation'), filename = path.join(directory, 'base-navegador.json');
    const base = read(filename), entries = base?.version === 1 && base.root === root ? {...base.cases} : {};
    const current = inputs({root, sources, environment, runtime, dependencies});
    const origin = read(path.join(directory, 'base-navegador-origen.json'));
    if (origin && !base) {
        const migrated = migration({origin, root, current, fixtureHash: sources['tests/Browser/portal-fixture.php'], now,
            runner: fs.readFileSync(path.join(root, 'tests/Browser/portal-livewire.mjs'), 'utf8'),
            layout: read(path.join(root, 'tests/Browser/portal-casos.json'))});
        Object.assign(entries, migrated);
        if (Object.keys(migrated).length) write(filename, {version: 1, root, cases: entries});
    }
    return select({root, entries, current, force, now});
}
function verify(root, plan, state) {
    const fresh = loadPlan({...state, root});
    if (plan.root !== root || ids.some(id => plan.inputs[id].sha256 !== fresh.inputs[id].sha256)) throw new Error('Cambió una entrada del navegador después de seleccionar los casos.');
    for (const id of plan.reutilizar) if (!fresh.reutilizar.includes(id) || JSON.stringify(fresh.evidencia[id]) !== JSON.stringify(plan.evidencia[id])) throw new Error(`La evidencia reutilizada de ${id} cambió o venció.`);
}
function prepareRun(root, plan) {
    if (plan.root !== root || !plan.ejecutar.length || plan.ejecutar.some(id => !ids.includes(id))) throw new Error('Plan de navegador inválido.');
    const filename = path.join(root, '.local/validation/base-navegador.json'), base = read(filename);
    const entries = base?.version === 1 && base.root === root ? {...base.cases} : {};
    // Si el proceso falla o se cancela, los casos seleccionados NO quedan con
    // una aprobación antigua aparentemente válida. Los ya completados sí cuentan.
    for (const id of plan.ejecutar) delete entries[id];
    write(filename, {version: 1, root, cases: entries});
    return {root, filename, entries, plan};
}
function approveCase(state, id, now = Date.now()) {
    if (!state.plan.ejecutar.includes(id)) throw new Error('No se acredita un caso no ejecutado.');
    state.entries[id] = {root: state.root, status: 'passed', approved_at: new Date(now).toISOString(),
        input_sha256: state.plan.inputs[id].sha256, inputs: state.plan.inputs[id].values,
        provenance: {type: 'case-run-success', new_runner_executed: true}};
    write(state.filename, {version: 1, root: state.root, cases: state.entries});
}
function assertCaseUnchanged(root, plan, id) {
    const expected = plan.inputs[id].values;
    const runner = fs.readFileSync(path.join(root, 'tests/Browser/portal-livewire.mjs'), 'utf8').replaceAll('\r\n', '\n');
    const parts = runnerParts(runner);
    const layout = JSON.parse(fs.readFileSync(path.join(root, 'tests/Browser/portal-casos.json'), 'utf8'));
    const tests = {'test:cuerpo': hash(parts.bodies[id]), 'test:preparacion': hash(parts.common),
        'test:contrato': hash(layout.cases[id]), 'test:fixture': hash(fs.readFileSync(path.join(root, 'tests/Browser/portal-fixture.php')))};
    const loaded = new Map();
    for (const [key, approved] of Object.entries(expected)) {
        if (['entorno', 'vendor'].includes(key)) continue;
        let actual = tests[key];
        if (!key.startsWith('test:')) {
            const [file, fragment] = key.split('#');
            if (!loaded.has(file)) {
                try { loaded.set(file, fs.readFileSync(path.join(root, file))); }
                catch (error) { if (error.code !== 'ENOENT') throw error; loaded.set(file, null); }
            }
            const bytes = loaded.get(file);
            actual = !bytes ? 'eliminado' : fragment
                ? hash(fragments(file, bytes.toString('utf8')).find(part => part.key === key)?.text ?? '') : hash(bytes);
        }
        if (actual !== approved) throw new Error(`Cambio durante el recorrido ${id}: ${key}. No se acredita el caso.`);
    }
}
module.exports = {ids, labels, hash, inputs, select, fragments, runnerParts, preservedBody, assertionStatements, migration, loadPlan, verify, prepareRun, approveCase, assertCaseUnchanged, directCases};
