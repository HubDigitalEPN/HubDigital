import test from 'node:test';
import assert from 'node:assert/strict';
import {crearArbolDiversidad, encuadreDiversidad, proyectarDiversidad, zoomDiversidad} from '../../resources/js/portal-taxonomy-diversity-model.js';

function indice(nodos) {
    const porClave = new Map(), porPadre = new Map();
    for (const n of nodos) {
        if (porClave.has(n.clave)) continue;
        porClave.set(n.clave, n); if (!porPadre.has(n.padre)) porPadre.set(n.padre, []); porPadre.get(n.padre).push(n);
    }
    return {porClave,porPadre};
}
const nodo = (clave, padre = null) => Object.freeze({clave,padre,nombre:clave});

test('el árbol conserva multifurcaciones, homónimos y huecos de rango sin inventar ancestros', () => {
    const fuente = indice([nodo('filo'),nodo('clase','filo'),nodo('familia-a','clase'),nodo('familia-b','clase'),nodo('genero','clase'),nodo('especie','genero'),nodo('ajeno','filo')]);
    const a = crearArbolDiversidad(fuente,'clase');
    assert.deepEqual(a.claves,['clase','familia-a','familia-b','genero','especie']);
    assert.equal(a.hijos[0].length,3);
    assert.equal(a.terminales[0],3);
    for(let i=1;i<a.claves.length;i++) assert.equal(a.claves[a.padres[i]],fuente.porClave.get(a.claves[i]).padre);
    assert.ok(a.posiciones instanceof Float64Array);
    for(let i=1;i<a.claves.length;i++) {
        const p=a.padres[i]; assert.ok(a.sectores[i*2]>=a.sectores[p*2]);assert.ok(a.sectores[i*2+1]<=a.sectores[p*2+1]);
        assert.ok(a.posiciones[i*2+1]>a.posiciones[p*2+1]);
    }
    assert.equal(fuente.porClave.get('genero').padre,'clase');
});

test('zoom profundo anclado mantiene el taxón bajo el cursor y respeta ambos límites', () => {
    let cam={x:311.457,y:240.3,escala:.5};
    const punto={x:123,y:-71}, esperado={x:cam.x+punto.x/cam.escala,y:cam.y+punto.y/cam.escala};
    const limites={min:.25,max:1000000};
    for(let i=0;i<100;i++) {
        cam=zoomDiversidad(cam,1.25,punto,limites);
        assert.ok(Math.abs(cam.x+punto.x/cam.escala-esperado.x)<1e-9);
        assert.ok(Math.abs(cam.y+punto.y/cam.escala-esperado.y)<1e-9);
    }
    assert.equal(cam.escala,limites.max);
    cam=zoomDiversidad(cam,1e-20,punto,limites);assert.equal(cam.escala,limites.min);
    assert.equal(zoomDiversidad(cam,NaN,punto,limites),cam);
    assert.equal(zoomDiversidad(cam,-1,punto,limites),cam);
});

test('una hoja, una rama extensa y una cadena profunda tienen encuadres finitos sin truncar taxones', () => {
    const extensa=indice([nodo('raiz'),...Array.from({length:10000},(_,i)=>nodo('h'+i,'raiz'))]);
    const a=crearArbolDiversidad(extensa,'raiz');
    assert.equal(a.claves.length,10001);assert.equal(a.terminales[0],10000);
    assert.ok(a.separacion>0);assert.ok(a.posiciones.every(Number.isFinite));
    const vista=encuadreDiversidad(a,900,500);
    for(const i of [0,1,10000]){const p=proyectarDiversidad(a,i,vista,900,500);assert.ok(p.x>=0&&p.x<=900&&p.y>=0&&p.y<=500);}
    const cadena=crearArbolDiversidad(indice([nodo('r'),...Array.from({length:12000},(_,i)=>nodo('n'+i,i?'n'+(i-1):'r'))]),'r');
    assert.equal(cadena.claves.length,12001);assert.equal(cadena.terminales[0],1);
    assert.ok(Object.values(encuadreDiversidad(cadena,400,300)).every(Number.isFinite));
    const hoja=crearArbolDiversidad(extensa,'h1');assert.equal(hoja.claves.length,1);assert.equal(hoja.terminales[0],1);
    assert.ok(Object.values(encuadreDiversidad(hoja,1,1)).every(Number.isFinite));
    const ciclo=crearArbolDiversidad(indice([nodo('a','b'),nodo('b','a')]),'a');assert.deepEqual(ciclo.claves,['a','b']);
    assert.throws(()=>crearArbolDiversidad(extensa,'privado'),/pública/);
});

test('recentrar una rama y proyectar coordenadas rebajadas mantiene precisión durante acercamientos extremos', () => {
    const a=crearArbolDiversidad(indice([nodo('a'),nodo('b','a'),nodo('c','a'),nodo('d','b')]),'a');
    const i=a.claves.indexOf('d'), vista={x:a.posiciones[i*2],y:a.posiciones[i*2+1],escala:1e8};
    assert.deepEqual(proyectarDiversidad(a,i,vista,800,500),{x:400,y:250});
    assert.ok(Object.values(encuadreDiversidad(a,800,500,a.claves.indexOf('b'))).every(Number.isFinite));
});
