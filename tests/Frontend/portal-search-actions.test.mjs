import test from 'node:test';
import assert from 'node:assert/strict';

let crearBusqueda;
globalThis.window = {Alpine: {data: (_nombre, factory) => { crearBusqueda = factory; }}};
await import('../../resources/js/portal-search.js');

function control(campo = 'filtroCatalogo') {
    const llamadas = [];
    const estado = crearBusqueda(campo, '/portal/sugerencias');
    estado.$wire = {[campo]: '', aplicarBusquedaTexto: (...args) => llamadas.push(args)};
    estado.$refs = {entrada: {focus() {}}};
    estado.$watch = () => {};
    estado.init();
    return {estado, llamadas};
}

test('escribir y escoger una sugerencia no aplica filtros hasta Enter', async () => {
    const {estado, llamadas} = control();
    estado.texto = '123'; estado.buscar();
    assert.deepEqual(llamadas, []);
    estado.sugerencias = ['MEPN-INV-123']; estado.elegir(0);
    assert.equal(estado.texto, 'MEPN-INV-123');
    assert.deepEqual(llamadas, []);
    await estado.aplicar({isComposing: true});
    await estado.aplicar({repeat: true});
    assert.deepEqual(llamadas, []);
    await estado.aplicar({});
    assert.deepEqual(llamadas, [['filtroCatalogo', 'MEPN-INV-123']]);
    estado.destroy();
});

test('Enter confirma la sugerencia de teclado y limpiar retira también un borrador sin aplicar', async () => {
    const {estado, llamadas} = control('filtroTaxon');
    estado.texto = 'arth'; estado.sugerencias = ['Arthropoda']; estado.mover(1);
    await estado.aplicar({});
    assert.deepEqual(llamadas, [['filtroTaxon', 'Arthropoda']]);
    estado.texto = 'texto pendiente'; estado.buscar();
    estado.restaurar();
    assert.equal(estado.texto, '');
    assert.equal(estado.abierto, false);
    assert.equal(estado.cargando, false);
    estado.destroy();
});
