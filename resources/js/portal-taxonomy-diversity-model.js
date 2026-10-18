import {rangoVisual3D} from './portal-taxonomy-three-ranks.js';

/** Proyección taxonómica, sin distancias evolutivas ni bifurcaciones inventadas.
 * Coordenadas dobles: el visor las proyecta y rebasa antes de enviarlas a la GPU.
 */
const ETAPAS_DIVERSIDAD=[
    {orden:3,nombre:'Clase'},{orden:7,nombre:'Orden'},{orden:8,nombre:'Suborden'},
    {orden:11,nombre:'Familia'},{orden:12,nombre:'Subfamilia'},{orden:13,nombre:'Tribu'},
    {orden:15,nombre:'Género'},{orden:18,nombre:'Especie'},
];

/** El zoom habilita rangos formales, independientemente de los huecos del linaje. */
export function detalleTaxonomicoDiversidad(ordenRaiz,zoom,disponibles){
    const encontrado=ETAPAS_DIVERSIDAD.findIndex(n=>n.orden>=ordenRaiz);
    // La vista general abre Clase y Orden juntos. Una selección más específica
    // comienza en su propio rango y nunca fabrica categorías intermedias.
    const inicial=encontrado<0?ETAPAS_DIVERSIDAD.length-1:Math.max(1,encontrado);
    const pasos=Math.max(0,Math.floor(Math.log(Math.max(1,Number.isFinite(zoom)?zoom:1))/Math.log(1.6)+1e-6));
    const etapas=ETAPAS_DIVERSIDAD.filter((etapa,i)=>i>=inicial&&(i===inicial||!disponibles||disponibles.some(orden=>orden>(ETAPAS_DIVERSIDAD[i-1]?.orden??-1)&&orden<=etapa.orden)));
    return etapas[Math.min(etapas.length-1,pasos)];
}

/** Radio en píxeles CSS: contar especies identificadas, nunca ejemplares ni
 * terminales sin identificar. El logaritmo mantiene legibles clados muy grandes.
 */
export function radioYemaDiversidad(especies){
    return 4+2.5*Math.log1p(Math.max(0,Number.isFinite(especies)?especies:0));
}

/** Presupuesto visual: nunca recorre rangos aún cerrados ni borra taxones del modelo.
 * Los clados fuera de pantalla no compiten por espacio. Los densos conservan
 * su nodo real como brote; al acercarse, sus hijos vuelven a competir por espacio.
 */
export function seleccionarDetalleDiversidad(arbol,rangos,limite,medir,{presupuesto=160,celda=22,umbral=100,ancho=1000,alto=600}={}){
    const visibles=[],brotes=new Set(),ocupadas=new Set(),pendientes=[];
    const agregar=i=>{
        const m=medir(i);if(!m)return;
        const p={i,...m,prioridad:Math.min(1e9,m.extension)/(1+Math.hypot(m.x-ancho/2,m.y-alto/2)/Math.max(ancho,alto))};
        let j=pendientes.length;pendientes.push(p);
        while(j>0){const padre=(j-1)>>1;if(pendientes[padre].prioridad>=p.prioridad)break;pendientes[j]=pendientes[padre];j=padre;}pendientes[j]=p;
    };
    const sacar=()=>{
        const primero=pendientes[0],ultimo=pendientes.pop();if(!pendientes.length)return primero;
        let j=0;while(j*2+1<pendientes.length){let h=j*2+1;if(h+1<pendientes.length&&pendientes[h+1].prioridad>pendientes[h].prioridad)h++;if(pendientes[h].prioridad<=ultimo.prioridad)break;pendientes[j]=pendientes[h];j=h;}pendientes[j]=ultimo;return primero;
    };
    agregar(0);
    while(pendientes.length){
        if(visibles.length>=presupuesto){for(const p of pendientes)if(arbol.padres[p.i]>=0)brotes.add(arbol.padres[p.i]);break;}
        const p=sacar(),i=p.i,padre=arbol.padres[i];
        const enPantalla=p.x>=0&&p.x<=ancho&&p.y>=0&&p.y<=alto;
        const celdaId=`${rangos[i]}:${Math.floor(p.x/celda)}:${Math.floor(p.y/celda)}`;
        if(visibles.length>=presupuesto||(i>0&&enPantalla&&ocupadas.has(celdaId))){if(padre>=0)brotes.add(padre);continue;}
        visibles.push(i);if(enPantalla)ocupadas.add(celdaId);
        const hijos=arbol.hijos[i];if(!hijos.length)continue;
        if(p.extension<umbral){brotes.add(i);continue;}
        for(const h of hijos){if(rangos[h]>limite){brotes.add(i);continue;}agregar(h);}
    }
    return {visibles,brotes};
}

