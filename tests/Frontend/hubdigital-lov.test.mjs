import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/hubdigital-lov.js', import.meta.url), 'utf8');
const {crearLov} = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
function lov(options = {}) {
    const state = crearLov({campo: 'cargo', placeholder: 'Selecciona un cargo', opciones: Array.from({length: 45}, (_, i) => ({valor: String(i), etiqueta: `Coordinación técnica ${i}`})), ...options});
    state.$wire = {$get: () => '30'};
    state.$refs = {busqueda: {focus() {}}, lista: {querySelector: () => null}};
    state.$nextTick = callback => callback?.();
    return state;
}
test('abrir la lista incluye el valor actual y mostrar más amplía sin repetir opciones', () => {
    const state = lov();
    assert.equal(state.visibles.length, 20);
    state.abrir();
    assert.equal(state.activa, 30);
    assert.equal(state.visibles.at(-1).valor, '30');
    state.mostrarMas();
    assert.equal(state.visibles.length, 45);
    assert.equal(new Set(state.visibles.map(o => o.valor)).size, 45);
    state.mostrarMas();
    assert.equal(state.visibles.length, 45);
});
test('la búsqueda ignora tildes, reduce la ventana y conserva sólo las coincidencias', () => {
    const state = lov();
    state.abrir();
    state.busqueda = 'coordinacion tecnica 4';
    state.buscar();
    assert.deepEqual(state.visibles.map(o => o.valor), ['4', '14', '24', '34', '40', '41', '42', '43', '44']);
    assert.equal(state.activa, 0);
    assert.equal(state.limite, 20);
});
test('el teclado alcanza una opción fuera de la ventana y la lista bloqueada no abre', () => {
    const state = lov();
    state.activa = 19;
    state.mover(1);
    assert.equal(state.activa, 20);
    assert.equal(state.visibles.at(-1).valor, '20');
    const locked = lov({disabled: true});
    locked.abrir();
    assert.equal(locked.abierto, false);
});
