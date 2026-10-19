import test from 'node:test';
import assert from 'node:assert/strict';
import {aperturaRamaDiversidad, crearArbolDiversidad, detalleTaxonomicoDiversidad, encuadreDiversidad, encuadreRaizDiversidad, orientarDiversidad, proyectarDiversidad, radioYemaDiversidad, seleccionarDetalleDiversidad, zoomRaizDiversidad, zoomDiversidad} from '../../resources/js/portal-taxonomy-diversity-model.js';
import {resumenTaxon3D} from '../../resources/js/portal-taxonomy-three-ranks.js';
import {Euler, Matrix4, PerspectiveCamera, Quaternion, Vector3} from 'three';

function indice(nodos) {
    const porClave = new Map(), porPadre = new Map();
    for (const n of nodos) {
        if (porClave.has(n.clave)) continue;
        porClave.set(n.clave, n); if (!porPadre.has(n.padre)) porPadre.set(n.padre, []); porPadre.get(n.padre).push(n);
    }
    return {porClave,porPadre};
}
const nodo = (clave, padre = null) => Object.freeze({clave,padre,nombre:clave});

test('los rangos cerrados no generan ramas ni recorridos anticipados',()=>{
    const a=crearArbolDiversidad(indice([nodo('filo'),nodo('clase','filo'),nodo('orden','clase'),nodo('familia','orden'),nodo('especie','familia')]),'filo');
    const medidos=[],medir=i=>(medidos.push(i),{x:50+i*40,y:50,extension:400});
    const inicial=seleccionarDetalleDiversidad(a,[0,3,7,11,17],3,medir);
    assert.deepEqual(inicial.visibles,[0,1]);assert.deepEqual(medidos,[0,1]);assert.ok(inicial.brotes.has(1));
    const siguiente=seleccionarDetalleDiversidad(a,[0,3,7,11,17],7,medir);
    assert.deepEqual(siguiente.visibles,[0,1,2]);assert.equal(a.claves.length,5);
});

test('en especies masivas se acota el dibujo, se agrupa la densidad y el zoom recupera nodos sin eliminar datos',()=>{
    const a=crearArbolDiversidad(indice([nodo('r'),...Array.from({length:10000},(_,i)=>nodo('e'+i,'r'))]),'r'),rangos=[15,...Array(10000).fill(17)];
    const denso=seleccionarDetalleDiversidad(a,rangos,18,i=>({x:i?200:100,y:100,extension:500}));
    assert.equal(denso.visibles.length,2);assert.ok(denso.brotes.has(0));assert.equal(a.claves.length,10001);
    const abierto=seleccionarDetalleDiversidad(a,rangos,18,i=>({x:i%40*24,y:Math.floor(i/40)%20*24,extension:1000}),{presupuesto:80});
    assert.equal(abierto.visibles.length,80);assert.ok(abierto.brotes.has(0));
    const detalle=seleccionarDetalleDiversidad(a,rangos,18,i=>i===0||i===9999?{x:i?240:100,y:100,extension:1000}:null);
    assert.deepEqual(detalle.visibles,[0,9999]);assert.equal(a.claves[9999],'e9998');
});

test('la vista general abre clase y orden y cada acercamiento habilita el siguiente rango, sin adelantar especies',()=>{
    const esperados=['Orden','Suborden','Familia','Subfamilia','Tribu','Género','Especie'];
    for(let i=0;i<esperados.length;i++)assert.equal(detalleTaxonomicoDiversidad(0,1.6**i).nombre,esperados[i]);
    assert.equal(detalleTaxonomicoDiversidad(0,.5).nombre,'Orden');
    assert.equal(detalleTaxonomicoDiversidad(3,1).nombre,'Orden');
    assert.equal(detalleTaxonomicoDiversidad(0,1e10).nombre,'Especie');
    assert.equal(detalleTaxonomicoDiversidad(11,1).nombre,'Familia');
    assert.equal(detalleTaxonomicoDiversidad(11,1.6).nombre,'Subfamilia');
    assert.equal(detalleTaxonomicoDiversidad(17,1).nombre,'Especie');
    const sinIntermedios=[3,7,11,15,17];
    assert.deepEqual([0,1,2,3].map(i=>detalleTaxonomicoDiversidad(0,1.6**i,sinIntermedios).nombre),['Orden','Familia','Género','Especie']);
});

