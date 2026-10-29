// Puntos de avance de una misma seleccion PHP; nunca acreditan casos incompletos.
const fs=require('node:fs'),path=require('node:path'),{createHash}=require('node:crypto'),{spawnSync}=require('node:child_process');
const {parseMessage}=require('./progreso-pruebas-php.cjs');
const hash=value=>createHash('sha256').update(value).digest('hex');
function state(root,plan,original) {
    let sources=original?.sources;
    const evidence=original || JSON.parse(fs.readFileSync(path.join(root,'.local/validation/evidencia-agente.json'),'utf8'));
    if(!sources){const r=spawnSync(process.execPath,[path.join(root,'scripts/deployment/huellas-codigo.cjs'),root],{encoding:'utf8',windowsHide:true,maxBuffer:33554432});if(r.status!==0)throw Error('No se identificaron las fuentes PHP.');sources=Object.fromEntries(r.stdout.trimEnd().split('\n').map(line=>{const i=line.indexOf('  ');return[line.slice(i+2),line.slice(0,i)]}));}
    const test=file=>/^tests\//.test(file)||/^Modules\/[^/]+\/tests\//.test(file);
    const app=Object.fromEntries(Object.entries(sources).filter(([file])=>!test(file)&&!/^scripts\//.test(file)&&!file.endsWith('.md')));
    const support=Object.fromEntries(Object.entries(sources).filter(([file])=>/^tests\/(Pest\.php|bootstrap\.php|[^/]*TestCase\.php|Support\/|Concerns\/)/.test(file)));
    return {root:fs.realpathSync(root),baseline:plan.baseline_sha256,shared:hash(JSON.stringify([app,support,evidence.runtime,evidence.sector_environment||evidence.environment])),own:Object.fromEntries(plan.paquete.pruebas_php.map(file=>[file,sources[file]]))};
}
function results(text,tests) {
    const names=new Map(tests.map(file=>[('P\\'+file.slice(0,-4).replaceAll('/','\\')).toLowerCase(),file]));
    const flows=new Map(),files={};
    for(const line of text.split(/\r?\n/)) {
        // stdout puede dejar puntos de Pest antes del mensaje oficial, sin LF.
        const match=/##teamcity\[[^\r\n]*\]/.exec(line);if(!match)continue;
        const event=parseMessage(match[0]);if(!event)continue;
        const {type,attrs:a}=event,id=a.flowId||'principal';let flow=flows.get(id);if(!flow){flow={stack:[],file:null};flows.set(id,flow);}
        if(type==='testSuiteStarted'){flow.stack.push(a.name);const file=names.get(a.name.toLowerCase());if(file){flow.file=file;files[file]={completed:0,failed:false,finished:false};}}
        else if(type==='testSuiteFinished') {const file=names.get(a.name.toLowerCase());if(file&&flow.file===file){files[file].finished=true;flow.file=null;}const index=flow.stack.lastIndexOf(a.name);if(index>=0)flow.stack.splice(index);}
        else if(flow.file&&type==='testFinished')files[flow.file].completed++;
        else if(flow.file&&['testFailed','testIgnored'].includes(type))files[flow.file].failed=true;
    }
    return Object.fromEntries(Object.entries(files).filter(([,value])=>value.finished&&value.completed>0&&!value.failed));
}
function filename(root,plan){if(!/^[a-f0-9]{64}$/.test(plan.baseline_sha256||''))throw Error('Falta base del punto de avance PHP.');return path.join(root,'.local/validation/php-parciales',plan.baseline_sha256+'.json');}
function load(root,plan,current) {let value;try{value=JSON.parse(fs.readFileSync(filename(root,plan),'utf8'));}catch{return{version:1,shared:current.shared,root:current.root,entries:{}};}if(value.root!==current.root||value.shared!==current.shared)throw Error('Cambio una entrada compartida de PHP: delimitar los recibos afectados antes de continuar; no se repite toda la seleccion automaticamente.');return value;}
function write(root,plan,value){const file=filename(root,plan);fs.mkdirSync(path.dirname(file),{recursive:true});const temporary=file+'.tmp';fs.writeFileSync(temporary,JSON.stringify(value,null,2)+'\n');fs.renameSync(temporary,file);}
function pending(tests,cache,current){return tests.filter(file=>cache.entries[file]?.own!==current.own[file]);}
function record(root,plan,cache,before,after,log,provenance='ejecucion') {
    if(JSON.stringify(before)!==JSON.stringify(after))throw Error('Cambiaron entradas durante PHP; no se conserva una aprobacion.');
    const text=fs.readFileSync(log,'utf8'),passed=results(text,Object.keys(before.own)),time=fs.statSync(log).mtime.toISOString();
    for(const [file,value] of Object.entries(passed))cache.entries[file]={own:before.own[file],completed:value.completed,completed_at:time,log_sha256:hash(text),provenance};
    write(root,plan,cache);return Object.keys(passed);
}
module.exports={state,results,load,write,pending,record};
if(require.main===module){try{const [rootArg,originalPath]=process.argv.slice(2),root=fs.realpathSync(rootArg),original=JSON.parse(fs.readFileSync(originalPath,'utf8')),plan=JSON.parse(fs.readFileSync(path.join(root,'.local/validation/plan-impacto.json'),'utf8'));if(original.root!==root||original.baseline!==plan.baseline_sha256)throw Error('Origen PHP incompatible.');const before=state(root,plan,original),after=state(root,plan);const cache=load(root,plan,after);const passed=record(root,plan,cache,before,after,path.resolve(root,original.log),'log-original-completo; runner-anterior-no-repetido');console.log('PHP_ARCHIVOS_COMPLETOS_CONSERVADOS='+passed.length);console.log('PHP_PENDIENTES='+pending(plan.paquete.pruebas_php,cache,after).join(', '));}catch(error){console.error(error.message);process.exitCode=1;}}
