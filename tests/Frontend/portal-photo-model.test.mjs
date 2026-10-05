import test from 'node:test';
import assert from 'node:assert/strict';
import {consultaFotografias, seleccionarTaxonExacto, linajeFotografia, urlFotoINaturalist, fotografiasDeObservaciones, fotografiasLocalesValidas, combinarFotografias, crearCacheFotografias, candidatasEcuador, LUGAR_ECUADOR_INATURALIST, descripcionFotografias} from '../../resources/js/portal-photo-model.js';
import {crearEstadoFotografias} from '../../resources/js/portal-photos.js';

const nodo = (id, name, rank, parent_id) => ({id, name, rank, parent_id, is_active: true});
const base = [nodo(1, 'Animalia', 'kingdom', 100), nodo(2, 'Arthropoda', 'phylum', 1), nodo(3, 'Insecta', 'class', 2), nodo(4, 'Hymenoptera', 'order', 3), nodo(5, 'Formicidae', 'family', 4)];
function especie(name = 'Camponotus sericeiventris', id = 7, genus = 'Camponotus', genusId = 6) {
    return {...nodo(id, name, 'species', genusId), ancestors: [...base, nodo(genusId, genus, 'genus', 5)]};
}
const familia = {...base[4], ancestors: base.slice(0, 4)};
const foto = (id, license_code = 'cc-by') => ({id, license_code, attribution: 'Autora de la foto / iNaturalist', url: `https://inaturalist-open-data.s3.amazonaws.com/photos/${id}/square.jpg`, hidden: false, flags: []});
const observacion = (id, taxon, photos) => ({id, quality_grade: 'research', place_ids: [LUGAR_ECUADOR_INATURALIST], taxon: {id: taxon.id, name: taxon.name, rank: taxon.rank, is_active: true, ancestor_ids: taxon.ancestors.map(n => n.id)}, photos});
// Metadatos simulados de licencia abierta; no atribuir país ni autoría a la foto AntWeb real.
const local = (nombre = 'Camponotus sericeiventris', archivo = 'camponotus-sericeiventris') => ({kingdom: 'Animalia', phylum: 'Arthropoda', class: 'Insecta', order: 'Hymenoptera', family: 'Formicidae', genus: 'Camponotus', species: nombre, url: `/images/taxonomia/fotografias/${archivo}.webp?v=20261002-fuentes1`, alt: `Fotografía de referencia de ${nombre}.`, descripcion: '', foto_real: true, morfologia: true, autor: '', autor_fuente: 'Autor de referencia', fuente: 'https://www.inaturalist.org/observations/20', licencia: 'CC0 1.0', licencia_url: 'https://creativecommons.org/publicdomain/zero/1.0/'});
const respuesta = datos => ({ok: true, headers: {get: () => null}, text: async () => JSON.stringify(datos)});

test('mostrar una sola referencia local evita consultas para completar un mosaico', async () => {
    const anterior = globalThis.fetch;
    let peticiones = 0;
    globalThis.fetch = async () => {peticiones++; throw new Error('No debe completar cuatro fotografías');};
    try {
        const estado = crearEstadoFotografias({species: 'Camponotus sericeiventris'}, [local()], 1);
        estado.init();
        await estado.cargar();
        assert.equal(peticiones, 0);
        assert.equal(estado.fotos.length, 1);
        assert.equal(estado.cargando, false);
        estado.destroy();
    } finally {globalThis.fetch = anterior;}
});

test('la consulta conserva la identidad más específica y no reduce una especie a su familia', () => {
    const consulta = consultaFotografias({family: 'Formicidae', genus: 'Camponotus', species: '\u00a0Camponotus  femoratus\u00a0'});
    assert.equal(consulta.rank, 'species');
    assert.equal(consulta.name, 'Camponotus femoratus');
    assert.equal(consulta.linaje.family, 'Formicidae');
    assert.equal(consultaFotografias({rank: 'especie', name: 'Camponotus sericeiventris', species: 'Camponotus femoratus'}), null);
    assert.equal(consultaFotografias({rank: 'rango-desconocido', name: 'nota', family: 'Formicidae'}), null);
    assert.equal(consultaFotografias({family: '\u00a0\u2002'}), null);
    const intermedio = consultaFotografias({ancestros: [{rango: 'Familia', nombre: 'Formicidae'}, {rango: 'Subfamilia', nombre: 'Formicinae'}]});
    assert.equal(intermedio.rank, 'subfamily');
    assert.equal(intermedio.name, 'Formicinae');
    assert.equal(consultaFotografias({family: 'Formicidae', _rango_desconocido: 'Dato pendiente'}), null);
    assert.equal(consultaFotografias({ancestros: [{rango: 'Familia', nombre: 'Formicidae'}, {rango: 'No documentado', nombre: 'Otro dato'}]}), null);
    assert.equal(consultaFotografias({genus: 'Camponotus', subgenus: 'Myrmaphaenus'}).api, false);
    assert.equal(consultaFotografias({kingdom: 'Animalia', subkingdom: 'Eumetazoa'}).api, false);
    assert.equal(consultaFotografias({class: 'Insecta', infraclass: 'Neoptera'}).api, false);
});

