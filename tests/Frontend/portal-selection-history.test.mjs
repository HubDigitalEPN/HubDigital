import test from 'node:test';
import assert from 'node:assert/strict';
import {crearHistorialCatalogo, leerSeleccionCatalogo, normalizarEstadoCatalogo, urlSeleccionCatalogo} from '../../resources/js/portal-history-model.js';

const defectos = {nivel: '', taxon: '', explorar: '', vista: 'tarjetas', pagina: 1, fph: '', fpais: '', fprov: '', fg: [], fp: [], ffd: '', ffh: '', fed: '', feh: ''};
const especie = {...defectos, nivel: 'species', taxon: 'Aulacomya atra', fpais: 'Ecuador', fg: ['Galápagos', 'Isla Santa Cruz']};
const orden = {...especie, nivel: 'order', taxon: 'Mytiloida'};

function navegador(inicial = especie) {
    const location = {href: urlSeleccionCatalogo('https://laboratorio.example/portal/catalogo?origen=coleccion#contenido', inicial, defectos).href};
    const entradas = [{state: {exterior: 'conservar', alpine: {snapshotIdx: 'html-anterior'}}, href: location.href}];
    let indice = 0;
    const history = {
        get state() { return entradas[indice].state; },
        replaceState(state, _titulo, href) { entradas[indice] = {state: structuredClone(state), href}; location.href = href; },
        pushState(state, _titulo, href) { entradas.splice(indice + 1); entradas.push({state: structuredClone(state), href}); indice++; location.href = href; },
    };
    return {history, location, entradas, mover(paso) { indice += paso; location.href = entradas[indice].href; return location.href; }};
}

test('una acción guarda rango y taxón juntos y un Atrás/Adelante restaura todos los filtros', async () => {
    const nav = navegador();
    const restauraciones = [];
    const historial = crearHistorialCatalogo({...nav, estado: especie, defectos, restaurar: async estado => { restauraciones.push(estado); return estado; }});
    historial.recibir(orden);
    assert.equal(nav.entradas.length, 2);
    assert.deepEqual(nav.entradas.map(({state}) => [state.portalCatalogo.estado.nivel, state.portalCatalogo.estado.taxon]), [['species', 'Aulacomya atra'], ['order', 'Mytiloida']]);
    await historial.volver(nav.mover(-1));
    assert.deepEqual(restauraciones[0], especie);
    await historial.volver(nav.mover(1));
    assert.deepEqual(restauraciones[1], orden);
    assert.equal(nav.entradas.length, 2);
});

test('fechas, altitud, listas y vista se guardan en la misma entrada sin historial del borrador', () => {
    const nav = navegador();
    const historial = crearHistorialCatalogo({...nav, estado: especie, defectos, restaurar: async estado => estado});
    const filtrada = {...orden, vista: 'mapa', ffd: '2000-05-01', ffh: '2000-05-31', fed: '1000', feh: '2000', fp: ['Alcohol', 'Seco']};
    historial.recibir({...filtrada, borradorFiltros: {fprov: 'Napo'}});
    historial.recibir(filtrada);
    assert.equal(nav.entradas.length, 2);
    assert.deepEqual(leerSeleccionCatalogo(nav.location.href, defectos), filtrada);
    assert.equal(new URL(nav.location.href).searchParams.get('origen'), 'coleccion');
    assert.equal(new URL(nav.location.href).hash, '#contenido');
    assert.ok(!new URL(nav.location.href).search.includes('borrador'));
});

test('una URL copiada admite índices/listas PHP y descarta tipos anidados sin inventar valores', () => {
    const href = 'https://laboratorio.example/portal/catalogo?nivel=species&taxon=Aulacomya+atra&pagina=2&fg[2]=Yasun%C3%AD&fg[0]=Gal%C3%A1pagos&fp[]=Alcohol&fp[]=Seco&fg[oculto][nombre]=reservado&fph[0]=uuid&vista=mapa';
    const estado = leerSeleccionCatalogo(href, defectos);
    assert.equal(estado.taxon, 'Aulacomya atra');
    assert.equal(estado.pagina, 2);
    assert.deepEqual(estado.fg, ['Galápagos', 'Yasuní']);
    assert.deepEqual(estado.fp, ['Alcohol', 'Seco']);
    assert.equal(estado.fph, '');
    assert.deepEqual(leerSeleccionCatalogo(urlSeleccionCatalogo(href, estado, defectos), defectos), estado);
    assert.equal(normalizarEstadoCatalogo({pagina: 'Infinity', taxon: {nombre: 'inventado'}}, defectos).pagina, 1);
    assert.equal(normalizarEstadoCatalogo({pagina: -2, taxon: {nombre: 'inventado'}}, defectos).taxon, '');
});

test('la canonicalización del servidor reemplaza la entrada restaurada y no crea un paso híbrido', async () => {
    const nav = navegador();
    const historial = crearHistorialCatalogo({...nav, estado: especie, defectos, restaurar: async estado => ({...estado, nivel: 'species'})});
    historial.recibir(orden);
    nav.mover(-1);
    const hibrida = urlSeleccionCatalogo(nav.location.href, {...especie, nivel: 'order'}, defectos).href;
    nav.history.replaceState(nav.history.state, '', hibrida);
    await historial.volver(hibrida);
    assert.equal(nav.entradas.length, 2);
    assert.equal(leerSeleccionCatalogo(nav.location.href, defectos).nivel, 'species');
    assert.equal(leerSeleccionCatalogo(nav.location.href, defectos).taxon, 'Aulacomya atra');
});

test('Atrás rápido ignora la respuesta anterior y aplica la última selección solicitada', async () => {
    const nav = navegador();
    let resolverPrimera;
    const restauraciones = [];
    const historial = crearHistorialCatalogo({...nav, estado: especie, defectos, restaurar: estado => {
        restauraciones.push(estado);
        if (restauraciones.length === 1) return new Promise(resolve => { resolverPrimera = resolve; });
        return Promise.resolve(estado);
    }});
    historial.recibir(orden);
    historial.recibir({...orden, nivel: 'phylum', taxon: 'Mollusca'});
    const primera = historial.volver(nav.mover(-1));
    await historial.volver(nav.mover(-1));
    historial.recibir({...orden, vista: 'registros'}); // Commit tardío de la selección abandonada.
    resolverPrimera(orden);
    await primera;
    assert.deepEqual(restauraciones, [orden, especie]);
    assert.deepEqual(leerSeleccionCatalogo(nav.location.href, defectos), especie);
    assert.equal(nav.entradas.length, 3);
    historial.recibir({...orden, vista: 'registros'}, 0, 0); // Respuesta anterior que termina después de restaurar.
    assert.deepEqual(leerSeleccionCatalogo(nav.location.href, defectos), especie);
    assert.equal(nav.entradas.length, 3);
});

test('los snapshots ajenos no restauran HTML de otra selección y los errores no alteran el historial', async () => {
    const nav = navegador();
    const errores = [];
    const historial = crearHistorialCatalogo({...nav, estado: especie, defectos, restaurar: async () => { throw new Error('red'); }, onError: error => errores.push(error.message)});
    assert.equal(nav.history.state.exterior, 'conservar');
    assert.ok(!nav.history.state.alpine);
    historial.recibir(orden);
    await historial.volver(nav.mover(-1));
    assert.deepEqual(errores, ['red']);
    assert.equal(nav.entradas.length, 2);
    const antes = nav.location.href;
    historial.destroy();
    historial.recibir(orden);
    await historial.volver('https://laboratorio.example/login');
    assert.equal(nav.location.href, antes);
});
