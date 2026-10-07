import test from 'node:test';
import assert from 'node:assert/strict';
import {enlaceRecuperacionCatalogo, vigilarPeticionCatalogo} from '../../resources/js/portal-request-model.js';

const propiedadesCatalogo = {
    nivel: 'nivel', taxon: 'taxon', explorar: 'explorar', vista: 'vista', pagina: 'pagina',
    fc: 'filtroCatalogo', fp: 'filtroPreparaciones', ft: 'filtroTaxon', fti: 'filtroTaxonId', fg: 'filtroGeografias', fco: 'filtroColector',
    ffd: 'filtroFechaDesde', ffh: 'filtroFechaHasta', fm: 'filtroMetodos', flat: 'filtroLatMin', flax: 'filtroLatMax',
    flon: 'filtroLonMin', flox: 'filtroLonMax', fed: 'filtroElevDesde', feh: 'filtroElevHasta', fb: 'filtroBiomas',
    fh: 'filtroHabitat', fsti: 'filtroTipo', fd: 'filtroDisposicion', fca: 'filtroCasta', fes: 'filtroEstadio',
    fpais: 'filtroPais', fprov: 'filtroProvincia', fph: 'filtroFiloId', fmes: 'filtroMes', fid: 'filtroIdentificacion',
    fprovs: 'filtroProvincias', fphs: 'filtroFilos',
    fgeo: 'filtroSoloUbicacion', fap: 'filtroDatosCompletos',
};
const defectosCatalogo = Object.fromEntries(Object.keys(propiedadesCatalogo).map(alias => [alias,
    ['fp', 'fg', 'fm', 'fb', 'fprovs', 'fphs'].includes(alias) ? [] : (alias === 'pagina' ? 1 : (alias === 'vista' ? 'tarjetas' : '')),
]));
const seleccionPrevia = {vista: 'mapa', pagina: 4, nivel: 'species', taxon: 'Camponotus femoratus', fpais: 'Ecuador',
    fprov: 'Orellana', fd: 'in_collection', fsti: 'paratype', fm: ['beating'], ffd: '2001-01-01', ffh: '2002-12-31'};

function recuperarLlamadas(llamadas, {estado = seleccionPrevia, datos = {}, updates = {}, componentes = []} = {}) {
    const configuracion = {estado: {...defectosCatalogo, ...estado}, defectos: defectosCatalogo, propiedades: propiedadesCatalogo};
    const request = {payload: {components: [{snapshot: JSON.stringify({data: {vista: estado.vista, ...datos}}), updates,
        calls: llamadas.map(([method, ...params]) => ({method, params}))}, ...componentes]}};
    return new URL(enlaceRecuperacionCatalogo('https://example.test/portal/catalogo?utm_source=QA6', configuracion, request)).searchParams;
}

test('un cambio a mapa fallido conserva especie, provincia, país y disposición al reintentar', () => {
    const estado = {vista: 'registros', nivel: 'species', taxon: 'Camponotus femoratus', fpais: 'Ecuador', fprov: 'Orellana', fd: 'in_collection', fm: ['beating']};
    const defectos = {vista: 'tarjetas', nivel: '', taxon: '', fpais: '', fprov: '', fd: '', fm: []};
    const propiedades = {vista: 'vista', nivel: 'nivel', taxon: 'taxon', fpais: 'filtroPais', fprov: 'filtroProvincia', fd: 'filtroDisposicion', fm: 'filtroMetodos'};
    const request = {payload: {components: [{snapshot: JSON.stringify({data: {vista: 'registros', filtroMetodos: [['beating'], {s: 'arr'}]}}), updates: {}, calls: [{method: 'cambiarVista', params: ['mapa']}]}]}};
    const url = new URL(enlaceRecuperacionCatalogo('https://example.test/portal/catalogo', {estado, defectos, propiedades}, request));
    assert.equal(url.searchParams.get('vista'), 'mapa');
    for (const clave of ['nivel', 'taxon', 'fpais', 'fprov', 'fd']) assert.equal(url.searchParams.get(clave), estado[clave]);
    assert.equal(url.searchParams.get('fm[0]'), 'beating');
});

