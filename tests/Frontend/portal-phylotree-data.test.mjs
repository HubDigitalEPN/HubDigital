import test from 'node:test';
import assert from 'node:assert/strict';
import {indicePublicoTaxonomico, ramaPublicaPhylotree} from '../../resources/js/portal-phylotree-data.js';

const nodo = (clave, padre = null, nombre = clave) => ({clave, padre, nombre, id: clave, etiqueta: 'Taxón', total: 1});

test('la vista inicial conserva el filo y cada hijo directo, sin confundir nietos con hermanos', () => {
    const hijos = ['Arachnida', 'Chilopoda', 'Crustacea', 'Diplopoda', 'Insecta', 'Malacostraca', 'Tingidae'];
    const datos = [nodo('arthropoda', null, 'Arthropoda'), nodo('annelida'),
        ...hijos.map(nombre => nodo(nombre, 'arthropoda', nombre)),
        nodo('orden', 'Insecta'), nodo('familia', 'orden'), nodo('clitellata', 'annelida')];
    const rama = ramaPublicaPhylotree(indicePublicoTaxonomico(datos), 'arthropoda', 1);
    assert.equal(rama.raiz.nombre, 'Arthropoda');
    assert.deepEqual(new Set(rama.json.children.map(n => n.name)), new Set(hijos));
    assert.equal(rama.nodos.length, 8); assert.equal(rama.relaciones.length, 7);
    assert.ok(rama.relaciones.every(r => r.padre === 'arthropoda'));
    assert.ok(rama.json.children.every(n => !n.children));
    const otro = ramaPublicaPhylotree(indicePublicoTaxonomico(datos), 'annelida', 1);
    assert.deepEqual(otro.nodos.map(n => n.clave), ['annelida', 'clitellata']);
    assert.deepEqual(ramaPublicaPhylotree(indicePublicoTaxonomico([nodo('terminal')]), 'terminal', 1).relaciones, []);
});

test('el JSON del motor conserva la raíz elegida y todas las relaciones publicadas, sin otras familias', () => {
    const datos = [nodo('filo'), nodo('familia', 'filo', 'Formicidae'), nodo('subfamilia', 'familia'),
        nodo('genero', 'subfamilia'), nodo('especie', 'genero'), nodo('directo', 'familia'), nodo('otra', 'filo'), nodo('ajeno', 'otra')];
    const rama = ramaPublicaPhylotree(indicePublicoTaxonomico(datos), 'familia');
    assert.equal(rama.json.name, 'Formicidae'); assert.equal(rama.raiz.clave, 'familia');
    assert.deepEqual(new Set(rama.nodos.map(n => n.clave)), new Set(['familia', 'subfamilia', 'genero', 'especie', 'directo']));
    assert.deepEqual(new Set(rama.relaciones.map(r => r.padre + '>' + r.hijo)), new Set(['familia>subfamilia', 'subfamilia>genero', 'genero>especie', 'familia>directo']));
    assert.equal(rama.json.children.find(n => n.portal.clave === 'subfamilia').children[0].children[0].portal.clave, 'especie');
});

test('las ramas masivas no se truncan y los homónimos mantienen sus identidades', () => {
    for (const cantidad of [31, 122, 768, 3141]) {
        const datos = [nodo('a'), ...Array.from({length: cantidad}, (_, i) => nodo('h-' + i, 'a', 'Ponerinae'))];
        const rama = ramaPublicaPhylotree(indicePublicoTaxonomico(datos), 'a');
        assert.equal(rama.json.children.length, cantidad); assert.equal(rama.nodos.length, cantidad + 1);
        assert.equal(rama.relaciones.length, cantidad); assert.equal(new Set(rama.nodos.map(n => n.clave)).size, cantidad + 1);
    }
});

test('todas las familias de un catálogo conservan padres reales, incluidos terminales y nombres largos', () => {
    const datos = [nodo('filo')];
    for (let i = 0; i < 393; i++) {
        datos.push(nodo('familia-' + i, 'filo', 'Familia ' + i));
        for (let j = 0; j < i % 6; j++) datos.push(nodo(`g-${i}-${j}`, 'familia-' + i, 'Género con nombre largo ' + j));
    }
    const indice = indicePublicoTaxonomico(datos);
    for (let i = 0; i < 393; i++) {
        const rama = ramaPublicaPhylotree(indice, 'familia-' + i);
        assert.equal(rama.nodos.length, 1 + i % 6);
        assert.ok(rama.relaciones.every(r => indice.porClave.get(r.hijo).padre === r.padre));
        if (i % 6 === 0) assert.equal(rama.json.children, undefined);
    }
});

test('no inventa hijos para raíces terminales, ni reubica huérfanos, ni recorre ciclos', () => {
    const indice = indicePublicoTaxonomico([nodo('terminal'), nodo('huérfano', 'ausente'), nodo('a', 'b'), nodo('b', 'a')]);
    assert.deepEqual(ramaPublicaPhylotree(indice, 'terminal').relaciones, []);
    assert.equal(ramaPublicaPhylotree(indice, 'ausente'), null);
    assert.deepEqual(ramaPublicaPhylotree(indice, 'a').nodos.map(n => n.clave), ['a', 'b']);
    assert.equal(ramaPublicaPhylotree(indice, 'a').relaciones.length, 1);
});

test('metadatos públicos se copian sin modificar el catálogo ni serializar nombres como Newick', () => {
    const publicado = nodo('a', null, "Taxón (A:B); 'C'");
    const indice = indicePublicoTaxonomico([publicado, {...publicado, nombre: 'Duplicado'}]);
    assert.notEqual(indice.porClave.get('a'), publicado);
    assert.equal(ramaPublicaPhylotree(indice, 'a').json.name, publicado.nombre);
    assert.equal(indice.porClave.size, 1);
});
