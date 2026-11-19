import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync,mkdirSync,writeFileSync,readFileSync,utimesSync,rmSync} from 'node:fs';
import path from 'node:path';
import {tmpdir} from 'node:os';
import php from '../../scripts/deployment/php-parcial.cjs';
const a='tests/Feature/ATest.php',b='tests/Feature/BTest.php',c='tests/Feature/CTest.php';
const event=(type,name,extra='')=>`##teamcity[${type} name='${name}' flowId='1'${extra}]\n`;
const suite=file=>'P\\'+file.slice(0,-4).replaceAll('/','\\').replace(/^tests\\/,'Tests\\');

test('delimitar exige la huella original y contratos propios completos y conserva fechas de archivos intactos',()=>{
    const root=mkdtempSync(path.join(tmpdir(),'hub-php-checkpoint-')),plan={baseline_sha256:'b'.repeat(64),paquete:{pruebas_php:[a,b]}};
    const original={sources:{'app/Changed.php':'old',[a]:'test-a',[b]:'test-b'},runtime:{php:'same'},sector_environment:'env'},current={...original,sources:{...original.sources,'app/Changed.php':'new'}};
    const before=php.state(root,plan,original),stamp='2026-11-17T07:30:00.000Z',cache={root,shared:before.shared,entries:{[a]:{own:'test-a',completed_at:stamp},[b]:{own:'test-b',completed_at:stamp}}};
    try{
        php.write(root,plan,cache);const file=path.join(root,'.local/validation/php-parciales',plan.baseline_sha256+'.json'),saved=readFileSync(file,'utf8');
        assert.throws(()=>php.delimit(root,plan,original,current,{}),/sin contrato/);assert.equal(readFileSync(file,'utf8'),saved);
        assert.throws(()=>php.delimit(root,plan,{...original,sources:{...original.sources,'app/Changed.php':'false'}},current,{}),/fuentes originales/);
        assert.throws(()=>php.delimit(root,plan,original,{...current,runtime:{php:'changed'}},{}),/runtime/);
        const report=php.delimit(root,plan,original,current,{'app/Changed.php':[a]});
        assert.deepEqual(report.removed,[a]);assert.deepEqual(report.preserved,[b]);
        const after=php.load(root,plan,php.state(root,plan,current));assert.equal(after.entries[b].completed_at,stamp);assert.equal(after.entries[a],undefined);assert.equal(after.delimitations[0].previous_shared,before.shared);
    }finally{assert.equal(path.dirname(root),path.resolve(tmpdir()));assert.ok(path.basename(root).startsWith('hub-php-checkpoint-'));rmSync(root,{recursive:true,force:true});}
});
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

test('la equivalencia de runtime admite solo resultados JSON de Pest y bloquea escrituras de bibliotecas',()=>{
    const root=mkdtempSync(path.join(tmpdir(),'hub-php-checkpoint-')),plan={baseline_sha256:'c'.repeat(64),paquete:{pruebas_php:[a]}},cutoff=new Date(Date.now()-60000);
    mkdirSync(path.join(root,'vendor/pestphp/pest/.temp'),{recursive:true});
    const library=path.join(root,'vendor/library.php'),pest=path.join(root,'vendor/pestphp/pest/.temp/test-results');writeFileSync(library,'<?php');utimesSync(library,new Date(cutoff-60000),new Date(cutoff-60000));writeFileSync(pest,'{}');
    const original={completed_at:cutoff.toISOString(),sources:{[a]:'own'},sector_environment:'same',runtime:{sha256:'old',files:2,roots:['vendor'],per_root:{vendor:'old'}}},current={...original,runtime:{...original.runtime,sha256:'new',per_root:{vendor:'new'}}},before=php.state(root,plan,original),cache={root,shared:before.shared,entries:{[a]:{own:'own',completed_at:cutoff.toISOString()}}};
    try{
        php.write(root,plan,cache);const report=php.delimit(root,plan,original,current,{});assert.deepEqual(report.preserved,[a]);assert.deepEqual(report.runtime_compatibility.modified,['vendor/pestphp/pest/.temp/test-results']);
        php.write(root,plan,cache);writeFileSync(library,'<?php /* changed */');assert.throws(()=>php.delimit(root,plan,original,current,{}),/bibliotecas/);
    }finally{assert.equal(path.dirname(root),path.resolve(tmpdir()));assert.ok(path.basename(root).startsWith('hub-php-checkpoint-'));rmSync(root,{recursive:true,force:true});}
});
