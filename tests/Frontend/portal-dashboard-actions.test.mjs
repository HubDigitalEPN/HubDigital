import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
import {crearAgrupadorMapa, crearGeojsonMapa, prepararPuntosMapa, etiquetaAgrupacionMapa, ZOOM_UBICACIONES_ORIGINALES} from '../../resources/js/portal-map-model.js';
import {colorFilo, composicionFilos, fondoFilos} from '../../resources/js/portal-map-model.js';

const fuenteDashboard = readFileSync(new URL('../../resources/js/portal-dashboard.js', import.meta.url), 'utf8');
const plantillaDashboard = readFileSync(new URL('../../Modules/CatalogoPublico/resources/views/dashboard-coleccion.blade.php', import.meta.url), 'utf8');
const fuenteLivewire = readFileSync(new URL('../../vendor/livewire/livewire/dist/livewire.js', import.meta.url), 'utf8');

function dashboardConMapa(celdas) {
    const registros = new Map();
    const marcadores = [];
    const cuadros = new Map();
    const capasOriginales = [];
    const eventosTeselas = {};
    let redibujosTeselas = 0;
    let siguienteCuadro = 0;
    const nodo = () => ({style: {}, atributos: {}, eventos: {}, setAttribute(clave, valor) {this.atributos[clave] = valor;}, removeAttribute(clave) {delete this.atributos[clave];}, addEventListener(clave, manejador) {this.eventos[clave] = manejador;}, getBoundingClientRect() {return {left: 20, top: 300, bottom: 316, width: 16, height: 16};}});
    const mapa = {getZoom: () => 10, on() {}, fitBounds() {}, createPane: () => ({style: {}}), invalidateSize() {}, remove() {}};
    const capa = {addTo() { return this; }, clearLayers() { marcadores.length = 0; }};
    const control = () => ({addTo() { return this; }, on() { return this; }, redraw() {}});
    const L = {
        map: () => mapa, control: {zoom: control, scale: control}, tileLayer: () => ({
            on(nombres, accion) { for (const nombre of nombres.split(' ')) eventosTeselas[nombre] = accion; return this; },
            addTo() { return this; }, redraw() { redibujosTeselas++; },
        }),
        featureGroup: () => capa, latLngBounds: puntos => ({isValid: () => puntos.length > 0}),
        DomUtil: {create: nodo},
        divIcon: opciones => opciones,
        marker(coordenadas, opciones) { return L.circleMarker(coordenadas, opciones); },
        circleMarker(coordenadas, opciones) {
            const elemento = nodo();
            const marcador = {
                addTo() { marcadores.push({coordenadas, opciones, elemento}); return this; },
                getElement: () => elemento, bindTooltip() {}, on() {},
            };
            return marcador;
        },
    };
    // El adaptador conserva el contrato de construcción de las capas reales.
    L.CircleMarker = {extend: metodos => function (coordenadas, opciones) {
        Object.assign(this, L.circleMarker(coordenadas, opciones), metodos);
        capasOriginales.push(this);
    }};
    L.Marker = {extend: metodos => function (coordenadas, opciones) {
        Object.assign(this, L.marker(coordenadas, opciones), metodos);
        capasOriginales.push(this);
    }};
    const contexto = {
        L, crearAgrupadorMapa, crearGeojsonMapa, prepararPuntosMapa, etiquetaAgrupacionMapa, ZOOM_UBICACIONES_ORIGINALES, colorFilo, composicionFilos, fondoFilos,
        window: {innerWidth: 1280, innerHeight: 800, Alpine: {data: (nombre, fabrica) => registros.set(nombre, fabrica)}, addEventListener() {}},
        document: {documentElement: {classList: {add() {}, remove() {}}}},
        requestAnimationFrame: tarea => { const id = ++siguienteCuadro; cuadros.set(id, tarea); return id; },
        cancelAnimationFrame: id => cuadros.delete(id),
    };
    // Ejecuta el componente real; sólo sustituye imports y el adaptador de Leaflet/DOM.
    runInNewContext(fuenteDashboard.replace(/^import .*;\r?$/gm, ''), contexto);
    const dashboard = registros.get('portalDashboard')(celdas, {Mollusca: 8, Annelida: 2});
    dashboard.$refs = {mapa: {focus() {}}, panelMapa: {scrollIntoView() {}}, ayudaMapa: {getBoundingClientRect: () => ({width: 320, height: 70})}};
    dashboard.$id = () => 'ayuda-mapa-prueba';
    dashboard.$nextTick = tarea => tarea();
    dashboard.init();
    return {dashboard, filtros: registros.get('portalFiltros')(), ventana: contexto.window, marcadores, capasOriginales, eventosTeselas, redibujosTeselas: () => redibujosTeselas, pintar() {
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

test('el selector de localidad busca sin acentos y aplica una opción vigente después de cerrar', () => {
    const {filtros} = dashboardConMapa([]);
    const eventos = [];
    filtros.$el = {getBoundingClientRect: () => ({right: 330})};
    filtros.$refs = {
        datosLocalidades: {dataset: {localidades: JSON.stringify(['La Peña', 'Puyo', 'Río Pastaza'])}},
        dialogoLocalidades: {style: {}, showModal() {eventos.push('abrir');}, close() {eventos.push('cerrar');}},
        buscarLocalidad: {focus() {eventos.push('foco-buscador');}},
        elegirLocalidad: {focus() {eventos.push('foco-filtro');}},
    };
    filtros.$wire = {$set(campo, valor) {eventos.push([campo, Array.from(valor)]);}};
    filtros.$nextTick = accion => accion();
    filtros.abrirLocalidades();
    assert.equal(filtros.localidadesAbiertas, true);
    assert.deepEqual(eventos, ['abrir', 'foco-buscador']);
    filtros.busquedaLocalidad = ' PENA ';
    assert.deepEqual(Array.from(filtros.localidadesEncontradas), ['La Peña']);
    filtros.elegirLocalidad('Localidad ajena');
    assert.equal(eventos.length, 2);
    filtros.elegirLocalidad('La Peña');
    assert.deepEqual(eventos.slice(-2), ['cerrar', ['borradorFiltros.filtroGeografias', ['La Peña']]]);
    filtros.restaurarFocoLocalidades();
    assert.equal(filtros.localidadesAbiertas, false);
    assert.equal(eventos.at(-1), 'foco-filtro');
    filtros.$refs.datosLocalidades.dataset.localidades = JSON.stringify(['Puyo']);
    filtros.abrirLocalidades();
    assert.equal(filtros.busquedaLocalidad, '');
    assert.deepEqual(Array.from(filtros.localidadesEncontradas), ['Puyo']);
    filtros.elegirLocalidad('');
    assert.deepEqual(eventos.at(-1), ['borradorFiltros.filtroGeografias', []]);
});

test('el selector acota el DOM sin perder búsquedas y se coloca junto a los filtros cuando cabe', () => {
    const {filtros, ventana} = dashboardConMapa([]);
    filtros.localidades = Array.from({length: 250}, (_, i) => `Localidad ${i}`);
    assert.equal(filtros.localidadesMostradas.length, 100);
    filtros.busquedaLocalidad = 'Localidad 249';
    assert.deepEqual(Array.from(filtros.localidadesMostradas), ['Localidad 249']);
    filtros.$el = {getBoundingClientRect: () => ({right: 330})};
    filtros.$refs = {dialogoLocalidades: {style: {}}};
    for (const ancho of [1280, 700]) {
        ventana.innerWidth = ancho;
        filtros.posicionarLocalidades();
        const izquierda = parseFloat(filtros.$refs.dialogoLocalidades.style.left);
        const anchoDialogo = parseFloat(filtros.$refs.dialogoLocalidades.style.width);
        assert.ok(izquierda >= 346);
        assert.ok(izquierda + anchoDialogo <= ancho - 16);
        assert.ok(anchoDialogo <= 420);
    }
    ventana.innerWidth = 390;
    filtros.posicionarLocalidades();
    assert.equal(filtros.$refs.dialogoLocalidades.style.width, '358px');
    assert.equal(filtros.$refs.dialogoLocalidades.style.left, '16px');
    let enfocado = 0;
    const botones = Array.from({length: 3}, (_, i) => ({focus() {enfocado = i;}}));
    filtros.$refs.opcionesLocalidades = {querySelectorAll: () => botones};
    for (const [key, actual, esperado] of [['ArrowDown', 0, 1], ['End', 0, 2], ['ArrowDown', 2, 2], ['ArrowUp', 2, 1], ['Home', 2, 0]]) {
        let evitado = false;
        filtros.navegarLocalidades({key, target: botones[actual], preventDefault() {evitado = true;}});
        assert.equal(enfocado, esperado);
        assert.equal(evitado, true);
    }
});

test('el tooltip responde al ratón y al foco sin salir de la ventana y se retira al actualizar', () => {
    const {dashboard, marcadores, ventana} = dashboardConMapa([{lat: -1, lon: -78, total: 1, filos: {Annelida: 1}}]);
    const elemento = marcadores[0].elemento;
    elemento.eventos.mouseenter();
    assert.match(dashboard.ayudaMapaTexto, /coordenadas -1, -78/);
    assert.equal(dashboard.ayudaMapaIzquierda, 8);
    assert.equal(dashboard.ayudaMapaSuperior, 222);
    assert.equal(elemento.atributos['aria-describedby'], 'ayuda-mapa-prueba');
    elemento.eventos.mouseleave();
    assert.equal(dashboard.ayudaMapaTexto, '');
    assert.equal(elemento.atributos['aria-describedby'], undefined);
    elemento.getBoundingClientRect = () => ({left: 1240, top: 2, bottom: 18, width: 16, height: 16});
    elemento.eventos.focus();
    assert.equal(dashboard.ayudaMapaIzquierda, ventana.innerWidth - 320 - 8);
    assert.equal(dashboard.ayudaMapaSuperior, 26);
    elemento.eventos.blur();
    assert.equal(dashboard.ayudaMapaTexto, '');
    elemento.eventos.focus();
    dashboard.actualizar({celdas: [], filos: {}});
    assert.equal(dashboard.ayudaMapaTexto, '');
    assert.equal(elemento.atributos['aria-describedby'], undefined);
});

test('una ubicación compartida muestra todos sus colores y abre las coordenadas originales con teclado', () => {
    const celdas = [{lat: -0.63194, lon: -76.14416, total: 8, filos: {Arthropoda: 6, Mollusca: 2}}];
    const {dashboard, marcadores, pintar} = dashboardConMapa(celdas);
    assert.equal(marcadores.length, 1);
    const mixto = marcadores[0];
    assert.equal(mixto.opciones.icon.className, 'atlas-map-mixed');
    assert.match(mixto.elemento.style.background, /#17699b/);
    assert.match(mixto.elemento.style.background, /#d17d28/);
    assert.match(mixto.elemento.atributos['aria-label'], /Mollusca: 2/);
    const aperturas = [];
    dashboard.abrirUbicacion = (...datos) => aperturas.push(datos.slice(0, 3));
    let evitado = false;
    mixto.elemento.eventos.keydown({key: 'Enter', preventDefault() {evitado = true;}});
    assert.equal(evitado, true);
    assert.deepEqual(Array.from(aperturas[0]), [-0.63194, -76.14416, 8]);
    dashboard.actualizar({celdas: [{...celdas[0], total: 2, filos: {Mollusca: 2}}], filos: {Mollusca: 2}});
    pintar();
    assert.equal(marcadores[0].opciones.fillColor, '#d17d28');
    assert.deepEqual(Array.from(marcadores[0].coordenadas), [-0.63194, -76.14416]);
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

test('las ubicaciones solapadas conservan acceso por teclado al retirar el selector y al filtrar', () => {
    const celdas = [
        {lat: -3.762, lon: -78.502, total: 1, filos: {Annelida: 1}},
        {lat: -3.7620001, lon: -78.5020001, total: 2, filos: {Annelida: 2}},
    ];
    const {dashboard, marcadores, pintar} = dashboardConMapa(celdas);
    const aperturas = [];
    dashboard.abrirUbicacion = (...datos) => aperturas.push(datos.slice(0, 3));
    for (const marcador of marcadores) {
        // El agrupador ordena las coordenadas; ese orden no cambia su identidad.
        const punto = celdas.find(celda => celda.lat === marcador.coordenadas[0] && celda.lon === marcador.coordenadas[1]);
        assert.ok(punto, 'cada marcador corresponde a una ubicación original');
        marcador.elemento.eventos.keydown({key: 'Enter', preventDefault() {}});
        assert.deepEqual(Array.from(aperturas.at(-1)), [punto.lat, punto.lon, punto.total]);
    }
    assert.equal(aperturas.length, 2);
    assert.deepEqual(aperturas.map(datos => Array.from(datos)).sort((a, b) => a[1] - b[1]),
        celdas.map(punto => [punto.lat, punto.lon, punto.total]).sort((a, b) => a[1] - b[1]));
    dashboard.actualizar({celdas: [celdas[1]], filos: {Annelida: 2}}); pintar();
    assert.equal(marcadores.length, 1);
    assert.deepEqual(Array.from(marcadores[0].coordenadas), [celdas[1].lat, celdas[1].lon]);
    marcadores[0].elemento.eventos.keydown({key: 'Enter', preventDefault() {}});
    assert.deepEqual(Array.from(aperturas.at(-1)), [celdas[1].lat, celdas[1].lon, celdas[1].total]);
    assert.equal('abrirUbicacionElegida' in dashboard, false);
});

test('QA7 una tesela recuperada no oculta otro error y el reintento conserva la selección', () => {
    const {dashboard, marcadores, eventosTeselas, redibujosTeselas} = dashboardConMapa([{lat: -3.762, lon: -78.502, total: 1, filos: {Annelida: 1}}]);
    const primera = {coords: {z: 10, x: 1, y: 1}}; const segunda = {coords: {z: 10, x: 2, y: 1}};
    eventosTeselas.tileerror(primera); eventosTeselas.tileerror(segunda);
    eventosTeselas.tileload(primera); assert.equal(dashboard.errorTeselas, true);
    eventosTeselas.tileunload(segunda); assert.equal(dashboard.errorTeselas, false);
    eventosTeselas.tileerror(primera); dashboard.reintentarTeselas();
    assert.equal(redibujosTeselas(), 1); assert.equal(dashboard.errorTeselas, false);
    assert.deepEqual(Array.from(marcadores[0].coordenadas), [-3.762, -78.502]);
});

test('QA7 puntos e iconos conservan la proyección fraccionaria sin redondear ni alterar coordenadas', () => {
    for (const filos of [{Annelida: 1}, {Annelida: 1, Arthropoda: 1}]) {
        const {capasOriginales} = dashboardConMapa([{lat: -3.762, lon: -78.502, total: 2, filos}]);
        const capa = capasOriginales[0]; const latlng = {lat: -3.762, lng: -78.502};
        capa._latlng = latlng;
        capa._map = {getZoom: () => 10, getPixelOrigin: () => ({x: 100, y: 200}), project(valor) {
            assert.equal(valor, latlng);
            return {x: 123.463829091, y: 245.375, subtract(origen) {return {x: this.x - origen.x, y: this.y - origen.y};}};
        }};
        let posicion = null;
        capa._updateBounds = () => {posicion = capa._point;};
        capa._setPos = punto => {posicion = punto;}; capa._icon = {};
        capa.options = {zIndexOffset: 0};
        capa._resetZIndex = () => {assert.equal(capa._zIndex, 45);};
        if (capa._project) capa._project(); else capa.update();
        assert.ok(Math.abs(posicion.x - 23.463829091) < 1e-9); assert.equal(posicion.y, 45.375);
        assert.deepEqual(capa._latlng, latlng);
    }
});

test('QA9 el icono original conserva fracciones durante zoom sin cambiar su latitud o longitud', () => {
    const lat = -.833333333; const lon = -89.5;
    const {capasOriginales} = dashboardConMapa([{lat, lon, total: 2, filos: {Annelida: 1, Arthropoda: 1}}]);
    const icono = capasOriginales[0]; const latlng = {lat, lng: lon};
    const centro = {lat: -1, lng: -78};
    let posicion; icono._latlng = latlng;
    icono._map = {_latLngToNewLayerPoint(origen, zoom, center) {
        assert.equal(origen, latlng); assert.equal(center, centro);
        return {x: 23.44252 * 2 ** (zoom - 9), y: 45.01121 * 2 ** (zoom - 9)};
    }};
    icono._setPos = punto => {posicion = punto;};
    for (const zoom of [9, 10, 11, 9]) {
        icono._animateZoom({zoom, center: centro});
        assert.equal(posicion.x, 23.44252 * 2 ** (zoom - 9));
        assert.equal(posicion.y, 45.01121 * 2 ** (zoom - 9));
        assert.deepEqual(icono._latlng, latlng);
    }
});
