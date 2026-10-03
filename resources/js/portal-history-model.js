// Historial de una selección completa: ninguna propiedad puede generar una entrada aislada.
export function normalizarEstadoCatalogo(entrada, defectos) {
    const estado = {};
    for (const [clave, defecto] of Object.entries(defectos)) {
        const valor = entrada?.[clave] ?? defecto;
        if (Array.isArray(defecto)) {
            estado[clave] = Array.isArray(valor)
                ? valor.filter(item => ['string', 'number', 'boolean'].includes(typeof item)).slice(0, 100).map(String).filter(Boolean)
                : [];
        } else if (typeof defecto === 'number') {
            const numero = typeof valor === 'string' && !/^\d+$/.test(valor) ? NaN : Number(valor);
            estado[clave] = Number.isSafeInteger(numero) && numero > 0 ? numero : defecto;
        } else {
            estado[clave] = ['string', 'number', 'boolean'].includes(typeof valor) ? String(valor) : defecto;
        }
    }
    return estado;
}

export function leerSeleccionCatalogo(href, defectos) {
    const parametros = new URL(href).searchParams;
    const entrada = {};
    for (const [clave, defecto] of Object.entries(defectos)) {
        if (Array.isArray(defecto)) {
            const posiciones = new Map();
            let siguiente = 0;
            for (const [nombre, valor] of parametros) {
                if (!nombre.startsWith(`${clave}[`) || !nombre.endsWith(']')) continue;
                const indice = nombre.slice(clave.length + 1, -1);
                if (indice !== '' && !/^\d+$/.test(indice)) continue;
                const posicion = indice === '' ? siguiente : Number(indice);
                if (!Number.isSafeInteger(posicion) || posicion > 1000) continue;
                posiciones.set(posicion, valor);
                siguiente = Math.max(siguiente, posicion + 1);
            }
            entrada[clave] = [...posiciones].sort(([a], [b]) => a - b).map(([, valor]) => valor);
        } else if (parametros.has(clave)) {
            entrada[clave] = parametros.getAll(clave).at(-1);
        }
    }
    return normalizarEstadoCatalogo(entrada, defectos);
}

export function urlSeleccionCatalogo(href, entrada, defectos) {
    const url = new URL(href);
    const estado = normalizarEstadoCatalogo(entrada, defectos);
    for (const clave of [...url.searchParams.keys()]) {
        if (Object.hasOwn(defectos, clave) || Object.keys(defectos).some(alias => clave.startsWith(`${alias}[`))) url.searchParams.delete(clave);
    }
    for (const [clave, valor] of Object.entries(estado)) {
        if (Array.isArray(valor)) {
            valor.forEach((item, indice) => url.searchParams.set(`${clave}[${indice}]`, item));
        } else if (JSON.stringify(valor) !== JSON.stringify(defectos[clave])) {
            url.searchParams.set(clave, String(valor));
        }
    }
    return url;
}

/** Adaptador sin DOM/red: permite comprobar el historial real con una History API acotada. */
export function crearHistorialCatalogo({history, location, estado, defectos, version = 0, restaurar, onError = () => {}}) {
    const ruta = new URL(location.href).pathname;
    let actual = normalizarEstadoCatalogo(estado, defectos);
    let secuencia = version;
    let pendiente = null;
    let restaurando = false;
    let destruido = false;
    const firma = valor => JSON.stringify(valor);
    const escribir = (metodo, seleccion) => {
        const {alpine, portalCatalogo, ...ajeno} = history.state ?? {};
        // Las entradas propias no llevan snapshot de wire:navigate: su restauración
        // corresponde al request único del catálogo, no a HTML de otra selección.
        const entrada = {...ajeno, portalCatalogo: {ruta, estado: seleccion}};
        history[metodo](entrada, '', urlSeleccionCatalogo(location.href, seleccion, defectos).href);
    };
    escribir('replaceState', actual);

    const recibir = (entrada, restauracion = 0, versionRespuesta = secuencia) => {
        if (destruido) return;
        // Un commit iniciado antes de Atrás no puede deshacer la URL ya restaurada.
        if (restauracion === 0 && versionRespuesta !== secuencia) return;
        const seleccion = normalizarEstadoCatalogo(entrada, defectos);
        if (restauracion > 0) {
            if (restauracion !== secuencia || pendiente) return;
            actual = seleccion;
            escribir('replaceState', seleccion);
        } else if (!restaurando && firma(seleccion) !== firma(actual)) {
            actual = seleccion;
            escribir('pushState', seleccion);
        }
    };

    const volver = async href => {
        if (destruido || new URL(href).pathname !== ruta) return;
        pendiente = {estado: leerSeleccionCatalogo(href, defectos), secuencia: ++secuencia};
        if (restaurando) return;
        restaurando = true;
        try {
            while (pendiente && !destruido) {
                const destino = pendiente;
                pendiente = null;
                const restaurado = await restaurar(destino.estado, destino.secuencia);
                if (restaurado) recibir(restaurado, destino.secuencia);
            }
        } catch (error) {
            onError(error);
        } finally {
            restaurando = false;
        }
    };

    return {recibir, volver, destroy() { destruido = true; pendiente = null; }};
}