test('el enlace de reintento del explorador conserva el UUID elegido y los filtros de contexto', () => {
    const id = '9ea89f9b-e411-4098-8ca4-ff3a374f7c06';
    const p = recuperarLlamadas([['seleccionarTaxonExplorador', id]]);
    assert.equal(p.get('fti'), id); assert.equal(p.get('vista'), 'mapa');
    assert.equal(p.has('nivel'), false); assert.equal(p.has('taxon'), false); assert.equal(p.has('pagina'), false);
    for (const clave of ['fpais', 'fprov', 'fd']) assert.equal(p.get(clave), seleccionPrevia[clave]);
    const borrador = recuperarLlamadas([['aplicarBorrador']], {estado: {...seleccionPrevia, fti: id}, datos: {borradorFiltros: {filtroTaxonId: '7605c19f-ec8f-46b5-a544-c6b3b7431389'}}});
    assert.equal(borrador.get('fti'), id);
    assert.equal(recuperarLlamadas([['navegar', 'class', 'Insecta']], {estado: {...seleccionPrevia, fti: id}}).has('fti'), false);
});

test('la espera cancela la petición y los errores HTTP tienen salida explícita sin reintento automático', () => {
    const callbacks = {};
    const mensajes = [];
    let cancelarPeticion = 0;
    let esperar;
    const interceptor = {request: {cancel() { cancelarPeticion++; }}};
    for (const evento of ['onSend', 'onFinish', 'onFailure', 'onError']) interceptor[evento] = fn => {callbacks[evento] = fn;};
    vigilarPeticionCatalogo(interceptor, mensaje => mensajes.push(mensaje), (fn, ms) => { assert.equal(ms, 30000); esperar = fn; return 7; }, id => assert.equal(id, 7));
    callbacks.onSend();
    esperar();
    callbacks.onFailure();
    callbacks.onFinish();
    assert.equal(cancelarPeticion, 1);
    assert.equal(mensajes.length, 1);
    assert.match(mensajes[0], /conservando tu selección/);
    let impedido = false;
    callbacks.onError({response: {status: 524}, preventDefault() {impedido = true;}});
    assert.equal(impedido, true);
    assert.equal(cancelarPeticion, 1);
});

test('una respuesta normal limpia el temporizador y un error de sesión conserva el manejo del framework', () => {
    const callbacks = {};
    let limpiado = false;
    const interceptor = {request: {cancel() { throw new Error('No debe cancelar una respuesta normal'); }}};
    for (const evento of ['onSend', 'onFinish', 'onFailure', 'onError']) interceptor[evento] = fn => {callbacks[evento] = fn;};
    vigilarPeticionCatalogo(interceptor, () => { throw new Error('No debe mostrar error'); }, () => 9, id => { assert.equal(id, 9); limpiado = true; });
    callbacks.onSend();
    callbacks.onFinish();
    callbacks.onError({response: {status: 419}, preventDefault() { throw new Error('No debe ocultar sesión expirada'); }});
    assert.equal(limpiado, true);
});

test('una cancelación externa limpia la espera sin avisos tardíos ni fallo de conexión', () => {
    const callbacks = {};
    const controller = new AbortController();
    let cancelada = false;
    let esperar;
    let limpiados = 0;
    const interceptor = {request: {controller, isCancelled: () => cancelada,
        cancel() { throw new Error('La cancelación externa ya ocurrió'); }}};
    for (const evento of ['onSend', 'onFinish', 'onFailure', 'onError']) interceptor[evento] = fn => {callbacks[evento] = fn;};
    vigilarPeticionCatalogo(interceptor, () => { throw new Error('No debe mostrar un fallo por navegación'); },
        fn => { esperar = fn; return 11; }, id => { assert.equal(id, 11); limpiados++; });
    callbacks.onSend();
    cancelada = true;
    controller.abort();
    assert.equal(limpiados, 1);
    esperar();
    callbacks.onFailure();
    callbacks.onFinish();
    assert.equal(limpiados, 1);
});