test('la búsqueda acepta únicamente un nombre activo exacto con su rango, sin sinónimos ni resultados parciales', () => {
    const consulta = consultaFotografias({species: 'Camponotus sericeiventris'});
    const correcto = especie();
    assert.equal(seleccionarTaxonExacto({total_results: 3, results: [{...correcto, name: 'Camponotus femoratus', matched_term: consulta.name}, {...correcto, id: 8, rank: 'subspecies'}, correcto]}, consulta), correcto);
    for (const results of [[{...correcto, is_active: false}], [{...correcto, name: 'Camponotus sericeiventris Fabricius'}], [correcto, {...correcto, id: 9}], []]) assert.equal(seleccionarTaxonExacto({results}, consulta), null);
    assert.equal(seleccionarTaxonExacto({total_results: 31, results: [correcto]}, consulta), null);
});

test('el mosaico familiar reúne cuatro fotos distintas con especie, género, familia y crédito de cada fuente', () => {
    const a = especie();
    const b = especie('Paraponera clavata', 9, 'Paraponera', 8);
    const observaciones = [observacion(20, a, [foto(11, 'cc-by-nc'), foto(12), foto(12)]), observacion(21, b, [foto(13, 'cc0'), foto(14, 'cc-by-sa'), foto(15), foto(16)])];
    const fotos = fotografiasDeObservaciones(observaciones, [familia, a, b], familia, consultaFotografias({family: 'Formicidae'}));
    assert.equal(fotos.length, 4);
    assert.equal(new Set(fotos.map(imagen => imagen.id)).size, 4);
    assert.deepEqual(fotos.map(imagen => imagen.species), ['Camponotus sericeiventris', 'Paraponera clavata', 'Paraponera clavata', 'Paraponera clavata']);
    assert.ok(fotos.every(imagen => imagen.family === 'Formicidae' && imagen.autor === 'Autora de la foto / iNaturalist' && imagen.url.includes('/medium.jpg')));
    assert.equal(fotos[0].fuente_url, 'https://www.inaturalist.org/observations/20');
    assert.equal(fotos[1].licencia, 'CC0 1.0');
    assert.equal(fotos[2].licencia, 'CC BY-SA 4.0');
    assert.ok(fotos.every(imagen => imagen.descripcion.includes('no corresponde a un ejemplar de la colección')));
});

test('una especie o género seleccionado rechaza otra especie, un ancestro homónimo y un linaje ajeno', () => {
    const a = especie();
    const b = especie('Camponotus femoratus', 10);
    const otra = especie('Paraponera clavata', 9, 'Paraponera', 8);
    const consulta = consultaFotografias({family: 'Formicidae', genus: 'Camponotus', species: a.name});
    assert.deepEqual(fotografiasDeObservaciones([observacion(20, b, [foto(11)]), observacion(21, otra, [foto(12)])], [a, b, otra], a, consulta), []);
    const genero = {...a.ancestors.at(-1), ancestors: base};
    assert.equal(fotografiasDeObservaciones([observacion(20, a, [foto(11)]), observacion(21, otra, [foto(12)])], [genero, a, otra], genero, consultaFotografias({genus: 'Camponotus', family: 'Formicidae'})).length, 1);
    const ajeno = structuredClone(a);
    ajeno.ancestors[4].name = 'Vespidae';
    assert.deepEqual(fotografiasDeObservaciones([observacion(20, ajeno, [foto(11)])], [familia, ajeno], familia, consultaFotografias({family: 'Formicidae'})), []);
    assert.deepEqual(fotografiasDeObservaciones([observacion(20, {...a, name: 'Identificación diferente'}, [foto(11)])], [a], a, consulta), []);
});

