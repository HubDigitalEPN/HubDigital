import test from 'node:test';
import assert from 'node:assert/strict';
import {crearIndiceRama3D, distribuirRama3D, filtrarRama3D} from '../../resources/js/portal-taxonomy-three-model.js';

const nodo = (clave, padre = null) => Object.freeze({clave, padre, nombre: clave, total: 1});

test('la topología compacta y el índice compartido conservan la misma proyección por rango', () => {
    const fuente = [{...nodo('clase'), etiqueta: 'Clase'}, {...nodo('orden', 'clase'), etiqueta: 'Orden'},
        {...nodo('familia', 'orden'), etiqueta: 'Familia'}, {...nodo('otro', 'clase'), etiqueta: 'Familia'}];
    const indice = crearIndiceRama3D(fuente), completo = distribuirRama3D(fuente, 'clase');
    assert.deepEqual(distribuirRama3D(fuente, 'clase', indice), completo);
    const compacta = distribuirRama3D(fuente, 'clase', indice, true);
    assert.equal(compacta.posiciones, undefined); assert.equal(compacta.profundidades, undefined);
    assert.deepEqual(compacta.claves, completo.claves); assert.deepEqual(compacta.padres, completo.padres);
    assert.deepEqual(filtrarRama3D(compacta, indice.porClave, 'familia'), filtrarRama3D(completo, fuente, 'familia'));
});

test('los buffers 3D conservan cada padre publicado y aíslan la raíz seleccionada', () => {
    const fuente = Object.freeze([nodo('filo'), nodo('familia', 'filo'), nodo('subfamilia', 'familia'),
        nodo('genero', 'subfamilia'), nodo('especie', 'genero'), nodo('otro-genero', 'familia'),
        nodo('otra-familia', 'filo'), nodo('ajeno', 'otra-familia')]);
    const dibujo = distribuirRama3D(fuente, 'familia');
    assert.deepEqual(new Set(dibujo.claves), new Set(['familia', 'subfamilia', 'genero', 'especie', 'otro-genero']));
    assert.equal(dibujo.padres[0], -1);
    assert.equal(dibujo.posiciones.length, dibujo.claves.length * 3);
    assert.ok(dibujo.posiciones instanceof Float32Array);
    assert.ok(dibujo.padres instanceof Int32Array);
    assert.deepEqual([...dibujo.posiciones.subarray(0, 3)], [0, 0, 0]);
    for (let i = 1; i < dibujo.claves.length; i++) {
        const publicado = fuente.find(n => n.clave === dibujo.claves[i]);
        assert.equal(dibujo.claves[dibujo.padres[i]], publicado.padre);
        assert.equal(dibujo.profundidades[i], dibujo.profundidades[dibujo.padres[i]] + 1);
        assert.ok([...dibujo.posiciones.subarray(i * 3, i * 3 + 3)].every(Number.isFinite));
        assert.notDeepEqual([...dibujo.posiciones.subarray(i * 3, i * 3 + 3)],
            [...dibujo.posiciones.subarray(dibujo.padres[i] * 3, dibujo.padres[i] * 3 + 3)]);
    }
    assert.equal(dibujo.grupos[dibujo.claves.indexOf('especie')], dibujo.grupos[dibujo.claves.indexOf('subfamilia')]);
    assert.notEqual(dibujo.grupos[dibujo.claves.indexOf('otro-genero')], dibujo.grupos[dibujo.claves.indexOf('subfamilia')]);
});

test('las ramas anchas y profundas no truncan puntos ni desbordan la pila o el encuadre', () => {
    const ancha = [nodo('raiz'), ...Array.from({length: 100001}, (_, i) => nodo('h-' + i, 'raiz'))];
    const esfera = distribuirRama3D(ancha, 'raiz');
    assert.equal(esfera.claves.length, ancha.length);
    assert.equal(esfera.padres.length, ancha.length);
    assert.ok(esfera.padres.subarray(1).every(padre => padre === 0));
    assert.ok(esfera.posiciones.every(Number.isFinite));
    const profunda = [nodo('raiz'), ...Array.from({length: 12000}, (_, i) => nodo('n-' + i, i ? 'n-' + (i - 1) : 'raiz'))];
    const cadena = distribuirRama3D(profunda, 'raiz');
    assert.equal(cadena.claves.length, profunda.length);
    assert.equal(cadena.profundidadMaxima, 12000);
    assert.ok(cadena.posiciones.every(x => Number.isFinite(x) && Math.abs(x) <= 600));
    for (let i = 1; i < cadena.claves.length; i++) assert.equal(cadena.padres[i], i - 1);
});

