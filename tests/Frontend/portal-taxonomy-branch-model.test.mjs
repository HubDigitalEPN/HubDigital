import test from 'node:test';
import assert from 'node:assert/strict';
import {ramaExploradorTaxonomico, filasExploradorTaxonomico, ventanaExploradorTaxonomico} from '../../resources/js/portal-taxonomy-branch-model.js';

const nodo = (clave, padre, nombre = clave, total = 1) => ({clave, padre, nombre, total});
function indice(nodos) {
    const porClave = new Map(nodos.map(n => [n.clave, n])), porPadre = new Map();
    for (const n of nodos) {if (!porPadre.has(n.padre)) porPadre.set(n.padre, []); porPadre.get(n.padre).push(n);}
    return {porClave, porPadre};
}
test('el explorador aísla todos los descendientes de la esfera, conserva contadores e identifica hermanos', () => {
    const rama = ramaExploradorTaxonomico(indice([nodo('filo', null), nodo('a', 'filo', 'Arachnida', 1253),
        nodo('orden', 'a'), nodo('genero', 'orden'), nodo('otro', 'a'), nodo('ajeno', 'filo')]), 'a');
    assert.deepEqual(rama.nodos.map(n => n.clave), ['a', 'orden', 'genero', 'otro']);
    assert.equal(rama.nodos[0].total, 1253); assert.equal(rama.nodos[0].descendientes, 3);
    assert.equal(rama.nodos[3].posicion, 2); assert.equal(rama.nodos[3].hermanos, 2);
    const inicial = filasExploradorTaxonomico(rama, new Set(['a']));
    assert.deepEqual(inicial.map(f => f.nodo.clave), ['a', 'orden', 'otro']);
    assert.equal(rama.nodos[2].padreIndice, 1);
});
test('buscar sin acentos conserva el camino completo y excluye otras ramas', () => {
    const rama = ramaExploradorTaxonomico(indice([nodo('raiz', null), nodo('orden', 'raiz'), nodo('genero', 'orden', 'Género buscado'), nodo('otro', 'raiz')]), 'raiz');
    const filas = filasExploradorTaxonomico(rama, new Set(), 'GENERO');
    assert.deepEqual(filas.map(f => f.nodo.clave), ['raiz', 'orden', 'genero']);
    assert.deepEqual(filas.map(f => f.coincide), [false, false, true]);
    assert.equal(filasExploradorTaxonomico(rama, new Set(), 'inexistente').length, 0);
});
test('una rama profunda y un ciclo no recortan descendientes ni desbordan la pila', () => {
    const nodos = Array.from({length: 12001}, (_, i) => nodo('n'+i, i ? 'n'+(i-1) : null));
    const datos = indice(nodos); datos.porPadre.set('n12000', [nodos[0]]);
    const rama = ramaExploradorTaxonomico(datos, 'n0');
    assert.equal(rama.nodos.length, 12001); assert.equal(rama.nodos[0].descendientes, 12000);
    assert.equal(filasExploradorTaxonomico(rama, new Set(nodos.map(n => n.clave))).length, 12001);
});
test('el DOM virtual conserva acceso hasta la última fila y sólo necesita una ventana acotada', () => {
    const inicio = ventanaExploradorTaxonomico(1000000, 0, 400);
    assert.deepEqual(inicio, {inicio: 0, fin: 15});
    const fin = ventanaExploradorTaxonomico(1000000, 39999600, 400);
    assert.equal(fin.fin, 1000000); assert.ok(fin.fin - fin.inicio <= 20);
    assert.deepEqual(ventanaExploradorTaxonomico(0, 0, 400), {inicio: 0, fin: 0});
});
