import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync,mkdirSync,writeFileSync,readFileSync,rmSync} from 'node:fs';
import path from 'node:path';
import {tmpdir} from 'node:os';
import ui from '../../scripts/deployment/contratos-ui-focales.cjs';
test('una aprobación UI exige todos los controles y huellas idénticas sin renovar otros recorridos', () => {
    const root=mkdtempSync(path.join(tmpdir(),'hub-ui-contract-'));
    try {
        const files=[...new Set([...Object.values(ui.definitions).flatMap(x=>x.sources),...Object.keys(ui.owner),'public/build/manifest.json'])];
        for(const file of files){const target=path.join(root,file);mkdirSync(path.dirname(target),{recursive:true});writeFileSync(target,'fixture '+file);}
        const before=ui.inputs(root,'laboratorio');assert.equal(ui.status(root,'laboratorio'),false);
        assert.throws(()=>ui.approve(root,'laboratorio',{navigation:true},before),/incompleto/);
        const checks=Object.fromEntries(ui.definitions.laboratorio.checks.map(check=>[check,true]));
        ui.approve(root,'laboratorio',checks,before);assert.equal(ui.status(root,'laboratorio'),true);
        const receipt=path.join(root,'.local/validation/ui-focal/laboratorio.json'),original=readFileSync(receipt,'utf8');
        writeFileSync(path.join(root,'resources/js/portal-map-model.js'),'cambio ajeno');assert.equal(ui.status(root,'laboratorio'),true);assert.equal(readFileSync(receipt,'utf8'),original);
        writeFileSync(path.join(root,'public/css/pagina-laboratorio.css'),'modificado');assert.equal(ui.status(root,'laboratorio'),false);
        assert.throws(()=>ui.approve(root,'laboratorio',checks,before),/Cambió/);
    } finally {assert.equal(path.dirname(root),path.resolve(tmpdir()));assert.ok(path.basename(root).startsWith('hub-ui-contract-'));rmSync(root,{recursive:true,force:true});}
});


test('los contratos de depósitos y Pasante seleccionan exclusivamente sus pantallas propias', async () => {
    const {default: impact}=await import('../../scripts/deployment/impacto-validacion.cjs');
    const ids=['depositos_documento','depositos_firma','depositos_ingreso','depositos_contenido','captura_dwc','depositos_origen','depositos_rechazo','depositos_reintento','depositos_cerrado'];
    const shared=['depositos_ingreso','depositos_rechazo','depositos_reintento','depositos_cerrado'].sort();
    for(const id of ids) {
        assert.deepEqual(impact.focalScope(ui.definitions[id].sources).sort(),shared.includes(id) ? shared : id==='depositos_origen'?['depositos_origen','editor_wizard']:[id]);
        assert.ok(ui.definitions[id].checks.length>=4);
    }
    assert.deepEqual(impact.focalScope(['tests/Browser/contratos-ui-focales.mjs']),[]);
});

test('los contratos de alta y taxones no seleccionan recorridos ajenos del catálogo', async () => {
    const {default: impact}=await import('../../scripts/deployment/impacto-validacion.cjs');
    for(const id of ['catalogo_alta','catalogo_taxones']) {
        assert.deepEqual(impact.focalScope(ui.definitions[id].sources),id==='catalogo_alta'?[id,'catalogo_borrado','grillas_continuas']:[id,'grillas_continuas']);
        assert.ok(ui.definitions[id].checks.includes('responsive'));
    }
    assert.deepEqual(impact.focalScope(['Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/Importers/LectorActualizacionCatalogo.php']),[]);
    assert.ok(ui.contracts['Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/Importers/LectorActualizacionCatalogo.php'].includes('tests/Unit/LectorCatalogoNamespacesTest.php'));
});

test('la pantalla única de instituciones tiene cobertura propia incluso de las pantallas retiradas', async () => {
    const {default: impact}=await import('../../scripts/deployment/impacto-validacion.cjs');
    assert.deepEqual(impact.focalScope(ui.definitions.instituciones_unificadas.sources),['instituciones_unificadas','grillas_continuas']);
    for(const file of ['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EntidadDepositanteIndex.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/entidades/index.blade.php']) {
        assert.deepEqual(ui.contracts[file],['tests/Feature/InstitucionesUnificadasTest.php']);
        assert.deepEqual(impact.focalScope([file]),[]);
    }
});


