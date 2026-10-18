import test from 'node:test';
import assert from 'node:assert/strict';
import {crearExploradorTaxonomico, distribuirBosque, escalaBosque, panelTaxonomico, filosPublicos, posicionMenuTaxonomico} from '../../resources/js/portal-taxonomy-explorer-model.js';

const nodo = (clave, padre = null, nombre = clave) => ({clave, id: clave, padre, nombre, etiqueta: padre ? 'Especie' : 'Filo', total: 4, tieneHijos: false, hoja: false});
const diferido = () => { let resolver; const promesa = new Promise(resolve => {resolver = resolve;}); return {promesa, resolver}; };
globalThis.window ??= {dispatchEvent() {}};
const resultado = (seleccionado = '', nodos = []) => ({seleccionado, nodos, total: 4, expandidos: [], hayMas: false});

test('el menú queda junto a la esfera, cambia de lado y respeta los límites del canvas', () => {
    const limite = {left: 500, top: 200, right: 900, bottom: 600}, menu = {width: 180, height: 112};
    assert.deepEqual(posicionMenuTaxonomico({left: 650, right: 700, top: 340, bottom: 390}, menu, limite), {x: 708, y: 309});
    assert.deepEqual(posicionMenuTaxonomico({left: 850, right: 890, top: 340, bottom: 390}, menu, limite), {x: 662, y: 309});
    assert.deepEqual(posicionMenuTaxonomico({left: 600, right: 650, top: 560, bottom: 590}, menu, limite), {x: 658, y: 480});
    assert.deepEqual(posicionMenuTaxonomico({left: 0, right: 0, top: 0, bottom: 0}, menu,
        {left: 0, top: 0, right: 320, bottom: 240}), {x: 8, y: 8});
});

test('la respuesta asíncrona del octree abre el menú sin currentTarget y respeta el origen del modal', () => {
    const anterior = globalThis.requestAnimationFrame, cuadros = [];
    globalThis.requestAnimationFrame = tarea => cuadros.push(tarea);
    try {
        const {ui} = componente(); let focos = 0;
        ui.$refs.dialogo.getBoundingClientRect = () => ({left: 100, top: 80, right: 1100, bottom: 800});
        ui.$refs.dialogo.clientLeft = 1; ui.$refs.dialogo.clientTop = 1;
        const menu = {style: {}, getBoundingClientRect: () => ({width: 180, height: 112}), querySelector: () => ({focus() {focos++;}})};
        ui.$refs.menu = menu;
        const ancla = {left: 734, right: 766, top: 380, bottom: 420, limite: {left: 500, top: 200, right: 900, bottom: 600}};
        ui.abrirMenu(nodo('esfera'), {type: 'contextmenu', currentTarget: null, clientX: 750, clientY: 400}, ancla);
        assert.deepEqual(menu.style, {}, 'Espera a que x-show revele el menú antes de medirlo.');
        cuadros.shift()();
        assert.equal(menu.style.left, '445px'); assert.equal(menu.style.top, '263px'); assert.equal(focos, 1);
        assert.equal(ui.menuNodo.id, 'esfera');
        ui.abrirMenu(nodo('retirada'), {type: 'contextmenu', currentTarget: null}, ancla); ui.cerrarMenu();
        cuadros.shift()();
        assert.equal(ui.menuNodo, null); assert.equal(focos, 1, 'Cerrar el menú cancela el enfoque pendiente.');
    } finally {
        if (anterior === undefined) delete globalThis.requestAnimationFrame;
        else globalThis.requestAnimationFrame = anterior;
    }
});

test('el menú se sitúa dentro de la escena en pantalla completa y regresa al diálogo al cerrarlo',()=>{
    const anteriores={document:globalThis.document,requestAnimationFrame:globalThis.requestAnimationFrame},cuadros=[];
    globalThis.requestAnimationFrame=fn=>cuadros.push(fn);
    try{
        const {ui}=componente(),dialogo=ui.$refs.dialogo;
        dialogo.append=el=>{el.parentElement=dialogo;};
        const escena={clientLeft:0,clientTop:0,getBoundingClientRect:()=>({left:0,top:0,right:1200,bottom:800}),append(el){el.parentElement=this;}};
        dialogo.contains=el=>el===escena;globalThis.document={fullscreenElement:escena};
        const menu={parentElement:dialogo,style:{},getBoundingClientRect:()=>({width:180,height:44}),querySelector:()=>({focus(){}})};ui.$refs.menu=menu;
        const ancla={left:590,right:610,top:390,bottom:410,limite:{left:0,top:0,right:1200,bottom:800}};
        ui.abrirMenu(nodo('publico'),{type:'contextmenu',clientX:600,clientY:400},ancla);cuadros.shift()();
        assert.equal(menu.parentElement,escena,'El top layer nativo contiene el menú público.');assert.equal(menu.style.left,'618px');
        ui.cerrarMenu();assert.equal(menu.parentElement,dialogo);assert.equal(ui.menuNodo,null);
    }finally{for(const[k,v]of Object.entries(anteriores)){if(v===undefined)delete globalThis[k];else globalThis[k]=v;}}
});

test('el autocentrado del modal no desplaza una escena expandida ni pierde la posición que debe restaurar',()=>{
    const {ui}=componente();ui.desplazamientoX=24;ui.desplazamientoY=-18;
    ui.$refs.dialogo.classList={contains:clase=>clase==='has-diversity-expanded'};
    ui.moverModal(8,8);assert.equal(ui.desplazamientoX,24);assert.equal(ui.desplazamientoY,-18);
});

test('el selector prioriza Arthropoda y ofrece únicamente las raíces disponibles', () => {
    assert.deepEqual(filosPublicos([nodo('m', null, 'Mollusca'), nodo('a', null, 'Annelida'), nodo('h', 'a'), nodo('r', null, 'Arthropoda')]).map(n => n.clave), ['r', 'a', 'm']);
    assert.deepEqual(filosPublicos([]), []);
    assert.equal(filosPublicos([nodo('m', null, 'Mollusca')])[0].nombre, 'Mollusca');
});

test('entrar en una rama del visor conserva el linaje y no cambia el mapa ni contrae al repetir', async () => {
    const {ui} = componente(); let consultas = 0;
    ui.nodos = [{...nodo('filo'), tieneHijos: true}, {...nodo('familia', 'filo'), tieneHijos: true}, nodo('terminal', 'familia')];
    ui.cargados = ['filo', 'familia'];
    ui.$wire.consultarExploradorTaxonomico = async () => {consultas++; return resultado();};
    await ui.navegarEnVisor(ui.nodos[1]); await ui.navegarEnVisor(ui.nodos[1]);
    assert.deepEqual(ui.panel.ruta.map(n => n.clave), ['filo', 'familia']); assert.ok(ui.expandidos.includes('familia'));
    await ui.navegarEnVisor(ui.nodos[2]);
    assert.equal(ui.panel.actual.clave, 'terminal'); assert.equal(ui.panel.total, 0); assert.equal(consultas, 0);
});

test('la rama masiva conserva el linaje y limita el DOM sin omitir hijos de la paginación', () => {
    const raices = [nodo('filo'), nodo('clase', 'filo'), nodo('familia', 'clase')];
    const hijos = Array.from({length: 122}, (_, i) => nodo('g-' + i, 'familia', 'Género ' + i));
    const panel = panelTaxonomico([...raices, ...hijos], ['filo', 'clase', 'familia'], 'familia', '', 1, 16);
    assert.deepEqual(panel.ruta.map(n => n.clave), ['filo', 'clase', 'familia']);
    assert.equal(panel.hijos.length, 16); assert.equal(panel.paginas, 8); assert.equal(panel.total, 122);
    assert.ok(panel.filas.length <= 12); assert.equal(panel.filas.at(-1).total, 122);
    const todos = [];
    for (let pagina = 1; pagina <= panel.paginas; pagina++) todos.push(...panelTaxonomico([...raices, ...hijos], ['filo', 'clase', 'familia'], 'familia', '', pagina, 16).hijos.map(n => n.clave));
    assert.equal(new Set(todos).size, 122); assert.equal(todos.length, 122);
    const ultimo = panelTaxonomico([...raices, ...hijos], ['filo', 'clase', 'familia'], 'g-121');
    assert.ok(ultimo.filas.some(n => n.clave === 'g-121'), 'El hijo activo permanece en la jerarquía compacta aunque esté al final.');
});

