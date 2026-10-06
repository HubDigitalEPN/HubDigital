import test from 'node:test';
import assert from 'node:assert/strict';
import {distribuirArbol} from '../../resources/js/portal-tree-model.js';

const cadena = Array.from({length: 8}, (_, i) => ({id: String(i), padre_id: i ? String(i - 1) : null}));

test('una cadena corta ocupa ancho y alto del modal y cabe sin desplazamiento vertical', () => {
    const vista = distribuirArbol(cadena, 1100, 480);
    assert.equal(vista.nodos.length, 8);
    assert.equal(vista.enlaces.length, 7);
    assert.ok(new Set(vista.nodos.map(nodo => nodo.y)).size > 1);
    assert.ok(new Set(vista.nodos.map(nodo => nodo.x)).size > 1);
    assert.equal(vista.alto, 480);
    assert.ok(Math.max(...vista.nodos.map(nodo => nodo.y)) > 240);
    for (const nodo of vista.nodos) {
        assert.ok(nodo.x >= 0 && nodo.x + nodo.ancho <= vista.ancho);
        assert.ok(nodo.y >= 0 && nodo.y + 64 <= vista.alto);
    }
});

test('cinco hojas permanecen legibles, sin superposiciones, en tamaños distintos', () => {
    const nodos = [...cadena.slice(0, 5), ...Array.from({length: 5}, (_, i) => ({id: `hoja-${i}`, padre_id: '4'}))];
    for (const [ancho, alto] of [[1100, 600], [600, 480], [320, 480]]) {
        const vista = distribuirArbol(nodos, ancho, alto);
        for (const [i, a] of vista.nodos.entries()) {
            assert.ok(a.x + a.ancho <= vista.ancho);
            assert.ok(a.y + 64 <= vista.alto);
            for (const b of vista.nodos.slice(i + 1)) {
                const seSolapan = a.x < b.x + b.ancho && a.x + a.ancho > b.x && a.y < b.y + 64 && a.y + 64 > b.y;
                assert.equal(seSolapan, false, `${ancho}: ${a.id} y ${b.id}`);
            }
        }
    }
});

test('un padre ausente o cíclico no bloquea el cálculo de posiciones', () => {
    const vista = distribuirArbol([{id: 'a', padre_id: 'b'}, {id: 'b', padre_id: 'a'}, {id: 'c', padre_id: 'reservado'}], 600, 480);
    assert.equal(vista.nodos.length, 3);
    assert.ok(vista.nodos.every(nodo => Number.isFinite(nodo.x) && Number.isFinite(nodo.y)));
});