test('la cadena exige padres reales, IDs únicos y clasificación completa, sin reconstruir ancestros ocultos en la salida', () => {
    const a = especie();
    for (const modificar of [taxon => {taxon.parent_id = 999;}, taxon => {taxon.ancestors[2].id = 1;}, taxon => {taxon.ancestors[4].is_active = false;}, taxon => {delete taxon.ancestors;}]) {
        const corrupto = structuredClone(a);
        modificar(corrupto);
        assert.equal(linajeFotografia(corrupto), null);
    }
    const fotos = fotografiasDeObservaciones([observacion(20, a, [foto(11)])], [a], a, consultaFotografias({species: a.name}));
    assert.equal(fotos.length, 1);
    assert.equal(fotos[0].family, '');
    assert.equal(fotos[0].genus, '');
    assert.ok(!fotos[0].descripcion.includes('Formicidae') && !fotos[0].descripcion.includes('Género Camponotus'));
    const incompleto = {...a, ancestors: a.ancestors.filter(n => n.rank !== 'family')};
    assert.equal(linajeFotografia(incompleto), null);
    assert.deepEqual(fotografiasDeObservaciones([observacion(20, a, [foto(11)])], [a, a], a, consultaFotografias({species: a.name})), []);
});

test('cada foto verifica su propia licencia, visibilidad, host HTTPS e ID del recurso', () => {
    assert.equal(urlFotoINaturalist(foto(11)), 'https://inaturalist-open-data.s3.amazonaws.com/photos/11/medium.jpg');
    const invalidados = [
        {...foto(11), license_code: 'cc-by-nc'}, {...foto(11), license_code: null}, {...foto(11), hidden: true},
        {...foto(11), flags: [{reason: 'redactado'}]}, {...foto(11), moderator_actions: [{hidden: true}]},
        {...foto(11), url: 'http://inaturalist-open-data.s3.amazonaws.com/photos/11/square.jpg'},
        {...foto(11), url: 'https://static.inaturalist.org/photos/11/square.jpg'},
        {...foto(11), url: 'https://inaturalist-open-data.s3.amazonaws.com.attacker.test/photos/11/square.jpg'},
        {...foto(11), url: 'https://user@inaturalist-open-data.s3.amazonaws.com/photos/11/square.jpg'},
        {...foto(11), url: 'https://inaturalist-open-data.s3.amazonaws.com/photos/12/square.jpg'},
        {...foto(11), url: 'https://inaturalist-open-data.s3.amazonaws.com/photos/11/square.jpg?token=x'},
        {...foto(11), url: 'javascript:alert(1)'}, {...foto(11), id: '11'},
    ];
    assert.ok(invalidados.every(imagen => urlFotoINaturalist(imagen) === null));
    assert.equal(urlFotoINaturalist({...foto(11), url: 'https://inaturalist-open-data.s3.amazonaws.com/photos/11/square.jpeg'}), 'https://inaturalist-open-data.s3.amazonaws.com/photos/11/medium.jpeg');
});

test('los originales locales siguen su selección exacta y jamás usan imágenes generadas o enlaces arbitrarios', () => {
    const original = Object.freeze(local());
    assert.deepEqual(fotografiasLocalesValidas([original, original], consultaFotografias({species: original.species, genus: 'Camponotus', family: 'Formicidae'})), [original]);
    const ocultos = fotografiasLocalesValidas([{...original, alt: 'Nombre familiar Formicidae', descripcion: 'Familia Formicidae y género Camponotus'}], consultaFotografias({species: original.species}));
    assert.equal(ocultos[0].family, '');
    assert.equal(ocultos[0].genus, '');
    assert.ok(!ocultos[0].alt.includes('Formicidae') && !ocultos[0].descripcion.includes('Formicidae'));
    assert.deepEqual(fotografiasLocalesValidas([original], consultaFotografias({species: 'Camponotus femoratus'})), []);
    assert.deepEqual(fotografiasLocalesValidas([{...original, foto_real: false}, {...original, url: '/images/taxonomia/ant-ai.webp'}, {...original, fuente: 'javascript:alert(1)'}, {...original, licencia_url: true}], null), []);
    const grupo = Array.from({length: 5}, (_, indice) => local(`Camponotus referencia${indice}`, `referencia-${indice}`));
    assert.equal(fotografiasLocalesValidas(grupo, consultaFotografias({family: 'Formicidae'})).length, 4);
    assert.equal(combinarFotografias([original], [{...original, id: 3}, ...grupo]).length, 4);
});

