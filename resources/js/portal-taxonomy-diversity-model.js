/** Proyección taxonómica, sin distancias evolutivas ni bifurcaciones inventadas.
 * Coordenadas dobles: el visor las proyecta y rebasa antes de enviarlas a la GPU.
 */
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
    const n = claves.length, terminales = new Float64Array(n), posiciones = new Float64Array(n * 2);
    const sectores = new Float64Array(n * 2), limites = new Float64Array(n * 4);
    for (let i = n - 1; i >= 0; i--) terminales[i] = hijos[i].length ? hijos[i].reduce((s, h) => s + terminales[h], 0) : 1;
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
    for (let i = n - 1; i >= 0; i--) {
        let x0 = posiciones[i * 2], x1 = x0, y0 = posiciones[i * 2 + 1], y1 = y0;
        for (const h of hijos[i]) {
            x0 = Math.min(x0, limites[h * 4]); y0 = Math.min(y0, limites[h * 4 + 1]);
            x1 = Math.max(x1, limites[h * 4 + 2]); y1 = Math.max(y1, limites[h * 4 + 3]);
        }
        limites.set([x0, y0, x1, y1], i * 4);
    }
    // Distribución espacial esquemática: cada multifurcación abre un abanico
    // de profundidad. De frente conserva el árbol legible; al girar tiene volumen.
    const profundidad = new Float64Array(n);
    for (let i = 0; i < n; i++) {
        const lista = hijos[i], amplitud = Math.min(180, (sectores[i*2+1]-sectores[i*2])*.5);
        lista.forEach((h,j) => {profundidad[h] = profundidad[i] + (lista.length > 1 ? Math.sin((j+.5)*Math.PI*2/lista.length)*amplitud : 0);});
    }
    const curvas = new Float64Array(n*6);
    for(let i=1;i<n;i++) {
        const p=padres[i],y=(posiciones[p*2+1]+posiciones[i*2+1])/2,z=(profundidad[p]+profundidad[i])/2;
        curvas.set([posiciones[p*2],y,z,posiciones[i*2],y,z],i*6);
    }
    return {claves, padres: Int32Array.from(padres), niveles: Uint32Array.from(niveles), hijos, terminales,
        posiciones, profundidad, curvas, sectores, limites, separacion: Math.max(Number.EPSILON, separacion)};
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