export function crearArbolDiversidad(indice, raiz) {
    if (!indice.porClave.has(raiz)) throw new Error('La raíz no pertenece a la taxonomía pública.');
    const claves = [], padres = [], niveles = [], hijos = [], vistos = new Set();
    const pila = [{clave: raiz, padre: -1, nivel: 0}];
    while (pila.length) {
        const entrada = pila.pop();
        if (vistos.has(entrada.clave) || !indice.porClave.has(entrada.clave)) continue;
        vistos.add(entrada.clave);
        const i = claves.length;
        claves.push(entrada.clave); padres.push(entrada.padre); niveles.push(entrada.nivel); hijos.push([]);
        if (entrada.padre >= 0) hijos[entrada.padre].push(i);
        const lista = indice.porPadre.get(entrada.clave) || [];
        for (let j = lista.length - 1; j >= 0; j--) pila.push({clave: lista[j].clave, padre: i, nivel: entrada.nivel + 1});
    }
    const n = claves.length, terminales = new Float64Array(n), especies = new Float64Array(n), posiciones = new Float64Array(n * 2);
    const sectores = new Float64Array(n * 2), limites = new Float64Array(n * 4);
    for (let i = n - 1; i >= 0; i--) terminales[i] = hijos[i].length ? hijos[i].reduce((s, h) => s + terminales[h], 0) : 1;
    // Una especie con subespecies cuenta una vez. Las claves públicas ya
    // distinguen identidades y linajes, incluidos los nombres homónimos.
    for (let i=n-1;i>=0;i--) especies[i]=rangoVisual3D(indice.porClave.get(claves[i])).orden===17?1:hijos[i].reduce((s,h)=>s+especies[h],0);
    sectores[0] = -700; sectores[1] = 700;
    let separacion = 140;
    for (let i = 0; i < n; i++) {
        const inicio = sectores[i * 2], fin = sectores[i * 2 + 1], lista = hijos[i];
        posiciones[i * 2] = (inicio + fin) / 2; posiciones[i * 2 + 1] = niveles[i] * 140;
        if (!lista.length) {separacion = Math.min(separacion, fin - inicio); continue;}
        // El espacio facilita leer clados pequeños; no codifica abundancia ni tiempo.
        const pesos = lista.map(h => Math.pow(terminales[h], .65));
        const suma = pesos.reduce((s, v) => s + v, 0), margen = lista.length > 1 ? (fin - inicio) * .025 : 0;
        let cursor = inicio + margen;
        for (let j = 0; j < lista.length; j++) {
            const h = lista[j]; sectores[h * 2] = cursor;
            cursor += (fin - inicio - margen * 2) * pesos[j] / suma; sectores[h * 2 + 1] = cursor;
        }
    }
    // Ramas orgánicas de escala decreciente, en lugar de pisos horizontales.
    // Cada bifurcación sigue siendo exactamente la publicada en el catálogo.
    const profundidad = new Float64Array(n), longitudes = new Float64Array(n), radios = new Float64Array(n), angulos = new Float64Array(n);
    longitudes[0] = 230; radios[0] = 1.25;
    posiciones.fill(0);
    for (let i = 0; i < n; i++) {
        const lista = hijos[i], pesos = lista.map(h => Math.pow(terminales[h], .65));
        const suma = pesos.reduce((s, v) => s + v, 0);
        let cursor = 0;
        lista.forEach((h, j) => {
            const proporcion = pesos[j] / suma, medio = cursor + proporcion / 2;
            const abanico = lista.length > 1 ? (medio - .5) * 2.45 : Math.sin(niveles[h] * 1.7) * .12;
            angulos[h] = Math.max(-1.32, Math.min(1.32, angulos[i] * .48 + abanico));
            longitudes[h] = Math.max(.02, longitudes[i] * (lista.length === 1 ? .77 : .37 + .48 * Math.sqrt(proporcion)));
            // Los hijos grandes continúan el tronco; los pequeños se desprenden
            // a diferentes alturas. No se fabrican ancestros de esas salidas.
            const tramo = longitudes[h] + longitudes[i] * (lista.length > 1 ? .18 + .16 * medio : .12);
            posiciones[h*2] = posiciones[i*2] + Math.sin(angulos[h]) * tramo;
            posiciones[h*2+1] = posiciones[i*2+1] + Math.cos(angulos[h]) * tramo;
            profundidad[h] = profundidad[i] + Math.sin((j + .5) * 2.39996 + niveles[h] * .8) * tramo * .48;
            radios[h] = Math.max(.00002, radios[i] * (lista.length === 1 ? .78 : .24 + .57 * Math.sqrt(proporcion)));
            cursor += proporcion;
        });
    }
    const curvas = new Float64Array(n*6);
    for(let i=1;i<n;i++) {
        const p=padres[i], distancia=Math.hypot(posiciones[i*2]-posiciones[p*2],posiciones[i*2+1]-posiciones[p*2+1]);
        curvas.set([posiciones[p*2]+Math.sin(angulos[p]) * distancia * .46,
            posiciones[p*2+1]+Math.cos(angulos[p]) * distancia * .46,
            profundidad[p]+(profundidad[i]-profundidad[p])*.18,
            posiciones[i*2]-Math.sin(angulos[i]) * distancia * .36,
            posiciones[i*2+1]-Math.cos(angulos[i]) * distancia * .36,
            profundidad[i]-(profundidad[i]-profundidad[p])*.25],i*6);
    }
    const limites3D = new Float64Array(n*6);
    for (let i = n - 1; i >= 0; i--) {
        let x0 = posiciones[i * 2], x1 = x0, y0 = posiciones[i * 2 + 1], y1 = y0;
        for (const h of hijos[i]) {
            x0 = Math.min(x0, limites[h * 4]); y0 = Math.min(y0, limites[h * 4 + 1]);
            x1 = Math.max(x1, limites[h * 4 + 2]); y1 = Math.max(y1, limites[h * 4 + 3]);
        }
        limites.set([x0, y0, x1, y1], i * 4);
        let z0=profundidad[i],z1=z0;
        for(const h of hijos[i]) {z0=Math.min(z0,limites3D[h*6+2]);z1=Math.max(z1,limites3D[h*6+5]);}
        limites3D.set([x0,y0,z0,x1,y1,z1],i*6);
    }
    return {claves, padres: Int32Array.from(padres), niveles: Uint32Array.from(niveles), hijos, terminales, especies,
        posiciones, profundidad, curvas, sectores, limites, limites3D, radios, angulos, longitudes, separacion: Math.max(Number.EPSILON, separacion)};
}

