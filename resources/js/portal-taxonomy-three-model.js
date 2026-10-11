import {rangoVisual3D} from './portal-taxonomy-three-ranks.js';

/** La jerarquía pública es independiente del índice espacial y de Three.js. */
export function crearIndiceRama3D(nodos) {
    const porClave = new Map(), porPadre = new Map();
    for (const nodo of nodos) {
        if (!nodo?.clave || porClave.has(nodo.clave)) continue;
        porClave.set(nodo.clave, nodo);
        const hijos = porPadre.get(nodo.padre) || [];
        hijos.push(nodo.clave); porPadre.set(nodo.padre, hijos);
    }
    return {porClave, porPadre};
}

export function distribuirRama3D(nodos, claveRaiz, indicePreparado = crearIndiceRama3D(nodos), soloEstructura = false) {
    const {porClave, porPadre} = indicePreparado;
    if (!porClave.has(claveRaiz)) throw new Error('La raíz no pertenece a la taxonomía pública.');
    const claves = [], padres = [], profundidades = [], grupos = [], hijos = [], vistos = new Set();
    const pendientes = [{clave: claveRaiz, padre: -1, profundidad: 0, grupo: 0}];
    while (pendientes.length) {
        const entrada = pendientes.pop();
        if (vistos.has(entrada.clave)) continue;
        vistos.add(entrada.clave);
        const i = claves.length;
        claves.push(entrada.clave); padres.push(entrada.padre); grupos.push(entrada.grupo);
        if (!soloEstructura) {
            profundidades.push(entrada.profundidad); hijos.push([]);
            if (entrada.padre >= 0) hijos[entrada.padre].push(i);
        }
        const descendientes = porPadre.get(entrada.clave) || [];
        for (let j = descendientes.length - 1; j >= 0; j--) {
            pendientes.push({clave: descendientes[j], padre: i, profundidad: entrada.profundidad + 1,
                grupo: entrada.profundidad === 0 ? j + 1 : entrada.grupo});
        }
    }
    if (soloEstructura) return {claves, padres: Int32Array.from(padres), grupos: Uint32Array.from(grupos)};
    const cantidad = claves.length, pesos = new Float64Array(cantidad), posiciones = new Float32Array(cantidad * 3);
    const inicios = new Float64Array(cantidad), finales = new Float64Array(cantidad);
    let profundidadMaxima = 0;
    for (let i = cantidad - 1; i >= 0; i--) {
        pesos[i] = hijos[i].length ? hijos[i].reduce((suma, hijo) => suma + pesos[hijo], 0) : 1;
        profundidadMaxima = Math.max(profundidadMaxima, profundidades[i]);
    }
    finales[0] = Math.PI * 2;
    for (let i = 0; i < cantidad; i++) {
        const angulo = (inicios[i] + finales[i]) / 2;
        const radio = i === 0 ? 0 : 175 + 145 * (profundidades[i] - 1) / Math.max(1, profundidadMaxima - 1);
        // Sectores contiguos por padre; la latitud separa las capas en la esfera.
        const latitud = .78 * Math.sin(angulo) + .16 * Math.sin(angulo * 3 + profundidades[i] * .7);
        if (i > 0) {
            posiciones[i * 3] = radio * Math.cos(latitud) * Math.cos(angulo);
            posiciones[i * 3 + 1] = radio * Math.sin(latitud);
            posiciones[i * 3 + 2] = radio * Math.cos(latitud) * Math.sin(angulo);
        }
        const lista = hijos[i];
        if (!lista.length) continue;
        const ponderaciones = lista.map(hijo => Math.sqrt(pesos[hijo]));
        const suma = ponderaciones.reduce((total, peso) => total + peso, 0);
        const margen = i === 0 ? 0 : (finales[i] - inicios[i]) * .035;
        let cursor = inicios[i] + margen;
        const amplitud = finales[i] - inicios[i] - margen * 2;
        for (let j = 0; j < lista.length; j++) {
            const hijo = lista[j];
            inicios[hijo] = cursor; cursor += amplitud * ponderaciones[j] / suma; finales[hijo] = cursor;
        }
    }
    // Una cadena corta se lee de izquierda a derecha; no necesita una esfera vacía.
    if (hijos.every(lista => lista.length <= 1)) {
        const paso = Math.min(100, 600 / Math.max(1, profundidadMaxima));
        for (let i = 0; i < cantidad; i++) {
            posiciones[i * 3] = profundidades[i] * paso; posiciones[i * 3 + 1] = posiciones[i * 3 + 2] = 0;
        }
    }
    return {claves, posiciones, padres: Int32Array.from(padres), grupos: Uint32Array.from(grupos),
        profundidades: Uint32Array.from(profundidades), profundidadMaxima};
}

