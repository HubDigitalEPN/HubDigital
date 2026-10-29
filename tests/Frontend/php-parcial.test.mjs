import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync,writeFileSync,readFileSync,rmSync} from 'node:fs';
import path from 'node:path';
import {tmpdir} from 'node:os';
import php from '../../scripts/deployment/php-parcial.cjs';
const a='tests/Feature/ATest.php',b='tests/Feature/BTest.php',c='tests/Feature/CTest.php';
const event=(type,name,extra='')=>`##teamcity[${type} name='${name}' flowId='1'${extra}]\n`;
const suite=file=>'P\\'+file.slice(0,-4).replaceAll('/','\\').replace(/^tests\\/,'Tests\\');
test('solo un archivo completo sin fallos ni omisiones se conserva, incluidos datasets y puntos previos de stdout',()=>{
    let log='....'+event('testSuiteStarted',suite(a))+event('testSuiteStarted','dataset')+event('testStarted','caso')+event('testFinished','caso')+event('testSuiteFinished','dataset')+event('testSuiteFinished',suite(a));
    log+=event('testSuiteStarted',suite(b))+event('testStarted','fallo')+event('testFailed','fallo')+event('testFinished','fallo')+event('testSuiteFinished',suite(b));
    log+=event('testSuiteStarted',suite(c))+event('testStarted','interrumpido');
    assert.deepEqual(Object.keys(php.results(log,[a,b,c])),[a]);
    assert.equal(php.results(log,[a,b,c])[a].completed,1);
    assert.deepEqual(php.results(event('testSuiteFinished',suite(a)),[a]),{});
});
test('un cambio propio repite solo ese archivo y un cambio compartido detiene sin ampliar la seleccion',()=>{
    const root=mkdtempSync(path.join(tmpdir(),'hub-php-checkpoint-')),plan={baseline_sha256:'a'.repeat(64)};
    const before={root,shared:'shared',own:{[a]:'old',[b]:'same'}},cache={root,shared:'shared',entries:{}};
    try {
        const log=path.join(root,'run.log');writeFileSync(log,event('testSuiteStarted',suite(a))+event('testFinished','ok')+event('testSuiteFinished',suite(a)));
        php.record(root,plan,cache,before,before,log);
        const stamp=cache.entries[a].completed_at;
        assert.deepEqual(php.pending([a,b],cache,before),[b]);
        assert.deepEqual(php.pending([a,b],cache,{...before,own:{...before.own,[a]:'changed'}}),[a,b]);
        assert.equal(php.load(root,plan,before).entries[a].completed_at,stamp);
        assert.throws(()=>php.load(root,plan,{...before,shared:'changed'}),/entrada compartida/);
        const receipt=readFileSync(path.join(root,'.local/validation/php-parciales',plan.baseline_sha256+'.json'),'utf8');
        assert.throws(()=>php.record(root,plan,cache,before,{...before,shared:'changed'},log),/Cambiaron entradas/);
        assert.equal(readFileSync(path.join(root,'.local/validation/php-parciales',plan.baseline_sha256+'.json'),'utf8'),receipt);
    } finally {assert.equal(path.dirname(root),path.resolve(tmpdir()));assert.ok(path.basename(root).startsWith('hub-php-checkpoint-'));rmSync(root,{recursive:true,force:true});}
});
