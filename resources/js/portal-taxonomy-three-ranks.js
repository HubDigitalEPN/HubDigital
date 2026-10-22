const RANGOS = [
    ['Filo', '#476c9b'], ['Subfilo', '#7899be'], ['Superclase', '#448d9d'], ['Clase', '#2aa6b8'],
    ['Subclase', '#64b6c0'], ['Infraclase', '#367b91'], ['Superorden', '#c79842'], ['Orden', '#e3b448'],
    ['Suborden', '#e7814f'], ['Infraorden', '#bd6845'], ['Superfamilia', '#a391cb'], ['Familia', '#8a6bc2'],
    ['Subfamilia', '#cb79ad'], ['Tribu', '#a1b55b'], ['Subtribu', '#7e9448'],
    ['Género', '#d65d77'], ['Subgénero', '#b54d6a'], ['Especie', '#41a477'], ['Subespecie', '#32835e'],
];
const normalizar = nombre => String(nombre || 'Taxón').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
const conocidos = new Map(RANGOS.map(([nombre, color], orden) => [normalizar(nombre), {id: normalizar(nombre), nombre, color, orden}]));
export function rangoVisual3D(nodo) {
    const nombre = nodo.etiqueta || nodo.rango || 'Taxón', id = normalizar(nombre);
    if (conocidos.has(id)) return conocidos.get(id);
    let hash = 0; for (const letra of id) hash = (hash * 31 + letra.charCodeAt(0)) >>> 0;
    return {id, nombre, color: `hsl(${hash % 360}, 34%, 70%)`, orden: RANGOS.length};
}

/** Conteo breve compartido por raíces, yemas y leyendas de ambas vistas. */
export function resumenTaxon3D(nodo) {
    return `${nodo.etiqueta || nodo.rango || 'Taxón'} (${Number(nodo.total || 0).toLocaleString('es-EC')})`;
}
