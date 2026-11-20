import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
const source=readFileSync(new URL('../../public/js/portal-editor.js',import.meta.url),'utf8');
function fixture(mapa){
    const calls=[],events={},layouts=[],window={hubDisposicionContenido:{aplicarGuardada:(...args)=>layouts.push(args)}};
    runInNewContext(source,{window,setTimeout,clearTimeout,URL});
    const state=window.portalVisualEditor({seleccionarTexto:k=>calls.push(['campo',k]),seleccionarLiteral:k=>calls.push(['literal',k])},mapa);
    state.$refs={portal:{style:{},contentDocument:{body:{dataset:{}},getElementById(){return null;},createTreeWalker(){return {nextNode(){return false;}};},querySelectorAll(){return [];},createElement(){return {};},head:{append(){}},addEventListener:(k,v)=>events[k]=v}},opciones:{scrollIntoView(){}}};
    state.iniciar();return {state,calls,events,layouts};
}
function element(texto,clave){return {textContent:texto,parentElement:null,style:{removeProperty(){}},scrollIntoView(){},closest(s){return s==='[data-portal-copy]'&&clave?{dataset:{portalCopy:clave}}:null;}};}
function click(events,el){events.dblclick({preventDefault(){},stopImmediatePropagation(){},target:{closest:s=>s==='[data-disposicion-handle]'||s==='[data-disposicion-handle]'||s==='[data-editorial-foto]'||s==='[data-editor-no-text]'?null:el}});}
test('el marcador selecciona el campo correcto aunque el texto esté dividido por enlaces',()=>{
    const {state,calls,events}=fixture({'licencia.contenido_propio':'Texto {licencia}'});
    click(events,element('Texto CC BY 4.0','licencia.contenido_propio'));
    assert.equal(state.claveLocal,'licencia.contenido_propio');
    assert.equal(state.textoLocal,'Texto {licencia}');
    assert.deepEqual(calls,[]);
});
test('dos campos con el mismo texto exigen elegir y no publican en una ubicación arbitraria',()=>{
    const {state,calls,events}=fixture({'navegacion.inicio':'Inicio','navegacion.pie_inicio':'Inicio'});
    click(events,element('Inicio'));
    assert.equal(calls.length,0);assert.equal(state.opciones.length,2);
    state.editar('navegacion.pie_inicio');assert.equal(state.claveLocal,'navegacion.pie_inicio');
    assert.deepEqual(calls,[]);
});
test('un literal editado conserva su original al volver a seleccionarlo',()=>{
    const {state,calls,events}=fixture({'literal:Leer más':'Conoce el perfil'});
    click(events,element('Conoce el perfil'));
    assert.equal(state.claveLocal,'literal:Leer más');
    assert.equal(state.originalLocal,'Leer más');
    assert.equal(state.textoLocal,'Conoce el perfil');
    assert.deepEqual(calls,[]);
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
    estado.seleccionar({preventDefault(){},stopImmediatePropagation(){},target:{closest:s=>s==='[data-wizard-copy]'?null:hijo}});
    assert.equal(estado.claveLocal,'tramite.titulo');
    assert.equal(estado.textoLocal,'Tipo de trámite');
    estado.editar('literal:Texto original');
    assert.equal(estado.originalLocal,'Texto original');
    assert.equal(estado.textoLocal,'Texto cambiado');
    assert.deepEqual(calls,[]);
});

