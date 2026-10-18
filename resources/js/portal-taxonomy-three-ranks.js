const RANGOS = [
    ['Filo', '#a9bfd2'], ['Subfilo', '#8ba8c2'], ['Superclase', '#83bac7'], ['Clase', '#65c4cf'],
    ['Subclase', '#79adc4'], ['Infraclase', '#6594ad'], ['Superorden', '#d8c595'], ['Orden', '#dfb678'],
    ['Suborden', '#c99b6d'], ['Infraorden', '#b58d65'], ['Superfamilia', '#99a8d0'], ['Familia', '#ada0d5'],
    ['Subfamilia', '#9586bf'], ['Tribu', '#a18bb8'], ['Subtribu', '#8d79a5'],
    ['Género', '#cd99b8'], ['Subgénero', '#b889a3'], ['Especie', '#80bfa9'], ['Subespecie', '#6fa78f'],
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