test('buscar hijos no cambia el linaje ni consulta otras ramas y ajusta páginas tras redimensionar', () => {
    const nodos = [nodo('a'), nodo('g', 'a', 'Género Acari'), nodo('h', 'a', 'Hymenoptera'), nodo('b'), nodo('ajeno', 'b', 'Acari ajeno')];
    const panel = panelTaxonomico(nodos, ['a'], 'a', 'GENERO', 90, 1);
    assert.deepEqual(panel.hijos.map(n => n.clave), ['g']); assert.equal(panel.pagina, 1); assert.equal(panel.total, 2);
    const vacio = panelTaxonomico(nodos, ['a'], 'a', 'no existe');
    assert.equal(vacio.desde, 0); assert.equal(vacio.hasta, 0); assert.equal(vacio.paginas, 1);
    assert.equal(panelTaxonomico(nodos, ['a'], 'retirada').actual, null);
});

test('el lateral ETE termina en el segundo nivel sin ocultar clases y conserva la ruta completa', () => {
    const iniciales = [nodo('filo'), ...Array.from({length: 15}, (_, i) => nodo('clase-' + i, 'filo'))];
    const datos = [...iniciales, nodo('orden', 'clase-14'), nodo('familia', 'orden'), nodo('genero', 'familia')];
    const panel = panelTaxonomico(datos, ['filo', 'clase-14', 'orden', 'familia'], 'familia', '', 1, 20, 2);
    assert.equal(panel.filas.length, 16);
    assert.ok(panel.filas.every(n => n.profundidad <= 1 && !n.mas));
    assert.deepEqual(panel.ruta.map(n => n.clave), ['filo', 'clase-14', 'orden', 'familia']);
    assert.equal(panel.hijos[0].clave, 'genero');
});

test('la navegación de dos paneles reutiliza la precarga y contrae al repetir el nodo activo', async () => {
    const {ui} = componente(); let llamadas = 0;
    ui.nodos = [{...nodo('a'), tieneHijos: true}, nodo('h', 'a')]; ui.cargados = ['a'];
    ui.$refs.ramas = {clientWidth: 850, clientHeight: 320, scrollTop: 0};
    ui.$wire.consultarExploradorTaxonomico = async () => {llamadas++; return resultado();};
    await ui.navegarRama(ui.nodos[0]);
    assert.equal(ui.modo, 'ramas'); assert.equal(ui.panel.actual.id, 'a'); assert.equal(ui.panel.hijos[0].id, 'h');
    await ui.navegarRama(ui.nodos[0]);
    assert.equal(ui.ramaActiva, ''); assert.deepEqual(ui.expandidos, []); assert.equal(llamadas, 0);
});

test('nodos más anchos y bajos, raíz arriba a la izquierda y descendientes hacia la derecha', () => {
    const bosque = distribuirBosque([nodo('a'), nodo('h', 'a'), nodo('b')], ['a']);
    const [raiz, hijo, otra] = bosque.nodos;
    assert.ok(raiz.ancho > 220 * .85 / 2);
    assert.ok(raiz.alto < 116 * .85 / 2);
    assert.ok(hijo.alto < 104 * .85 / 2);
    assert.ok(hijo.x > raiz.x + raiz.ancho);
    assert.equal(otra.x, raiz.x);
    assert.match(bosque.conexiones[0].d, / H .* V .* H /);
    const {ui} = componente();
    assert.equal(typeof ui.cambiarOrientacion, 'undefined');
    assert.equal(raiz.x, 24); assert.equal(raiz.y, 24);
    assert.equal(hijo.y, raiz.y);
});

test('una familia numerosa comparte un recinto y usa columnas sin reducir ni girar sus nodos', () => {
    const cadena = [nodo('filo'), nodo('clase', 'filo'), nodo('orden', 'clase'), nodo('suborden', 'orden'), nodo('familia', 'suborden')];
    const hijos = Array.from({length: 31}, (_, i) => nodo('genero-' + String(i).padStart(2, '0'), 'familia'));
    const bosque = distribuirBosque([...cadena, ...hijos], cadena.map(n => n.clave), 1120);
    const visibles = bosque.nodos.filter(n => n.padre === 'familia');
    assert.equal(visibles.length, 31); assert.ok(new Set(visibles.map(n => n.x)).size >= 4);
    assert.ok(new Set(visibles.map(n => n.y)).size <= 7);
    assert.ok(visibles.every(n => n.ancho > n.alto && n.ancho === bosque.nodos[0].ancho));
    assert.equal(bosque.grupos.find(g => g.clave === 'familia').total, 31);
    assert.ok(bosque.alto <= 430, 'La familia completa usa filas próximas sin encoger los nodos.');
    for (const [i, a] of bosque.nodos.entries()) for (const b of bosque.nodos.slice(i + 1)) {
        assert.equal(a.x < b.x + b.ancho && a.x + a.ancho > b.x && a.y < b.y + b.alto && a.y + a.alto > b.y, false);
    }
});

test('Escape sale de la pantalla completa del octree y conserva abierto el explorador', () => {
    const anterior = globalThis.document, completo = {}; let salidas = 0;
    try {
        globalThis.document = {fullscreenElement: completo, exitFullscreen() {salidas++; return Promise.resolve();}};
        const {ui, cierres} = componente(); ui.$refs.dialogo.contains = el => el === completo;
        ui.escapar();
        assert.equal(salidas, 1); assert.equal(cierres(), 0); assert.equal(ui.abierto, true);
    } finally {if (anterior === undefined) delete globalThis.document; else globalThis.document = anterior;}
});

test('los filtros propios forman un linaje y retirar una pill elimina los criterios dependientes', async () => {
    const {ui, cierres} = componente({consultarExploradorTaxonomico: async () => resultado('', [nodo('a')])});
    await ui.filtrarArbol(nodo('a', null, 'Arthropoda'));
    await ui.filtrarArbol({...nodo('h', 'a', 'Insecta'), linaje: 'Arthropoda → Insecta'});
    assert.deepEqual(ui.filtrosArbol.map(f => f.id), ['a', 'h']);
    await ui.quitarFiltroArbol(1);
    assert.deepEqual(ui.filtrosArbol.map(f => f.id), ['a']);
    await ui.quitarFiltroArbol(0);
    assert.deepEqual(ui.filtrosArbol, []); assert.equal(cierres(), 0);
});

test('la consulta GET sólo envía el filtro del árbol y no los criterios del mapa', async contexto => {
    const solicitudes = [];
    contexto.mock.method(globalThis, 'fetch', async (url, opciones) => {
        solicitudes.push({url: new URL(url), opciones});
        return {ok: true, headers: new Headers({'X-Portal-Revision': 'revision-publica'}), json: async () => resultado()};
    });
    const anterior = globalThis.window;
    globalThis.window = {location: {origin: 'http://localhost:8000'}};
    try {
        const {ui} = componente({borradorFiltros: {provincia: 'Pichincha', taxon: 'Otro'}, taxonId: 'mapa'});
        ui.$refs.dialogo.dataset = {endpoint: '/portal/explorador'};
        ui.filtrosArbol = [{id: 'propio', nombre: 'Taxón'}];
        const datos = await ui.pedir('padre', '', ['ruta'], true);
        assert.deepEqual([...solicitudes[0].url.searchParams], [['padre', 'padre'], ['taxon', 'propio'], ['conservar[]', 'ruta']]);
        assert.equal(solicitudes[0].opciones.headers['X-Portal-Revalidate'], '1');
        assert.equal(datos.revision, 'revision-publica');
    } finally {globalThis.window = anterior;}
});