test('el wizard navega localmente y distingue el doble clic o F2 que edita el nombre del paso',()=>{
    const cambios=[],selecciones=[],disposiciones=[],pendientes=new Map();let siguiente=0;
    const fragmentos={1:{paso:1},4:{paso:4}};
    const composiciones={1:{posiciones:{campo:12}},4:{posiciones:{campo:25}}};
    const window={hubDisposicionContenido:{aplicarGuardada:(...args)=>disposiciones.push(args)}};
    runInNewContext(source,{window,URL,setTimeout:fn=>{const id=++siguiente;pendientes.set(id,fn);return id;},clearTimeout:id=>pendientes.delete(id)});
    const estado=window.wizardVisualEditor({$set:(...args)=>cambios.push(args)},{'pasos.tramite':'Trámite'},0,composiciones,{}, {1:'pasos.tramite',4:'pasos.datos'});
    estado.$flux={modal:()=>({show:()=>selecciones.push(estado.claveLocal)})};
    estado.$nextTick=callback=>callback();
    estado.$refs={vistaWizard:{scrollTop:80,querySelector:()=>fragmentos[estado.pasoActivo],querySelectorAll:()=>[]}};
    estado.ajustarAltura=()=>{};
    const boton={dataset:{wizardPaso:'1',wizardCopy:'pasos.tramite'}};
    const botonDatos={dataset:{wizardPaso:'4',wizardCopy:'pasos.datos'}};
    const evento=(detail,paso=boton)=>({detail,previno:false,target:{closest:()=>paso},preventDefault(){this.previno=true;},stopImmediatePropagation(){}});

    estado.navegarPaso(evento(1));
    assert.equal(pendientes.size,1);
    estado.navegarPaso(evento(2));estado.editarPaso(evento(2));
    assert.equal(pendientes.size,0);
    assert.equal(estado.pasoActivo,0);
    assert.deepEqual(cambios,[]);
    assert.deepEqual(selecciones,['pasos.tramite']);

    estado.navegarPaso(evento(1));
    [...pendientes.values()][0]();
    assert.equal(estado.pasoActivo,1);
    assert.equal(estado.$refs.vistaWizard.scrollTop,0);
    assert.deepEqual(cambios,[['pasoEditor',1,false]]);
    estado.editarPaso(evento(0));
    assert.deepEqual(selecciones,['pasos.tramite','pasos.tramite']);

    const teclado=evento(0,botonDatos);estado.navegarPaso(teclado);
    assert.equal(teclado.previno,true);
    assert.equal(estado.pasoActivo,4);
    assert.deepEqual(cambios,[['pasoEditor',1,false],['pasoEditor',4,false]]);
    assert.deepEqual(disposiciones,[[fragmentos[1],'wizard:1',composiciones[1]],[fragmentos[4],'wizard:4',composiciones[4]]]);
});

test('la etiqueta vinculada del permiso se abre aunque otro campo tenga el mismo texto',()=>{
    const window={};runInNewContext(source,{window,setTimeout,clearTimeout,URL});
    const estado=window.wizardVisualEditor({}, {'solicitud.nombre_permiso':'Etiqueta','solicitud.proyecto':'Etiqueta'});
    estado.seleccionar({preventDefault(){},stopImmediatePropagation(){},target:{closest:()=>({dataset:{wizardCopy:'solicitud.nombre_permiso'}})}});
    assert.equal(estado.claveLocal,'solicitud.nombre_permiso');
    assert.equal('disposicion' in estado,false);
});

test('el wizard abre y permite escribir de inmediato y guarda solo al confirmar',async()=>{
    const window={}, solicitudes=[], modales=[];
    runInNewContext(source,{window,setTimeout,clearTimeout,URL});
    const clave='solicitud.nombre_permiso';
    const estado=window.wizardVisualEditor({publicarTextoVisual:(...args)=>{
        solicitudes.push(args);
        return Promise.resolve({guardado:true,texto:'Nuevo titular'});
    }},{[clave]:'Titular vigente'},0,{},{[clave]:'Titular original'});
    estado.$flux={modal:nombre=>({show:()=>modales.push(['abre',nombre]),close:()=>modales.push(['cierra',nombre])})};
    estado.$nextTick=()=>{};
    estado.editar(clave);
    assert.equal(estado.originalLocal,'Titular original');
    assert.equal(estado.textoLocal,'Titular vigente');
    assert.equal('seleccionOcupada' in estado,false);
    assert.deepEqual(solicitudes,[]);
    estado.textoLocal='Nuevo titular';
    await estado.publicarTexto();
    assert.deepEqual(solicitudes,[[clave,'Titular vigente','Nuevo titular']]);
    assert.equal(estado.mapa[clave],'Nuevo titular');
    assert.deepEqual(modales,[['abre','wizard-texto-inmediato'],['cierra','wizard-texto-inmediato']]);
});

