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

test('el linaje real de nueve niveles y cinco registros cabe también en un modal de alto limitado', () => {
    const ancestros = Array.from({length: 9}, (_, i) => ({id: String(i), padre_id: i ? String(i - 1) : null}));
    const nodos = [...ancestros, ...Array.from({length: 5}, (_, i) => ({id: `registro-${i}`, padre_id: '8'}))];
    const vista = distribuirArbol(nodos, 809, 301);
    assert.equal(vista.nodos.length, 14);
    assert.equal(vista.enlaces.length, 13);
    assert.equal(vista.alto, 301);
    assert.equal(new Set(vista.nodos.map(n => n.fila)).size, 4);
    for (const [i, a] of vista.nodos.entries()) {
        assert.ok(a.y >= 0 && a.y + 64 <= 301 && a.x + a.ancho <= 809);
        for (const b of vista.nodos.slice(i + 1)) assert.equal(a.x < b.x + b.ancho && a.x + a.ancho > b.x && a.y < b.y + 64 && a.y + 64 > b.y, false);
    }
    // Cada enlace debe llegar a su hoja sin cruzar el interior de otra tarjeta.
    for (const enlace of vista.enlaces) {
        const hijo = vista.nodos.find(n => String(n.id) === enlace.id);
        let anterior;
        for (const comando of enlace.d.matchAll(/([MHV])([\d.]+)(?:,([\d.]+))?/g)) {
            const punto = comando[1] === 'M' ? [Number(comando[2]), Number(comando[3])]
                : comando[1] === 'H' ? [Number(comando[2]), anterior[1]] : [anterior[0], Number(comando[2])];
            if (anterior) for (const otro of vista.nodos.filter(n => n.id !== hijo.id && n.id !== hijo.padre_id)) {
                const cruza = anterior[0] === punto[0]
                    ? punto[0] > otro.x && punto[0] < otro.x + otro.ancho && Math.max(anterior[1], punto[1]) > otro.y && Math.min(anterior[1], punto[1]) < otro.y + 64
                    : punto[1] > otro.y && punto[1] < otro.y + 64 && Math.max(anterior[0], punto[0]) > otro.x && Math.min(anterior[0], punto[0]) < otro.x + otro.ancho;
                assert.equal(cruza, false, `${enlace.id} cruza la tarjeta ${otro.id}`);
            }
            anterior = punto;
        }
    }
});
