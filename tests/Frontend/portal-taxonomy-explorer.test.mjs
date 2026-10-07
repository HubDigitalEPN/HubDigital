import test from 'node:test';
import assert from 'node:assert/strict';
import {crearExploradorTaxonomico, distribuirBosque} from '../../resources/js/portal-taxonomy-explorer-model.js';

const nodo = (clave, padre = null, nombre = clave) => ({clave, id: clave, padre, nombre, etiqueta: padre ? 'Especie' : 'Filo', total: 4, tieneHijos: false, hoja: false});
const diferido = () => { let resolver; const promesa = new Promise(resolve => {resolver = resolve;}); return {promesa, resolver}; };
const resultado = (seleccionado = '', nodos = []) => ({seleccionado, nodos, total: 4, expandidos: [], hayMas: false});

function componente(wire = {}) {
    const tareas = new Map(); let numero = 0, cierres = 0, focos = 0, aperturas = 0;
    const reloj = {setTimeout(tarea, retraso) {const id = ++numero; tareas.set(id, {tarea, retraso}); return id;}, clearTimeout(id) {tareas.delete(id);}};
    const ui = crearExploradorTaxonomico(reloj);
    ui.$wire = wire;
    ui.$refs = {
        dialogo: {tagName: 'DIALOG', open: true, showModal() {this.open = true; aperturas++;},
            close() {this.open = false; cierres++; ui.alCerrar();},
            getBoundingClientRect() {return {left: 100, top: 80, right: 800, bottom: 600};},
            querySelector() {return {focus() {focos++;}};}},
        lienzo: {clientWidth: 720}, busqueda: {focus() {focos++;}},
    };
    ui.$nextTick = tarea => tarea();
    // Alpine expone el elemento del evento: un input o botón también llama estos métodos.
    ui.$el = {tagName: 'INPUT'};
    ui.abierto = true;
    return {ui, tareas, cierres: () => cierres, focos: () => focos, aperturas: () => aperturas};
}

