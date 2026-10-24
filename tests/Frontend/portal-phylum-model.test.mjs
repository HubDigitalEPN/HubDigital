import test from 'node:test';
import assert from 'node:assert/strict';
import {crearFiltroFilo, filasFilo} from '../../resources/js/portal-phylum-model.js';

const nodo = (clave, padre = null, tieneHijos = false) => ({clave, id: clave, nombre: clave, padre, tieneHijos, etiqueta: padre ? 'Clase' : 'Filo'});
const raiz = nodo('Arthropoda', null, true), hijo = nodo('Insecta', 'Arthropoda', true), nieto = nodo('Hymenoptera', 'Insecta');
const datos = (valores = {}) => ({contexto: 'global:1', busquedaTaxon: '', nodos: [raiz], precarga: {Arthropoda: [hijo]}, expandidos: [], filos: [], seleccionado: '', ...valores});
function componente(payload = datos()) {
    const ui = crearFiltroFilo(payload, '/portal/filos'); ui.$nextTick = tarea => tarea();
    ui.$wire = {async seleccionarNodoFilo() {}}; ui.init(); return ui;
}

test('las flechas muestran hijos inmediatamente sin modificar los filtros y conservan su rama al contraer', async () => {
    const ui = componente(); let selecciones = 0; ui.$wire.seleccionarNodoFilo = async () => selecciones++;
    assert.deepEqual(ui.filas.map(n => n.clave), ['Arthropoda']);
    await ui.alternar(raiz); assert.deepEqual(ui.filas.map(n => n.clave), ['Arthropoda', 'Insecta']);
    assert.equal(ui.filas[1].profundidad, 1); assert.equal(selecciones, 0);
    await ui.alternar(raiz); await ui.alternar(raiz); assert.equal(ui.filas.length, 2);
});

test('seleccionar un filo abre sus hijos y la actualización conserva los descendientes ya recibidos', async () => {
    const ui = componente(); const ids = []; ui.$wire.seleccionarNodoFilo = async id => ids.push(id);
    await ui.seleccionar(raiz, true); ui.actualizar(datos({filos: ['Arthropoda']}));
    assert.deepEqual(ids, ['Arthropoda']); assert.equal(ui.marcado(raiz), true); assert.equal(ui.filas.length, 2);
    ui.integrar({nodos: [nieto]}); ui.cargados.push('Insecta'); await ui.alternar(hijo);
    ui.actualizar(datos({filos: ['Arthropoda'], seleccionado: 'Insecta'}));
    assert.deepEqual(ui.filas.map(n => n.clave), ['Arthropoda', 'Insecta', 'Hymenoptera']);
    assert.equal(ui.marcado(hijo), true);
});

test('la confirmación sincroniza el hijo marcado y retira visualmente el filo incompatible', async () => {
    const otraRaiz = nodo('Annelida'), entrada = {checked: true};
    const ui = componente(datos({nodos: [raiz, otraRaiz], filos: ['Annelida'], precarga: {Arthropoda: [hijo], Insecta: [nieto]}}));
    ui.$wire.seleccionarNodoFilo = async () => datos({nodos: [raiz, otraRaiz], seleccionado: 'Insecta', expandidos: ['Arthropoda']});
    await ui.seleccionar(hijo, true, entrada);
    assert.equal(ui.marcado(hijo), true); assert.equal(ui.marcado(otraRaiz), false);
    assert.equal(entrada.checked, true); assert.deepEqual(ui.filas.map(n => n.clave), ['Annelida', 'Arthropoda', 'Insecta', 'Hymenoptera']);
});

test('el linaje de una especie resalta sus ancestros sin seleccionarlos como filtros y se limpia al cambiar de contexto',()=>{
    const ui=componente(datos({nodos:[raiz,hijo,nieto],seleccionado:'Hymenoptera',linaje:['Arthropoda','Insecta','Hymenoptera']}));
    assert.equal(ui.enLinaje(hijo),true);assert.equal(ui.marcado(hijo),false);assert.equal(ui.marcado(nieto),true);
    ui.actualizar(datos({contexto:'otra:2'}));assert.equal(ui.enLinaje(hijo),false);
});

test('una respuesta tardía de otro Taxón no repone sus ramas ni cambia el contexto confirmado', async () => {
    const originalFetch = globalThis.fetch, originalWindow = globalThis.window;
    let resolver; globalThis.window = {location: {origin: 'https://portal.test'}};
    globalThis.fetch = async url => {
        assert.equal(url.searchParams.get('padre'), 'Insecta');
        return new Promise(resolve => {resolver = resolve;});
    };
    try {
        const ui = componente(); await ui.alternar(raiz); const pendiente = ui.alternar(hijo);
        const otro = nodo('Annelida'); ui.actualizar(datos({contexto: 'Annelida:2', busquedaTaxon: 'Annelida', nodos: [otro], precarga: {}}));
        resolver({ok: true, async json() {return {nodos: [nieto], precarga: {}};}}); await pendiente;
        assert.deepEqual(ui.filas.map(n => n.clave), ['Annelida']); assert.deepEqual(ui.expandidos, []);
        assert.deepEqual(ui.cargando, []); assert.equal(ui.busquedaTaxon, 'Annelida');
    } finally {globalThis.fetch = originalFetch; globalThis.window = originalWindow;}
});

test('un fallo al confirmar restaura visualmente el checkbox y conserva la selección anterior', async () => {
    const ui = componente(datos({filos: ['Arthropoda']})), entrada = {checked: false};
    ui.$wire.seleccionarNodoFilo = async () => {throw new Error('Sin conexión');};
    await ui.seleccionar(raiz, false, entrada);
    assert.equal(entrada.checked, true); assert.equal(ui.marcado(raiz), true); assert.match(ui.error, /selección anterior/);
});

test('el teclado recorre el padre y los hijos visibles en su orden de explorador', async () => {
    const ui = componente(); let foco = ''; ui.$el = {querySelector(selector) {return {focus() {foco = selector;}};}};
    const tecla = key => ({key, preventDefault() {}});
    ui.teclado(tecla('ArrowRight'), raiz); assert.equal(ui.filas.length, 2);
    ui.teclado(tecla('ArrowDown'), raiz); assert.match(foco, /Insecta/);
    ui.teclado(tecla('ArrowLeft'), hijo); assert.match(foco, /Arthropoda/);
    assert.deepEqual(filasFilo([nieto, hijo, raiz], ['Arthropoda', 'Insecta']).map(n => n.profundidad), [0, 1, 2]);
});

test('un filtro completo abre todos sus niveles y limita el DOM sin perder el último descendiente', () => {
    const ramas = Array.from({length: 800}, (_, i) => nodo('Especie-' + String(i).padStart(4, '0'), 'Insecta'));
    const ui = componente(datos({contexto: 'familia:2', busquedaTaxon: 'Familia', completo: true,
        nodos: [raiz, hijo, ...ramas], precarga: {}, expandidos: ['Arthropoda', 'Insecta']}));
    assert.equal(ui.filas.length, 802);
    assert.deepEqual(ui.cargados, ['Arthropoda', 'Insecta']);
    assert.ok(ui.filasVisibles.length < 25);
    ui.actualizarVentana(802 * 48 - 320);
    assert.equal(ui.filasVisibles.at(-1).clave, 'Especie-0799');
    assert.ok(ui.filasVisibles.length < 25); assert.equal(ui.espacioInferior, 0);
    ui.actualizar(datos({contexto: 'global:3'}));
    assert.equal(ui.espacioSuperior, 0); assert.deepEqual(ui.filas.map(n => n.clave), ['Arthropoda']);
});
