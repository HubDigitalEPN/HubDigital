/** Presupuesto conservador de la geometría retenida, sin contar la escena activa ni el octree. */
export function bytesDibujo3D(dibujo) {
    let bytes = 256;
    const buffers = new Set();
    for (const valor of Object.values(dibujo)) {
        if (ArrayBuffer.isView(valor) && !buffers.has(valor.buffer)) {buffers.add(valor.buffer); bytes += valor.buffer.byteLength;}
    }
    for (const clave of dibujo.claves || []) bytes += 72 + String(clave).length * 2;
    return bytes;
}

/** LRU en memoria: servir la geometría vigente primero y renovar a demanda, una vez por clave. */
export function crearCacheRamas3D({maxBytes = 12 * 1024 * 1024, maxEntradas = 6, frescaMs = 30000,
    reloj = () => performance.now()} = {}) {
    const entradas = new Map(), pendientes = new Map(), reintentos = new Map();
    let bytes = 0, generacion = 0;
    const metricas = {aciertos: 0, stale: 0, fallos: 0, renovaciones: 0};
    function guardar(clave, valor) {
        const peso = bytesDibujo3D(valor), anterior = entradas.get(clave);
        if (anterior) {bytes -= anterior.bytes; entradas.delete(clave);}
        if (peso > maxBytes) return;
        entradas.set(clave, {valor, bytes: peso, actualizado: reloj()}); bytes += peso;
        while (bytes > maxBytes || entradas.size > maxEntradas) {
            const primera = entradas.keys().next().value; bytes -= entradas.get(primera).bytes; entradas.delete(primera); reintentos.delete(primera);
        }
    }
    function renovar(clave, cargar) {
        if (pendientes.has(clave)) return pendientes.get(clave);
        const version = generacion;
        const promesa = Promise.resolve().then(cargar).then(valor => {
            if (version === generacion) {guardar(clave, valor); reintentos.delete(clave);}
            return valor;
        }, error => {
            if (version === generacion && entradas.has(clave)) reintentos.set(clave, reloj() + 5000);
            throw error;
        }).finally(() => {if (pendientes.get(clave) === promesa) pendientes.delete(clave);});
        pendientes.set(clave, promesa); return promesa;
    }
    return {
        obtener(clave, cargar) {
            const entrada = entradas.get(clave);
            if (!entrada) {metricas.fallos++; return {estado: 'miss', promesa: renovar(clave, cargar)};}
            entradas.delete(clave); entradas.set(clave, entrada); metricas.aciertos++;
            if (reloj() - entrada.actualizado < frescaMs) return {estado: 'fresh', valor: entrada.valor};
            metricas.stale++;
            const renovando = pendientes.has(clave), esperar = reloj() < (reintentos.get(clave) || 0);
            if (!renovando && !esperar) metricas.renovaciones++;
            return {estado: 'stale', valor: entrada.valor, promesa: esperar ? undefined : renovar(clave, cargar)};
        },
        contiene: clave => entradas.has(clave),
        limpiar() {generacion++; entradas.clear(); pendientes.clear(); reintentos.clear(); bytes = 0;},
        estado: () => ({...metricas, bytes, entradas: entradas.size, pendientes: pendientes.size, maxBytes}),
    };
}

/** Una respuesta nueva con idénticos datos publicados no invalida geometrías ni índices. */
export function mismaFuenteTaxonomica(anterior, nueva) {
    if (anterior === nueva) return true;
    if (!anterior || !nueva || anterior.length !== nueva.length) return false;
    for (let i = 0; i < anterior.length; i++) {
        const a = anterior[i], b = nueva[i];
        if (!a || !b || Object.keys(a).length !== Object.keys(b).length) return false;
        for (const campo of Object.keys(a)) {
            if (!Object.hasOwn(b, campo)) return false;
            const x = a[campo], y = b[campo];
            if (x === y) continue;
            if (!Array.isArray(x) || !Array.isArray(y) || x.length !== y.length || x.some((valor, j) => valor !== y[j])) return false;
        }
    }
    return true;
}
