const RANGOS = [
    ['Filo', '#397bb5'], ['Subfilo', '#6c9ac4'], ['Superclase', '#2097a7'], ['Clase', '#00a7a0'],
    ['Subclase', '#459fbc'], ['Infraclase', '#28768b'], ['Superorden', '#cf9c30'], ['Orden', '#efb323'],
    ['Suborden', '#e56b24'], ['Infraorden', '#ad4e1b'], ['Superfamilia', '#7076c9'], ['Familia', '#8e58ce'],
    ['Subfamilia', '#cc4ba3'], ['Tribu', '#b3a125'], ['Subtribu', '#79751d'],
    ['Género', '#d44459'], ['Subgénero', '#a62c4b'], ['Especie', '#39a34b'], ['Subespecie', '#217138'],
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
