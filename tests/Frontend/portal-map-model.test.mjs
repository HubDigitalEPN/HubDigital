import test from 'node:test';
import assert from 'node:assert/strict';
import {coordenadasPublicas, radioRegistros, prepararPuntosMapa, crearGeojsonMapa} from '../../resources/js/portal-map-model.js';

test('las coordenadas y GeoJSON conservan la ubicación registrada, fuera del centro de cuadrícula', () => {
    const punto = {lat: '-1.35686', lon: '-79.897321', total: 72, filos: {Mollusca: 72}, taxones: 2};
    assert.deepEqual(coordenadasPublicas(punto), {lat: -1.35686, lon: -79.897321});
    assert.deepEqual(prepararPuntosMapa([punto]).map(({lat, lon, cantidad}) => ({lat, lon, cantidad})), [{lat: -1.35686, lon: -79.897321, cantidad: 72}]);
    const exportacion = crearGeojsonMapa([punto], {consulta: 'https://example.test/portal?ft=Mollusca'});
    assert.equal(exportacion.sistema_coordenadas, 'WGS84');
    assert.equal(exportacion.consulta, 'https://example.test/portal?ft=Mollusca');
    assert.deepEqual(exportacion.features[0].geometry.coordinates, [-79.897321, -1.35686]);
    assert.equal(exportacion.features[0].properties.registros, 72);
});

test('los puntos fuera de WGS84 o sin coordenadas nunca se dibujan ni exportan', () => {
    const invalidos = [
        {lat: 91, lon: -78}, {lat: -91, lon: -78}, {lat: 0, lon: 181},
        {lat: 0, lon: -181}, {lat: null, lon: -78}, {lat: '', lon: -78},
        {lat: 0, lon: '   '}, {lat: 'no disponible', lon: -78}, {lat: Infinity, lon: 0},
        {lat: false, lon: true}, {lat: [], lon: -78}, {},
    ].map(punto => ({...punto, total: 1}));
    for (const punto of invalidos) assert.equal(coordenadasPublicas(punto), null);
    assert.deepEqual(prepararPuntosMapa(invalidos), []);
    assert.deepEqual(crearGeojsonMapa(invalidos).features, []);
    assert.deepEqual(coordenadasPublicas({lat: 0, lon: 0}), {lat: 0, lon: 0});
    assert.deepEqual(coordenadasPublicas({lat: -90, lon: 180}), {lat: -90, lon: 180});
});

test('el área visible distingue cantidades y limita la superposición de marcadores grandes', () => {
    assert.equal(radioRegistros(1), 4.7);
    assert.equal(radioRegistros(100), 11);
    assert.equal(radioRegistros(100000), 17);
    assert.ok(radioRegistros(10) > radioRegistros(1));
    assert.ok(radioRegistros(100) > radioRegistros(10));
    assert.equal(radioRegistros(-2), 4);
    assert.equal(radioRegistros('desconocido'), 4);
});

test('filtrar un filo conserva posición y cantidad propias sin mutar el conjunto público', () => {
    const punto = Object.freeze({lat: .658, lon: -76.452, total: 10, filos: Object.freeze({Arthropoda: 8, Mollusca: 2})});
    const datos = Object.freeze([punto, Object.freeze({lat: 0, lon: -78, total: 3, filos: Object.freeze({Annelida: 3})})]);
    assert.deepEqual(prepararPuntosMapa(datos, 'Mollusca').map(({lat, lon, cantidad}) => ({lat, lon, cantidad})), [{lat: .658, lon: -76.452, cantidad: 2}]);
    assert.equal(prepararPuntosMapa(datos).length, 2);
    assert.deepEqual(prepararPuntosMapa(datos, 'Sin filo'), []);
    assert.equal(punto.total, 10);
});

test('las cantidades vacías o no finitas no producen botones del mapa', () => {
    const datos = [0, -1, null, Infinity, 'desconocido'].map(total => ({lat: -1, lon: -78, total}));
    assert.deepEqual(prepararPuntosMapa(datos), []);
});
