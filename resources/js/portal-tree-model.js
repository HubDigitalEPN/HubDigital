/** Árbol jerárquico: cada descendiente queda debajo de su padre y los hermanos comparten nivel. */
export function distribuirArbol(nodos, ancho, alto) {
    if (!nodos.length) return {nodos: [], enlaces: [], ancho: Math.max(180, ancho), alto, altoNodo: 48, compacto: false};
    const porId = new Map(nodos.map(n => [String(n.id), n]));
    const padres = new Map();
    for (const nodo of nodos) {
        let padre = porId.get(String(nodo.padre_id)), cursor = padre;
        const vistos = new Set([String(nodo.id)]);
        while (cursor) {
            if (vistos.has(String(cursor.id))) { padre = null; break; }
            vistos.add(String(cursor.id)); cursor = porId.get(String(cursor.padre_id));
        }
        padres.set(String(nodo.id), padre ? String(padre.id) : null);
    }
    const hijos = new Map(), raices = [];
    for (const nodo of nodos) {
        const padre = padres.get(String(nodo.id));
        if (padre === null) raices.push(nodo);
        else { if (!hijos.has(padre)) hijos.set(padre, []); hijos.get(padre).push(nodo); }
    }
    const medidas = new Map(); let hojas = 0, niveles = 0;
    function medir(nodo, nivel) {
        niveles = Math.max(niveles, nivel + 1);
        const descendientes = hijos.get(String(nodo.id)) || [];
        const primera = hojas;
        descendientes.forEach(hijo => medir(hijo, nivel + 1));
        if (!descendientes.length) hojas++;
        medidas.set(String(nodo.id), {nivel, primera, ultima: hojas - 1});
    }
    raices.forEach(nodo => medir(nodo, 0));
    const anchoContenido = Math.max(180, ancho, hojas * 148);
    const compacto = alto < niveles * 60, altoNodo = compacto ? 26 : 48;
    const paso = Math.max(altoNodo + 2, (alto - 8) / niveles);
    const altoContenido = Math.max(alto, niveles * paso + 8);
    const celda = (anchoContenido - 16) / hojas;
    const posiciones = nodos.map(nodo => {
        const {nivel, primera, ultima} = medidas.get(String(nodo.id));
        // Una única rama avanza también lateralmente, conservando el orden de arriba hacia abajo.
        const centroPropuesto = hojas === 1 && niveles > 1 ? 8 + celda * (.25 + .5 * nivel / (niveles - 1))
            : 8 + celda * (primera + ultima + 1) / 2;
        const anchoNodo = Math.min(264, celda * (ultima - primera + 1) - 12);
        const centro = Math.max(8 + anchoNodo / 2, Math.min(centroPropuesto, anchoContenido - 8 - anchoNodo / 2));
        return {...nodo, padre_id: padres.get(String(nodo.id)), fila: nivel, x: centro - anchoNodo / 2,
            y: 4 + nivel * paso + (paso - altoNodo) / 2, ancho: anchoNodo, alto: altoNodo};
    });
    const porPosicion = new Map(posiciones.map(n => [String(n.id), n]));
    const enlaces = posiciones.filter(n => porPosicion.has(n.padre_id)).map(nodo => {
        const padre = porPosicion.get(nodo.padre_id), x1 = padre.x + padre.ancho / 2, x2 = nodo.x + nodo.ancho / 2;
        const inicio = padre.y + padre.alto, medio = (inicio + nodo.y) / 2;
        return {id: String(nodo.id), d: `M${x1},${inicio} V${medio} H${x2} V${nodo.y}`};
    });
    return {nodos: posiciones, enlaces, ancho: anchoContenido, alto: altoContenido, altoNodo, compacto};
}