test('la reorganización selecciona solo sus espacios canónicos y las pantallas retiradas no requieren DOM inexistente', async () => {
    const {default: impact}=await import('../../scripts/deployment/impacto-validacion.cjs');
    for(const id of ['menu_curaduria','publicacion_unificada','contenidos_unificados','catalogo_importacion']) {
        const propios={menu_curaduria:[id],publicacion_unificada:[id,'grillas_continuas'],contenidos_unificados:[id,'editor_wizard','textos_contexto'],catalogo_importacion:[id]};
        assert.deepEqual(impact.focalScope(ui.definitions[id].sources),propios[id]);
        assert.ok(ui.definitions[id].checks.includes('responsive'));
    }
    for(const source of ['app/Livewire/Administracion/CentroAdministracion.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/revision/centro.blade.php']) {
        assert.deepEqual(impact.focalScope([source]),[]);
        assert.deepEqual(ui.contracts[source],['tests/Feature/NavegacionCuraduriaUnificadaTest.php']);
    }
});

test('wizard contextual y ventana continua acreditan contratos propios sin ejecutar recorridos históricos', async()=>{
    const {default:impact}=await import('../../scripts/deployment/impacto-validacion.cjs');
    assert.deepEqual(impact.focalScope(['app/Support/VistaPreviaWizard.php']),['editor_wizard']);
    assert.deepEqual(impact.focalScope(['app/Livewire/Concerns/WithContinuousGrid.php']),['grillas_continuas']);
    assert.deepEqual(impact.focalScope(['public/js/formularios-apex.js']),['textos_contexto']);
    for(const id of ['editor_wizard','textos_contexto','grillas_continuas'])assert.ok(ui.definitions[id].checks.length>=6);
});

test('conservar UI exige runner original fuentes y cuerpo identicos y no modifica la fecha ni el recibo',()=>{
    const root=mkdtempSync(path.join(tmpdir(),'hub-ui-contract-')),id='catalogo_alta';
    try{
        const files=[...new Set([...Object.values(ui.definitions).flatMap(x=>x.sources),...Object.keys(ui.owner),'public/build/manifest.json'])];
        for(const file of files){const target=path.join(root,file);mkdirSync(path.dirname(target),{recursive:true});writeFileSync(target,'fixture '+file);}
        const original="export async function comprobarUIFocal(caso) {\n    const q=()=>null;\n    const comprobarAyudas=()=>true;\n    if(caso==='catalogo_alta') {\n        checks.saved=true;\n    } else if(caso==='chat') {\n        checks.focus=true;\n    }\n}\n",runner=path.join(root,'tests/Browser/contratos-ui-focales.mjs');
        writeFileSync(runner,original);ui.approve(root,id,Object.fromEntries(ui.definitions[id].checks.map(c=>[c,true])),ui.inputs(root,id));
        const receipt=path.join(root,'.local/validation/ui-focal/'+id+'.json'),saved=readFileSync(receipt,'utf8');
        const changed=original.replace('checks.focus=true','checks.focus=false').replace('    const comprobarAyudas=', '    const comprobarOrden=async()=>false;\n    const comprobarAyudas=');writeFileSync(runner,changed);
        assert.equal(ui.status(root,id),false);assert.throws(()=>ui.preserveCompatible(root,id,original+' '),/runner original/);
        ui.preserveCompatible(root,id,original);assert.equal(ui.status(root,id),true);assert.equal(readFileSync(receipt,'utf8'),saved);
        writeFileSync(runner,changed.replace('const q=()=>null','const q=()=>false'));assert.equal(ui.status(root,id),false);assert.throws(()=>ui.preserveCompatible(root,id,original),/cuerpo|ayuda/);
        writeFileSync(runner,changed.replace('checks.saved=true','checks.saved=false'));assert.throws(()=>ui.preserveCompatible(root,id,original),/cuerpo/);
        writeFileSync(runner,changed);writeFileSync(path.join(root,ui.definitions[id].sources[0]),'fuente cambiada');assert.equal(ui.status(root,id),false);assert.throws(()=>ui.preserveCompatible(root,id,original),/fuente propia/);
    }finally{assert.equal(path.dirname(root),path.resolve(tmpdir()));assert.ok(path.basename(root).startsWith('hub-ui-contract-'));rmSync(root,{recursive:true,force:true});}
});
