import test from 'node:test';
import assert from 'node:assert/strict';
import {octree} from 'd3-octree';
import {buscarNodoEspacial, nodosEnEncuadre, prepararRadiosEspaciales} from '../../resources/js/portal-taxonomy-three-spatial.js';

const indice = puntos => octree().x(i => puntos[i * 3]).y(i => puntos[i * 3 + 1]).z(i => puntos[i * 3 + 2])
    .addAll(Array.from({length: puntos.length / 3}, (_, i) => i));

test('el octree elige el punto frontal, acepta el índice cero y descarta puntos detrás de la cámara', () => {
    const puntos = Float32Array.from([0, 0, 0, 0, 0, -20, 0, 0, 20, 50, 50, 0]);
    const arbol = indice(puntos);
    assert.equal(buscarNodoEspacial(arbol, puntos, [0, 0, 10], [0, 0, -1], .01), 0);
    assert.equal(buscarNodoEspacial(arbol, puntos, [50, 50, 10], [0, 0, -1], .01), 3);
    assert.equal(buscarNodoEspacial(arbol, puntos, [10, 10, 10], [0, 0, -1], .01), -1);
    assert.equal(buscarNodoEspacial(indice(new Float32Array()), new Float32Array(), [0, 0, 10], [0, 0, -1], .01), -1);
});

test('la tolerancia de selección sigue los píxeles tras hacer zoom y conserva puntos coincidentes', () => {
    const puntos = Float32Array.from([.4, 0, 0, .4, 0, 0, 100, 0, 0]);
    const arbol = indice(puntos);
    assert.equal(buscarNodoEspacial(arbol, puntos, [0, 0, 10], [0, 0, -1], .05), 0);
    assert.equal(buscarNodoEspacial(arbol, puntos, [0, 0, 5], [0, 0, -1], .05), -1);
    assert.equal(buscarNodoEspacial(arbol, puntos, [0, 0, 5], [0, 0, -1], .1), 0);
});

test('la selección respeta el diámetro del mundo y no consulta el ancla invisible del filtro', () => {
    const puntos = Float32Array.from([0, 0, 0, .8, 0, 0, .8, 0, -20]);
    const arbol = octree().x(i => puntos[i * 3]).y(i => puntos[i * 3 + 1]).z(i => puntos[i * 3 + 2]).addAll([1, 2]);
    const mundos = {tamanos: Float32Array.from([92, 20, 20]), maxTamano: 92, distancia: 10, factor: 1};
    assert.equal(buscarNodoEspacial(arbol, puntos, [.8, 0, 10], [0, 0, -1], .01, {}, mundos), 1);
    assert.equal(buscarNodoEspacial(arbol, puntos, [0, 0, 10], [0, 0, -1], .01, {}, mundos), 1, 'El borde de un mundo grande también responde al ratón.');
    const pequenos = {...mundos, tamanos: Float32Array.from([92, 2, 2])};
    assert.equal(buscarNodoEspacial(arbol.copy().remove(2), puntos, [0, 0, 10], [0, 0, -1], .01, {}, pequenos), -1);
    assert.equal(buscarNodoEspacial(arbol, puntos, [-10, 0, 10], [0, 0, -1], .01, {}, mundos), -1);
});

test('el encuadre conserva puntos coincidentes y descarta octantes completos fuera de pantalla', () => {
    const puntos = Float32Array.from([0, 0, 0, 0, 0, 0, 1, 1, 1,
        ...Array.from({length: 5000}, (_, i) => [100 + i, 100, 100]).flat()]);
    const planos = [1, 0, 0, 1, -1, 0, 0, 1, 0, 1, 0, 1, 0, -1, 0, 1, 0, 0, 1, 1, 0, 0, -1, 1];
    const estadisticas = {}, visibles = nodosEnEncuadre(indice(puntos), puntos, planos, estadisticas);
    assert.deepEqual(new Set(visibles), new Set([0, 1, 2]));
    assert.ok(estadisticas.descartados > 0);
    assert.ok(estadisticas.puntos < 20, 'Los 5.000 puntos fuera del encuadre se descartan por sus octantes.');
    assert.ok(visibles instanceof Uint32Array);
    assert.equal(nodosEnEncuadre(null, puntos, planos).length, 0);
});

test('cada octante usa el diámetro de sus propios mundos al descartar candidatos', () => {
    const puntos = Float32Array.from([0, 0, 0, 20, 20, 20, 200, 200, 200]);
    const arbol = indice(puntos), tamanos = Float32Array.from([92, 26, 8]);
    prepararRadiosEspaciales(arbol, tamanos);
    assert.equal(arbol.root().maxTamano, 92);
    arbol.visit(octante => {
        if (!octante.length) assert.equal(octante.maxTamano, tamanos[octante.data]);
        return false;
    });
});

test('una especie lateral se selecciona en su círculo proyectado, antes y después del zoom', () => {
    const puntos = Float32Array.from([0, 0, 0, 100, 60, -100, 100, 60, -130]);
    const arbol = indice(puntos), tamanos = Float32Array.from([92, 10, 10]); prepararRadiosEspaciales(arbol, tamanos);
    for (const distancia of [300, 700]) {
        const origen = [0, 0, distancia], destino = [100, 60, -100 - distancia];
        const longitud = Math.hypot(...destino), direccion = destino.map(v => v / longitud);
        const mundos = {tamanos, maxTamano: 92, distancia, frente: [0, 0, -1]};
        assert.equal(buscarNodoEspacial(arbol, puntos, origen, direccion, .001, {}, mundos), 1);
    }
});

test('los discos superpuestos se seleccionan según la profundidad que utiliza el buffer gráfico', () => {
    const puntos = Float32Array.from([0, 0, -100, 0, 0, -95]);
    const arbol = indice(puntos), tamanos = Float32Array.from([92, 10]); prepararRadiosEspaciales(arbol, tamanos);
    assert.equal(buscarNodoEspacial(arbol, puntos, [0, 0, 100], [0, 0, -1], .001, {},
        {tamanos, maxTamano: 92, distancia: 200, frente: [0, 0, -1]}), 1, 'La esfera grande detrás no tapa la especie frontal.');
});
