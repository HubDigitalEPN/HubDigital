import test from 'node:test';
import assert from 'node:assert/strict';
import {configuracionGraficoPanel, valoresPanel} from '../../resources/js/portal-chart-model.js';
import {colorFilo} from '../../resources/js/portal-map-model.js';

test('las seis representaciones conservan la acción que filtra el mapa y su valor original', () => {
    const casos = [
        ['filos', {Arthropoda: 1127, Nematomorpha: 1}, 'seleccionarFilo', ['Nematomorpha']],
        ['riqueza', [{provincia: 'Orellana', registros: 20}, {provincia: 'Los Ríos', registros: 2}], 'seleccionarProvincia', ['Los Ríos']],
        ['decadas', [{decada: 1900, registros: 2}, {decada: 2000, registros: 8}], 'seleccionarDecada', [2000]],
        ['estacionalidad', [{mes: 2, registros: 8}], 'seleccionarMes', [2]],
        ['altitud', [{desde: 0, hasta: 499, registros: 3}, {desde: 500, hasta: 999, registros: 4}], 'seleccionarAltitud', [500, 999]],
        ['metodos', [{metodo: 'beating', etiqueta: 'Batido de vegetación', registros: 2}, {metodo: 'fogging', etiqueta: 'Nebulización de dosel', registros: 8}], 'seleccionarMetodo', ['fogging']],
    ];
    for (const [tipo, datos, accion, parametros] of casos) {
        const llamadas = [];
        const wire = new Proxy({}, {get: (_, nombre) => (...args) => llamadas.push([nombre, args])});
        const config = configuracionGraficoPanel(tipo, datos, tipo, wire);
        config.options.onClick({}, []);
        assert.equal(llamadas.length, 0);
        config.options.onClick({}, [{index: 1}]);
        assert.deepEqual(llamadas, [[accion, parametros]], tipo);
    }
});

test('el tiempo respeta décadas ausentes y los meses conservan doce posiciones con ceros explícitos', () => {
    const temporal = configuracionGraficoPanel('decadas', [{decada: 1900, registros: 3}, {decada: 2000, registros: 9}], '', {});
    assert.equal(temporal.type, 'line');
    assert.equal(temporal.options.scales.x.type, 'linear');
    assert.deepEqual(temporal.data.datasets[0].data, [{x: 1900, y: 3}, {x: 2000, y: 9}]);
    const mensual = valoresPanel('estacionalidad', [{mes: 2, registros: 8}]);
    assert.equal(mensual.length, 12);
    assert.deepEqual(mensual.slice(0, 3).map(fila => fila.value), [0, 8, 0]);
});

test('los intervalos altitudinales se etiquetan completos y los filos usan los colores del mapa', () => {
    const altitud = configuracionGraficoPanel('altitud', [{desde: 0, hasta: 499, registros: 3}], '', {});
    assert.equal(altitud.type, 'scatter');
    assert.deepEqual(altitud.data.labels, ['0–499 m']);
    assert.deepEqual(altitud.data.datasets[0].data, [{x: 3, y: 0}]);
    const filos = configuracionGraficoPanel('filos', {Nematomorpha: 1, Mollusca: 347}, '', {});
    assert.deepEqual(filos.data.datasets[0].backgroundColor, [colorFilo('Nematomorpha'), colorFilo('Mollusca')]);
    assert.deepEqual(valoresPanel('metodos', [{metodo: 'fogging', etiqueta: 'Nebulización de dosel', registros: 8}])[0], {label: 'Nebulización de dosel', value: 8, x: NaN});
});
