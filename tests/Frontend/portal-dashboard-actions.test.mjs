import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
import {crearAgrupadorMapa, crearGeojsonMapa, prepararPuntosMapa, etiquetaAgrupacionMapa, ZOOM_UBICACIONES_ORIGINALES} from '../../resources/js/portal-map-model.js';

const fuenteDashboard = readFileSync(new URL('../../resources/js/portal-dashboard.js', import.meta.url), 'utf8');
const plantillaDashboard = readFileSync(new URL('../../Modules/CatalogoPublico/resources/views/dashboard-coleccion.blade.php', import.meta.url), 'utf8');
const fuenteLivewire = readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.js', import.meta.url), 'utf8');

function dashboardConMapa(celdas) {
    const registros = new Map();
    const marcadores = [];
    const cuadros = new Map();
    let siguienteCuadro = 0;
    const nodo = () => ({setAttribute() {}, addEventListener() {}});
    const mapa = {getZoom: () => 10, on() {}, fitBounds() {}, createPane: () => ({style: {}}), invalidateSize() {}, remove() {}};
    const capa = {addTo() { return this; }, clearLayers() { marcadores.length = 0; }};
    const control = () => ({addTo() {}});
    const L = {
        map: () => mapa, control: {zoom: control, scale: control}, tileLayer: control,
        featureGroup: () => capa, latLngBounds: puntos => ({isValid: () => puntos.length > 0}),
        DomUtil: {create: nodo},
        circleMarker(coordenadas) {
            const marcador = {
                addTo() { marcadores.push({coordenadas}); return this; },
                getElement: nodo, bindTooltip() {}, on() {},
            };
            return marcador;
        },
    };
    const contexto = {
        L, crearAgrupadorMapa, crearGeojsonMapa, prepararPuntosMapa, etiquetaAgrupacionMapa, ZOOM_UBICACIONES_ORIGINALES,
        window: {Alpine: {data: (nombre, fabrica) => registros.set(nombre, fabrica)}, addEventListener() {}},
        document: {documentElement: {classList: {add() {}, remove() {}}}},
        requestAnimationFrame: tarea => { const id = ++siguienteCuadro; cuadros.set(id, tarea); return id; },
        cancelAnimationFrame: id => cuadros.delete(id),
    };
    // Ejecuta el componente real; sólo sustituye imports y el adaptador de Leaflet/DOM.
    runInNewContext(fuenteDashboard.replace(/^import .*;\r?$/gm, ''), contexto);
    const dashboard = registros.get('portalDashboard')(celdas, {Mollusca: 8, Annelida: 2});
    dashboard.$refs = {mapa: {focus() {}}, panelMapa: {scrollIntoView() {}}};
    dashboard.$nextTick = tarea => tarea();
    dashboard.init();
    return {dashboard, marcadores, pintar() {
        while (cuadros.size) {
            const [id, tarea] = cuadros.entries().next().value;
            cuadros.delete(id);
            tarea();
        }
    }};
}

function activarComposicion(dashboard, identificador) {
    // Usa la contextualización de la versión de Livewire instalada: el scope Alpine
    // puede interceptar una acción sin prefijo, incluso con backend correcto en Pest.
    const funciones = ['getAlpineScopeKeys', 'contextualizeExpression'].map(nombre => {
        const funcion = fuenteLivewire.match(new RegExp(`  function ${nombre}\\([\\s\\S]*?\\n  }`));
        assert.ok(funcion, `Livewire conserva el evaluador ${nombre}`);
        return funcion[0];
    }).join('\n');
    const expresion = plantillaDashboard.match(/wire:click="([^"]*seleccionarFilo[^"]*)"/)[1]
        .replace("@js($idFiloCategoria ?? '')", JSON.stringify(identificador));
    const el = {_x_dataStack: [dashboard], hasAttribute: () => true};
    return runInNewContext(`${funciones}\n${'contextualizeExpression(expresion, el)'}`, {expresion, el});
}

test('composición llega a Livewire por UUID y el mapa reemplaza la población al seleccionar y retirar cada filo', () => {
    const celdas = [
        {lat: -0.63194, lon: -76.14416, total: 8, filos: {Mollusca: 8}},
        {lat: -1.1, lon: -79.2, total: 2, filos: {Annelida: 2}},
    ];
    const {dashboard, marcadores, pintar} = dashboardConMapa(celdas);
    const llamadas = [];
    let activo = '';
    dashboard.$wire = {seleccionarFilo(id) {
        llamadas.push(id);
        activo = activo === id ? '' : id;
        const seleccion = activo === 'id-mollusca' ? [celdas[0]] : activo === 'id-annelida' ? [celdas[1]] : celdas;
        dashboard.actualizar({celdas: seleccion, filos: Object.fromEntries(seleccion.flatMap(celda => Object.entries(celda.filos)))});
    }};
    assert.equal(marcadores.length, 2);
    for (const [id, coordenadas] of [['id-mollusca', [-0.63194, -76.14416]], ['id-annelida', [-1.1, -79.2]]]) {
        const expresion = activarComposicion(dashboard, id);
        runInNewContext(expresion, {$wire: dashboard.$wire, ...dashboard});
        pintar();
        assert.equal(llamadas.at(-1), id);
        assert.equal(marcadores.length, 1);
        assert.deepEqual(Array.from(marcadores[0].coordenadas), coordenadas);
        runInNewContext(expresion, {$wire: dashboard.$wire, ...dashboard});
        pintar();
        assert.equal(marcadores.length, 2);
    }
    assert.deepEqual(llamadas, ['id-mollusca', 'id-mollusca', 'id-annelida', 'id-annelida']);
});

test('una acción explícita de composición conserva el foco del mapa durante su actualización', () => {
    const {dashboard} = dashboardConMapa([]);
    const acciones = [];
    dashboard.$refs = {mapa: {focus: () => acciones.push('foco')}, panelMapa: {scrollIntoView: () => acciones.push('desplazar')}};
    dashboard.recordarAccion({target: {closest: () => ({getAttribute: () => '$wire.seleccionarFilo("id-mollusca")'})}});
    assert.equal(dashboard.enfocarTrasCambio, true);
    dashboard.actualizar({celdas: [], filos: {}});
    assert.equal(dashboard.enfocarTrasCambio, false);
    assert.deepEqual(acciones, ['desplazar', 'foco', 'desplazar', 'foco']);
});