test('las yemas cuentan especies reales una sola vez, excluyen terminales sin identificar y crecen logarítmicamente',()=>{
    const taxon=(clave,padre,etiqueta)=>({...nodo(clave,padre),etiqueta,total:99999});
    const a=crearArbolDiversidad(indice([taxon('r',null,'Clase'),taxon('g','r','Género'),taxon('e1','g','Especie'),taxon('s1','e1','Subespecie'),taxon('s2','e1','Subespecie'),taxon('e2','g','Especie'),taxon('sin-identificar','r','Género')]),'r');
    const cantidad=k=>a.especies[a.claves.indexOf(k)];
    assert.equal(cantidad('r'),2);assert.equal(cantidad('g'),2);assert.equal(cantidad('e1'),1);assert.equal(cantidad('sin-identificar'),0);
    assert.ok(radioYemaDiversidad(10)>radioYemaDiversidad(1));
    assert.ok(radioYemaDiversidad(100)-radioYemaDiversidad(10)<radioYemaDiversidad(10)-radioYemaDiversidad(0));
    assert.ok(radioYemaDiversidad(1000000)<40);assert.equal(radioYemaDiversidad(NaN),radioYemaDiversidad(0));
    assert.ok(a.radios[0]<2,'El tallo general es fino e independiente del diámetro de la yema.');
});

test('los registros y el resumen breve no sustituyen el conteo de especies de las yemas',()=>{
    const taxon=(clave,padre,etiqueta,total)=>({...nodo(clave,padre),etiqueta,total});
    const fuente=indice([taxon('r',null,'Filo',1000),taxon('c','r','Clase',1000),taxon('o','c','Orden',1000),taxon('so','o','Suborden',1000),taxon('f','so','Familia',1000),taxon('sf','f','Subfamilia',1000),taxon('t','sf','Tribu',1000),taxon('sin-identificar','t','Género',1),taxon('g','t','Género',999),taxon('e','g','Especie',990),taxon('e-menor','g','Especie',9)]);
    const a=crearArbolDiversidad(fuente,'r');
    const especie=a.claves.indexOf('e');
    assert.equal(a.registros[especie],990);assert.equal(a.especies[especie],1,'El conteo de especies no se sustituye por ejemplares.');
    assert.equal(resumenTaxon3D(fuente.porClave.get('o')),'Orden (1.000)');
});