test('ver registros abre el segundo modal por evento sin cerrar el árbol ni aplicar el mapa', () => {
    const {ui, cierres} = componente(); const eventos = [];
    ui.$dispatch = (nombre, detalle) => eventos.push([nombre, detalle]);
    ui.menuNodo = nodo('a'); ui.verRegistros(ui.menuNodo);
    assert.deepEqual(eventos, [['abrir-registros-taxon', {id: 'a'}]]);
    assert.equal(ui.menuNodo, null); assert.equal(ui.abierto, true); assert.equal(cierres(), 0); assert.equal(ui.total, 4);
});

test('un clic expande, otro contrae y ninguno aplica filtros al mapa', async () => {
    const raiz = {...nodo('filo'), tieneHijos: true}, hijo = nodo('especie', 'filo'), consultas = [], selecciones = [];
    const {ui, cierres} = componente({
        consultarExploradorTaxonomico: async padre => {consultas.push(padre); return resultado('', [hijo]);},
        verTaxonExploradorEnMapa: async id => {selecciones.push(id);},
    });
    ui.nodos = [raiz]; await ui.seleccionar(raiz);
    assert.deepEqual(consultas, ['filo']); assert.deepEqual(selecciones, []);
    assert.deepEqual(ui.expandidos, ['filo']); assert.equal(ui.bosque.nodos.length, 2); assert.equal(ui.total, 4);
    await ui.seleccionar(raiz);
    assert.deepEqual(consultas, ['filo']); assert.deepEqual(selecciones, []); assert.deepEqual(ui.expandidos, []);
    assert.equal(ui.bosque.nodos.length, 1); assert.equal(cierres(), 0);
});
test('ajustar el bosque incluye cada nodo sin scroll y mantiene expansión y selección al restaurar', () => {
    const {ui} = componente();
    ui.$refs.lienzo.clientWidth = 600; ui.$refs.lienzo.clientHeight = 240;
    ui.nodos = [nodo('raiz'), ...Array.from({length: 8}, (_, i) => nodo(`h-${i}`, 'raiz'))];
    ui.expandidos = ['raiz']; ui.seleccionado = 'h-7'; ui.$refs.lienzo.scrollLeft = 140; ui.$refs.lienzo.scrollTop = 100;
    ui.ajustarArbol();
    assert.ok(ui.escala < 1); assert.equal(ui.ajustado, true);
    for (const n of ui.bosque.nodos) {
        assert.ok((n.x + n.ancho) * ui.escala <= 600); assert.ok((n.y + n.alto) * ui.escala <= 240);
    }
    assert.equal(ui.$refs.lienzo.scrollLeft, 0); assert.equal(ui.$refs.lienzo.scrollTop, 0);
    assert.deepEqual(ui.expandidos, ['raiz']); assert.equal(ui.seleccionado, 'h-7');
    ui.ajustarArbol(); assert.equal(ui.escala, 1); assert.equal(ui.ajustado, false); assert.deepEqual(ui.expandidos, ['raiz']);
    assert.equal(escalaBosque({ancho: 200, alto: 200}, 600, 240), 1);
});

test('los hijos precargados abren inmediatamente y se reutilizan al contraer y expandir', async () => {
    const raiz = {...nodo('a'), tieneHijos: true}, hijo = nodo('h', 'a'); let consultas = 0;
    const {ui} = componente({consultarExploradorTaxonomico: async () => {consultas++; return {...resultado('', [raiz]), precarga: {a: [hijo]}};}});
    await ui.consultar(); assert.equal(consultas, 1);
    await ui.alternar(raiz); await ui.alternar(raiz); await ui.alternar(raiz);
    assert.equal(consultas, 1); assert.deepEqual(ui.expandidos, ['a']); assert.equal(ui.bosque.nodos.length, 2);
    assert.equal(typeof ui.mostrarAyuda, 'undefined');
});

function componente(wire = {}, cargarModuloVisor) {
    const tareas = new Map(); let numero = 0, cierres = 0, focos = 0, aperturas = 0;
    const reloj = {setTimeout(tarea, retraso) {const id = ++numero; tareas.set(id, {tarea, retraso}); return id;}, clearTimeout(id) {tareas.delete(id);}};
    const ui = crearExploradorTaxonomico(reloj, cargarModuloVisor);
    ui.$wire = {vista: 'mapa', borradorFiltros: {}, ...wire};
    ui.$refs = {
        dialogo: {tagName: 'DIALOG', open: true, showModal() {this.open = true; aperturas++;},
            close() {this.open = false; cierres++; ui.alCerrar();},
            getBoundingClientRect() {return {left: 100, top: 80, right: 800, bottom: 600};},
            querySelector(selector) {return selector==='.portal-diversity-scene.is-expanded'?null:{focus() {focos++;}};}},
        lienzo: {clientWidth: 720}, cuerpo: {scrollTop: 0}, busqueda: {focus() {focos++;}},
    };
    ui.$nextTick = tarea => tarea();
    // Alpine expone el elemento del evento: un input o botón también llama estos métodos.
    ui.$el = {tagName: 'INPUT'};
    ui.abierto = true; ui.listo = true; ui.total = 4;
    return {ui, tareas, cierres: () => cierres, focos: () => focos, aperturas: () => aperturas};
}

test('el indicador termina aunque la raíz cambie durante el dibujo; una respuesta vieja no termina una actualización nueva', async () => {
    const dibujos = [diferido(), diferido()], inicios = [diferido(), diferido()]; let llamadas = 0;
    const {ui} = componente({}, async () => ({crearVisorTaxonomico: () => ({actualizar() {
        const i = llamadas++; inicios[i].resolver(); return dibujos[i].promesa;
    }})}));
    ui.$refs.ete = {}; ui.$refs.dialogo.dataset = {}; ui.visorDisponible = true;
    ui.nodos = [nodo('filo'), nodo('clase', 'filo')]; ui.ramaActiva = 'filo';
    const primera = ui.actualizarVisor(); await inicios[0].promesa;
    ui.ramaActiva = 'clase';
    const segunda = ui.actualizarVisor(); await inicios[1].promesa;
    dibujos[0].resolver(); await primera;
    assert.equal(ui.visorCargando, true, 'La actualización vigente todavía está dibujando.');
    ui.ramaActiva = 'filo'; // Un cambio de estado no puede dejar el aviso pegado al nombre anterior.
    dibujos[1].resolver(); await segunda;
    assert.equal(ui.visorCargando, false); assert.equal(ui.visorError, '');
});

test('la búsqueda 3D prioriza el rango y localiza el homónimo por clave sin filtrar el catálogo ni cambiar las tarjetas', async () => {
    const familia = {...nodo('derma-familia', 'diptera', 'Dermaptera'), etiqueta: 'Familia', coincide: true};
    const orden = {...nodo('derma-orden', 'insecta', 'Dermaptera'), etiqueta: 'Orden', coincide: true};
    const ubicaciones = [], peticiones = [];
    const {ui, cierres} = componente({}, async () => ({crearVisorTaxonomico: () => ({
        actualizar: async () => {}, rangoActivo: () => 'orden', localizar: async clave => {ubicaciones.push(clave); return true;},
    })}));
    ui.$refs.ete = {}; ui.$refs.dialogo.dataset = {}; ui.visorDisponible = true;
    ui.nodos = [nodo('filo'), nodo('arachnida', 'filo'), nodo('insecta', 'filo'), nodo('diptera', 'insecta'), familia, orden];
    ui.ramaActiva = 'filo'; ui.actualizarPanel();
    const fuente = ui.nodos, hijos = ui.hijosRama.map(n => n.clave);
    await ui.actualizarVisor();
    ui.pedir = async (padre, busqueda) => {peticiones.push({padre, busqueda}); return resultado('', [familia, orden]);};
    ui.busqueda = 'Dermapter'; await ui.predecir();
    assert.deepEqual(ui.sugerencias.map(n => n.clave), [orden.clave, familia.clave]);
    await ui.confirmar();
    assert.deepEqual(ubicaciones, [orden.clave]); assert.equal(ui.seleccionado, orden.id);
    assert.deepEqual(ui.filtrosVisor, [{id: orden.id, clave: orden.clave, nombre: orden.nombre}]);
    assert.equal(ui.nodos, fuente); assert.equal(ui.ramaActiva, 'filo'); assert.deepEqual(ui.hijosRama.map(n => n.clave), hijos);
    assert.deepEqual(ui.filtrosArbol, []); assert.equal(ui.total, 4); assert.equal(cierres(), 0);
    assert.deepEqual(peticiones, [{padre: null, busqueda: 'Dermapter'}]);
    await ui.filtrarArbol(familia);
    assert.deepEqual(ubicaciones, [orden.clave, familia.clave], 'Un clic explícito conserva la identidad de la familia homónima.');
    assert.deepEqual(ui.filtrosVisor, [{id: familia.id, clave: familia.clave, nombre: familia.nombre}], 'Un homónimo de otra rama reemplaza el ámbito incompatible.');
    await ui.filtrarArbol({...orden, clave: 'ruta-retirada'});
    assert.equal(ubicaciones.length, 2); assert.match(ui.error, /ya no está/);
});