test('la caché limita 32 selecciones y cuatro metadatos, expira y protege las respuestas frente a mutaciones', () => {
    let tiempo = 100;
    const cache = crearCacheFotografias(() => tiempo);
    for (let indice = 0; indice < 33; indice++) cache.guardar(String(indice), Array.from({length: 9}, (_, id) => ({id, url: `foto-${id}`})));
    assert.equal(cache.obtener('0'), null);
    assert.equal(cache.obtener('32').length, 4);
    const datos = cache.obtener('32');
    datos[0].url = 'modificado';
    assert.equal(cache.obtener('32')[0].url, 'foto-0');
    cache.guardar('sin-fotos', []);
    assert.deepEqual(cache.obtener('sin-fotos'), []);
    tiempo += 300001;
    assert.equal(cache.obtener('32'), null);
    assert.equal(cache.obtener('sin-fotos'), null);
});

test('cuatro referencias locales no generan solicitudes al iniciar ni al pedir carga', async () => {
    const anteriores = globalThis.fetch;
    let peticiones = 0;
    globalThis.fetch = async () => {peticiones++; throw new Error('No debe consultar');};
    try {
        const locales = Array.from({length: 4}, (_, indice) => local(`Camponotus referencia${indice}`, `referencia-${indice}`));
        const estado = crearEstadoFotografias({family: 'Formicidae'}, locales);
        estado.init();
        await estado.cargar();
        assert.equal(peticiones, 0);
        assert.equal(estado.fotos.length, 4);
        estado.destroy();
    } finally {globalThis.fetch = anteriores;}
});

test('el cliente encadena únicamente tres GET públicos y conserva metadatos pequeños de la especie exacta', async () => {
    const anterior = globalThis.fetch;
    const a = especie();
    const llamadas = [];
    const cola = [{total_results: 1, results: [a]}, {results: [observacion(20, a, [foto(11), foto(12), foto(13), foto(14)])]}, {results: [a]}];
    globalThis.fetch = async (url, opciones) => {llamadas.push({url: new URL(url), opciones}); return respuesta(cola.shift());};
    try {
        const estado = crearEstadoFotografias({species: a.name, genus: 'Camponotus', family: 'Formicidae'});
        await estado.cargar();
        assert.equal(llamadas.length, 3);
        assert.equal(llamadas[0].url.pathname, '/v1/taxa');
        assert.equal(llamadas[0].url.searchParams.get('q'), a.name);
        assert.equal(llamadas[0].url.searchParams.get('rank'), 'species');
        assert.equal(llamadas[1].url.searchParams.get('per_page'), '12');
        assert.equal(llamadas[1].url.searchParams.get('photo_license'), 'cc0,cc-by,cc-by-sa');
        assert.equal(llamadas[1].url.searchParams.get('place_id'), '7512');
        assert.equal(llamadas[2].url.pathname, '/v1/taxa/7');
        assert.ok(llamadas.every(({url, opciones}) => url.origin === 'https://api.inaturalist.org' && opciones.method === 'GET' && opciones.credentials === 'omit' && !opciones.headers.Authorization));
        assert.equal(estado.fotos.length, 4);
        assert.equal(estado.cargando, false);
        assert.equal(estado.error, '');
        assert.ok(!('ancestors' in estado.fotos[0]) && !('photos' in estado.fotos[0]));
        estado.destroy();
    } finally {globalThis.fetch = anterior;}
});

test('consultar una fotografía no limita el mosaico posterior del mismo taxón en la caché', async () => {
    const anterior = globalThis.fetch;
    const a = especie('Camponotus cachefotografias', 71);
    const ecuatoriana = observacion(710, a, [foto(711)]);
    const global = {...observacion(720, a, [foto(712, 'cc0'), foto(713, 'cc0'), foto(714, 'cc0')]), place_ids: []};
    const cola = [
        {results: [a]}, {results: [ecuatoriana]}, {results: [a]},
        {results: [a]}, {results: [ecuatoriana]}, {results: [global]}, {results: [a]},
    ];
    const llamadas = [];
    globalThis.fetch = async url => {llamadas.push(new URL(url)); return respuesta(cola.shift());};
    try {
        const individual = crearEstadoFotografias({species: a.name}, [], 1);
        await individual.cargar();
        assert.equal(individual.fotos.length, 1);
        assert.equal(llamadas.length, 3);
        individual.destroy();
        const mosaico = crearEstadoFotografias({species: a.name}, [], 4);
        await mosaico.cargar();
        assert.equal(mosaico.fotos.length, 4);
        assert.equal(llamadas.length, 7);
        assert.equal(llamadas[5].searchParams.get('photo_license'), 'cc0');
        assert.equal(llamadas[5].searchParams.has('place_id'), false);
        mosaico.destroy();
        const repetida = crearEstadoFotografias({species: a.name}, [], 1);
        await repetida.cargar();
        assert.equal(repetida.fotos.length, 1);
        assert.equal(llamadas.length, 7);
        repetida.destroy();
    } finally {globalThis.fetch = anterior;}
});

