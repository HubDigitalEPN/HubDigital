/** Adapta las relaciones publicadas al JSON jerárquico del visor phylotree.js. */
export function indicePublicoTaxonomico(nodos) {
    const porClave = new Map(), porPadre = new Map();
    for (const nodo of nodos) {
        if (!nodo?.clave || porClave.has(nodo.clave)) continue;
        // El motor recibe objetos planos, fuera de los proxies reactivos de Alpine.
        const publicado = {...nodo};
        porClave.set(nodo.clave, publicado);
        if (!porPadre.has(nodo.padre ?? null)) porPadre.set(nodo.padre ?? null, []);
        porPadre.get(nodo.padre ?? null).push(publicado);
    }
    for (const hijos of porPadre.values()) hijos.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es') || a.clave.localeCompare(b.clave));
    return {porClave, porPadre};
}

export function ramaPublicaPhylotree(indice, clave, niveles = Infinity) {
    const raiz = indice.porClave.get(clave);
    if (!raiz) return null;
    const hijos = indice.porPadre.get(clave) || [];
    const nodos = [], relaciones = [], vistos = new Set();
    const convertir = (nodo, nivel = 0) => {
        vistos.add(nodo.clave); nodos.push(nodo);
        const json = {name: nodo.nombre, attribute: '1', portal: nodo};
        const descendientes = [];
        for (const hijo of nivel < niveles ? indice.porPadre.get(nodo.clave) || [] : []) {
            if (vistos.has(hijo.clave)) continue;
            relaciones.push({padre: nodo.clave, hijo: hijo.clave});
            descendientes.push(convertir(hijo, nivel + 1));
        }
        if (descendientes.length) json.children = descendientes;
        return json;
    };
    const json = convertir(raiz);
    return {json, raiz, hijos, nodos, relaciones};
}
