// Observa los eventos oficiales TeamCity de Pest/ParaTest; no selecciona ni repite casos.
const fs = require('node:fs');
const path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const {randomUUID} = require('node:crypto');
const {StringDecoder} = require('node:string_decoder');

const plain = value => String(value ?? '').replace(/\x1b\[[0-9;]*[A-Za-z]/g, '').replace(/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/g, '');
const label = value => plain(value).replace(/\s+/g, ' ').slice(0, 190);
const seconds = ms => `${Math.max(0, ms / 1000).toFixed(1)} s`;

function parseMessage(line) {
    const source = plain(line).trim();
    if (!source.startsWith('##teamcity[')) return null;
    const head = /^##teamcity\[([a-zA-Z]+)\s*/.exec(source);
    if (!head) throw new Error('Cabecera de progreso incompleta.');
    const attrs = Object.create(null);
    let i = head[0].length;
    while (source[i] !== ']') {
        const attr = /^([a-zA-Z][a-zA-Z0-9_]*)\s*=\s*'/.exec(source.slice(i));
        if (!attr) throw new Error('Atributo de progreso incompleto.');
        i += attr[0].length;
        let value = '', closed = false;
        while (i < source.length) {
            const c = source[i++];
            if (c === "'") { closed = true; break; }
            if (c !== '|') { value += c; continue; }
            const escaped = source[i++];
            if (escaped === '0' && source[i] === 'x' && /^[0-9a-fA-F]{4}/.test(source.slice(i + 1))) {
                value += String.fromCharCode(parseInt(source.slice(i + 1, i + 5), 16)); i += 5;
            } else {
                const escapes = {n: '\n', r: '\r', '|': '|', "'": "'", '[': '[', ']': ']'};
                if (!Object.hasOwn(escapes, escaped)) throw new Error('Escape de progreso desconocido.');
                value += escapes[escaped];
            }
        }
        if (!closed || Object.hasOwn(attrs, attr[1])) throw new Error('Valor de progreso incompleto o duplicado.');
        attrs[attr[1]] = value;
        while (/\s/.test(source[i] ?? '') && i < source.length) i++;
    }
    if (i !== source.length - 1) throw new Error('Mensaje de progreso sin terminar.');
    return {type: head[1], attrs};
}

class Progress {
    constructor(now = Date.now()) {
        this.startedAt = now; this.lastResultAt = now;
        this.flows = new Map(); this.completed = new Set(); this.failed = new Set(); this.ignored = new Set();
        this.slowest = []; this.lastCompleted = ''; this.events = 0;
    }
    accept({type, attrs: a}, now = Date.now()) {
        this.events++;
        const id = a.flowId || 'principal';
        if (!this.flows.has(id)) this.flows.set(id, {suites: [], active: null, since: now});
        const flow = this.flows.get(id);
        const scope = () => flow.suites.map(s => s.name).join(' / ');
        const key = () => flow.active?.name === a.name ? flow.active.key : JSON.stringify([id, scope(), a.name]);
        const output = [];
        if (type === 'testSuiteStarted') {
            flow.suites.push({name: a.name, since: now}); flow.since = now;
        } else if (type === 'testSuiteFinished') {
            const index = flow.suites.map(s => s.name).lastIndexOf(a.name);
            if (index >= 0) flow.suites.splice(index);
            flow.since = now;
        } else if (type === 'testStarted') {
            flow.active = {name: a.name, key: JSON.stringify([id, scope(), a.name]), since: now};
        } else if (type === 'testFailed') {
            this.failed.add(key());
            output.push(`FALLO: ${label(scope())} > ${label(a.name)}\n${plain(a.message)}\n${plain(a.details)}`);
        } else if (type === 'testIgnored') {
            const k = key();
            this.ignored.add(k); this.completed.add(k); this.lastResultAt = now;
            if (flow.active?.key === k) flow.active = null;
        } else if (type === 'testFinished') {
            const k = key();
            if (!this.completed.has(k)) {
                this.completed.add(k);
                const duration = Number(a.duration);
                if (Number.isFinite(duration) && duration >= 0) {
                    this.slowest.push({name: `${scope()} > ${a.name}`, duration});
                    this.slowest.sort((x, y) => y.duration - x.duration); this.slowest.length = Math.min(5, this.slowest.length);
                }
            }
            this.lastCompleted = `${scope()} > ${a.name}`; this.lastResultAt = now;
            if (flow.active?.key === k) flow.active = null;
        } else if (type === 'testStdOut' || type === 'testStdErr') {
            if (a.out) output.push(plain(a.out));
        } else if (type === 'message' && ['ERROR', 'FAILURE'].includes(a.status)) {
            this.failed.add(JSON.stringify(['message', a.text])); output.push(plain(a.text));
        }
        return output;
    }
    render(now = Date.now()) {
        const lines = [`PHP | ${seconds(now - this.startedAt)} | finalizadas ${this.completed.size} | fallos ${this.failed.size} | omitidas ${this.ignored.size}`];
        let busy = 0;
        for (const [id, flow] of this.flows) {
            const active = flow.active;
            if (active) {
                lines.push(`  Proceso ${label(id)}: ${label(flow.suites.at(-1)?.name)} > ${label(active.name)} (${seconds(now - active.since)})`); busy++;
            } else if (flow.suites.length) {
                const suite = flow.suites.at(-1);
                lines.push(`  Proceso ${label(id)}: preparando/finalizando ${label(suite.name)} (${seconds(now - flow.since)})`); busy++;
            }
        }
        if (!busy) lines.push(this.events ? '  Esperando el siguiente archivo o el cierre del runner.' : '  Descubriendo y preparando los casos; todavia no hay resultados.');
        if (this.lastCompleted) lines.push(`  Ultima finalizada: ${label(this.lastCompleted)}`);
        if (now - this.lastResultAt >= 30000) lines.push(`  Sin nuevos resultados desde hace ${seconds(now - this.lastResultAt)}; el proceso sigue en curso.`);
        return lines.join('\n') + '\n';
    }
    validCompletion() { return this.completed.size > 0 && this.failed.size === 0 && [...this.flows.values()].every(f => !f.active); }
}

function stopOwnedChild(child) {
    if (!Number.isInteger(child.pid) || child.pid <= 0 || child.exitCode !== null || child.signalCode !== null) return;
    if (process.platform === 'win32') {
        // Solo el PID creado por este observador y sus descendientes, nunca /IM.
        const result = spawnSync('taskkill.exe', ['/PID', String(child.pid), '/T', '/F'], {windowsHide: true, timeout: 10000, stdio: 'ignore'});
        if (result.status !== 0) child.kill();
    } else {
        try { process.kill(-child.pid, 'SIGKILL'); } catch { child.kill(); }
    }
}

// El binario Pest 4 consume --teamcity antes de entrar a ParaTest. El logger
// --log-teamcity conserva el protocolo oficial tanto en serie como en paralelo.
function pestArguments(workers, progressDirectory, tests = [], root = process.cwd()) {
    const protocolLogPath = path.join(progressDirectory, 'suite.teamcity');
    let selection = tests;
    if (tests.length > 1) {
        // ParaTest admite una sola ruta posicional. Conservar el bootstrap y
        // entorno oficiales, sustituyendo exclusivamente la lista de archivos.
        const xml = value => String(value).replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;').replaceAll('>','&gt;');
        // Pest indexa cada archivo por su ruta nativa real; C:/ y C:\ no son
        // la misma clave de su repositorio aunque Windows abra ambas rutas.
        const absolute = value => xml(fs.realpathSync(path.resolve(root,value)));
        let config = fs.readFileSync(path.join(root,'phpunit.xml'),'utf8');
        if ((config.match(/<testsuites>[\s\S]*?<\/testsuites>/g) || []).length !== 1) throw Error('No se pudo acotar la configuracion PHP oficial.');
        config = config.replace(/<testsuites>[\s\S]*?<\/testsuites>/, '<testsuites><testsuite name="Cambio seleccionado">'+tests.map(file=>'<file>'+absolute(file)+'</file>').join('')+'</testsuite></testsuites>')
            .replace(/\b(bootstrap|xsi:noNamespaceSchemaLocation)="([^"]+)"/g, (_,key,value)=>key+'="'+absolute(value)+'"')
            .replace(/<directory([^>]*)>([^<]+)<\/directory>/g, (_,attrs,value)=>'<directory'+attrs+'>'+absolute(value)+'</directory>');
        const configPath=path.join(progressDirectory,'seleccion.xml');
        fs.writeFileSync(configPath,config,{flag:'wx',mode:0o600});
        selection=['--configuration='+configPath];
    }
    return {protocolLogPath, args: ['artisan', 'test', '--parallel', `--processes=${workers}`, '--without-databases',
        '--exclude-group=bootstrap-inicial', `--log-teamcity=${protocolLogPath}`, '--colors=never', `--tmp-dir=${progressDirectory}`, `--cache-directory=${progressDirectory}`, ...selection]};
}