/** Gira posiciones y cajas con la misma matriz 3D; etiquetas y selección usan
 * estas coordenadas, nunca un desplazamiento CSS independiente de la geometría.
 * El pivote es el centro del árbol, no el origen de la página ni el de la cámara.
 */
export function orientarDiversidad(arbol, matriz) {
    const posiciones = new Float64Array(arbol.posiciones.length), profundidad = new Float64Array(arbol.claves.length);
    const limites = new Float64Array(arbol.limites.length), curvas = new Float64Array(arbol.curvas.length), [x0,y0,x1,y1] = arbol.limites.subarray(0,4);
    const cx=(x0+x1)/2, cy=(y0+y1)/2;
    for(let i=0;i<arbol.claves.length;i++) {
        const x=arbol.posiciones[i*2]-cx,y=arbol.posiciones[i*2+1]-cy,z=arbol.profundidad[i];
        posiciones[i*2]=cx+matriz[0]*x+matriz[4]*y+matriz[8]*z;
        posiciones[i*2+1]=cy+matriz[1]*x+matriz[5]*y+matriz[9]*z;
        profundidad[i]=matriz[2]*x+matriz[6]*y+matriz[10]*z;
        for(let j=0;j<2;j++) {
            const offset=i*6+j*3,x=arbol.curvas[offset]-cx,y=arbol.curvas[offset+1]-cy,z=arbol.curvas[offset+2];
            curvas[offset]=cx+matriz[0]*x+matriz[4]*y+matriz[8]*z;
            curvas[offset+1]=cy+matriz[1]*x+matriz[5]*y+matriz[9]*z;
            curvas[offset+2]=matriz[2]*x+matriz[6]*y+matriz[10]*z;
        }
    }
    for(let i=arbol.claves.length-1;i>=0;i--) {
        let x0=posiciones[i*2],x1=x0,y0=posiciones[i*2+1],y1=y0;
        for(const h of arbol.hijos[i]) {x0=Math.min(x0,limites[h*4]);y0=Math.min(y0,limites[h*4+1]);x1=Math.max(x1,limites[h*4+2]);y1=Math.max(y1,limites[h*4+3]);}
        // Incluye los controles Bézier: una rama arqueada también puede entrar
        // en el encuadre cuando sus dos extremos quedan fuera.
        for(const h of arbol.hijos[i]) for(let j=0;j<2;j++) {
            const o=h*6+j*3;x0=Math.min(x0,curvas[o]);x1=Math.max(x1,curvas[o]);y0=Math.min(y0,curvas[o+1]);y1=Math.max(y1,curvas[o+1]);
        }
        limites.set([x0,y0,x1,y1],i*4);
    }
    return {...arbol,posiciones,profundidad,curvas,limites};
}

export function encuadreDiversidad(arbol, ancho, alto, nodo = 0) {
    const [x0, y0, x1, y1] = arbol.limites.subarray(nodo * 4, nodo * 4 + 4);
    const escala = Math.min(Math.max(1, ancho - 160) / Math.max(200, x1 - x0), Math.max(1, alto - 125) / Math.max(180, y1 - y0));
    return {x: (x0 + x1) / 2, y: (y0 + y1) / 2, escala};
}

/** Conserva el punto bajo el cursor, también tras muchos acercamientos. */
export function zoomDiversidad(vista, factor, punto, limites) {
    if (!Number.isFinite(factor) || factor <= 0) return vista;
    const escala = Math.max(limites.min, Math.min(limites.max, vista.escala * factor));
    return {escala, x: vista.x + punto.x / vista.escala - punto.x / escala,
        y: vista.y + punto.y / vista.escala - punto.y / escala};
}

export function proyectarDiversidad(arbol, i, vista, ancho, alto) {
    return {x: ancho / 2 + (arbol.posiciones[i * 2] - vista.x) * vista.escala,
        y: alto / 2 - (arbol.posiciones[i * 2 + 1] - vista.y) * vista.escala};
}