test('un fallo de la fuente conserva la fotografía local y no fabrica fotografías de relleno', async () => {
    const anterior = globalThis.fetch;
    globalThis.fetch = async () => {throw new Error('Fuente caída');};
    try {
        const original = local();
        const estado = crearEstadoFotografias({genus: 'Camponotus', family: 'Formicidae'}, [original]);
        await estado.cargar();
        assert.deepEqual(estado.fotos, [original]);
        assert.equal(estado.cargando, false);
        assert.match(estado.error, /no está disponible/u);
        estado.destroy();
    } finally {globalThis.fetch = anterior;}
});

test('cerrar o cambiar el componente aborta y descarta una respuesta tardía antes de solicitar observaciones', async () => {
    const anterior = globalThis.fetch;
    let resolver;
    let señal;
    let peticiones = 0;
    globalThis.fetch = async (_url, opciones) => {peticiones++; señal = opciones.signal; return new Promise(resolve => {resolver = resolve;});};
    try {
        const estado = crearEstadoFotografias({species: 'Camponotus femoratus'});
        const pendiente = estado.cargar();
        estado.destroy();
        assert.equal(señal.aborted, true);
        resolver(respuesta({results: [especie('Camponotus femoratus', 10)]}));
        await pendiente;
        assert.equal(peticiones, 1);
        assert.deepEqual(estado.fotos, []);
        assert.equal(estado.error, '');
    } finally {globalThis.fetch = anterior;}
});

test('Ecuador tiene prioridad verificada y el complemento global acepta sólo CC0 sin crédito visible', () => {
    const a = especie();
    const ecuatoriana = observacion(20, a, [foto(11)]);
    const global = {...observacion(21, a, [foto(12), foto(13, 'cc0')]), place_ids: [99]};
    const sinPais = {...observacion(22, a, [foto(14)]), place_ids: [], country: 'Ecuador'};
    const fotos = fotografiasDeObservaciones([global, sinPais, ecuatoriana], [familia, a], familia, consultaFotografias({family: 'Formicidae'}));
    assert.deepEqual(fotos.map(imagen => imagen.id), ['inaturalist-11', 'inaturalist-13']);
    assert.equal(fotos[0].credito_ecuador, true);
    assert.equal(fotos[0].pais_referencia, 'Ecuador');
    assert.equal(fotos[0].autor, 'Autora de la foto / iNaturalist');
    assert.equal(fotos[1].credito_ecuador, false);
    assert.equal(fotos[1].pais_referencia, '');
    assert.equal(fotos[1].autor, '');
    assert.equal(fotos[1].autor_fuente, 'Autora de la foto / iNaturalist');
    assert.ok(!fotos[1].alt.includes('Autora') && !fotos[1].descripcion.includes('Ecuador'));
    assert.equal(candidatasEcuador([global, sinPais, ecuatoriana], familia), 1);
    assert.equal(candidatasEcuador([ecuatoriana], familia, [{id: 'inaturalist-11'}]), 0);
    assert.deepEqual(fotografiasLocalesValidas([{...local(), licencia: 'CC BY 4.0', licencia_url: 'https://creativecommons.org/licenses/by/4.0/', autor: 'Autora'}], null), []);
    for (const [licencia, plantilla] of [['Dominio público (PD-self / PD-user)', 'PD-self'], ['Dominio público (PD-self)', 'PD-self'], ['Dominio público (PD-user-w)', 'PD-user-w'], ['Dominio público (PD-USGov-NIH)', 'PD-USGov-NIH']]) {
        const libre = fotografiasLocalesValidas([{...local(), autor: 'Autor conservado en fuente', licencia, licencia_url: `https://commons.wikimedia.org/wiki/Template:${plantilla}`}], null);
        assert.equal(libre.length, 1);
        assert.equal(libre[0].autor, '');
        assert.equal(libre[0].autor_fuente, 'Autor de referencia');
    }
    assert.deepEqual(fotografiasLocalesValidas([{...local(), licencia: 'Dominio público (PD-self)', licencia_url: 'https://commons.wikimedia.org/wiki/Template:Otra_licencia'}], null), []);
});

