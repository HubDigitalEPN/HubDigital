/** Distribuye niveles en bandas alternadas sin reducir el tamaño de las etiquetas. */
export function distribuirArbol(nodos, ancho, alto) {
    const porId = new Map(nodos.map(nodo => [String(nodo.id), nodo]));
    const profundidad = nodo => {
        let nivel = 0, actual = nodo;
        const vistos = new Set([String(nodo.id)]);
        while (actual.padre_id && porId.has(String(actual.padre_id)) && !vistos.has(String(actual.padre_id))) {
            vistos.add(String(actual.padre_id)); actual = porId.get(String(actual.padre_id)); nivel++;
        }
        return nivel;
    };
    // Cada nodo ocupa una celda. Una familia con cinco ejemplares no necesita
    // una columna de cinco filas que agrande también toda la banda de su padre.
    const ordenados = nodos.map((nodo, indice) => ({nodo, indice, nivel: profundidad(nodo)}))
        .sort((a, b) => a.nivel - b.nivel || a.indice - b.indice).map(({nodo}) => nodo);
    const columnas = Math.max(1, Math.min(nodos.length || 1, Math.floor(Math.max(170, ancho) / 175), Math.ceil(Math.sqrt(nodos.length * Math.max(1, ancho / Math.max(180, alto))))));
    const celda = Math.max(170, ancho / columnas);
    const posiciones = [], porPosicion = new Map();
    const filas = Math.max(1, Math.ceil(ordenados.length / columnas));
    const paso = Math.max(72, (alto - 8) / filas);
    ordenados.forEach((nodo, indice) => {
        const fila = Math.floor(indice / columnas);
        const columna = fila % 2 ? columnas - 1 - indice % columnas : indice % columnas;
        const pos = {...nodo, fila, x: columna * celda + 12, y: 4 + fila * paso + (paso - 64) / 2, ancho: celda - 32};
        posiciones.push(pos); porPosicion.set(String(nodo.id), pos);
    });
    const enlaces = posiciones.filter(nodo => porPosicion.has(String(nodo.padre_id))).map(nodo => {
        const padre = porPosicion.get(String(nodo.padre_id));
        const mismaBanda = padre.fila === nodo.fila;
        const adyacentes = mismaBanda && Math.abs(padre.x - nodo.x) < celda + 1;
        const derecha = nodo.x > padre.x;
        if (adyacentes) {
            const x1 = padre.x + (derecha ? padre.ancho : 0), x2 = nodo.x + (derecha ? 0 : nodo.ancho);
            return {id: String(nodo.id), d: `M${x1},${padre.y+32} H${x2}`};
        }
        // Los enlaces largos recorren los espacios entre celdas y filas.
        // Así una rama con cinco hojas no atraviesa las tarjetas intermedias.
        const salida = padre.x + padre.ancho, carril = salida + 6, llegada = nodo.x + nodo.ancho / 2;
        return {id: String(nodo.id), d: `M${salida},${padre.y+32} H${carril} V${nodo.y-6} H${llegada} V${nodo.y}`};
    });
    return {nodos: posiciones, enlaces, ancho: Math.max(170, ancho), alto: Math.max(alto, filas * paso + 8)};
}
