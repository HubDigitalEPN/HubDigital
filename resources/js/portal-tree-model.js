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
    const niveles = [];
    for (const nodo of nodos) (niveles[profundidad(nodo)] ||= []).push(nodo);
    // Un padre cíclico o ausente nunca deja bandas indefinidas en la presentación.
    for (let nivel = 0; nivel < niveles.length; nivel++) niveles[nivel] ||= [];
    const columnas = Math.max(1, Math.min(niveles.length, Math.floor(Math.max(170, ancho) / 175), Math.ceil(Math.sqrt(niveles.length * Math.max(1, ancho / Math.max(250, alto))))));
    const celda = Math.max(170, ancho / columnas);
    const posiciones = [], porPosicion = new Map();
    const bandas = [];
    for (let inicio = 0; inicio < niveles.length; inicio += columnas) {
        const nivelesBanda = niveles.slice(inicio, inicio + columnas);
        bandas.push({inicio, niveles: nivelesBanda, minimo: Math.max(100, Math.max(...nivelesBanda.map(nivel => nivel.length)) * 82)});
    }
    const espacio = Math.max(0, alto - 40 - Math.max(0, bandas.length - 1) * 26 - bandas.reduce((suma, banda) => suma + banda.minimo, 0));
    let yBanda = 20;
    for (const {inicio, niveles: banda, minimo} of bandas) {
        const altoBanda = minimo + espacio / bandas.length;
        banda.forEach((nivel, i) => {
            const columna = Math.floor(inicio / columnas) % 2 ? columnas - 1 - i : i;
            nivel.forEach((nodo, j) => {
                const pos = {...nodo, x: columna * celda + 12, y: yBanda + (j + .5) * altoBanda / nivel.length - 32, ancho: celda - 32};
                posiciones.push(pos); porPosicion.set(String(nodo.id), pos);
            });
        });
        yBanda += altoBanda + 26;
    }
    const enlaces = posiciones.filter(nodo => porPosicion.has(String(nodo.padre_id))).map(nodo => {
        const padre = porPosicion.get(String(nodo.padre_id));
        const mismaBanda = Math.floor(profundidad(padre) / columnas) === Math.floor(profundidad(nodo) / columnas);
        const derecha = nodo.x > padre.x;
        const x1 = mismaBanda ? padre.x + (derecha ? padre.ancho : 0) : padre.x + padre.ancho / 2;
        const x2 = mismaBanda ? nodo.x + (derecha ? 0 : nodo.ancho) : nodo.x + nodo.ancho / 2;
        const y1 = padre.y + (mismaBanda ? 32 : 64), y2 = nodo.y + (mismaBanda ? 32 : 0);
        return {id: String(nodo.id), d: mismaBanda ? `M${x1},${y1} H${(x1+x2)/2} V${y2} H${x2}` : `M${x1},${y1} V${(y1+y2)/2} H${x2} V${y2}`};
    });
    return {nodos: posiciones, enlaces, ancho: Math.max(170, ancho), alto: Math.max(alto, yBanda - 6)};
}