test('abrir y cerrar utilizan el diálogo aunque el evento venga de un descendiente; sólo el fondo exterior cierra', async () => {
    const {ui, cierres, aperturas} = componente({consultarExploradorTaxonomico: async () => resultado('', [nodo('filo')])});
    let restaurados = 0;
    ui.cerrar();
    await ui.abrir({focus() {restaurados++;}});
    assert.equal(aperturas(), 1); assert.equal(ui.$refs.dialogo.open, true); assert.equal(ui.abierto, true);
    ui.cerrarDesdeFondo({target: ui.$el, clientX: 0, clientY: 0});
    ui.cerrarDesdeFondo({target: ui.$refs.dialogo, clientX: 200, clientY: 100});
    assert.equal(cierres(), 1); assert.equal(ui.abierto, true);
    ui.cerrarDesdeFondo({target: ui.$refs.dialogo, clientX: 90, clientY: 100});
    assert.equal(cierres(), 2); assert.equal(ui.$refs.dialogo.open, false); assert.equal(ui.abierto, false);
    assert.equal(restaurados, 1);
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

test('uno cuatro y cinco filos son raíces independientes en paralelo; una raíz queda centrada arriba', () => {
    for (const cantidad of [1, 4, 5]) for (const ancho of [320, 748, 1000]) {
        const raices = Array.from({length: cantidad}, (_, i) => nodo(`filo-${i}`));
        const bosque = distribuirBosque(raices, [], ancho);
        assert.equal(bosque.nodos.length, cantidad);
        assert.equal(bosque.conexiones.length, 0);
        assert.equal(new Set(bosque.nodos.map(item => item.y)).size, 1);
        assert.ok(bosque.nodos.every(item => item.padre === null && item.profundidad === 0));
        if (cantidad === 1) assert.equal(bosque.nodos[0].x + bosque.nodos[0].ancho / 2, bosque.ancho / 2);
        for (const [i, a] of bosque.nodos.entries()) {
            assert.ok(a.x >= 0 && a.x + a.ancho <= bosque.ancho);
            for (const b of bosque.nodos.slice(i + 1)) assert.ok(a.x + a.ancho < b.x);
        }
    }
});

test('ramas extensas conservan el parentesco, el color de su filo y nodos sin superposición', () => {
    const raices = [nodo('a', null, 'Arthropoda'), nodo('b', null, 'Mollusca')];
    const nodos = [...raices, ...Array.from({length: 6}, (_, i) => nodo(`h-${i}`, 'a')), nodo('m', 'b'), nodo('especie', 'm')];
    const bosque = distribuirBosque(nodos, ['a', 'b', 'm'], 740);
    assert.equal(bosque.conexiones.length, 8);
    assert.equal(bosque.nodos.find(item => item.clave === 'especie').profundidad, 2);
    for (const a of bosque.nodos) {
        const padre = bosque.nodos.find(item => item.clave === a.padre);
        if (padre) {assert.ok(a.y > padre.y + padre.alto); assert.equal(a.color, padre.color);}
        for (const b of bosque.nodos.filter(item => item.clave !== a.clave)) {
            assert.equal(a.x < b.x + b.ancho && a.x + a.ancho > b.x && a.y < b.y + b.alto && a.y + a.alto > b.y, false);
        }
    }
    const colapsado = distribuirBosque(nodos, [], 740);
    assert.deepEqual(colapsado.nodos.map(item => item.id), ['a', 'b']);
    const soloUna = distribuirBosque(nodos.filter(item => item.clave === 'a' || item.padre === 'a'), ['a'], 740);
    assert.equal(soloUna.nodos[0].x + soloUna.nodos[0].ancho / 2, soloUna.ancho / 2);
});

test('escribir espera 300 ms y una respuesta antigua no sustituye las coincidencias nuevas', async () => {
    const consultas = [], vieja = diferido(), nueva = diferido();
    const {ui, tareas} = componente({consultarExploradorTaxonomico(padre, texto) {consultas.push([padre, texto]); return texto === 'Coleop' ? vieja.promesa : nueva.promesa;}});
    ui.busqueda = 'Coleop'; ui.buscar();
    assert.equal(consultas.length, 0);
    assert.equal([...tareas.values()][0].retraso, 300);
    [...tareas.values()][0].tarea(); tareas.clear();
    ui.busqueda = 'Mollusca'; ui.buscar(); [...tareas.values()][0].tarea(); tareas.clear();
    nueva.resolver({...resultado('', [nodo('mol', null, 'Mollusca')]), expandidos: ['mol']});
    await Promise.resolve();
    vieja.resolver(resultado('', [nodo('coleop')])); await Promise.resolve();
    assert.deepEqual(consultas, [[null, 'Coleop'], [null, 'Mollusca']]);
    assert.equal(ui.nodos[0].nombre, 'Mollusca');
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
    ui.abierto = true;
    ui.$wire = {consultarExploradorTaxonomico: async (padre, texto) => {
        consultas.push([padre, texto]); return resultado('', [nodo('coleop', null, texto)]);
    }};
    ui.$refs = {lienzo: {clientWidth: 720}};
    ui.$nextTick = tarea => tarea();
    ui.busqueda = 'Coleop'; ui.buscar();
    ui.busqueda = 'Coleoptera'; ui.buscar();
    assert.deepEqual(retrasos, [300, 300]);
    assert.deepEqual(cancelados, [1]); assert.equal(tareas.size, 1);
    const tarea = tareas.get(2); tareas.delete(2); tarea();
    await Promise.resolve();
    assert.deepEqual(consultas, [[null, 'Coleoptera']]);
    assert.equal(ui.nodos[0].nombre, 'Coleoptera'); assert.equal(ui.buscando, false);
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

test('doble clic durante el primer clic reutiliza su consulta y cierra sólo después de actualizar', async () => {
    const respuesta = diferido(), llamadas = [];
    const {ui, cierres} = componente({seleccionarTaxonExplorador(id) {llamadas.push(id); return respuesta.promesa;}});
    const tarea = ui.seleccionar(nodo('filo'));
    ui.seleccionar(nodo('filo')); ui.seleccionar(nodo('filo'), true);
    assert.equal(ui.destacado, 'filo'); assert.equal(cierres(), 0);
    respuesta.resolver({seleccionado: 'filo', total: 17, hoja: false}); await tarea;
    assert.deepEqual(llamadas, ['filo']); assert.equal(ui.total, 17); assert.equal(cierres(), 1);
});

test('expandir conserva a la vista su raíz y contraer recupera los filos sin alterar la selección', async () => {
    const raiz = {...nodo('a', null, 'Arthropoda'), tieneHijos: true};
    const {ui, focos} = componente({consultarExploradorTaxonomico: async () => resultado('', Array.from({length: 6}, (_, i) => nodo(`h-${i}`, 'a')))});
    ui.nodos = [raiz, nodo('b', null, 'Mollusca')]; ui.seleccionado = 'a'; ui.distribuir();
    const posicion = ui.bosque.nodos[0].x;
    await ui.alternar(raiz);
    assert.equal(ui.bosque.nodos[0].x - ui.$refs.lienzo.scrollLeft, posicion);
    ui.volverAFilos();
    assert.equal(ui.bosque.nodos.length, 2); assert.equal(ui.$refs.lienzo.scrollLeft, 0);
    assert.equal(ui.seleccionado, 'a'); assert.equal(focos(), 1);
});

test('un fallo de selección libera la espera y permite reintentar sin cerrar ni perder el filtro anterior', async () => {
    let falla = true;
    const {ui, cierres} = componente({seleccionarTaxonExplorador: async () => {if (falla) throw new Error('Sin conexión'); return {seleccionado: 'nuevo', total: 2, hoja: false};}});
    ui.seleccionado = 'anterior';
    await ui.seleccionar(nodo('nuevo'), true);
    assert.equal(ui.seleccionado, 'anterior'); assert.equal(ui.aplicando, false); assert.equal(cierres(), 0);
    assert.match(ui.error, /reintentar/);
    falla = false; await ui.seleccionar(nodo('nuevo'));
    assert.equal(ui.seleccionado, 'nuevo'); assert.equal(ui.error, ''); assert.equal(cierres(), 0);
});

test('clics rápidos en ramas distintas aplican la última intención sin cerrar con una respuesta anterior', async () => {
    const a = diferido(), b = diferido(), llamadas = [];
    const {ui, cierres} = componente({seleccionarTaxonExplorador(id) {llamadas.push(id); return id === 'a' ? a.promesa : b.promesa;}});
    const tarea = ui.seleccionar(nodo('a'), true);
    ui.seleccionar(nodo('b'));
    a.resolver({seleccionado: 'a', total: 8, hoja: false}); await Promise.resolve();
    assert.equal(cierres(), 0); assert.deepEqual(llamadas, ['a', 'b']);
    b.resolver({seleccionado: 'b', total: 3, hoja: false}); await tarea;
    assert.equal(ui.seleccionado, 'b'); assert.equal(ui.total, 3); assert.equal(cierres(), 0);
});

test('una búsqueda que termina después de seleccionar conserva el contador y la selección del mapa', async () => {
    const consulta = diferido();
    const {ui} = componente({consultarExploradorTaxonomico: () => consulta.promesa, seleccionarTaxonExplorador: async () => ({seleccionado: 'b', total: 3, hoja: false})});
    const leyendo = ui.consultar();
    ui.buscando = false;
    await ui.seleccionar(nodo('b'));
    consulta.resolver(resultado('', [nodo('a')])); await leyendo;
    assert.equal(ui.seleccionado, 'b'); assert.equal(ui.total, 3);
});

test('Enter cancela la espera y consulta el nombre exacto inmediatamente; errores conservan el modal', async () => {
    const llamadas = [];
    const {ui, tareas, cierres} = componente({consultarExploradorTaxonomico() {throw new Error('No debe esperar el debounce');}, confirmarTaxonExplorador: async nombre => {llamadas.push(nombre); return {error: 'Nombre ambiguo'};}});
    ui.busqueda = '  Taxon exacto  '; ui.buscar(); await ui.confirmar();
    assert.equal(tareas.size, 0); assert.deepEqual(llamadas, ['Taxon exacto']);
    assert.equal(ui.error, 'Nombre ambiguo'); assert.equal(cierres(), 0);
    ui.$wire.confirmarTaxonExplorador = async () => ({seleccionado: 'exacto', total: 2, hoja: false});
    await ui.confirmar(); assert.equal(ui.seleccionado, 'exacto'); assert.equal(cierres(), 1);
});

test('Enter desde el campo cancela el debounce, confirma una vez y respeta composición y repetición', async () => {
    const llamadas = [], respuesta = diferido();
    const {ui, tareas, cierres} = componente({
        consultarExploradorTaxonomico() {throw new Error('Enter debe cancelar la consulta diferida');},
        confirmarTaxonExplorador(nombre) {llamadas.push(nombre); return respuesta.promesa;},
    });
    ui.busqueda = '  Taxon exacto  '; ui.buscar();
    for (const cambio of [{key: 'a'}, {isComposing: true}, {keyCode: 229}]) {
        let prevenido = false;
        assert.equal(ui.confirmarConTeclado({key: 'Enter', ...cambio, preventDefault() {prevenido = true;}}), undefined);
        assert.equal(prevenido, false);
    }
    assert.equal(tareas.size, 1); assert.deepEqual(llamadas, []);
    let prevenidos = 0;
    const tarea = ui.confirmarConTeclado({key: 'Enter', preventDefault() {prevenidos++;}});
    ui.confirmarConTeclado({key: 'Enter', repeat: true, preventDefault() {prevenidos++;}});
    assert.equal(prevenidos, 2); assert.equal(tareas.size, 0);
    assert.deepEqual(llamadas, ['Taxon exacto']); assert.equal(cierres(), 0);
    respuesta.resolver({seleccionado: 'exacto', total: 2, hoja: false}); await tarea;
    assert.equal(ui.seleccionado, 'exacto'); assert.equal(ui.total, 2); assert.equal(cierres(), 1);
});

test('una respuesta de una sesión cerrada no cierra el explorador al volver a abrirlo', async () => {
    const respuesta = diferido();
    const {ui, cierres} = componente({seleccionarTaxonExplorador: () => respuesta.promesa, consultarExploradorTaxonomico: async () => resultado('', [nodo('nuevo')])});
    const tarea = ui.seleccionar(nodo('viejo'), true);
    ui.cerrar(); await ui.abrir({focus() {}});
    respuesta.resolver({seleccionado: 'viejo', total: 1, hoja: false}); await tarea;
    assert.equal(cierres(), 1); assert.equal(ui.abierto, true); assert.equal(ui.nodos[0].id, 'nuevo');
});

test('un cierre programado se descarta si antes se elige otra rama', async () => {
    const tareas = [];
    const {ui, cierres} = componente({seleccionarTaxonExplorador: async id => ({seleccionado: id, total: 1, hoja: false})});
    ui.$nextTick = tarea => tareas.push(tarea);
    await ui.seleccionar(nodo('a'), true);
    await ui.seleccionar(nodo('b'));
    for (const tarea of tareas) tarea();
    assert.equal(ui.seleccionado, 'b'); assert.equal(cierres(), 0);
});

test('el doble clic de una hoja solicita encuadre; el de un padre sólo cierra', async () => {
    const anterior = globalThis.window, eventos = [];
    globalThis.window = {dispatchEvent: evento => eventos.push(evento.type)};
    try {
        const {ui, cierres} = componente({seleccionarTaxonExplorador: async id => ({seleccionado: id, total: 1, hoja: true})});
        await ui.seleccionar({...nodo('especie'), hoja: true}, true);
        assert.deepEqual(eventos, ['encuadrar-taxonomia']); assert.equal(cierres(), 1);
    } finally { if (anterior === undefined) delete globalThis.window; else globalThis.window = anterior; }
});
