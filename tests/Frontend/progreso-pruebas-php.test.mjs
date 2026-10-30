import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, readFileSync, writeFileSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import observer from '../../scripts/deployment/progreso-pruebas-php.cjs';

test('dos rutas seleccionadas usan una configuracion exacta que Pest interpreta sin ampliar los casos', () => {
    const root=path.resolve(new URL('../..',import.meta.url).pathname.replace(/^\/([A-Za-z]:)/,'$1'));
    const directory=mkdtempSync(path.join(tmpdir(),'hub-pest-selection-'));
    try {
        const files=['tests/Unit/RolMembresiaTest.php','tests/Unit/TextoOficioTest.php'];
        const invocation=observer.pestArguments(2,directory,files,root);
        const config=invocation.args.find(arg=>arg.startsWith('--configuration=')).slice('--configuration='.length);
        const xml=readFileSync(config,'utf8');
        assert.equal((xml.match(/<file>/g)||[]).length,2);
        assert.ok(!invocation.args.some(arg=>files.includes(arg)));
        assert.match(xml,/<env name="APP_ENV" value="testing"/);
        assert.ok(xml.includes('bootstrap="'+path.join(root,'tests','bootstrap.php')+'"'));
        assert.ok(invocation.args.includes('--cache-directory='+directory));
        const result=spawnSync('php.exe',['vendor/bin/pest','--configuration',config,'--cache-directory='+directory,'--list-tests','--colors=never'],{cwd:root,encoding:'utf8',windowsHide:true,timeout:15000});
        assert.equal(result.status,0,result.stdout+result.stderr);
        assert.match(result.stdout,/tieneRol_detecta/);
        assert.match(result.stdout,/conserva_nombres_propios/);
        assert.doesNotMatch(result.stdout,/PortalCartografiaRealTest|AdminInteractiveGridTest/);
    } finally { assert.equal(path.dirname(directory),path.resolve(tmpdir()));assert.ok(path.basename(directory).startsWith('hub-pest-selection-'));rmSync(directory,{recursive:true,force:true}); }
});

const escape = value => String(value).replaceAll('|', '||').replaceAll("'", "|'")
    .replaceAll('\n', '|n').replaceAll('\r', '|r').replaceAll('[', '|[').replaceAll(']', '|]');
const message = (type, attrs = {}) => `##teamcity[${type}${Object.entries(attrs).map(([k, v]) => ` ${k}='${escape(v)}'`).join('')}]`;
const event = (type, attrs) => observer.parseMessage(message(type, attrs));