test('las etiquetas encadenan búsquedas por identidad y su equis restaura el padre natural sin perder el ámbito anterior', async () => {
    const ubicaciones = [], consultas = [];
    const {ui} = componente({}, async () => ({crearVisorTaxonomico: () => ({actualizar: async () => {},
        localizar: async clave => {ubicaciones.push(clave); return true;},
    })}));
    ui.$refs.ete = {}; ui.$refs.dialogo.dataset = {endpoint: 'http://localhost/portal/explorador'}; ui.visorDisponible = true;
    ui.nodos = [nodo('filo'), nodo('clase', 'filo'), nodo('orden', 'clase', 'Hymenoptera'),
        nodo('suborden', 'orden', 'Apocrita'), nodo('familia', 'suborden', 'Formicidae')];
    ui.ramaActiva = 'filo'; ui.actualizarPanel(); await ui.actualizarVisor();
    const fetchAnterior = globalThis.fetch, locationAnterior = window.location;
    try {
        window.location = {origin: 'http://localhost'};
        globalThis.fetch = async url => {consultas.push(new URL(url)); return {ok: true, headers: {get: () => 'revision-publica'}, json: async () => resultado()};};
        await ui.filtrarArbol(ui.nodos[2]);
        await ui.pedir(null, 'hormig');
        assert.equal(consultas.at(-1).searchParams.get('taxon'), 'orden');
        assert.equal(consultas.at(-1).searchParams.get('busqueda'), 'hormig');
        await ui.pedir();
        assert.equal(consultas.at(-1).searchParams.has('taxon'), false, 'Revalidar el catálogo completo no aplica el ámbito del buscador.');
        assert.equal(consultas.at(-1).searchParams.get('completo'), '1');
        await ui.filtrarArbol(ui.nodos[4]);
        assert.deepEqual(ui.filtrosVisor.map(f => f.nombre), ['Hymenoptera', 'Formicidae']);
        await ui.confirmar();
        assert.equal(ubicaciones.at(-1), 'familia'); assert.equal(ui.filtrosVisor.length, 2, 'Enter sobre las etiquetas recentra sin duplicarlas.');
        await ui.quitarFiltroVisor(1);
        assert.deepEqual(ui.filtrosVisor.map(f => f.clave), ['orden']); assert.equal(ubicaciones.at(-1), 'suborden');
        assert.equal(ui.seleccionado, 'suborden', 'El padre real puede no tener etiqueta en el buscador.');
        await ui.pedir(null, 'himenopt'); assert.equal(consultas.at(-1).searchParams.get('taxon'), 'orden');
        await ui.quitarFiltroVisor(0);
        assert.deepEqual(ui.filtrosVisor, []); assert.equal(ui.seleccionado, 'clase'); assert.equal(ubicaciones.at(-1), 'clase');
        await ui.pedir(null, 'coleopt'); assert.equal(consultas.at(-1).searchParams.has('taxon'), false);
    } finally {globalThis.fetch = fetchAnterior; window.location = locationAnterior;}
});

test('quitar la única etiqueta vuelve al padre publicado y conserva el catálogo y las tarjetas del filo', async () => {
    const ubicaciones = [];
    const {ui, cierres} = componente({}, async () => ({crearVisorTaxonomico: () => ({actualizar: async () => {},
        localizar: async clave => {ubicaciones.push(clave); return true;},
    })}));
    ui.$refs.ete = {}; ui.$refs.dialogo.dataset = {}; ui.visorDisponible = true;
    ui.nodos = [nodo('filo'), nodo('clase', 'filo'), nodo('suborden', 'clase', 'Apocrita'), nodo('familia', 'suborden', 'Formicidae')];
    ui.ramaActiva = 'filo'; ui.actualizarPanel(); await ui.actualizarVisor();
    const fuente = ui.nodos, hijos = ui.hijosRama.map(n => n.clave);
    await ui.filtrarArbol(ui.nodos[3]);
    await ui.quitarFiltroVisor(0);
    assert.deepEqual(ui.filtrosVisor, []); assert.equal(ui.seleccionado, 'suborden');
    assert.deepEqual(ubicaciones, ['familia', 'suborden']); assert.equal(ui.busqueda, '');
    assert.equal(ui.nodos, fuente); assert.equal(ui.ramaActiva, 'filo'); assert.deepEqual(ui.hijosRama.map(n => n.clave), hijos);
    assert.equal(ui.total, 4); assert.equal(cierres(), 0);
    await ui.quitarFiltroVisor(0);
    assert.deepEqual(ubicaciones, ['familia', 'suborden'], 'Una equis retirada no provoca otra navegación.');
});

test('retirar una etiqueta anterior durante la restauración del hijo cancela la selección tardía', async () => {
    const dibujo = diferido(), inicio = diferido();
    const {ui} = componente({}, async () => ({crearVisorTaxonomico: () => ({actualizar: async () => {},
        localizar: clave => clave === 'suborden' ? (inicio.resolver(), dibujo.promesa) : Promise.resolve(true),
    })}));
    ui.$refs.ete = {}; ui.$refs.dialogo.dataset = {}; ui.visorDisponible = true;
    ui.nodos = [nodo('filo'), nodo('clase', 'filo'), nodo('orden', 'clase'), nodo('suborden', 'orden'), nodo('familia', 'suborden')];
    ui.ramaActiva = 'filo'; ui.actualizarPanel(); await ui.actualizarVisor();
    ui.filtrosVisor = [ui.nodos[2], ui.nodos[4]].map(({id, clave, nombre}) => ({id, clave, nombre}));
    const primera = ui.quitarFiltroVisor(1); await inicio.promesa;
    await ui.quitarFiltroVisor(0);
    dibujo.resolver(true); await primera;
    assert.deepEqual(ui.filtrosVisor, []); assert.equal(ui.seleccionado, 'clase'); assert.equal(ui.error, '');
});

test('la equis conserva el foco del buscador aunque Alpine retire su ámbito durante el dibujo', async () => {
    const dibujo = diferido(), inicio = diferido();
    const {ui, focos, tareas} = componente({}, async () => ({crearVisorTaxonomico: () => ({actualizar: async () => {},
        localizar: () => {inicio.resolver(); return dibujo.promesa;},
    })}));
    ui.$refs.ete = {}; ui.$refs.dialogo.dataset = {}; ui.visorDisponible = true;
    ui.nodos = [nodo('filo'), nodo('clase', 'filo'), nodo('familia', 'clase')];
    ui.ramaActiva = 'filo'; ui.actualizarPanel(); await ui.actualizarVisor();
    ui.filtrosVisor = [{id: 'familia', clave: 'familia', nombre: 'familia'}];
    ui.busqueda = 'coleóp';
    const restauracion = ui.quitarFiltroVisor(0); await inicio.promesa;
    delete ui.$refs.busqueda;
    dibujo.resolver(true); await restauracion;
    assert.equal(focos(), 1); assert.equal(ui.busqueda, 'coleóp'); assert.equal(ui.seleccionado, 'clase');
    assert.deepEqual(ui.filtrosVisor, []); assert.equal(tareas.size, 1, 'La consulta escrita continúa en el ámbito general.');
});

