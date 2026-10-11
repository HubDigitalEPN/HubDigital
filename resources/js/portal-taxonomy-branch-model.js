const normalizar = texto => String(texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();

/** Rama publicada y acíclica; el índice espacial nunca sustituye sus relaciones padre/hijo. */
export function ramaExploradorTaxonomico(indice, clave) {
    const raiz = indice.porClave.get(clave), nodos = [], porClave = new Map();
    if (!raiz) return {nodos, porClave};
    const pendientes = [{nodo: raiz, padreIndice: -1, profundidad: 0}];
    while (pendientes.length) {
        const entrada = pendientes.pop();
        if (porClave.has(entrada.nodo.clave)) continue;
        const i = nodos.length;
        porClave.set(entrada.nodo.clave, i);
        nodos.push({...entrada.nodo, padreIndice: entrada.padreIndice, profundidad: entrada.profundidad, hijos: [],
            posicion: 1, hermanos: 1, descendientes: 0, busqueda: normalizar(entrada.nodo.nombre)});
        if (entrada.padreIndice >= 0) nodos[entrada.padreIndice].hijos.push(i);
        const hijos = indice.porPadre.get(entrada.nodo.clave) || [];
        for (let j = hijos.length - 1; j >= 0; j--)
            pendientes.push({nodo: hijos[j], padreIndice: i, profundidad: entrada.profundidad + 1});
    }
    for (let i = nodos.length - 1; i >= 0; i--) {
        const nodo = nodos[i];
        if (nodo.padreIndice >= 0) nodos[nodo.padreIndice].descendientes += nodo.descendientes + 1;
        nodo.hijos.forEach((hijo, posicion) => {nodos[hijo].posicion = posicion + 1; nodos[hijo].hermanos = nodo.hijos.length;});
    }
    return {nodos, porClave};
}

/** Todas las filas lógicas: el visor sólo virtualiza su DOM, nunca recorta los hijos. */
export function filasExploradorTaxonomico(rama, expandidos, consulta = '') {
    const termino = normalizar(consulta), incluir = new Uint8Array(rama.nodos.length), coincide = new Uint8Array(rama.nodos.length);
    if (termino) {
        for (let i = rama.nodos.length - 1; i >= 0; i--) {
            const nodo = rama.nodos[i];
            coincide[i] = Number(nodo.busqueda.includes(termino)); incluir[i] ||= coincide[i];
            if (incluir[i] && nodo.padreIndice >= 0) incluir[nodo.padreIndice] = 1;
        }
    } else incluir.fill(1);
    const filas = [], visibles = new Uint8Array(rama.nodos.length);
    for (let i = 0; i < rama.nodos.length; i++) {
        const nodo = rama.nodos[i];
        if (!incluir[i] || nodo.padreIndice >= 0 && (!visibles[nodo.padreIndice] || !termino && !expandidos.has(rama.nodos[nodo.padreIndice].clave))) continue;
        visibles[i] = 1;
        filas.push({nodo, coincide: !!coincide[i], abierto: termino ? !!nodo.hijos.some(hijo => incluir[hijo]) : expandidos.has(nodo.clave)});
    }
    return filas;
}

export function ventanaExploradorTaxonomico(cantidad, scroll, alto, alturaFila = 40, margen = 5) {
    const inicio = Math.max(0, Math.min(Math.max(0, cantidad - 1), Math.floor(scroll / alturaFila) - margen));
    const fin = Math.min(cantidad, Math.max(inicio + 1, Math.ceil((scroll + alto) / alturaFila) + margen));
    return {inicio, fin};
}