test('el argumento del logger sobrevive al filtro real de Pest y activa los archivos oficiales de ParaTest', () => {
    const vendor = relative => readFileSync(new URL(`../../vendor/${relative}`, import.meta.url), 'utf8');
    const pest = vendor('pestphp/pest/bin/pest');
    const intercepted = [...pest.matchAll(/if \(str_contains\(\$value, '([^']+)'\)\) \{\s*unset\(\$arguments\[\$key\]\);/g)].map(match => match[1]);
    assert.ok(intercepted.includes('--teamcity'), 'Revisar la adaptacion al actualizar el binario Pest.');
    const invocation = observer.pestArguments(2, path.resolve('registro separado'), ['tests/Feature/FirmaTest.php']);
    const forwarded = invocation.args.filter(arg => !intercepted.some(fragment => arg.includes(fragment)));
    assert.equal(['--teamcity'].filter(arg => !intercepted.some(fragment => arg.includes(fragment))).length, 0);
    assert.deepEqual(forwarded, invocation.args);
    assert.ok(forwarded.includes(`--log-teamcity=${invocation.protocolLogPath}`));
    assert.ok(forwarded.includes('--without-databases'));
    assert.ok(forwarded.includes('--cache-directory='+path.resolve('registro separado')));
    assert.ok(forwarded.includes('--exclude-group=bootstrap-inicial'));
    assert.ok(forwarded.includes('tests/Feature/FirmaTest.php'));
    assert.match(vendor('brianium/paratest/src/Options.php'), /needsTeamcity\s*=\s*\$configuration->outputIsTeamCity\(\)\s*\|\|\s*\$configuration->hasLogfileTeamcity\(\)/);
    assert.match(vendor('brianium/paratest/src/WrapperRunner/WrapperWorker.php'), /if \(\$options->needsTeamcity\)\s*\{\s*\$this->teamcityFile/);
    assert.match(vendor('pestphp/pest/src/Plugins/Parallel/Paratest/ResultPrinter.php'), /fwrite\(\$this->teamcityLogFileHandle, \$teamcityProgress\)/);
});

test('interpreta el protocolo Pest con comillas, corchetes, barras, saltos y Unicode', () => {
    const attrs = {name: "depósito [Adrián] | 'firma'", details: 'primera\nsegunda\rtercera', flowId: '71'};
    assert.deepEqual({...observer.parseMessage(message('testFailed', attrs)).attrs}, attrs);
    assert.equal(observer.parseMessage("##teamcity[testStarted name='Caf|0x00e9']").attrs.name, 'Café');
    assert.equal(observer.parseMessage('Una advertencia de PHP'), null);
    for (const bad of ["##teamcity[testStarted name='sin cierre", "##teamcity[testStarted name='a' name='b']",
        "##teamcity[testStarted name='|z']", "##teamcity[testStarted name='a'] basura"]) assert.throws(() => observer.parseMessage(bad));
});

test('separa dos procesos, conserva el caso lento visible y no confunde empezadas con finalizadas', () => {
    const p = new observer.Progress(0);
    for (const flowId of ['11', '22']) {
        p.accept(event('testSuiteStarted', {flowId, name: 'DepositosTest'}), 0);
        p.accept(event('testStarted', {flowId, name: 'firma de depósito'}), 1000);
    }
    assert.equal(p.completed.size, 0);
    assert.match(p.render(45000), /Proceso 11:.*firma de depósito \(44\.0 s\)/);
    assert.match(p.render(45000), /Proceso 22:/);
    assert.match(p.render(45000), /Sin nuevos resultados/);
    p.accept(event('testFinished', {flowId: '11', name: 'firma de depósito', duration: 44000}), 45000);
    assert.equal(p.completed.size, 1);
    assert.equal(p.validCompletion(), false);
    p.accept(event('testFinished', {flowId: '22', name: 'firma de depósito', duration: 45000}), 46000);
    assert.equal(p.completed.size, 2);
    assert.equal(p.validCompletion(), true);
    assert.equal(p.slowest[0].duration, 45000);
});

test('un worker reutilizado distingue clases y cuenta omitidas una sola vez aunque no haya testFinished', () => {
    const p = new observer.Progress(0);
    for (const name of ['AuthTest', 'CatalogoTest']) {
        p.accept(event('testSuiteStarted', {flowId: '1', name}));
        p.accept(event('testStarted', {flowId: '1', name: 'permite consultar'}));
        p.accept(event('testIgnored', {flowId: '1', name: 'permite consultar'}));
        p.accept(event('testFinished', {flowId: '1', name: 'permite consultar', duration: 0}));
        p.accept(event('testSuiteFinished', {flowId: '1', name}));
    }
    assert.equal(p.completed.size, 2);
    assert.equal(p.ignored.size, 2);
    assert.equal(p.validCompletion(), true);
});

async function fixtureRun(source, options = {}) {
    const root = mkdtempSync(path.join(tmpdir(), 'hub-progress-'));
    const script = path.join(root, 'emitter.cjs'), logPath = path.join(root, 'suite.log');
    let output = '';
    try {
        writeFileSync(script, source, 'utf8');
        const code = await observer.runSuite({program: process.execPath, args: [script], cwd: root, logPath,
            heartbeatMs: 25, ...options, progressDirectory: options.fileProgress ? root : undefined,
            protocolLogPath: options.protocolFile ? path.join(root, 'suite.teamcity') : undefined,
            write: text => { output += text; options.onWrite?.(text); }});
        return {code, output, log: readFileSync(logPath, 'utf8')};
    } finally {
        assert.equal(path.dirname(path.resolve(root)), path.resolve(tmpdir()));
        assert.ok(path.basename(root).startsWith('hub-progress-'));
        rmSync(root, {recursive: true, force: true});
    }
}

const started = [message('testSuiteStarted', {name: 'FirmaTest', flowId: '44'}),
    message('testStarted', {name: 'comprobación larga', flowId: '44'})].join('\n') + '\n';
const finished = [message('testFinished', {name: 'comprobación larga', duration: 200, flowId: '44'}),
    message('testSuiteFinished', {name: 'FirmaTest', flowId: '44'})].join('\n') + '\n';

test('muestra actividad antes de terminar y conserva eventos partidos y la salida original', async () => {
    const code = `const b=Buffer.from(${JSON.stringify(started)}); const i=b.indexOf(Buffer.from('ó'))+1;
        process.stdout.write(b.subarray(0,i)); setTimeout(()=>process.stdout.write(b.subarray(i)),30);
        setTimeout(()=>{process.stdout.write(${JSON.stringify(finished)});console.error('advertencia original');},220);`;
    const result = await fixtureRun(code);
    assert.equal(result.code, 0);
    assert.match(result.output, /Proceso 44:.*comprobación larga/);
    assert.match(result.output, /finalizadas 1/);
    assert.match(result.output, /advertencia original/);
    assert.match(result.output, /Suite PHP completada correctamente/);
    assert.ok(result.log.includes(started));
    assert.ok(result.log.includes(finished));
});

test('lee el caso activo del worker mientras ParaTest retiene stdout y no duplica los totales finales', async () => {
    let activeBeforeFlush = false;
    const code = `const fs=require('node:fs'); const f='worker_01_stdout_abc123_teamcity';
        fs.writeFileSync(f,${JSON.stringify(started)});
        setTimeout(()=>{fs.appendFileSync(f,${JSON.stringify(finished)}); console.log('SALIDA_FINAL'); process.stdout.write(${JSON.stringify(started + finished)});},800);`;
    let flushed = false;
    const result = await fixtureRun(code, {fileProgress: true, onWrite: text => {
        if (text.includes('SALIDA_FINAL')) flushed = true;
        if (!flushed && text.includes('Proceso 44:') && text.includes('comprobación larga')) activeBeforeFlush = true;
    }});
    assert.equal(activeBeforeFlush, true);
    assert.equal(result.code, 0);
    assert.match(result.output, /finalizadas 1/);
    assert.doesNotMatch(result.output, /finalizadas 2/);
});

test('Pest puede escribir solo puntos: el logger agregado acredita los resultados y el worker muestra actividad en vivo', async () => {
    let activeBeforeFinish = false, finishedOutput = false;
    const code = `const fs=require('node:fs'); const f='worker_01_stdout_abc123_teamcity';
        fs.writeFileSync(f,${JSON.stringify(started)}); process.stdout.write('........\\n');
        setTimeout(()=>{fs.appendFileSync(f,${JSON.stringify(finished)});fs.writeFileSync('suite.teamcity',fs.readFileSync(f));
        fs.unlinkSync(f); console.log('FIN_REAL');},800);`;
    const result = await fixtureRun(code, {fileProgress: true, protocolFile: true, onWrite: text => {
        if (text.includes('FIN_REAL')) finishedOutput = true;
        if (!finishedOutput && text.includes('Proceso 44:')) activeBeforeFinish = true;
    }});
    assert.equal(activeBeforeFinish, true);
    assert.equal(result.code, 0);
    assert.match(result.output, /finalizadas 1/);
    assert.doesNotMatch(result.output, /finalizadas 2|\.\.\.\./);
    assert.ok(result.log.includes('........'));
    assert.ok(result.log.includes(started + finished));
});

for (const [name, protocol] of [['ausente', null], ['truncado', started + finished + "##teamcity[testStarted name='sin cerrar"]]) {
    test(`no usa el worker provisional para aprobar si el registro definitivo esta ${name}`, async () => {
        const code = `const fs=require('node:fs'); fs.writeFileSync('worker_01_stdout_abc123_teamcity',${JSON.stringify(started + finished)});
            ${protocol === null ? '' : `fs.writeFileSync('suite.teamcity',${JSON.stringify(protocol)});`}
            setTimeout(()=>console.log('........'),300);`;
        const result = await fixtureRun(code, {fileProgress: true, protocolFile: true});
        assert.equal(result.code, 1);
        assert.doesNotMatch(result.output, /Suite PHP completada correctamente/);
    });
}

for (const [name, source, expected] of [
    ['salida no cero aunque lleguen resultados correctos', `process.stdout.write(${JSON.stringify(started + finished)});process.exitCode=7;`, 7],
    ['fallo notificado aunque el hijo salga con cero', `process.stdout.write(${JSON.stringify(started + message('testFailed', {flowId: '44', name: 'comprobación larga', message: 'Fallo real', details: 'detalle\nsegunda línea'}) + '\n' + finished)});`, 1],
    ['cero casos y cero de salida', "console.log('No hubo eventos de pruebas');", 1],
    ['evento truncado antes de cerrar', `process.stdout.write(${JSON.stringify(started + finished + "##teamcity[testFinished name='sin cierre")});`, 1],
]) test(`bloquea la aprobación: ${name}`, async () => {
    const result = await fixtureRun(source);
    assert.equal(result.code, expected);
    assert.doesNotMatch(result.output, /Suite PHP completada correctamente/);
    if (name.startsWith('fallo notificado')) assert.match(result.output, /Fallo real\ndetalle\nsegunda línea/);
});

test('cancelar detiene el hijo creado, conserva el registro y devuelve 130 sin acreditar aprobación', async () => {
    const controller = new AbortController();
    const result = await fixtureRun(`process.stdout.write(${JSON.stringify(started)});setInterval(()=>{},1000);`, {
        signal: controller.signal, onWrite: text => { if (text.includes('Proceso 44:')) controller.abort(); },
    });
    assert.equal(result.code, 130);
    assert.match(result.output, /Cancelacion solicitada/);
    assert.doesNotMatch(result.output, /Suite PHP completada correctamente/);
    assert.ok(result.log.includes(started));
});