test('el modal abre con el texto disponible y el literal de la vista sin consultar el servidor',()=>{
    const {state,calls,events}=fixture({'inicio.hero_titulo':'Título visible'});const modales=[];
    state.originales={'inicio.hero_titulo':'Título original'};
    state.$flux={modal:nombre=>({show:()=>modales.push(nombre)})};
    state.editar('inicio.hero_titulo');
    assert.equal(state.textoLocal,'Título visible');
    assert.equal(state.originalLocal,'Título original');
    click(events,element('Texto literal en la vista'));
    assert.equal(state.textoLocal,'Texto literal en la vista');
    assert.deepEqual(modales,['portal-texto-inmediato','portal-texto-inmediato']);
    assert.deepEqual(calls,[]);
    assert.equal('seleccionOcupada' in state,false);
});

test('publicar envia el borrador vigente una sola vez aunque se repita el clic',async()=>{
    const {state}=fixture({'inicio.hero_titulo':'Texto previo'});const solicitudes=[];let finish;
    state.editar('inicio.hero_titulo');
    state.textoLocal='Texto recién escrito';
    state.wire.publicarTextoVisual=(...args)=>{solicitudes.push(args);return new Promise(r=>{finish=r});};
    const first=state.publicarTexto();await state.publicarTexto();
    assert.deepEqual(solicitudes,[['inicio.hero_titulo','Texto previo','Texto recién escrito']]);
    assert.equal(state.publicando,true);
    finish({error:'Revisa el texto'});await first;
    assert.equal(solicitudes.length,1);
    assert.equal(state.publicando,false);
    assert.equal(state.errorSeleccion,'Revisa el texto');
    assert.equal(state.textoLocal,'Texto recién escrito');
    let cerrado=false;
    state.$flux={modal:()=>({close:()=>{cerrado=true;}})};
    state.wire.publicarTextoVisual=async()=>({guardado:true});
    await state.publicarTexto();
    assert.equal(cerrado,true);
    assert.equal(state.mensajePublicacion,'Transacción Efectuada');
});

test('un clic sobre un texto no abre el editor y el doble clic permite editarlo',()=>{
    const {state,calls,events}=fixture({'inicio.hero_titulo':'Título'}),el=element('Título','inicio.hero_titulo');
    events.click({detail:1,preventDefault(){},stopImmediatePropagation(){},target:{closest(){return null;}}});
    assert.equal(calls.length,0);assert.equal(state.disposicion,undefined);
    assert.equal(state.claveLocal,'');
    click(events,el);assert.equal(state.claveLocal,'inicio.hero_titulo');
    assert.equal(el.style.outline,undefined);
    assert.deepEqual(calls,[]);
});

test('la vista editorial aplica la composición guardada sin registrar controles de movimiento',()=>{
    const {state,events,layouts}=fixture({});
    assert.deepEqual(layouts,[[state.$refs.portal.contentDocument.body,'portal:inicio']]);
    assert.deepEqual(Object.keys(events).sort(),['click','dblclick','keydown','submit']);
});

test('la navegación editorial solo acepta páginas internas conocidas y cambia su ámbito',()=>{
    const {state}=fixture({}),updates=[],direcciones=[];
    state.paginas={'/depositos':'depositos'};state.wire.$set=(...v)=>updates.push(v);
    const window={location:{href:'http://localhost:8000/administracion/contenidos',origin:'http://localhost:8000'},history:{replaceState:(...args)=>direcciones.push(args.at(-1).href)}};
    runInNewContext(source,{window,URL,setTimeout,clearTimeout});
    const editor=window.portalVisualEditor(state.wire,{},'portal:inicio',state.paginas);
    editor.$refs=state.$refs;
    editor.navegar('https://example.test/depositos');editor.navegar('/logout');assert.equal(updates.length,0);
    editor.navegar('/depositos');assert.deepEqual(updates,[['grupo','depositos',false]]);
    assert.equal(editor.scope,'portal:depositos');
    assert.equal(editor.$refs.portal.src,'http://localhost:8000/depositos?_editor=1');
    assert.deepEqual(direcciones,['http://localhost:8000/administracion/contenidos?grupo=depositos']);
});
