import test from 'node:test';
import assert from 'node:assert/strict';
import {prepararZonasETE, encuadreETE, escalaAjustadaETE} from '../../resources/js/portal-ete-viewer.js';

test('el mapa de ETE conserva identidades y traduce coordenadas al SVG sin inventar taxones', () => {
    const nodos = Array.from({length: 122}, (_, i) => ({clave: `g${i}`, nombre: `Género ${i}`, total: i + 1}));
    const dibujo = {viewBox: [10, 20, 1000, 1000], nodos: nodos.map((n, i) => ({clave: n.clave, caja: [10, 20 + i * 8, 110, 26 + i * 8]}))};
    const zonas = prepararZonasETE(dibujo, nodos);
    assert.equal(zonas.length, 122);
    assert.equal(zonas[0].izquierda, 0); assert.equal(zonas[0].arriba, 0); assert.equal(zonas[0].ancho, 10);
    zonas.forEach((z, i) => {assert.equal(z.nodo, nodos[i]); assert.equal(z.nodo.total, i + 1);});
    assert.equal(new Set(zonas.map(z => z.arriba)).size, 122);
});

test('el encuadre fija el centro circular y la base semicircular incluso con SVG asimétrico', () => {
    for (const [ancho, alto] of [[1000, 600], [300, 240]]) {
        for (const vista of ['circular', 'semicircular']) {
            for (const escala of [1, .4]) {
                const dibujo = {viewBox: [10, 20, 1600, 900], raiz: [710, 720], vista};
                const e = encuadreETE(dibujo, ancho, alto, escala);
                assert.equal(e.izquierda + 700 * escala - e.scrollX, ancho / 2);
                assert.equal(e.arriba + 700 * escala - e.scrollY, vista === 'circular' ? alto / 2 : alto - Math.max(24, 200 * escala + 12));
                assert.ok(e.scrollX >= 0 && e.scrollX <= e.ancho - ancho);
                assert.ok(e.scrollY >= 0 && e.scrollY <= e.alto - alto);
            }
        }
    }
});

test('el ajuste conserva las proporciones de los nombres y maximiza el dibujo completo sin recortarlo', () => {
    for (const [ancho, alto] of [[1000, 600], [300, 240], [1600, 700]]) {
        for (const vista of ['circular', 'semicircular']) {
            for (const [w, h, rx, ry] of [[1600, 900, 700, 700], [240, 130, 120, 110], [800, 460, 390, 420]]) {
                const dibujo = {viewBox: [10, 20, w, h], raiz: [10 + rx, 20 + ry], vista};
                const escala = escalaAjustadaETE(dibujo, ancho, alto);
                const e = encuadreETE(dibujo, ancho, alto, escala);
                assert.ok(e.ancho <= ancho + .001 && e.alto <= alto + .001, 'El dibujo completo cabe en el panel.');
                assert.ok(Math.abs(e.ancho - ancho) < .001 && Math.abs(e.alto - alto) < .001);
                assert.ok(Math.abs(e.izquierda + rx * escala.x - ancho / 2) < .001);
                assert.equal(escala.x, escala.y, 'Las letras y las ramas no se ensanchan lateralmente.');
                const mayor = encuadreETE(dibujo, ancho, alto, {x: escala.x * 1.01, y: escala.y * 1.01});
                assert.ok(mayor.ancho > ancho + .001 || mayor.alto > alto + .001, 'La mayor escala uniforme alcanza al menos un límite del panel.');
                if (vista === 'circular') assert.ok(Math.abs(e.arriba + ry * escala.y - alto / 2) < .001);
            }
        }
    }
    const pequeno = escalaAjustadaETE({viewBox: [0, 0, 240, 130], raiz: [120, 110], vista: 'semicircular'}, 1000, 600);
    assert.ok(pequeno.x > 1 && pequeno.y > 1, 'Un árbol pequeño también se amplía.');
    const circulo = escalaAjustadaETE({viewBox: [0, 0, 1000, 1000], raiz: [500, 500], vista: 'circular'}, 1600, 600);
    assert.equal(circulo.x, circulo.y, 'Un panel ancho conserva el círculo y la proporción de sus nombres.');
});

test('la rama única queda centrada y legible, en lugar de ocupar la base de un arco vacío', () => {
    const dibujo = {viewBox: [0, 0, 320, 60], raiz: [20, 30], vista: 'semicircular', disposicion: 'lineal'};
    const escala = escalaAjustadaETE(dibujo, 1000, 600), e = encuadreETE(dibujo, 1000, 600, escala);
    assert.equal(escala.x, escala.y, 'Un único rótulo conserva la proporción de sus letras.');
    assert.ok(Math.abs(e.arriba + 30 * escala.y - 300) < .001);
    assert.ok(Math.abs(e.izquierda + 160 * escala.x - 500) < .001);
    assert.ok(e.ancho <= 1000 && e.alto <= 600);
});

test('las zonas rotadas recortan el botón al polígono real y los padres usan puntos accesibles', () => {
    const zonas = prepararZonasETE({viewBox: [0, 0, 100, 100], nodos: [
        {clave: 'hoja', caja: [10, 10, 30, 30], poligono: [[10, 20], [20, 10], [30, 20], [20, 30]]},
        {clave: 'padre', caja: [40, 40, 62, 62], tipo: 'punto'},
    ]}, [{clave: 'hoja'}, {clave: 'padre'}]);
    assert.equal(zonas[0].recorte, 'polygon(0% 50%,50% 0%,100% 50%,50% 100%)');
    assert.equal(zonas[1].recorte, 'circle(50%)');
});

test('la etiqueta de un padre conserva por separado el centro nativo de su bifurcación', () => {
    const dibujo = {viewBox: [10, 20, 1000, 500], nodos: [
        {clave: 'familia', caja: [360, 290, 660, 320], poligono: [[360, 290], [660, 290], [660, 320], [360, 320]], punto: [510, 270]},
    ]};
    const [zona] = prepararZonasETE(dibujo, [{clave: 'familia', nombre: 'Formicidae'}]);
    assert.deepEqual(zona.centro, [50, 50]);
    assert.notEqual(zona.arriba + zona.alto / 2, zona.centro[1], 'La face no se confunde con el punto de origen del arco.');
    assert.equal(zona.punto, false);
    assert.equal(prepararZonasETE({...dibujo, nodos: [{...dibujo.nodos[0], punto: [Infinity, 270]}]}, [zona.nodo])[0].centro, null);
});

test('las zonas no admiten una raíz técnica, taxones fuera del contexto ni geometría inválida', () => {
    const dibujo = {viewBox: [0, 0, 500, 300], nodos: [
        {clave: '', caja: [0, 0, 10, 10]}, {clave: 'ajeno', caja: [0, 0, 10, 10]},
        {clave: 'a', caja: [20, 20, 10, 10]}, {clave: 'a', caja: [0, 0, NaN, 10]}, {clave: 'a', caja: [50, 30, 100, 60]},
    ]};
    const zonas = prepararZonasETE(dibujo, [{clave: 'a', nombre: '<nombre "especial">'}]);
    assert.equal(zonas.length, 1); assert.equal(zonas[0].nodo.nombre, '<nombre "especial">');
    assert.equal(zonas[0].izquierda, 10); assert.equal(zonas[0].arriba, 10);
    assert.throws(() => prepararZonasETE({...dibujo, viewBox: [0, 0, 0, 300]}, []));
});