test('cerrar durante una localización cancela su selección tardía y apaga el aviso', async () => {
    const dibujo = diferido(), inicio = diferido();
    const {ui} = componente({}, async () => ({crearVisorTaxonomico: () => ({
        actualizar: async () => {}, localizar: () => {inicio.resolver(); return dibujo.promesa;},
    })}));
    ui.$refs.ete = {}; ui.$refs.dialogo.dataset = {}; ui.visorDisponible = true;
    ui.nodos = [nodo('filo'), nodo('hijo', 'filo')]; ui.ramaActiva = 'filo'; ui.actualizarPanel();
    await ui.actualizarVisor();
    const tarea = ui.filtrarArbol(ui.nodos[1]); await inicio.promesa;
    ui.cerrar(); dibujo.resolver(true); await tarea;
    assert.equal(ui.seleccionado, ''); assert.deepEqual(ui.filtrosVisor, []); assert.equal(ui.visorCargando, false); assert.equal(ui.abierto, false);
});

test('redimensionar compara marcos por elemento, agrupa cambios y no oscila con las barras internas', () => {
    const originales = Object.fromEntries(['ResizeObserver', 'requestAnimationFrame', 'cancelAnimationFrame'].map(clave => [clave, globalThis[clave]]));
    const cuadros = new Map(), observados = []; let callback, numero = 0, distribuciones = 0;
    try {
        globalThis.ResizeObserver = class {
            constructor(tarea) {callback = tarea;}
            observe(elemento, opciones) {observados.push({elemento, opciones});}
            disconnect() {}
        };
        globalThis.requestAnimationFrame = tarea => {cuadros.set(++numero, tarea); return numero;};
        globalThis.cancelAnimationFrame = id => cuadros.delete(id);
        const {ui} = componente(); ui.$refs.ete = {};
        ui.distribuir = () => distribuciones++; ui.moverModal = () => {};
        const entrada = (target, width, height, contenido = width) => ({target, borderBoxSize: [{inlineSize: width, blockSize: height}], contentRect: {width: contenido, height}});
        const avanzar = () => {const tareas = [...cuadros.values()]; cuadros.clear(); tareas.forEach(tarea => tarea());};
        ui.init();
        assert.deepEqual(observados.map(o => o.elemento), [ui.$refs.dialogo, ui.$refs.ete]);
        assert.ok(observados.every(o => o.opciones.box === 'border-box'));
        callback([entrada(ui.$refs.dialogo, 1000, 700), entrada(ui.$refs.ete, 720, 530)]);
        assert.equal(cuadros.size, 1); avanzar(); assert.equal(distribuciones, 1);
        for (let i = 0; i < 6; i++) {
            callback([entrada(ui.$refs.ete, 720, 530, i % 2 ? 705 : 720)]);
            callback([entrada(ui.$refs.dialogo, 1000, 700)]);
        }
        assert.equal(cuadros.size, 0); assert.equal(distribuciones, 1);
        callback([entrada(ui.$refs.dialogo, 1100, 700), entrada(ui.$refs.ete, 820, 530)]);
        callback([entrada(ui.$refs.ete, 840, 530)]);
        assert.equal(cuadros.size, 1); avanzar(); assert.equal(distribuciones, 2);
        ui.destroy();
    } finally {
        for (const [clave, valor] of Object.entries(originales)) {
            if (valor === undefined) delete globalThis[clave]; else globalThis[clave] = valor;
        }
    }
});

test('el cambio de tamaño mantiene la esquina, límites del viewport y restauración por teclado', () => {
    const anteriores = [globalThis.innerWidth, globalThis.innerHeight];
    try {
        globalThis.innerWidth = 1200; globalThis.innerHeight = 800;
        const {ui} = componente();
        ui.$refs.dialogo.getBoundingClientRect = () => ({left: 100, top: 80, width: 700, height: 520});
        ui.redimensionarModal(800, 560);
        assert.equal((1200 - ui.anchoVentana) / 2 + ui.desplazamientoX, 100);
        assert.equal((800 - ui.altoVentana) / 2 + ui.desplazamientoY, 80);
        ui.redimensionarModal(2000, 2000);
        assert.equal(ui.anchoVentana, 1092); assert.equal(ui.altoVentana, 712);
        assert.equal((1200 - ui.anchoVentana) / 2 + ui.desplazamientoX, 100);
        assert.equal((800 - ui.altoVentana) / 2 + ui.desplazamientoY, 80);
        globalThis.innerWidth = 390; globalThis.innerHeight = 320;
        ui.redimensionarModal(10, 10);
        assert.equal(ui.anchoVentana, 374); assert.equal(ui.altoVentana, 304);
        let prevenido = false;
        ui.tecladoRedimension({key: 'Home', preventDefault() {prevenido = true;}});
        assert.equal(prevenido, true); assert.equal(ui.anchoVentana, 0); assert.equal(ui.altoVentana, 0);
    } finally {
        if (anteriores[0] === undefined) delete globalThis.innerWidth; else globalThis.innerWidth = anteriores[0];
        if (anteriores[1] === undefined) delete globalThis.innerHeight; else globalThis.innerHeight = anteriores[1];
    }
});

test('las cuatro esquinas conservan su esquina opuesta al reducir y ampliar el modal', () => {
    const anteriores = [globalThis.innerWidth, globalThis.innerHeight];
    try {
        globalThis.innerWidth = 1200; globalThis.innerHeight = 800;
        for (const esquina of ['nw', 'ne', 'sw', 'se']) {
            const {ui} = componente(), origen = {left: 100, top: 80, width: 700, height: 520};
            for (const [w, h] of [[500, 400], [740, 570]]) {
                ui.redimensionarModal(w, h, origen, esquina);
                const x = (1200 - ui.anchoVentana) / 2 + ui.desplazamientoX;
                const y = (800 - ui.altoVentana) / 2 + ui.desplazamientoY;
                assert.equal(esquina.includes('w') ? x + ui.anchoVentana : x, esquina.includes('w') ? 800 : 100);
                assert.equal(esquina.includes('n') ? y + ui.altoVentana : y, esquina.includes('n') ? 600 : 80);
                assert.equal(ui.anchoVentana, w); assert.equal(ui.altoVentana, h);
            }
        }
    } finally {
        for (const [i, clave] of ['innerWidth', 'innerHeight'].entries()) {
            if (anteriores[i] === undefined) delete globalThis[clave]; else globalThis[clave] = anteriores[i];
        }
    }
});

test('un filo terminal como Nematomorpha siempre habilita su gráfico ETE', () => {
    const {ui} = componente(); ui.$refs.ete = {};
    ui.nodos = [{clave: 'nematomorpha', padre: null, id: 'id-nema', nombre: 'Nematomorpha', etiqueta: 'Filo', total: 1, tieneHijos: false}];
    ui.ramaActiva = 'nematomorpha'; ui.actualizarPanel();
    assert.equal(ui.visorDisponible, true);
    assert.equal(ui.panel.actual.nombre, 'Nematomorpha');
});

test('abrir y cerrar utilizan el diálogo aunque el evento venga de un descendiente y restauran el foco', async () => {
    const {ui, cierres, aperturas} = componente({consultarExploradorTaxonomico: async () => resultado('', [nodo('filo')])});
    let restaurados = 0;
    ui.cerrar();
    await ui.abrir({focus() {restaurados++;}});
    assert.equal(aperturas(), 1); assert.equal(ui.$refs.dialogo.open, true); assert.equal(ui.abierto, true);
    assert.equal(cierres(), 1); assert.equal(ui.abierto, true);
    ui.cerrar();
    assert.equal(cierres(), 2); assert.equal(ui.$refs.dialogo.open, false); assert.equal(ui.abierto, false);
    assert.equal(restaurados, 1);
});

