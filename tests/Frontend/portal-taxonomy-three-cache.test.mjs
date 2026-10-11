import test from 'node:test';
import assert from 'node:assert/strict';
import {bytesDibujo3D, crearCacheRamas3D, mismaFuenteTaxonomica} from '../../resources/js/portal-taxonomy-three-cache.js';

const dibujo = clave => ({claves: [clave], posiciones: new Float32Array(3), padres: new Int32Array([-1]), grupos: new Uint32Array(1)});
const diferida = () => {let resolve, reject; const promesa = new Promise((a, b) => {resolve = a; reject = b;}); return {promesa, resolve, reject};};

test('SWR entrega la rama anterior sin esperar y comparte una sola renovación', async () => {
    let tiempo = 0, llamadas = 0;
    const cache = crearCacheRamas3D({reloj: () => tiempo, frescaMs: 30});
    const anterior = dibujo('a'), siguiente = dibujo('a');
    const carga = cache.obtener('a', () => {llamadas++; return anterior;});
    assert.equal(carga.estado, 'miss'); assert.equal(await carga.promesa, anterior);
    const fresca = cache.obtener('a', () => {throw new Error('No debería cargar.');});
    assert.equal(fresca.estado, 'fresh'); assert.equal(fresca.valor, anterior);
    tiempo = 31;
    const pendiente = diferida(), cargar = () => {llamadas++; return pendiente.promesa;};
    const stale = cache.obtener('a', cargar), repetida = cache.obtener('a', cargar);
    assert.equal(stale.estado, 'stale'); assert.equal(stale.valor, anterior);
    assert.equal(repetida.promesa, stale.promesa);
    await Promise.resolve(); assert.equal(llamadas, 2);
    pendiente.resolve(siguiente); await stale.promesa;
    assert.equal(cache.obtener('a', cargar).valor, siguiente);
    assert.equal(cache.estado().renovaciones, 1);
});

test('una fuente invalidada nunca vuelve a recibir una geometría tardía', async () => {
    const cache = crearCacheRamas3D(), vieja = diferida();
    const primera = cache.obtener('a', () => vieja.promesa); await Promise.resolve();
    cache.limpiar();
    const actual = dibujo('actual'), segunda = cache.obtener('a', () => actual);
    await segunda.promesa; vieja.resolve(dibujo('retirado')); await primera.promesa;
    assert.equal(cache.obtener('a', () => {}).valor, actual);
    assert.equal(cache.estado().pendientes, 0);
});

test('una renovación fallida conserva la vista válida y aplica espera antes de reintentar', async () => {
    let tiempo = 0, cargas = 0;
    const cache = crearCacheRamas3D({reloj: () => tiempo, frescaMs: 10});
    const valor = dibujo('a'); await cache.obtener('a', () => valor).promesa;
    tiempo = 11;
    const fallo = cache.obtener('a', () => {cargas++; throw new Error('Sin worker.');});
    await assert.rejects(fallo.promesa, /Sin worker/);
    const conservada = cache.obtener('a', () => {cargas++; return valor;});
    assert.equal(conservada.valor, valor); assert.equal(conservada.promesa, undefined); assert.equal(cargas, 1);
    tiempo = 5012; await cache.obtener('a', () => {cargas++; return valor;}).promesa;
    assert.equal(cargas, 2);
});

test('la caché respeta bytes y LRU y no retiene una rama mayor que su presupuesto', async () => {
    const a = dibujo('a'), b = dibujo('b'), c = dibujo('c'), peso = bytesDibujo3D(a);
    const cache = crearCacheRamas3D({maxBytes: peso * 2, maxEntradas: 6});
    await cache.obtener('a', () => a).promesa; await cache.obtener('b', () => b).promesa;
    cache.obtener('a', () => {}); await cache.obtener('c', () => c).promesa;
    assert.equal(cache.contiene('a'), true); assert.equal(cache.contiene('b'), false);
    assert.ok(cache.estado().bytes <= peso * 2);
    const enorme = {claves: ['enorme'], posiciones: new Float32Array(peso)};
    await cache.obtener('enorme', () => enorme).promesa;
    assert.equal(cache.contiene('enorme'), false); assert.equal(cache.estado().entradas, 2);
    const limitado = crearCacheRamas3D({maxEntradas: 1});
    await limitado.obtener('a', () => a).promesa; await limitado.obtener('b', () => b).promesa;
    assert.equal(limitado.estado().entradas, 1); assert.equal(limitado.contiene('a'), false);
});

test('respuestas equivalentes se reutilizan; retirar o modificar un taxón invalida la fuente', () => {
    const fuente = [{clave: 'a', padre: null, total: 2, nombre: 'A', rango: 'clase', linaje: ['A']},
        {clave: 'b', padre: 'a', total: 1, nombre: 'B', rango: 'orden', linaje: ['A', 'B']}];
    const copia = () => fuente.map(n => ({...n, linaje: [...n.linaje]}));
    assert.equal(mismaFuenteTaxonomica(fuente, copia()), true);
    for (const campo of ['clave', 'padre', 'nombre', 'rango', 'total', 'linaje']) {
        const distinta = copia(); distinta[1][campo] = campo === 'linaje' ? ['A', 'C'] : 'cambio';
        assert.equal(mismaFuenteTaxonomica(fuente, distinta), false, campo);
    }
    assert.equal(mismaFuenteTaxonomica(fuente, fuente.slice(0, 1)), false);
});