test('el zoom conserva la proyección de la raíz y hace crecer la corona, incluso tras giro y desplazamiento',()=>{
    const raiz=[117,-93,21],corona=new Vector3(137,7,26);
    for(const [ancho,alto,centro,posicion]of[[1000,600,[117,27,21],[217,107,621]],[390,700,[80,-3,30],[-190,147,830]]]){
        let estado={centro,posicion};
        const camara=new PerspectiveCamera(38,ancho/alto,1e-9,1e9);
        const proyectar=p=>{camara.position.fromArray(estado.posicion);camara.lookAt(new Vector3(...estado.centro));camara.updateMatrixWorld();const q=p.clone().project(camara);return new Vector3((q.x+1)*ancho/2,(1-q.y)*alto/2,0);};
        const ancla=proyectar(new Vector3(...raiz)),apertura=proyectar(corona).distanceTo(ancla);
        const inicial=Math.hypot(...posicion.map((v,i)=>v-centro[i]));
        let anterior=apertura;
        // 0,5× -> 0,6× ... 1× y más: la raíz no sigue al cursor ni a otro rango.
        estado=zoomRaizDiversidad(estado,raiz,.5);
        for(const factor of[1.2,7/6,8/7,9/8,10/9,1.6,1.6]){
            const antes=proyectar(corona).distanceTo(ancla);
            estado=zoomRaizDiversidad(estado,raiz,factor);
            assert.ok(proyectar(new Vector3(...raiz)).distanceTo(ancla)<1e-8);
            anterior=proyectar(corona).distanceTo(ancla);assert.ok(anterior>antes,'La corona se amplía alrededor de la raíz fija.');
        }
        for(let i=0;i<30;i++)estado=zoomRaizDiversidad(estado,raiz,1.6,{min:inicial/4096,max:inicial*2});
        assert.ok(proyectar(new Vector3(...raiz)).distanceTo(ancla)<1e-6,'Los gestos acumulados conservan el anclaje a zoom extremo.');
        assert.ok(Math.abs(Math.hypot(...estado.posicion.map((v,i)=>v-estado.centro[i]))-inicial/4096)<1e-8);
        estado=zoomRaizDiversidad(estado,raiz,1e-20,{min:inicial/4096,max:inicial*2});
        assert.ok(proyectar(new Vector3(...raiz)).distanceTo(ancla)<1e-6,'El alejamiento limitado tampoco traslada la raíz.');
        assert.ok(Math.abs(Math.hypot(...estado.posicion.map((v,i)=>v-estado.centro[i]))-inicial*2)<1e-6);
        assert.equal(zoomRaizDiversidad(estado,raiz,NaN),estado);assert.equal(zoomRaizDiversidad(estado,raiz,0),estado);
    }
});

test('pulsar una yema abre dos rangos descendientes disponibles y conserva el zoom progresivo posterior',()=>{
    const taxon=(clave,padre,etiqueta)=>({...nodo(clave,padre),etiqueta});
    const fuente=indice([taxon('c',null,'Clase'),taxon('o','c','Orden'),taxon('so','o','Suborden'),taxon('f','so','Familia'),taxon('g','f','Género'),taxon('e','g','Especie')]);
    const a=crearArbolDiversidad(fuente,'c'),inicial=aperturaRamaDiversidad(a);
    assert.equal(inicial,8);assert.equal(detalleTaxonomicoDiversidad(3,1,[...a.rangos],inicial).nombre,'Suborden');
    assert.equal(detalleTaxonomicoDiversidad(3,1.6,[...a.rangos],inicial).nombre,'Familia');
    assert.equal(aperturaRamaDiversidad(crearArbolDiversidad(fuente,'f')),18,'Los rangos ausentes se omiten sin fabricar taxones.');
    assert.equal(aperturaRamaDiversidad(crearArbolDiversidad(fuente,'e')),17,'Una especie terminal no inventa hijos.');
});

test('el encuadre de clic centra la raíz horizontalmente abajo y contiene los dos niveles abiertos en perspectiva',()=>{
    const taxon=(clave,padre,etiqueta)=>({...nodo(clave,padre),etiqueta});
    const a=crearArbolDiversidad(indice([taxon('c',null,'Clase'),taxon('o1','c','Orden'),taxon('so','o1','Suborden'),taxon('cerrado','so','Familia'),taxon('o2','c','Orden')]),'c');
    const cerrado=a.claves.indexOf('cerrado');a.posiciones[cerrado*2]=1e8;a.posiciones[cerrado*2+1]=1e8;
    for(const [ancho,alto,inferior]of[[1000,600,90],[390,500,150]]){
        const e=encuadreRaizDiversidad(a,8,ancho,alto,{inferior}),camara=new PerspectiveCamera(38,ancho/alto,.01,1e9);
        camara.position.fromArray(e.posicion);camara.lookAt(new Vector3(...e.centro));camara.updateMatrixWorld();
        const proyectar=i=>{const p=new Vector3(a.posiciones[i*2],a.posiciones[i*2+1],a.profundidad[i]).project(camara);return{x:(p.x+1)*ancho/2,y:(1-p.y)*alto/2};};
        const raiz=proyectar(0);assert.ok(Math.abs(raiz.x-ancho/2)<1e-8);assert.ok(Math.abs(raiz.y-(alto-inferior))<1e-8);
        assert.ok(raiz.y>alto*.65);assert.ok(e.distancia<10000,'No se encuadra la geometría de Familia todavía cerrada.');
        for(let i=0;i<a.claves.length;i++)if(a.rangos[i]<=8){const p=proyectar(i);assert.ok(p.x>=35&&p.x<=ancho-35&&p.y>=35&&p.y<=alto-12,'Todos los nodos abiertos caben en la vista.');}
    }
});

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