test('una petición ya cancelada no programa espera y una respuesta terminada no genera aviso tardío', () => {
    const callbacks = {};
    let cancelada = true;
    let esperar;
    let programados = 0;
    const interceptor = {request: {isCancelled: () => cancelada,
        cancel() { throw new Error('No debe cancelar'); }}};
    for (const evento of ['onSend', 'onFinish', 'onFailure', 'onError']) interceptor[evento] = fn => {callbacks[evento] = fn;};
    vigilarPeticionCatalogo(interceptor, () => { throw new Error('No debe mostrar error'); },
        fn => { programados++; esperar = fn; return 12; }, id => assert.equal(id, 12));
    callbacks.onSend();
    assert.equal(programados, 0);
    cancelada = false;
    callbacks.onSend();
    assert.equal(programados, 1);
    callbacks.onFinish();
    esperar();
});

test('reintentar una técnica y área nuevas conserva especie geografía disposición y tipo con página reiniciada', () => {
    const parametros = recuperarLlamadas([['seleccionarMetodo', ' Beating '], ['seleccionarArea', -1, 0, -77, -76]]);
    assert.equal(parametros.get('fm[0]'), 'beating');
    assert.deepEqual(['flat', 'flax', 'flon', 'flox'].map(alias => parametros.get(alias)), ['-1', '0', '-77', '-76']);
    for (const alias of ['nivel', 'taxon', 'fpais', 'fprov', 'fsti', 'fd']) assert.equal(parametros.get(alias), seleccionPrevia[alias]);
    assert.equal(parametros.has('pagina'), false);
    assert.equal(parametros.get('utm_source'), 'QA6');
});

test('las barras de provincia década mes altitud y filo reproducen su selección al recuperar', () => {
    const filo = '11111111-1111-4111-8111-111111111111';
    const parametros = recuperarLlamadas([['seleccionarProvincia', 'Nariño'], ['seleccionarDecada', 1990],
        ['seleccionarMes', 5], ['seleccionarAltitud', 0, 499], ['seleccionarFilo', filo]]);
    assert.equal(parametros.has('fprov'), false);
    assert.equal(parametros.get('fprovs[0]'), 'Orellana');
    assert.equal(parametros.get('fprovs[1]'), 'Nariño');
    assert.equal(parametros.get('ffd'), '1990-01-01');
    assert.equal(parametros.get('ffh'), '1999-12-31');
    assert.equal(parametros.get('fmes'), '5');
    assert.equal(parametros.get('fed'), '0');
    assert.equal(parametros.get('feh'), '499');
    assert.equal(parametros.get('fph'), filo);
    assert.equal(parametros.get('fpais'), 'Ecuador');
    const sinFilo = recuperarLlamadas([['seleccionarFilo', filo], ['seleccionarFilo', filo]]);
    assert.equal(sinFilo.has('fph'), false);
});

test('la restauración parcial reemplaza filtros omitidos y las llamadas posteriores se aplican en orden', () => {
    const parametros = recuperarLlamadas([['restaurarSeleccionUrl', {vista: 'registros', fprov: 'Chocó', pagina: 2}], ['cambiarVista', 'mapa']]);
    assert.equal(parametros.get('vista'), 'mapa');
    assert.equal(parametros.get('fprov'), 'Chocó');
    for (const alias of ['nivel', 'taxon', 'fpais', 'fd', 'fsti', 'fm[0]', 'ffd', 'ffh', 'pagina']) assert.equal(parametros.has(alias), false);
});