test('terminales y ciclos mantienen buffers finitos sin añadir taxones inexistentes', () => {
    const terminal = distribuirRama3D([nodo('terminal'), nodo('huérfano', 'ausente')], 'terminal');
    assert.deepEqual(terminal.claves, ['terminal']);
    assert.deepEqual([...terminal.posiciones], [0, 0, 0]);
    const ciclo = distribuirRama3D([nodo('a', 'b'), nodo('b', 'a'), nodo('a', 'b')], 'a');
    assert.deepEqual(ciclo.claves, ['a', 'b']);
    assert.deepEqual([...ciclo.padres], [-1, 0]);
    assert.ok(ciclo.posiciones.every(Number.isFinite));
    assert.throws(() => distribuirRama3D([nodo('terminal')], 'ausente'), /raíz/);
});

test('el filtro conserva la raíz visible y seleccionable junto a todos los descendientes del rango', () => {
    const taxon = (clave, padre, etiqueta) => Object.freeze({...nodo(clave, padre), etiqueta});
    const fuente = Object.freeze([taxon('filo', null, 'Filo'), taxon('clase', 'filo', 'Clase'),
        taxon('orden-a', 'clase', 'Orden'), taxon('intermedio', 'orden-a', 'Suborden'),
        taxon('familia-a', 'intermedio', 'Familia'), taxon('familia-anidada', 'familia-a', 'Familia'),
        taxon('orden-b', 'clase', 'Orden'), taxon('familia-b', 'orden-b', 'Familia'),
        taxon('otra-clase', 'filo', 'Clase'), taxon('familia-ajena', 'otra-clase', 'Familia')]);
    const completo = distribuirRama3D(fuente, 'clase'), posicionesOriginales = [...completo.posiciones];
    for (const rango of ['clase', 'orden', 'suborden', 'familia']) {
        const filtrado = filtrarRama3D(completo, fuente, rango);
        const visibles = filtrado.claves.filter((_, i) => filtrado.visibles[i]);
        const esperados = completo.claves.filter(clave => fuente.find(n => n.clave === clave).etiqueta.toLowerCase() === rango);
        assert.deepEqual(new Set(visibles), new Set(['clase', ...esperados]));
        assert.equal(filtrado.cantidadVisible, new Set(['clase', ...esperados]).size);
        assert.equal(filtrado.visibles[0], 1);
        assert.deepEqual([...filtrado.posiciones.subarray(0, 3)], [0, 0, 0]);
        assert.ok(filtrado.posiciones.every(Number.isFinite));
        assert.equal(filtrado.claves[0], 'clase');
        assert.ok(!filtrado.claves.includes('familia-ajena'));
    }
    const familias = filtrarRama3D(completo, fuente, 'familia');
    assert.equal(familias.claves[familias.padres[familias.claves.indexOf('familia-anidada')]], 'familia-a');
    assert.equal(familias.padres[familias.claves.indexOf('familia-b')], 0);
    assert.deepEqual([...completo.posiciones], posicionesOriginales, 'La vista completa se recupera sin alterar su geometría.');
    assert.equal(filtrarRama3D(completo, fuente, ''), completo);
    const ausente = filtrarRama3D(completo, fuente, 'especie');
    assert.equal(ausente.cantidadVisible, 1);
    assert.deepEqual([...ausente.visibles], [1]);
    assert.deepEqual(ausente.claves, ['clase']);
    assert.throws(() => distribuirRama3D(fuente, 'inexistente'), /La raíz no pertenece a la taxonomía pública/,
        'Un linaje inexistente se rechaza y no crea una raíz artificial.');
});

test('un rango masivo mantiene todos sus mundos y las claves originales, sin límite de resultados', () => {
    const fuente = [Object.freeze({...nodo('clase'), etiqueta: 'Clase'}),
        ...Array.from({length: 12001}, (_, i) => Object.freeze({...nodo('orden-' + i, 'clase'), etiqueta: 'Orden'}))];
    const completo = distribuirRama3D(fuente, 'clase'), filtrado = filtrarRama3D(completo, fuente, 'orden');
    assert.equal(filtrado.cantidadVisible, 12002);
    assert.equal(filtrado.visibles.reduce((suma, valor) => suma + valor, 0), 12002);
    assert.equal(filtrado.claves.at(-1), 'orden-12000');
    assert.ok(filtrado.posiciones.every(Number.isFinite));
    assert.ok(filtrado.padres.subarray(1).every(padre => padre === 0));
    assert.deepEqual(new Set(filtrado.origenes), new Set(Array.from({length: 12002}, (_, i) => i)));
});