test('reabrir conserva búsqueda, ramas cargadas, expansiones y desplazamiento con contadores actualizados', async () => {
    const raiz = {...nodo('a'), tieneHijos: true}, hijo = nodo('h', 'a');
    const llamadas = [];
    const {ui} = componente({consultarExploradorTaxonomico(padre, texto, conservar) {
        llamadas.push([padre, texto, conservar]);
        if (padre === 'a') return resultado('', [hijo]);
        return {...resultado('', texto || conservar ? [raiz, hijo] : [raiz]), total: conservar ? 7 : 4,
            expandidos: texto ? ['a'] : []};
    }});
    ui.cerrar(); await ui.abrir({focus() {}});
    ui.busqueda = 'Arthro'; await ui.consultar();
    await ui.alternar(raiz);
    ui.$refs.lienzo.scrollLeft = 180; ui.$refs.lienzo.scrollTop = 240;
    ui.$refs.cuerpo.scrollTop = 32;
    ui.cerrar();
    ui.$refs.lienzo.scrollLeft = ui.$refs.lienzo.scrollTop = 0;
    ui.$refs.cuerpo.scrollTop = 0;
    await ui.abrir({focus() {}});
    assert.deepEqual(llamadas.at(-1), [null, '', ['a']]);
    assert.equal(ui.busqueda, 'Arthro'); assert.deepEqual(ui.expandidos, ['a']); assert.deepEqual(ui.cargados, ['a']);
    assert.equal(ui.$refs.lienzo.scrollLeft, 180); assert.equal(ui.$refs.lienzo.scrollTop, 240);
    assert.equal(ui.$refs.cuerpo.scrollTop, 32);
    assert.equal(ui.total, 7); assert.equal(ui.bosque.nodos.length, 2);
});

test('cambiar filtros del mapa conserva el árbol global, sus ramas y sus propios filtros', async () => {
    const raiz = {...nodo('a'), tieneHijos: true}, hijo = nodo('h', 'a'), llamadas = [];
    const {ui} = componente({filtroProvincia: 'Pichincha', filtroTaxonId: '',
        consultarExploradorTaxonomico(padre, texto, conservar) {
            llamadas.push([padre, texto, conservar]);
            if (padre === 'a') return resultado('', [hijo]);
            return resultado('', conservar ? [raiz, hijo] : [raiz]);
        }});
    ui.cerrar(); await ui.abrir({focus() {}}); await ui.alternar(raiz); ui.cerrar();
    ui.$wire.filtroTaxonId = 'otro'; ui.$wire.filtroProvincia = 'Napo'; await ui.abrir({focus() {}});
    assert.deepEqual(llamadas.at(-1), [null, '', ['a']]); assert.deepEqual(ui.expandidos, ['a']);
    assert.deepEqual(ui.nodos.map(n => n.id), ['a', 'h']); assert.equal(ui.seleccionado, ''); assert.deepEqual(ui.filtrosArbol, []);
});
test('cerrar durante el debounce conserva el texto y descarta sugerencias de la sesión anterior', async () => {
    const vieja = diferido(), llamadas = [];
    const {ui, tareas} = componente({consultarExploradorTaxonomico(padre, texto) {
        llamadas.push(texto); return texto === 'vieja' ? vieja.promesa : resultado('', [nodo('global')]);
    }});
    ui.busqueda = 'vieja'; const pendiente = ui.predecir();
    ui.busqueda = 'nueva'; ui.buscar(); ui.cerrar();
    assert.equal(tareas.size, 0); await ui.abrir({focus() {}});
    vieja.resolver(resultado('', [{...nodo('vieja'), coincide: true}])); await pendiente;
    assert.deepEqual(llamadas, ['vieja', '']); assert.equal(ui.busqueda, 'nueva'); assert.equal(ui.nodos[0].id, 'global');
    assert.deepEqual(ui.sugerencias, []);
});
test('ver en el mapa espera la vista de mapa, cierra antes del encuadre y conserva la navegación', async () => {
    const anterior = globalThis.window, eventos = [], respuesta = diferido(), vistas = [];
    const {ui, cierres} = componente({vista: 'registros', cambiarVista(vista) {vistas.push(vista); return respuesta.promesa;}});
    let restaurados = 0;
    globalThis.window = {dispatchEvent(evento) {eventos.push({tipo: evento.type, mostrar: evento.detail.mostrar, abierto: ui.$refs.dialogo.open});}};
    try {
        ui.$wire.consultarExploradorTaxonomico = async () => ({...resultado('a', [nodo('a'), nodo('h', 'a')]), expandidos: ['a']});
        ui.cerrar(); await ui.abrir({focus() {restaurados++;}});
        ui.$refs.lienzo.scrollTop = 240;
        const tarea = ui.verEnMapa();
        assert.deepEqual(vistas, ['mapa']); assert.equal(ui.$refs.dialogo.open, true); assert.equal(ui.mostrandoMapa, true);
        respuesta.resolver(); await tarea;
        assert.equal(cierres(), 2); assert.equal(ui.mostrandoMapa, false); assert.equal(restaurados, 0);
        assert.deepEqual(eventos, [{tipo: 'encuadrar-taxonomia', mostrar: true, abierto: false}]);
        assert.deepEqual(ui.expandidos, ['a']); assert.equal(ui.nodos.length, 2); assert.equal(ui.seleccionado, 'a');
    } finally {if (anterior === undefined) delete globalThis.window; else globalThis.window = anterior;}
});

test('ver en el mapa no cierra con una selección pendiente y un fallo al cambiar de vista permite reintentar', async () => {
    const {ui, cierres} = componente({vista: 'tarjetas', cambiarVista: async () => {throw new Error('Sin conexión');}});
    ui.listo = true; ui.aplicando = true;
    await ui.verEnMapa(); assert.equal(cierres(), 0); assert.equal(ui.error, '');
    ui.aplicando = false; await ui.verEnMapa();
    assert.equal(cierres(), 0); assert.match(ui.error, /Vuelve a intentarlo/); assert.equal(ui.mostrandoMapa, false);
    const anterior = globalThis.window;
    globalThis.window = {dispatchEvent() {}};
    try {
        ui.$wire.cambiarVista = async () => {};
        await ui.verEnMapa();
        assert.equal(cierres(), 1); assert.equal(ui.error, ''); assert.equal(ui.mostrandoMapa, false);
    } finally {if (anterior === undefined) delete globalThis.window; else globalThis.window = anterior;}
});

test('las flechas del teclado buscan el destino dentro del diálogo desde el botón de un nodo', () => {
    const {ui} = componente();
    const raiz = {...nodo('a'), tieneHijos: true}, hijo = nodo('h', 'a');
    const destinos = []; let prevenidos = 0;
    ui.nodos = [raiz, hijo]; ui.expandidos = ['a']; ui.distribuir();
    ui.$el = {tagName: 'BUTTON'};
    ui.$refs.dialogo.querySelector = selector => ({focus() {destinos.push(selector);}});
    ui.teclado({key: 'ArrowDown', preventDefault() {prevenidos++;}}, raiz);
    ui.teclado({key: 'ArrowLeft', preventDefault() {prevenidos++;}}, hijo);
    assert.deepEqual(destinos, ['[data-taxon-clave="h"] .taxonomy-explorer-name', '[data-taxon-clave="a"] .taxonomy-explorer-name']);
    assert.equal(prevenidos, 2);
});

