import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {runInNewContext} from 'node:vm';

const baseSource = await readFile(new URL('../../resources/js/hubdigital-lov.js', import.meta.url), 'utf8');
const {crearLov} = await import('data:text/javascript;base64,' + Buffer.from(baseSource).toString('base64'));
const source = await readFile(new URL('../../public/js/lov-origen.js', import.meta.url), 'utf8');

function popup(call) {
    const window = {hubDigitalLov: crearLov, dispatchEvent() {}};
    runInNewContext(source, {window, clearTimeout, setTimeout, requestAnimationFrame: callback => callback(),
        document: {getElementById: () => null}, CustomEvent: class {constructor(type, options) {this.type = type; this.detail = options.detail;}}});
    let valor = '';
    const state = window.hubDigitalOriginLov({campo: 'localidadOrigenCodigo', opciones: [], placeholder: 'Selecciona una localidad',
        accion: 'seleccionarLocalidadOrigen', busquedaRemota: 'buscarLocalidadesOrigenLov'});
    state.$wire = {$get: () => valor, $call: async (method, ...args) => {
        if (method === 'seleccionarLocalidadOrigen') {valor = args[0]; return;}
        return call(...args);
    }};
    state.$refs = {busqueda: {focus() {}}, lista: {querySelector: () => null}, invocador: {focus() {}}};
    state.$nextTick = callback => callback?.();
    state.abierto = true;
    return state;
}

test('una respuesta atrasada no reemplaza la búsqueda reciente ni reaparece después de cerrar', async () => {
    const respuestas = [];
    const state = popup(() => new Promise(resolve => respuestas.push(resolve)));
    state.busqueda = 'consulta anterior';
    const anterior = state.cargar(false);
    state.busqueda = 'consulta reciente';
    const reciente = state.cargar(false);
    respuestas[1]({opciones: [{valor: 'nuevo', etiqueta: 'Resultado reciente'}], hayMas: false});
    await reciente;
    respuestas[0]({opciones: [{valor: 'viejo', etiqueta: 'Resultado anterior'}], hayMas: false});
    await anterior;
    assert.equal(state.visibles[0].valor, 'nuevo');
    const pendiente = state.cargar(false);
    state.cerrar();
    respuestas[2]({opciones: [{valor: 'tardio', etiqueta: 'Resultado tardío'}], hayMas: false});
    await pendiente;
    assert.equal(state.abierto, false);
    assert.equal(state.visibles[0].valor, 'nuevo');
    assert.equal(state.cargando, false);
});

test('mostrar más conserva las páginas y permite elegir una localidad fuera de las opciones iniciales', async () => {
    const consultas = [];
    const state = popup(async (busqueda, pagina) => {
        consultas.push([busqueda, pagina]);
        return {opciones: [{valor: String(pagina), etiqueta: `Localidad ${pagina}`}], hayMas: pagina === 0};
    });
    state.busqueda = 'localidad';
    await state.cargar(false);
    await state.cargar(true);
    assert.deepEqual(consultas, [['localidad', 0], ['localidad', 1]]);
    assert.equal(state.visibles.length, 2);
    assert.equal(state.hayMas, false);
    await state.escoger('1');
    assert.equal(state.valor, '1');
    assert.equal(state.etiqueta, 'Localidad 1');
    assert.equal(state.abierto, false);
});