test('el giro espacial conserva relaciones y distancias y permite restaurar el modelo frontal', () => {
    const a=crearArbolDiversidad(indice([nodo('r'),nodo('a','r'),nodo('b','r'),nodo('c','r'),nodo('d','a')]),'r');
    assert.ok(a.profundidad.some(z=>Math.abs(z)>1),'Las multifurcaciones ocupan también el eje de profundidad.');
    const q=new Quaternion().setFromEuler(new Euler(.47,1.1,.23)),m=new Matrix4().makeRotationFromQuaternion(q);
    const b=orientarDiversidad(a,m.elements);
    assert.equal(b.padres,a.padres);assert.equal(b.claves,a.claves);
    const distancia=(arbol,i,j)=>Math.hypot(arbol.posiciones[i*2]-arbol.posiciones[j*2],arbol.posiciones[i*2+1]-arbol.posiciones[j*2+1],arbol.profundidad[i]-arbol.profundidad[j]);
    for(let i=1;i<a.claves.length;i++) {
        assert.ok(Math.abs(distancia(a,0,i)-distancia(b,0,i))<1e-9);
        const p=b.padres[i],bounds=b.limites.subarray(p*4,p*4+4);
        assert.ok(b.posiciones[i*2]>=bounds[0]&&b.posiciones[i*2]<=bounds[2]&&b.posiciones[i*2+1]>=bounds[1]&&b.posiciones[i*2+1]<=bounds[3]);
    }
    // La restauración siempre parte del modelo base, sin acumular redondeos
    // ni modificar las coordenadas que alimentan los siguientes gestos.
    const vuelta=orientarDiversidad(a,new Matrix4().makeRotationFromQuaternion(new Quaternion()).elements);
    for(let i=0;i<a.posiciones.length;i++) assert.ok(Math.abs(vuelta.posiciones[i]-a.posiciones[i])<1e-10);
    assert.notDeepEqual(b.posiciones,a.posiciones);
    const foco=2,vista={x:b.posiciones[foco*2],y:b.posiciones[foco*2+1],escala:1e7};
    assert.deepEqual(proyectarDiversidad(b,foco,vista,800,500),{x:400,y:250});
});

test('la composición orgánica conserva volumen y abre ramas a alturas distintas sin pisos artificiales',()=>{
    const a=crearArbolDiversidad(indice([nodo('r'),nodo('a','r'),nodo('b','r'),nodo('c','r'),nodo('d','a'),nodo('e','a'),nodo('f','b')]),'r');
    assert.ok(new Set(a.hijos[0].map(i=>a.posiciones[i*2+1].toFixed(5))).size>1);
    for(let i=1;i<a.claves.length;i++){
        const p=a.padres[i];assert.ok(a.radios[i]>0&&a.radios[i]<a.radios[p]);
        assert.ok(a.longitudes[i]>0);assert.ok(a.curvas.subarray(i*6,i*6+6).every(Number.isFinite));
    }
    const b=a.limites3D.subarray(0,6);
    for(let i=0;i<a.claves.length;i++){
        assert.ok(a.posiciones[i*2]>=b[0]&&a.posiciones[i*2]<=b[3]);assert.ok(a.posiciones[i*2+1]>=b[1]&&a.posiciones[i*2+1]<=b[4]);assert.ok(a.profundidad[i]>=b[2]&&a.profundidad[i]<=b[5]);
    }
    assert.ok(b[5]-b[2]>10,'La vista oblicua tiene profundidad geométrica real.');
});