test('uno cuatro y cinco filos son raíces independientes alineadas al borde superior izquierdo', () => {
    for (const cantidad of [1, 4, 5]) for (const ancho of [320, 748, 1000]) {
        const raices = Array.from({length: cantidad}, (_, i) => nodo(`filo-${i}`));
        const bosque = distribuirBosque(raices, [], ancho);
        assert.equal(bosque.nodos.length, cantidad);
        assert.equal(bosque.conexiones.length, 0);
        assert.equal(new Set(bosque.nodos.map(item => item.x)).size, 1);
        assert.ok(bosque.nodos.every(item => item.padre === null && item.profundidad === 0));
        assert.equal(bosque.nodos[0].x, 24); assert.equal(bosque.nodos[0].y, 24);
        for (const [i, a] of bosque.nodos.entries()) {
            assert.ok(a.x >= 0 && a.x + a.ancho <= bosque.ancho);
            for (const b of bosque.nodos.slice(i + 1)) assert.ok(a.y + a.alto < b.y);
        }
    }
});

test('ramas extensas conservan el parentesco, el color de su filo y nodos sin superposición', () => {
    const raices = [nodo('a', null, 'Arthropoda'), nodo('b', null, 'Mollusca')];
    const nodos = [...raices, ...Array.from({length: 6}, (_, i) => nodo(`h-${i}`, 'a')), nodo('m', 'b'), nodo('especie', 'm')];
    const bosque = distribuirBosque(nodos, ['a', 'b', 'm'], 740);
    assert.equal(bosque.grupos.find(g => g.clave === 'a').total, 6);
    assert.equal(bosque.conexiones.length, 9);
    assert.equal(bosque.nodos.find(item => item.clave === 'especie').profundidad, 2);
    for (const a of bosque.nodos) {
        const padre = bosque.nodos.find(item => item.clave === a.padre);
        if (padre) {assert.ok(a.x > padre.x + padre.ancho || a.y > padre.y + padre.alto); assert.equal(a.color, padre.color);}
        for (const b of bosque.nodos.filter(item => item.clave !== a.clave)) {
            assert.equal(a.x < b.x + b.ancho && a.x + a.ancho > b.x && a.y < b.y + b.alto && a.y + a.alto > b.y, false);
        }
    }
    const colapsado = distribuirBosque(nodos, [], 740);
    assert.deepEqual(colapsado.nodos.map(item => item.id), ['a', 'b']);
    const soloUna = distribuirBosque(nodos.filter(item => item.clave === 'a' || item.padre === 'a'), ['a'], 740);
    assert.equal(soloUna.nodos[0].x, 24); assert.equal(soloUna.nodos[0].y, 24);
});

test('escribir espera 300 ms y una respuesta antigua no sustituye las coincidencias nuevas', async () => {
    const consultas = [], vieja = diferido(), nueva = diferido();
    const {ui, tareas} = componente({consultarExploradorTaxonomico(padre, texto) {consultas.push([padre, texto]); return texto === 'Coleop' ? vieja.promesa : nueva.promesa;}});
    const predicciones = [], predecir = ui.predecir.bind(ui);
    ui.predecir = () => {const respuesta = predecir(); predicciones.push(respuesta); return respuesta;};
    ui.busqueda = 'Coleop'; ui.buscar();
    assert.equal(consultas.length, 0);
    assert.equal([...tareas.values()][0].retraso, 300);
    [...tareas.values()][0].tarea(); tareas.clear();
    ui.busqueda = 'Mollusca'; ui.buscar(); [...tareas.values()][0].tarea(); tareas.clear();
    nueva.resolver({...resultado('', [{...nodo('mol', null, 'Mollusca'), coincide: true}]), expandidos: ['mol']});
    await predicciones[1];
    vieja.resolver(resultado('', [{...nodo('coleop'), coincide: true}])); await predicciones[0];
    assert.deepEqual(consultas, [[null, 'Coleop'], [null, 'Mollusca']]);
    assert.equal(ui.sugerencias[0].nombre, 'Mollusca'); assert.deepEqual(ui.nodos, []);
    assert.equal(ui.buscando, false);
});

test('el reloj predeterminado conserva el contexto global al buscar, cancelar y cerrar', async contexto => {
    const tareas = new Map(), retrasos = [], cancelados = [], consultas = [];
    let numero = 0;
    contexto.mock.method(globalThis, 'setTimeout', function(tarea, retraso) {
        assert.equal(this, globalThis, 'El temporizador del navegador requiere el contexto global');
        const id = ++numero; tareas.set(id, tarea); retrasos.push(retraso); return id;
    });
    contexto.mock.method(globalThis, 'clearTimeout', function(id) {
        assert.equal(this, globalThis, 'La cancelación del navegador requiere el contexto global');
        cancelados.push(id); tareas.delete(id);
    });
    const ui = crearExploradorTaxonomico();
    let prediccion;
    const predecir = ui.predecir.bind(ui);
    ui.predecir = () => (prediccion = predecir());
    ui.abierto = true;
    ui.$wire = {consultarExploradorTaxonomico: async (padre, texto) => {
        consultas.push([padre, texto]); return resultado('', [{...nodo('coleop', null, texto), coincide: true}]);
    }};
    ui.$refs = {lienzo: {clientWidth: 720}, dialogo: {}};
    ui.$nextTick = tarea => tarea();
    ui.busqueda = 'Coleop'; ui.buscar();
    ui.busqueda = 'Coleoptera'; ui.buscar();
    assert.deepEqual(retrasos, [300, 300]);
    assert.deepEqual(cancelados, [1]); assert.equal(tareas.size, 1);
    const tarea = tareas.get(2); tareas.delete(2); tarea();
    await prediccion;
    assert.deepEqual(consultas, [[null, 'Coleoptera']]);
    assert.equal(ui.sugerencias[0].nombre, 'Coleoptera'); assert.deepEqual(ui.nodos, []); assert.equal(ui.buscando, false);
    ui.buscar(); ui.alCerrar();
    assert.deepEqual(cancelados, [1, 3]); assert.equal(tareas.size, 0);
    assert.equal(ui.abierto, false);
});

test('la flecha sólo carga la rama solicitada y los siguientes despliegues utilizan sus hijos', async () => {
    const llamadas = [], raiz = {...nodo('a'), tieneHijos: true};
    const {ui, cierres} = componente({consultarExploradorTaxonomico(...args) {llamadas.push(args); return resultado('', [nodo('h', 'a')]);}, seleccionarTaxonExplorador() {throw new Error('La flecha no selecciona');}});
    ui.nodos = [raiz]; ui.distribuir();
    await ui.alternar(raiz); await ui.alternar(raiz); await ui.alternar(raiz);
    assert.deepEqual(llamadas, [['a', '']]);
    assert.deepEqual(ui.expandidos, ['a']); assert.equal(ui.bosque.nodos.length, 2);
    assert.equal(ui.seleccionado, ''); assert.equal(cierres(), 0);
});

test('repetir la acción de mapa durante su consulta no duplica la petición ni cierra antes de actualizar', async () => {
    const respuesta = diferido(), llamadas = [];
    const {ui, cierres} = componente({verTaxonExploradorEnMapa(id) {llamadas.push(id); return respuesta.promesa;}});
    const tarea = ui.verEnMapa(nodo('filo')); await ui.verEnMapa(nodo('filo'));
    assert.equal(ui.aplicando, true); assert.equal(cierres(), 0);
    respuesta.resolver({seleccionado: 'filo', total: 17, hoja: false}); await tarea;
    assert.deepEqual(llamadas, ['filo']); assert.equal(ui.total, 4); assert.equal(cierres(), 1);
});
test('expandir y contraer una raíz conserva la selección sin el antiguo botón de reinicio', async () => {
    const raiz = {...nodo('a', null, 'Arthropoda'), tieneHijos: true};
    const {ui} = componente({consultarExploradorTaxonomico: async () => resultado('', Array.from({length: 6}, (_, i) => nodo(`h-${i}`, 'a')))});
    ui.nodos = [raiz, nodo('b', null, 'Mollusca')]; ui.seleccionado = 'a'; ui.distribuir();
    const posicion = ui.bosque.nodos[0].x;
    await ui.alternar(raiz);
    assert.equal(ui.bosque.nodos[0].x - ui.$refs.lienzo.scrollLeft, posicion);
    await ui.alternar(raiz);
    assert.deepEqual(ui.bosque.nodos.map(n => n.clave), ['a', 'b'], 'Contraer no retira los otros filos publicados.');
    assert.ok(!ui.expandidos.includes('a')); assert.ok(ui.nodos.some(n => n.clave === 'b'));
    assert.equal(ui.$refs.lienzo.scrollLeft, 0);
    assert.equal(ui.seleccionado, 'a'); assert.equal(typeof ui.volverAFilos, 'undefined');
});