/** Proyección por rango: la raíz siempre permanece visible y seleccionable. */
export function filtrarRama3D(completo, nodos, rangoId) {
    if (!rangoId || !completo.claves.length) return completo;
    const porClave = nodos instanceof Map ? nodos : new Map(nodos.map(nodo => [nodo.clave, nodo]));
    const seleccion = [];
    for (let i = 0; i < completo.claves.length; i++) {
        const nodo = porClave.get(completo.claves[i]);
        if (nodo && rangoVisual3D(nodo).id === rangoId) seleccion.push(i);
    }
    // La raíz conserva su identidad aunque pertenezca a otro rango.
    const origenes = Uint32Array.from(seleccion[0] === 0 ? seleccion : [0, ...seleccion]);
    const cantidad = origenes.length, posiciones = new Float32Array(cantidad * 3);
    const padres = new Int32Array(cantidad), visibles = new Uint8Array(cantidad);
    const grupos = new Uint32Array(cantidad), profundidades = new Uint32Array(cantidad);
    const proyectados = new Int32Array(completo.claves.length); proyectados.fill(-1);
    for (let i = 0; i < cantidad; i++) proyectados[origenes[i]] = i;
    const ancestro = new Int32Array(completo.claves.length); ancestro.fill(-1);
    for (let i = 0; i < completo.claves.length; i++) {
        const padre = completo.padres[i];
        ancestro[i] = proyectados[i] >= 0 ? proyectados[i] : padre >= 0 ? ancestro[padre] : 0;
    }
    padres[0] = -1;
    const mundos = cantidad - 1;
    visibles[0] = 1;
    const anguloAureo = Math.PI * (3 - Math.sqrt(5));
    for (let i = 1; i < cantidad; i++) {
        const original = origenes[i], padre = completo.padres[original];
        padres[i] = padre >= 0 ? ancestro[padre] : 0;
        visibles[i] = 1; grupos[i] = completo.grupos[original];
        profundidades[i] = profundidades[padres[i]] + 1;
        // Distribución volumétrica, también para pocos mundos: evita que la
        // cámara inicial muestre una órbita de canto o estirada horizontalmente.
        const latitud = mundos < 4 ? .45 * Math.sin((i - 1) * Math.PI * 2 / Math.max(1, mundos))
            : 1 - 2 * (i - .5) / mundos;
        const angulo = mundos < 4 ? (i - 1) * Math.PI * 2 / Math.max(1, mundos) : (i - 1) * anguloAureo;
        const radio = 265, horizontal = Math.sqrt(Math.max(0, 1 - latitud * latitud));
        posiciones[i * 3] = radio * horizontal * Math.cos(angulo);
        posiciones[i * 3 + 1] = radio * latitud;
        posiciones[i * 3 + 2] = radio * horizontal * Math.sin(angulo);
    }
    return {claves: Array.from(origenes, i => completo.claves[i]), posiciones, padres, grupos, profundidades,
        profundidadMaxima: profundidades.reduce((maximo, valor) => Math.max(maximo, valor), 0),
        visibles, origenes, rango: rangoId, cantidadVisible: cantidad};
}
