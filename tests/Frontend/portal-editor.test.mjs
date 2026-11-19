import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
const source=readFileSync(new URL('../../public/js/portal-editor.js',import.meta.url),'utf8');
function fixture(mapa){
    const calls=[],events={},window={editorDisposicionContenido:()=>({activa:true,conectar(){}})};
    runInNewContext(source,{window,setTimeout,clearTimeout,URL});
    const state=window.portalVisualEditor({seleccionarTexto:k=>calls.push(['campo',k]),seleccionarLiteral:k=>calls.push(['literal',k])},mapa);
    state.$refs={portal:{style:{},contentDocument:{body:{dataset:{}},getElementById(){return null;},createTreeWalker(){return {nextNode(){return false;}};},querySelectorAll(){return [];},createElement(){return {};},head:{append(){}},addEventListener:(k,v)=>events[k]=v}},opciones:{scrollIntoView(){}}};
    state.iniciar();return {state,calls,events};
}
function element(texto,clave){return {textContent:texto,parentElement:null,style:{removeProperty(){}},scrollIntoView(){},closest(s){return s==='[data-portal-copy]'&&clave?{dataset:{portalCopy:clave}}:null;}};}
function click(events,el){events.dblclick({preventDefault(){},stopImmediatePropagation(){},target:{closest:s=>s==='[data-disposicion-handle]'||s==='[data-disposicion-handle]'||s==='[data-editorial-foto]'||s==='[data-editor-no-text]'?null:el}});}
test('el marcador selecciona el campo correcto aunque el texto esté dividido por enlaces',()=>{
    const {calls,events}=fixture({'licencia.contenido_propio':'Texto {licencia}'});
    click(events,element('Texto CC BY 4.0','licencia.contenido_propio'));
    assert.deepEqual(calls,[['campo','licencia.contenido_propio']]);
});
test('dos campos con el mismo texto exigen elegir y no publican en una ubicación arbitraria',()=>{
    const {state,calls,events}=fixture({'navegacion.inicio':'Inicio','navegacion.pie_inicio':'Inicio'});
    click(events,element('Inicio'));
    assert.equal(calls.length,0);assert.equal(state.opciones.length,2);
    state.editar('navegacion.pie_inicio');assert.deepEqual(calls,[['campo','navegacion.pie_inicio']]);
});
test('un literal editado conserva su original al volver a seleccionarlo',()=>{
    const {calls,events}=fixture({'literal:Leer más':'Conoce el perfil'});
    click(events,element('Conoce el perfil'));
    assert.deepEqual(calls,[['literal','Leer más']]);
});
test('en la vista editorial un envío por teclado no llega al formulario de la página real',()=>{
    const {events}=fixture({});let cancelado=false,detenido=false;
    events.submit({preventDefault(){cancelado=true;},stopImmediatePropagation(){detenido=true;}});
    assert.equal(cancelado,true);assert.equal(detenido,true);
});

test('el wizard busca la etiqueta completa y conserva el original de un texto compartido editado',()=>{
    const calls=[],window={editorDisposicionContenido:()=>({activa:true,conectar(){}})};runInNewContext(source,{window,setTimeout,clearTimeout,URL});
    const estado=window.wizardVisualEditor({seleccionarTexto:k=>calls.push(['campo',k]),seleccionarLiteral:k=>calls.push(['literal',k])},{'tramite.titulo':'Tipo de trámite','literal:Texto original':'Texto cambiado'});
    const padre=element('Tipo de trámite'),hijo=element('Tipo');hijo.parentElement=padre;
    estado.seleccionar({preventDefault(){},stopImmediatePropagation(){},target:{closest:s=>s==='[data-disposicion-handle]'?null:hijo}});
    assert.deepEqual(calls,[['campo','tramite.titulo']]);
    estado.editar('literal:Texto original');assert.deepEqual(calls.at(-1),['literal','Texto original']);
});

test('el modal abre antes de esperar al servidor y no permite publicar una selección pendiente',async()=>{
    const {state}=fixture({'inicio.hero_titulo':'Título'});let resolve,opened=false,saves=0;
    state.wire.seleccionarTexto=()=>new Promise(r=>{resolve=r});state.wire.guardarTexto=()=>saves++;
    state.$flux={modal:()=>({show:()=>{opened=true}})};
    state.editar('inicio.hero_titulo');assert.equal(opened,true);assert.equal(state.seleccionOcupada,true);
    await state.publicarTexto();assert.equal(saves,0);
    resolve({original:'Título original',texto:'Guardado'});await new Promise(r=>setTimeout(r,0));
    assert.equal(state.seleccionOcupada,false);assert.equal(state.textoLocal,'Guardado');
});

test('publicar envia el borrador vigente una sola vez aunque se repita el clic',async()=>{
    const {state}=fixture({});const values=[];let finish,saves=0;
    state.textoLocal='Texto recién escrito';state.wire.$set=(...args)=>values.push(args);
    state.wire.guardarTexto=()=>{saves++;return new Promise(r=>{finish=r});};
    const first=state.publicarTexto();await state.publicarTexto();assert.equal(saves,1);
    assert.deepEqual(values,[['textoActual','Texto recién escrito',false]]);assert.equal(state.publicando,true);
    finish();await first;assert.equal(state.publicando,false);
});

test('un clic sobre un texto no abre el editor y el doble clic funciona con arrastre activo',()=>{
    const {state,calls,events}=fixture({'inicio.hero_titulo':'Título'}),el=element('Título','inicio.hero_titulo');
    events.click({detail:1,preventDefault(){},stopImmediatePropagation(){},target:{closest(){return null;}}});
    assert.equal(calls.length,0);assert.equal(state.disposicion.activa,true);
    click(events,el);assert.deepEqual(calls,[['campo','inicio.hero_titulo']]);
});

test('el texto no se publica si las posiciones pendientes tienen un conflicto',async()=>{
    const {state}=fixture({});let saves=0;
    state.disposicion.pendiente=true;state.disposicion.guardar=async()=>false;
    state.wire.guardarTexto=()=>saves++;
    await state.publicarTexto();assert.equal(saves,0);assert.equal(state.publicando,false);
});

test('la navegación editorial solo acepta páginas internas conocidas y cambia su ámbito',()=>{
    const {state}=fixture({}),updates=[];
    state.paginas={'/depositos':'depositos'};state.wire.$set=(...v)=>updates.push(v);
    const window={location:{href:'http://localhost:8000/administracion/contenidos',origin:'http://localhost:8000'},editorDisposicionContenido:()=>({})};
    runInNewContext(source,{window,URL,setTimeout,clearTimeout});
    const editor=window.portalVisualEditor(state.wire,{},'portal:inicio','','',state.paginas);
    editor.navegar('https://example.test/depositos');editor.navegar('/logout');assert.equal(updates.length,0);
    editor.navegar('/depositos');assert.deepEqual(updates,[['grupo','depositos']]);
});