test('restaurar o limpiar sincroniza el borrador y no resucita filtros previos en una llamada posterior', () => {
    const datos = {borradorFiltros: [{filtroProvincia: 'Orellana', filtroMetodos: [['beating'], {s: 'arr'}]}, {s: 'arr'}]};
    const restaurado = recuperarLlamadas([['restaurarSeleccionUrl', {fprov: 'Pichincha', fm: ['Pitfall', 'pitfall'], explorar: 'family',
        nivel: 'species', taxon: 'Camponotus femoratus'}], ['aplicarBorrador']], {datos});
    assert.equal(restaurado.get('fprov'), 'Pichincha');
    assert.deepEqual(restaurado.getAll('fm[0]'), ['pitfall']);
    assert.equal(restaurado.has('fm[1]'), false);
    assert.equal(restaurado.get('explorar'), 'family');
    for (const alias of ['nivel', 'taxon', 'fpais', 'fd']) assert.equal(restaurado.has(alias), false);
    const limpio = recuperarLlamadas([['limpiarFiltros'], ['aplicarBorrador']], {datos});
    assert.deepEqual([...limpio], [['utm_source', 'QA6'], ['vista', 'mapa']]);
});

test('el borrador recupera cambios anidados de checkbox y una coordenada exacta desde tuplas Livewire', () => {
    const parametros = recuperarLlamadas([], {
        datos: {borradorFiltros: [{filtroMetodos: [['beating'], {s: 'arr'}], filtroProvincia: 'Orellana',
            filtroLatitud: '', filtroLongitud: '', filtroLatMin: '', filtroLatMax: '', filtroLonMin: '', filtroLonMax: ''}, {s: 'arr'}]},
        updates: {'borradorFiltros.filtroMetodos.0': 'Pitfall', 'borradorFiltros.filtroProvincia': 'Pichincha',
            'borradorFiltros.filtroLatitud': '-0.5', 'borradorFiltros.filtroLongitud': '-78.5'},
    });
    assert.equal(parametros.get('fm[0]'), 'pitfall');
    assert.equal(parametros.get('fprov'), 'Pichincha');
    assert.deepEqual(['flat', 'flax', 'flon', 'flox'].map(alias => parametros.get(alias)), ['-0.5', '-0.5', '-78.5', '-78.5']);
    assert.equal(parametros.get('fd'), 'in_collection');
    assert.equal(parametros.has('pagina'), false);
});

test('navegar y explorar reinician la página conservando los filtros mientras limpiar borra toda la selección', () => {
    const navegar = recuperarLlamadas([['navegar', 'genus', 'Camponotus']]);
    assert.equal(navegar.get('nivel'), 'genus');
    assert.equal(navegar.get('taxon'), 'Camponotus');
    assert.equal(navegar.get('fprov'), 'Orellana');
    assert.equal(navegar.has('pagina'), false);
    const explorar = recuperarLlamadas([['explorarNivel', 'family']]);
    assert.equal(explorar.get('explorar'), 'family');
    assert.equal(explorar.has('nivel'), false);
    assert.equal(explorar.has('taxon'), false);
    assert.equal(explorar.get('fd'), 'in_collection');
    const limpiar = recuperarLlamadas([['limpiarFiltros']]);
    assert.deepEqual([...limpiar], [['utm_source', 'QA6'], ['vista', 'mapa']]);
});

test('los accesos a registros completos georreferenciados y especie conservan su intención', () => {
    const completos = recuperarLlamadas([['filtrarCompletos']]);
    assert.equal(completos.get('fap'), '1');
    assert.equal(completos.get('vista'), 'registros');
    assert.equal(completos.get('taxon'), 'Camponotus femoratus');
    const ubicados = recuperarLlamadas([['verGeorreferenciados']]);
    assert.equal(ubicados.get('fgeo'), '1');
    assert.equal(ubicados.get('vista'), 'registros');
    const especie = recuperarLlamadas([['explorarEspecie', 'Camponotus cruentatus']]);
    assert.equal(especie.get('ft'), 'Camponotus cruentatus');
    assert.equal(especie.get('vista'), 'registros');
    assert.equal(especie.has('nivel'), false);
    assert.equal(especie.has('taxon'), false);
    assert.equal(especie.get('fprov'), 'Orellana');
});