test('la búsqueda incompleta se complementa en cuatro GET sin filtrar globales por un país supuesto', async () => {
    const anterior = globalThis.fetch;
    const a = especie('Paraponera clavata', 9, 'Paraponera', 8);
    const llamadas = [];
    const global = {...observacion(21, a, [foto(13, 'cc0'), foto(14, 'cc0'), foto(15, 'cc0')]), place_ids: []};
    const cola = [{results: [a]}, {results: [observacion(20, a, [foto(12)])]}, {results: [global]}, {results: [a]}];
    globalThis.fetch = async (url, opciones) => {llamadas.push({url: new URL(url), opciones}); return respuesta(cola.shift());};
    try {
        const estado = crearEstadoFotografias({species: a.name, genus: 'Paraponera', family: 'Formicidae'});
        await estado.cargar();
        assert.equal(llamadas.length, 4);
        assert.equal(llamadas[1].url.searchParams.get('place_id'), '7512');
        assert.equal(llamadas[2].url.searchParams.get('photo_license'), 'cc0');
        assert.equal(llamadas[2].url.searchParams.has('place_id'), false);
        assert.equal(llamadas[3].url.pathname, '/v1/taxa/9');
        assert.equal(estado.fotos.length, 4);
        assert.deepEqual(estado.fotos.map(imagen => imagen.credito_ecuador), [true, false, false, false]);
        assert.ok(estado.fotos.slice(1).every(imagen => imagen.autor === '' && imagen.licencia === 'CC0 1.0'));
        estado.destroy();
    } finally {globalThis.fetch = anterior;}
});

test('una subespecie conserva su ID terminal y no permite sustituirla por la especie padre', () => {
    const a = especie();
    const subespecie = {...nodo(30, 'Camponotus sericeiventris referencia', 'subspecies', a.id), ancestors: [...a.ancestors, {...a, ancestors: undefined}]};
    const consulta = consultaFotografias({species: a.name, subspecies: subespecie.name});
    assert.equal(consulta.rank, 'subspecies');
    const fotos = fotografiasDeObservaciones([observacion(20, a, [foto(11)]), observacion(21, subespecie, [foto(12)])], [a, subespecie], subespecie, consulta);
    assert.equal(fotos.length, 1);
    assert.equal(fotos[0].species, subespecie.name);
    assert.deepEqual(fotografiasDeObservaciones([observacion(21, subespecie, [foto(12)])], [a, subespecie], a, consultaFotografias({species: a.name})), []);
});

test('una caché reutilizada actualiza la descripción del conjunto efectivo y un rango desconocido no inicia búsqueda', async () => {
    const anterior = globalThis.fetch;
    const a = especie('Camponotus planatus', 31);
    const seleccion = {species: a.name, genus: 'Camponotus', family: 'Formicidae'};
    const cola = [{results: [a]}, {results: [observacion(20, a, [foto(100), foto(101), foto(102), foto(103)])]}, {results: [a]}];
    globalThis.fetch = async () => respuesta(cola.shift());
    try {
        const inicial = crearEstadoFotografias(seleccion);
        await inicial.cargar();
        assert.equal(inicial.fotos.length, 4);
        inicial.destroy();
        let peticiones = 0;
        globalThis.fetch = async () => {peticiones++; throw new Error('No debe consultar');};
        const estado = crearEstadoFotografias(seleccion);
        estado.descripcion = 'Texto anterior incorrecto';
        await estado.cargar();
        assert.equal(peticiones, 0);
        assert.equal(estado.fotos.length, 4);
        assert.equal(estado.descripcion, descripcionFotografias(estado.fotos));
        assert.ok(!estado.descripcion.includes('Texto anterior incorrecto'));
        estado.destroy();
        const desconocido = crearEstadoFotografias({family: 'Formicidae', _rango_desconocido: 'Nota'}, [local()]);
        desconocido.init();
        await desconocido.cargar();
        assert.equal(peticiones, 0);
        assert.deepEqual(desconocido.fotos, []);
        desconocido.destroy();
    } finally {globalThis.fetch = anterior;}
});
