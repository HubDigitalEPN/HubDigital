import test from 'node:test';
import assert from 'node:assert/strict';
import {GRUPOS_BIOLOGICOS_3D, grupoBiologico3D} from '../../resources/js/portal-taxonomy-three-organisms.js';

const nodo = (clave, nombre, padre = null) => Object.freeze({clave, nombre, padre});

test('los símbolos de especies heredan el grupo de sus ancestros públicos, aunque estén fuera de la rama abierta', () => {
    const fuente = [nodo('clase', 'Arachnida'), nodo('orden', 'Araneae', 'clase'),
        nodo('familia', 'Familia publicada', 'orden'), nodo('genero', 'Género publicado', 'familia'),
        nodo('especie', 'Especie publicada', 'genero'), nodo('moluscos', 'Mollusca'),
        nodo('otra-especie', 'Especie publicada', 'moluscos')];
    const porClave = new Map(fuente.map(n => [n.clave, n])), cache = new Map();
    assert.equal(grupoBiologico3D(porClave.get('especie'), porClave, cache), 'arachnida');
    assert.equal(cache.get('familia'), 'arachnida');
    assert.equal(grupoBiologico3D(porClave.get('otra-especie'), porClave, cache), 'mollusca');
    assert.equal(grupoBiologico3D(porClave.get('genero'), porClave, cache), 'arachnida');
});

test('la ascendencia profunda no usa recursión y la caché permite reutilizar el linaje completo', () => {
    const fuente = [nodo('raiz', 'Insecta'), ...Array.from({length: 12000}, (_, i) =>
        nodo('n-' + i, 'Taxón publicado ' + i, i ? 'n-' + (i - 1) : 'raiz'))];
    const porClave = new Map(fuente.map(n => [n.clave, n])), cache = new Map();
    assert.equal(grupoBiologico3D(fuente.at(-1), porClave, cache), 'insecta');
    assert.equal(cache.size, fuente.length);
    assert.equal(grupoBiologico3D(porClave.get('n-6000'), porClave, cache), 'insecta');
    assert.ok(GRUPOS_BIOLOGICOS_3D.includes(cache.get('n-6000')));
});

test('los grupos desconocidos, padres ausentes y ciclos reciben un símbolo neutro sin inferir especies', () => {
    const a = nodo('a', 'Especie sin grupo', 'b'), b = nodo('b', 'Otro taxón', 'a');
    const porClave = new Map([[a.clave, a], [b.clave, b]]), cache = new Map();
    assert.equal(grupoBiologico3D(a, porClave, cache), 'taxon');
    assert.equal(grupoBiologico3D(b, porClave, cache), 'taxon');
    assert.equal(grupoBiologico3D(nodo('huerfano', 'Nombre no identificado', 'ausente'), porClave), 'taxon');
    assert.equal(grupoBiologico3D(undefined, porClave), 'taxon');
});
