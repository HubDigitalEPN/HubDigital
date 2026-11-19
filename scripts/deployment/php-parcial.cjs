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
/** Delimita un cambio compartido con fuentes originales cuya huella coincide
 * exactamente con la del runner. No inventa ni renueva aprobaciones. */
function delimit(root,plan,original,current,contracts) {
    const file=filename(root,plan),cache=JSON.parse(fs.readFileSync(file,'utf8'));
    const before=state(root,plan,original),after=state(root,plan,current);
    if(cache.root!==before.root||cache.shared!==before.shared)throw Error('Las fuentes originales no corresponden al punto de avance PHP.');
    if((original.sector_environment||original.environment)!==(current.sector_environment||current.environment))throw Error('Cambio runtime o entorno; no se delimita automaticamente.');
    let runtimeCompatibility=null;
    if(JSON.stringify(original.runtime)!==JSON.stringify(current.runtime)){
        const old=original.runtime,now=current.runtime,roots=Object.keys(now.per_root||{}),cutoff=Date.parse(original.completed_at);
        const changedRoots=roots.filter(r=>old.per_root?.[r]!==now.per_root[r]);
        if(!Number.isFinite(cutoff)||old.files!==now.files||JSON.stringify(old.roots)!==JSON.stringify(now.roots)||JSON.stringify(changedRoots)!==JSON.stringify(['vendor']))throw Error('Cambio runtime; falta delimitar sus bibliotecas o builds.');
        const modified=[];
        function walk(relative){for(const entry of fs.readdirSync(path.join(root,relative),{withFileTypes:true})){const file=relative+'/'+entry.name;if(entry.isSymbolicLink())throw Error('Enlace inesperado en vendor.');if(entry.isDirectory())walk(file);else if(entry.isFile()&&fs.statSync(path.join(root,file)).mtimeMs>cutoff)modified.push(file);else if(!entry.isFile())throw Error('Runtime no regular.');}}
        walk('vendor');
        const pest='vendor/pestphp/pest/.temp/test-results';
        if(JSON.stringify(modified)!==JSON.stringify([pest]))throw Error('Cambio runtime de bibliotecas: '+modified.join(', '));
        JSON.parse(fs.readFileSync(path.join(root,pest),'utf8'));
        runtimeCompatibility={reason:'Unico cambio de vendor: resultados JSON generados por Pest; sin escritura de bibliotecas ni cambio de cantidad de archivos, builds o entorno.',since:original.completed_at,modified};
    }
    const relevant=f=>!/^tests\//.test(f)&&!/^Modules\/[^/]+\/tests\//.test(f)&&!/^scripts\//.test(f)&&!f.endsWith('.md');
    const changed=[...new Set([...Object.keys(original.sources),...Object.keys(current.sources)])].filter(f=>relevant(f)&&original.sources[f]!==current.sources[f]);
    const support=/^tests\/(Pest\.php|bootstrap\.php|[^/]*TestCase\.php|Support\/|Concerns\/)/;
    if([...new Set([...Object.keys(original.sources),...Object.keys(current.sources)])].some(f=>support.test(f)&&original.sources[f]!==current.sources[f]))throw Error('Cambio soporte compartido de pruebas; falta delimitar manualmente su contrato.');
    const unknown=changed.filter(f=>!Object.hasOwn(contracts,f));if(unknown.length)throw Error('Fuentes sin contrato propio para delimitar PHP: '+unknown.join(', '));
    const affected=new Set(changed.flatMap(f=>contracts[f]).filter(f=>/Test\.php$/.test(f)));
    const removed=[];for(const file of Object.keys(cache.entries))if(affected.has(file)||(after.own[file]!==undefined&&cache.entries[file].own!==after.own[file])){removed.push(file);delete cache.entries[file];}
    const preserved=Object.keys(cache.entries),report={at:new Date().toISOString(),previous_shared:cache.shared,shared:after.shared,changed,affected:[...affected],preserved,removed,runtime_compatibility:runtimeCompatibility};
    cache.shared=after.shared;(cache.delimitations||=[]).push(report);write(root,plan,cache);
    return report;
}
module.exports.delimit=delimit;
if(require.main===module&&process.argv[2]==='delimitar'){
    try{const [,rootArg,originalPath]=process.argv.slice(2),root=fs.realpathSync(rootArg),plan=JSON.parse(fs.readFileSync(path.join(root,'.local/validation/plan-impacto.json'),'utf8')),original=JSON.parse(fs.readFileSync(originalPath,'utf8')),evidence=JSON.parse(fs.readFileSync(path.join(root,'.local/validation/evidencia-agente.json'),'utf8'));
        const listing=spawnSync(process.execPath,[path.join(root,'scripts/deployment/huellas-codigo.cjs'),root],{encoding:'utf8',windowsHide:true,maxBuffer:33554432});if(listing.status!==0)throw Error('No se identificaron las fuentes actuales.');
        const sources=Object.fromEntries(listing.stdout.trimEnd().split('\n').map(line=>{const i=line.indexOf('  ');return[line.slice(i+2),line.slice(0,i)];}));
        const report=delimit(root,plan,original,{...evidence,sources},require('./contratos-ui-focales.cjs').contracts);console.log(JSON.stringify(report,null,2));
    }catch(error){console.error(error.message);process.exitCode=1;}
} else
if(require.main===module){try{const [rootArg,originalPath]=process.argv.slice(2),root=fs.realpathSync(rootArg),original=JSON.parse(fs.readFileSync(originalPath,'utf8')),plan=JSON.parse(fs.readFileSync(path.join(root,'.local/validation/plan-impacto.json'),'utf8'));if(original.root!==root||original.baseline!==plan.baseline_sha256)throw Error('Origen PHP incompatible.');const before=state(root,plan,original),after=state(root,plan);const cache=load(root,plan,after);const passed=record(root,plan,cache,before,after,path.resolve(root,original.log),'log-original-completo; runner-anterior-no-repetido');console.log('PHP_ARCHIVOS_COMPLETOS_CONSERVADOS='+passed.length);console.log('PHP_PENDIENTES='+pending(plan.paquete.pruebas_php,cache,after).join(', '));}catch(error){console.error(error.message);process.exitCode=1;}}