async function runSuite({program, args, cwd, logPath, progressDirectory, protocolLogPath, write = text => process.stdout.write(text), heartbeatMs = 10000, signal}) {
    const log = fs.openSync(logPath, 'wx', 0o600);
    const progress = new Progress();
    const live = new Progress(), tails = new Map();
    let child, interrupted = false, protocolError = false, timer, poller;
    // ParaTest publica stdout al terminar el archivo. Su directorio exclusivo
    // permite observar el caso activo antes de ese momento sin tocar el runner.
    const readTail = (file, key, accept, preserve = false) => {
        const info = fs.lstatSync(file);
        if (!info.isFile() || info.isSymbolicLink()) throw new Error('Registro de progreso no regular.');
        let tail = tails.get(key);
        if (!tail) { tail = {offset: 0, pending: '', decoder: new StringDecoder('utf8')}; tails.set(key, tail); }
        if (info.size < tail.offset) throw new Error('El registro de progreso se trunco.');
        if (info.size === tail.offset) return;
        const descriptor = fs.openSync(file, 'r');
        try {
            const buffer = Buffer.alloc(Math.min(65536, info.size - tail.offset));
            let read;
            while ((read = fs.readSync(descriptor, buffer, 0, buffer.length, tail.offset)) > 0) {
                tail.offset += read;
                if (preserve) fs.writeSync(log, buffer.subarray(0, read));
                tail.pending += tail.decoder.write(buffer.subarray(0, read));
                let newline;
                while ((newline = tail.pending.indexOf('\n')) >= 0) {
                    const line = tail.pending.slice(0, newline); tail.pending = tail.pending.slice(newline + 1);
                    const event = parseMessage(line);
                    if (event) accept(event);
                    else if (preserve && line.trim()) throw new Error('Texto fuera del registro TeamCity definitivo.');
                }
                if (tail.pending.length > 4 * 1024 * 1024) throw new Error('Evento de progreso demasiado extenso.');
            }
        } finally { fs.closeSync(descriptor); }
    };
    const acceptResult = event => { for (const text of progress.accept(event)) write(text + '\n'); };
    const poll = () => {
        if (!progressDirectory) return;
        try {
            for (const name of fs.readdirSync(progressDirectory)) {
                if (!/^worker_\d+_stdout_[a-f0-9]+_teamcity$/.test(name)) continue;
                try { readTail(path.join(progressDirectory, name), name, event => live.accept(event)); }
                catch (error) { if (error.code !== 'ENOENT') throw error; }
            }
            if (protocolLogPath) {
                try { readTail(protocolLogPath, 'definitivo', acceptResult, true); }
                catch (error) { if (error.code !== 'ENOENT') throw error; }
            }
        } catch (error) {
            // ParaTest retira sus workers al terminar; el logger agregado conserva
            // la copia definitiva. Otros errores bloquean la acreditacion.
            if (error.code !== 'ENOENT') { protocolError = true; write(`Error observando workers: ${error.message}\n`); clearInterval(poller); }
        }
    };
    const render = () => { poll(); return (live.events ? live : progress).render(); };
    const buffers = {stdout: '', stderr: ''};
    const consume = line => {
        try {
            const event = parseMessage(line);
            if (event) {
                if (!protocolLogPath) acceptResult(event);
            } else if (line.trim() && !/^[.\s]+$/.test(plain(line))) write(plain(line) + '\n');
        } catch (error) {
            protocolError = true; write(`No se pudo interpretar un evento: ${error.message} Consulte el registro completo.\n`);
        }
    };
    const cancel = () => {
        if (interrupted) return;
        interrupted = true; write('\nCancelacion solicitada; deteniendo solo los procesos de esta suite...\n');
        try { fs.writeSync(log, '\n[Estado al cancelar]\n' + render()); }
        finally { if (child) stopOwnedChild(child); }
    };
    try {
        write(`Registro completo PHP: ${logPath}\nSe muestra avance cada ${heartbeatMs / 1000} s; Ctrl+C cancela la suite.\n`);
        write(progress.render());
        if (signal?.aborted) return 130;
        child = spawn(program, args, {cwd, windowsHide: true, detached: process.platform !== 'win32', stdio: ['ignore', 'pipe', 'pipe']});
        signal?.addEventListener('abort', cancel, {once: true});
        if (progressDirectory) poller = setInterval(poll, 250);
        for (const channel of ['stdout', 'stderr']) {
            child[channel].setEncoding('utf8');
            child[channel].on('data', chunk => {
                try { fs.writeSync(log, chunk); }
                catch (error) {
                    protocolError = true;
                    write(`No se pudo conservar el registro PHP: ${error.message}\n`);
                    stopOwnedChild(child);
                    return;
                }
                buffers[channel] += chunk;
                let index;
                while ((index = buffers[channel].indexOf('\n')) >= 0) {
                    consume(buffers[channel].slice(0, index)); buffers[channel] = buffers[channel].slice(index + 1);
                }
                if (buffers[channel].length > 4 * 1024 * 1024) {
                    protocolError = true; buffers[channel] = '';
                    write('Una linea excedio el limite del observador; consulte el registro completo.\n');
                }
            });
        }
        timer = setInterval(() => write(render()), heartbeatMs);
        const result = await new Promise(resolve => {
            child.once('error', error => { write(`No se pudo iniciar PHP: ${error.message}\n`); resolve(1); });
            child.once('close', code => resolve(interrupted ? 130 : (code ?? 1)));
        });
        clearInterval(timer); clearInterval(poller); poll();
        const definitiveTail = tails.get('definitivo');
        if (definitiveTail) {
            definitiveTail.pending += definitiveTail.decoder.end();
            if (definitiveTail.pending.trim()) {
                try { const event = parseMessage(definitiveTail.pending); if (!event) throw new Error('Texto fuera del protocolo.'); acceptResult(event); }
                catch (error) { protocolError = true; write(`Registro TeamCity incompleto: ${error.message}\n`); }
            }
        }
        for (const pending of Object.values(buffers)) if (pending) consume(pending);
        write(progress.render());
        for (const test of progress.slowest) write(`  Caso lento: ${seconds(test.duration)} | ${label(test.name)}\n`);
        const code = interrupted ? 130 : result || (protocolError || !progress.validCompletion() ? 1 : 0);
        write(code === 0 ? 'Suite PHP completada correctamente.\n' : `Suite PHP incompleta, cancelada o fallida (codigo ${code}). No se acredita aprobacion.\n`);
        write(`Registro completo PHP: ${logPath}\n`);
        return code;
    } finally {
        clearInterval(timer); clearInterval(poller); signal?.removeEventListener('abort', cancel);
        if (child) stopOwnedChild(child);
        fs.closeSync(log);
    }
}

