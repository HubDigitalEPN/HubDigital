import test from 'node:test';
import assert from 'node:assert/strict';
import {coordenadasPublicas, radioRegistros, prepararPuntosMapa, crearGeojsonMapa, crearAgrupadorMapa, etiquetaAgrupacionMapa, ZOOM_UBICACIONES_ORIGINALES} from '../../resources/js/portal-map-model.js';
import {colorFilo, composicionFilos, fondoFilos} from '../../resources/js/portal-map-model.js';

test('el color de cada filo permanece estable y los símbolos mixtos conservan también la minoría', () => {
    const puntos = [
        {lat: -1, lon: -78, total: 5, filos: {Arthropoda: 4, Mollusca: 1}},
        {lat: -1, lon: -78, total: 2, filos: {Annelida: 2}},
        {lat: -1.0001, lon: -78.0001, total: 1, filos: {Nematomorpha: 1}},
    ];
    const mapa = crearAgrupadorMapa(puntos);
    const ubicacion = mapa.originales.find(punto => punto.lat === -1);
    assert.deepEqual(ubicacion.filos, {Arthropoda: 4, Mollusca: 1, Annelida: 2});
    const grupo = mapa.paraZoom(0)[0];
    assert.deepEqual(grupo.filos, {Nematomorpha: 1, Arthropoda: 4, Mollusca: 1, Annelida: 2});
    assert.equal(composicionFilos(grupo.filos).reduce((total, parte) => total + parte.cantidad, 0), 8);
    assert.match(fondoFilos(ubicacion.filos), /#d17d28/);
    assert.match(fondoFilos(ubicacion.filos), /#568c59/);
    const filtrado = crearAgrupadorMapa(puntos, 'Mollusca');
    assert.equal(fondoFilos(filtrado.originales[0].filos), colorFilo('Mollusca'));
    assert.equal(colorFilo('Mollusca'), '#d17d28');
    assert.equal(colorFilo('Nematoda'), '#8c62a5');
    assert.equal(colorFilo('Sin filo'), '#71828d');
    assert.deepEqual(puntos[0].filos, {Arthropoda: 4, Mollusca: 1});
});

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

test('la vista general agrupa ubicaciones cercanas y el detalle recupera únicamente sus coordenadas originales', () => {
    const datos = [
        {lat: -.658, lon: -76.452, total: 13},
        {lat: -.63194, lon: -76.14416, total: 3},
        {lat: -.413, lon: -76.013, total: 2},
        {lat: -.4, lon: -90.3, total: 4},
    ];
    const exportacion = crearGeojsonMapa(datos);
    const modelo = crearAgrupadorMapa(datos);
    const general = modelo.paraZoom(5);
    assert.equal(general.length, 2);
    const grupo = general.find(nodo => nodo.tipo === 'grupo');
    assert.equal(grupo.ubicaciones, 3);
    assert.equal(grupo.cantidad, 18);
    assert.deepEqual(grupo.limites, [[-.658, -76.452], [-.413, -76.013]]);
    assert.ok(grupo.puntos.some(({lat, lon}) => lat === grupo.ancla.lat && lon === grupo.ancla.lon));
    assert.equal(coordenadasPublicas(grupo), null);
    assert.match(etiquetaAgrupacionMapa(grupo), /3 ubicaciones originales/);
    assert.match(etiquetaAgrupacionMapa(grupo), /18 registros/);
    assert.match(etiquetaAgrupacionMapa(grupo), /no representa una nueva coordenada/);
    const detalle = modelo.paraZoom(ZOOM_UBICACIONES_ORIGINALES);
    assert.equal(detalle.length, datos.length);
    assert.ok(detalle.every(nodo => nodo.tipo === 'ubicacion'));
    for (const punto of datos) {
        assert.ok(detalle.some(nodo => nodo.lat === punto.lat && nodo.lon === punto.lon && nodo.cantidad === punto.total));
    }
    assert.deepEqual(crearGeojsonMapa(datos), exportacion);
    assert.deepEqual(crearAgrupadorMapa([...datos].reverse()).paraZoom(5), general);
});

test('cada aumento subdivide sus grupos sin perder, duplicar o fusionar ubicaciones ni registros', () => {
    const datos = Array.from({length: 40}, (_, i) => ({
        lat: -4.7 + (i % 8) * .57, lon: -80.9 + Math.floor(i / 8) * .63, total: i + 1,
    }));
    const modelo = crearAgrupadorMapa(datos);
    const clave = punto => `${punto.lat}:${punto.lon}`;
    let anterior = null;
    for (let zoom = 0; zoom <= ZOOM_UBICACIONES_ORIGINALES; zoom++) {
        const actual = modelo.paraZoom(zoom);
        const ubicaciones = actual.flatMap(nodo => nodo.puntos);
        assert.equal(ubicaciones.length, datos.length);
        assert.equal(new Set(ubicaciones.map(clave)).size, datos.length);
        assert.equal(actual.reduce((total, nodo) => total + nodo.cantidad, 0), 820);
        assert.equal(actual.reduce((total, nodo) => total + nodo.ubicaciones, 0), datos.length);
        if (anterior) {
            assert.ok(actual.length >= anterior.length);
            for (const nodo of actual) {
                assert.ok(anterior.some(padre => nodo.puntos.every(punto => padre.puntos.some(miembro => clave(miembro) === clave(punto)))));
            }
        }
        anterior = actual;
    }
    assert.deepEqual(modelo.paraZoom(5), crearAgrupadorMapa(datos).paraZoom(5));
    assert.deepEqual(modelo.paraZoom(18), modelo.paraZoom(9));
});

test('agrupar aplica el filtro real, suma coordenadas coincidentes y descarta entradas inválidas sin alterar la fuente', () => {
    const datos = Object.freeze([
        Object.freeze({lat: '-1.35686', lon: '-79.897321', total: 10, filos: Object.freeze({Mollusca: 2})}),
        Object.freeze({lat: -1.35686, lon: -79.897321, total: 7, filos: Object.freeze({Mollusca: 3})}),
        Object.freeze({lat: -1, lon: -78, total: 4, filos: Object.freeze({Arthropoda: 4})}),
        Object.freeze({lat: null, lon: -78, total: 8, filos: Object.freeze({Mollusca: 8})}),
        Object.freeze({lat: 91, lon: -78, total: 9, filos: Object.freeze({Mollusca: 9})}),
        Object.freeze({lat: 0, lon: 0, total: Infinity, filos: Object.freeze({Mollusca: Infinity})}),
        Object.freeze({lat: -1, lon: -77, total: 0, filos: Object.freeze({Mollusca: 0})}),
    ]);
    const modelo = crearAgrupadorMapa(datos, 'Mollusca');
    for (const zoom of [0, 5, 8, 9, 18, -10, NaN, Infinity]) {
        const nodos = modelo.paraZoom(zoom);
        assert.equal(nodos.length, 1);
        assert.equal(nodos[0].tipo, 'ubicacion');
        assert.equal(nodos[0].cantidad, 5);
        assert.equal(nodos[0].ubicaciones, 1);
        assert.equal(nodos[0].lat, -1.35686);
        assert.equal(nodos[0].lon, -79.897321);
    }
    assert.equal(datos[0].total, 10);
    assert.equal(datos[1].filos.Mollusca, 3);
    assert.deepEqual(crearAgrupadorMapa(null).paraZoom(5), []);
    assert.deepEqual(crearAgrupadorMapa(datos, 'Annelida').paraZoom(5), []);
});

test('la cercanía se evalúa en pantalla Mercator y no por una separación constante de grados', () => {
    const datos = [{lat: 0, lon: 0, total: 1}, {lat: .5, lon: 0, total: 1},
        {lat: 80, lon: 0, total: 1}, {lat: 80.5, lon: 0, total: 1}];
    const modelo = crearAgrupadorMapa(datos);
    const visibles = modelo.paraZoom(5);
    assert.equal(visibles.length, 3);
    assert.deepEqual(visibles.find(nodo => nodo.tipo === 'grupo').puntos.map(punto => punto.lat), [0, .5]);
    assert.deepEqual(visibles.filter(nodo => nodo.tipo === 'ubicacion').map(nodo => nodo.lat), [80, 80.5]);
    assert.deepEqual(modelo.paraZoom(9).map(nodo => nodo.lat), [0, .5, 80, 80.5]);
    const polares = crearAgrupadorMapa([{lat: 90, lon: 180, total: 2}, {lat: -90, lon: -180, total: 3}]);
    assert.deepEqual(polares.paraZoom(9).map(({lat, lon}) => ({lat, lon})), [{lat: -90, lon: -180}, {lat: 90, lon: 180}]);
});
