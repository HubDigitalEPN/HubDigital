/** Secuencia real desde Clase (si existe), sin completar rangos ausentes ni atravesar ciclos. */
export function linajeTaxonomico(indice, clave) {
    const ruta = [], vistos = new Set(); let nodo = indice?.porClave.get(clave);
    while (nodo && !vistos.has(nodo.clave)) {
        vistos.add(nodo.clave); ruta.push(nodo); nodo = indice.porClave.get(nodo.padre);
    }
    ruta.reverse();
    const clase = ruta.findIndex(n => ['clase', 'class'].includes(String(n.etiqueta || n.rango).toLowerCase()));
    return clase >= 0 ? ruta.slice(clase) : ruta;
}

export function textoLinajeTaxonomico(indice, clave) {
    return linajeTaxonomico(indice, clave).map(nodo => `${nodo.etiqueta || nodo.rango || 'Taxón'}: ${nodo.nombre}`).join(' → ');
}
