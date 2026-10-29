import {test} from 'node:test';
import assert from 'node:assert/strict';
import {crearBusquedaUniversal} from '../../resources/js/portal-universal-search.js';
import {colorCantidadRegistros} from '../../resources/js/portal-map-model.js';

test('el LOV descarta respuestas fuera de orden y solo aplica al seleccionar', async () => {
    const lov = crearBusquedaUniversal(); let terminar; const aplicados = [];
    lov.$wire = {buscarUniversal: texto => texto === 'primero' ? new Promise(r => terminar = r) : Promise.resolve({filas: [{id: 'segundo'}], pagina: 1, hay_mas: false}),
        aplicarUniversal: async (...args) => aplicados.push(args)};
    lov.$refs = {dialogo: {close() {}}, entrada: {focus() {}}};
    lov.consulta = 'primero'; const anterior = lov.buscar();
    lov.consulta = 'segundo'; await lov.buscar();
    terminar({filas: [{id: 'primero'}], pagina: 1, hay_mas: false}); await anterior;
    assert.equal(lov.filas[0].id, 'segundo'); assert.deepEqual(aplicados, []);
    await lov.seleccionar('segundo'); assert.deepEqual(aplicados, [['segundo', 'segundo']]);
});

test('el color aumenta de amarillo hacia rojo según registros sin alterar geometría', () => {
    assert.equal(colorCantidadRegistros(1), '#ffe600');
    assert.equal(colorCantidadRegistros(1000), '#d7191c');
    assert.equal(colorCantidadRegistros(10000), '#d7191c');
    const verde = cantidad => parseInt(colorCantidadRegistros(cantidad).slice(3, 5), 16);
    assert.ok(verde(1) > verde(10) && verde(10) > verde(100) && verde(100) > verde(1000));
    assert.equal(colorCantidadRegistros(NaN), colorCantidadRegistros(1));
});