async function main() {
    const [php, project, workersText, planPath] = process.argv.slice(2);
    const workers = Number(workersText);
    if (!php || !path.isAbsolute(php) || !project || ![2, 4].includes(workers)) throw new Error('Uso: progreso-pruebas-php.cjs PHP_NATIVO PROYECTO PROCESOS');
    const cwd = fs.realpathSync(project);
    const selected = planPath ? JSON.parse(fs.readFileSync(planPath, 'utf8')) : null;
    const tests = selected && !selected.integral ? selected.paquete.pruebas_php : [];
    if (!Array.isArray(tests) || tests.some(file => typeof file !== 'string' || !/^(tests|Modules)\/.+Test\.php$/u.test(file) || /[\\\x00-\x1f]/u.test(file) || file.split('/').some(part => part === '..' || part === '.' || part === '') || !fs.existsSync(path.join(cwd, file)))) throw new Error('Seleccion PHP invalida.');
    if (selected && !selected.integral && tests.length === 0) throw new Error('La seleccion PHP no puede estar vacia al iniciar Pest.');
    const checkpoints=selected&&!selected.integral?require('./php-parcial.cjs'):null;
    const before=checkpoints?.state(cwd,selected),cache=checkpoints?.load(cwd,selected,before);
    const testsToRun=checkpoints?checkpoints.pending(tests,cache,before):tests;
    if(checkpoints){console.log(`PHP: conservar ${tests.length-testsToRun.length} archivos completos; ejecutar ${testsToRun.length} pendientes.`);if(!testsToRun.length){process.exitCode=0;return;}for(const file of testsToRun)delete cache.entries[file];checkpoints.write(cwd,selected,cache);}
    const directory = path.join(cwd, '.local', 'deployment');
    fs.mkdirSync(directory, {recursive: true});
    const controller = new AbortController();
    const progressDirectory = fs.mkdtempSync(path.join(directory, 'paratest-'));
    const cancel = () => controller.abort();
    process.on('SIGINT', cancel); process.on('SIGTERM', cancel);
    try {
        const invocation = pestArguments(workers, progressDirectory, testsToRun, cwd);
        console.log(`Concurrencia PHP configurada: ${workers} procesos. Se muestra el caso activo de cada worker.`);
        const logPath=path.join(directory, `pruebas-php-${new Date().toISOString().replace(/[:.]/g, '-')}-${randomUUID().slice(0, 8)}.log`);
        process.exitCode = await runSuite({program: php, cwd,
            ...invocation,
            logPath, progressDirectory, signal: controller.signal});
        if(checkpoints){const after=checkpoints.state(cwd,selected);checkpoints.record(cwd,selected,cache,before,after,logPath);if(checkpoints.pending(tests,cache,after).length)process.exitCode=process.exitCode||1;}
    } finally {
        process.off('SIGINT', cancel); process.off('SIGTERM', cancel);
        // Retirar solo archivos regulares del directorio creado en esta llamada.
        if (path.dirname(progressDirectory) !== directory || !path.basename(progressDirectory).startsWith('paratest-') || fs.lstatSync(progressDirectory).isSymbolicLink()) throw new Error('Directorio temporal PHP inesperado.');
        for (const item of fs.readdirSync(progressDirectory, {withFileTypes: true})) {
            if (!item.isFile() || item.isSymbolicLink()) throw new Error('Contenido temporal PHP inesperado.');
            fs.unlinkSync(path.join(progressDirectory, item.name));
        }
        fs.rmdirSync(progressDirectory);
    }
}

module.exports = {parseMessage, Progress, runSuite, pestArguments};
if (require.main === module) main().catch(error => { console.error(error.message); process.exitCode = 1; });