test('actualizaciones anidadas directas preservan métodos y otros componentes no sobrescriben la selección', () => {
    const parametros = recuperarLlamadas([['actualizarFiltros']], {
        datos: {filtroMetodos: [['beating'], {s: 'arr'}]}, updates: {'filtroMetodos.0': 'Pitfall', filtroDisposicion: 'on_loan'},
        componentes: [{snapshot: JSON.stringify({data: {pregunta: '¿Cuántos?'}}), updates: {filtroProvincia: 'Quito'}, calls: []}],
    });
    assert.equal(parametros.get('fm[0]'), 'pitfall');
    assert.equal(parametros.get('fd'), 'on_loan');
    assert.equal(parametros.get('fprov'), 'Orellana');
});

test('los rangos y vistas inválidos no reemplazan una selección recuperable', () => {
    const parametros = recuperarLlamadas([['seleccionarMes', 13], ['seleccionarDecada', 2026], ['seleccionarAltitud', 1000, 0],
        ['seleccionarArea', 1, -1, -77, -76], ['cambiarVista', 'inexistente']]);
    assert.equal(parametros.get('pagina'), '4');
    assert.equal(parametros.get('vista'), 'mapa');
    assert.equal(parametros.get('ffd'), '2001-01-01');
    assert.equal(parametros.get('ffh'), '2002-12-31');
    for (const alias of ['fmes', 'fed', 'feh', 'flat', 'flax', 'flon', 'flox']) assert.equal(parametros.has(alias), false);
});


test('reintentar filtros múltiples y retirar una selección conserva las otras y descarta el alias escalar anterior', () => {
    const parametros = recuperarLlamadas([], {
        datos: {borradorFiltros: [{filtroFiloId: 'anterior', filtroFilos: [['anterior'], {s: 'arr'}], filtroProvincia: 'Orellana', filtroProvincias: [['Orellana'], {s: 'arr'}]}, {s: 'arr'}]},
        updates: {'borradorFiltros.filtroFilos': ['filo-1', 'filo-2', 'filo-3', 'filo-4'], 'borradorFiltros.filtroProvincias': ['Loja', 'Pastaza']},
    });
    assert.equal(parametros.has('fph'), false);
    assert.equal(parametros.has('fprov'), false);
    assert.deepEqual([0, 1, 2, 3].map(i => parametros.get(`fphs[${i}]`)), ['filo-1', 'filo-2', 'filo-3', 'filo-4']);
    assert.deepEqual([0, 1].map(i => parametros.get(`fprovs[${i}]`)), ['Loja', 'Pastaza']);
    const retirado = recuperarLlamadas([['seleccionarFilo', 'filo-2'], ['aplicarBorrador']], {estado: {...seleccionPrevia, fphs: ['filo-1', 'filo-2', 'filo-3']}});
    assert.deepEqual([0, 1].map(i => retirado.get(`fphs[${i}]`)), ['filo-1', 'filo-3']);
    assert.equal(retirado.get('fprov'), 'Orellana');
    const criterio = recuperarLlamadas([['retirarCriterio', 'filtroFilos', 1], ['aplicarBorrador']], {estado: {...seleccionPrevia, fphs: ['filo-1', 'filo-2', 'filo-3']}});
    assert.deepEqual([0, 1].map(i => criterio.get(`fphs[${i}]`)), ['filo-1', 'filo-3']);
    assert.equal(criterio.get('fprov'), 'Orellana');
    assert.equal(criterio.get('ffd'), seleccionPrevia.ffd);
});