test('un fallo de mapa libera la espera y permite reintentar sin cerrar ni perder la selección anterior', async () => {
    let falla = true;
    const {ui, cierres} = componente({verTaxonExploradorEnMapa: async () => {if (falla) throw new Error('Sin conexión'); return {seleccionado: 'nuevo', total: 2, hoja: false};}});
    ui.seleccionado = 'anterior';
    await ui.verEnMapa(nodo('nuevo'));
    assert.equal(ui.seleccionado, 'anterior'); assert.equal(ui.aplicando, false); assert.equal(cierres(), 0);
    assert.match(ui.error, /reintentar/);
    falla = false; await ui.verEnMapa(nodo('nuevo'));
    assert.equal(ui.seleccionado, 'nuevo'); assert.equal(ui.error, ''); assert.equal(cierres(), 1); assert.equal(ui.total, 4);
});
test('clics rápidos en ramas distintas conservan la última expansión y no envían filtros al mapa', async () => {
    const a = diferido(), b = diferido(), llamadas = [];
    const {ui, cierres} = componente({consultarExploradorTaxonomico(id) {llamadas.push(id); return id === 'a' ? a.promesa : b.promesa;}});
    const raizA = {...nodo('a'), tieneHijos: true}, raizB = {...nodo('b'), tieneHijos: true};
    ui.nodos = [raizA, raizB]; ui.distribuir();
    const tareaA = ui.seleccionar(raizA), tareaB = ui.seleccionar(raizB);
    b.resolver(resultado('', [nodo('bh', 'b')])); await tareaB;
    a.resolver(resultado('', [nodo('ah', 'a')])); await tareaA;
    assert.deepEqual(llamadas, ['a', 'b']); assert.equal(ui.seleccionado, 'b'); assert.equal(cierres(), 0);
    assert.deepEqual([...ui.expandidos].sort(), ['a', 'b']); assert.equal(ui.bosque.nodos.length, 4);
});
test('una predicción tardía no cambia los nodos, el contador ni la selección del árbol', async () => {
    const consulta = diferido();
    const {ui} = componente({consultarExploradorTaxonomico: () => consulta.promesa});
    ui.nodos = [nodo('a')]; ui.total = 18; ui.busqueda = 'abc'; const leyendo = ui.predecir();
    ui.seleccionado = 'b';
    consulta.resolver(resultado('', [{...nodo('sugerencia'), coincide: true}])); await leyendo;
    assert.equal(ui.seleccionado, 'b'); assert.equal(ui.total, 18); assert.deepEqual(ui.nodos.map(n => n.id), ['a']);
    assert.deepEqual(ui.sugerencias.map(n => n.id), ['sugerencia']);
});
test('Enter cancela la espera, elige la sugerencia y filtra sólo el árbol; los errores conservan el modal', async () => {
    const llamadas = [];
    const {ui, tareas, cierres} = componente({consultarExploradorTaxonomico(padre, texto) {
        llamadas.push(texto);
        if (texto) return resultado('', [{...nodo('exacto', null, 'Taxón sugerido'), coincide: true}]);
        return {...resultado('exacto', [nodo('exacto')]), total: 2};
    }});
    ui.busqueda = '  Taxon  '; ui.buscar(); await ui.confirmar();
    assert.equal(tareas.size, 0); assert.deepEqual(llamadas, ['Taxon', '']); assert.equal(cierres(), 0);
    assert.equal(ui.seleccionado, 'exacto'); assert.equal(ui.total, 2); assert.deepEqual(ui.filtrosArbol, [{id: 'exacto', nombre: 'Taxón sugerido'}]);
    ui.$wire.consultarExploradorTaxonomico = async () => {throw new Error('Sin conexión');};
    ui.busqueda = 'otra'; ui.sugerencias = []; await ui.confirmar();
    assert.match(ui.error, /sugerencias/); assert.equal(cierres(), 0);
});
test('Enter confirma una vez la sugerencia y respeta composición y repetición de tecla', async () => {
    const llamadas = [], respuesta = diferido();
    const {ui, tareas, cierres} = componente({consultarExploradorTaxonomico(padre, texto) {
        llamadas.push(texto); return texto ? respuesta.promesa : {...resultado('exacto', [nodo('exacto')]), total: 2};
    }});
    ui.busqueda = '  Taxon  '; ui.buscar();
    for (const cambio of [{key: 'a'}, {isComposing: true}, {keyCode: 229}]) {
        let prevenido = false;
        assert.equal(ui.confirmarConTeclado({key: 'Enter', ...cambio, preventDefault() {prevenido = true;}}), undefined);
        assert.equal(prevenido, false);
    }
    assert.equal(tareas.size, 1); assert.deepEqual(llamadas, []);
    let prevenidos = 0;
    const tarea = ui.confirmarConTeclado({key: 'Enter', preventDefault() {prevenidos++;}});
    ui.confirmarConTeclado({key: 'Enter', repeat: true, preventDefault() {prevenidos++;}});
    assert.equal(prevenidos, 2); assert.equal(tareas.size, 0); assert.deepEqual(llamadas, ['Taxon']); assert.equal(cierres(), 0);
    respuesta.resolver(resultado('', [{...nodo('exacto'), coincide: true}])); await tarea;
    assert.equal(ui.seleccionado, 'exacto'); assert.equal(ui.total, 2); assert.equal(cierres(), 0); assert.equal(ui.filtrosArbol.length, 1);
});
test('una respuesta de mapa de la sesión cerrada no cierra el explorador al volver a abrirlo', async () => {
    const respuesta = diferido();
    const {ui, cierres} = componente({verTaxonExploradorEnMapa: () => respuesta.promesa, consultarExploradorTaxonomico: async () => resultado('', [nodo('nuevo')])});
    const tarea = ui.verEnMapa(nodo('viejo'));
    ui.cerrar(); await ui.abrir({focus() {}});
    respuesta.resolver({seleccionado: 'viejo', total: 1, hoja: false}); await tarea;
    assert.equal(cierres(), 1); assert.equal(ui.abierto, true); assert.equal(ui.nodos[0].id, 'nuevo');
});
test('un cierre programado se descarta si la sesión se cerró y reabrió antes de ejecutarlo', async () => {
    const tareas = [];
    const {ui, cierres} = componente({verTaxonExploradorEnMapa: async id => ({seleccionado: id, total: 1, hoja: false})});
    ui.$nextTick = tarea => tareas.push(tarea);
    await ui.verEnMapa(nodo('a')); ui.cerrar();
    ui.abierto = true; ui.$refs.dialogo.open = true;
    for (const tarea of tareas) tarea();
    assert.equal(ui.abierto, true); assert.equal(cierres(), 1);
});
test('el menú de mapa de una hoja o un padre cierra y solicita encuadre de los puntos asociados', async () => {
    const anterior = globalThis.window, eventos = [];
    globalThis.window = {dispatchEvent: evento => eventos.push(evento.detail)};
    try {
        for (const hoja of [true, false]) {
            const {ui, cierres} = componente({verTaxonExploradorEnMapa: async id => ({seleccionado: id, total: 1, hoja})});
            await ui.verEnMapa({...nodo('taxon'), hoja});
            assert.equal(cierres(), 1); assert.equal(ui.total, 4);
        }
        assert.deepEqual(eventos, [{mostrar: true}, {mostrar: true}]);
    } finally {globalThis.window = anterior;}
});
