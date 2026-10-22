/** Secuencia completa desde la raíz publicada, sin inventar rangos ni atravesar ciclos. */
export function linajeTaxonomico(indice, clave) {
    const ruta = [], vistos = new Set(); let nodo = indice?.porClave.get(clave);
    while (nodo && !vistos.has(nodo.clave)) {
        vistos.add(nodo.clave); ruta.push(nodo); nodo = indice.porClave.get(nodo.padre);
    }
    ruta.reverse();
    return ruta;
}

export function textoLinajeTaxonomico(indice, clave) {
    return linajeTaxonomico(indice, clave).map(nodo => `${nodo.etiqueta || nodo.rango || 'Taxón'}: ${nodo.nombre}`).join(' → ');
}

/** Ancestros navegables; el nombre del taxón actual permanece en el título vecino. */
export function actualizarBreadcrumbTaxonomico(elemento, indice, clave, navegar) {
    const padres = linajeTaxonomico(indice, clave).slice(0, -1);
    elemento.replaceChildren(); elemento.hidden = padres.length === 0;
    elemento.setAttribute('aria-label', 'Linaje taxonómico');
    const lista = document.createElement('ol');
    for (const nodo of padres) {
        const item = document.createElement('li'), enlace = document.createElement('button');
        enlace.type = 'button'; enlace.textContent = nodo.nombre;
        enlace.title = `${nodo.etiqueta || nodo.rango || 'Taxón'}: ${nodo.nombre}`;
        enlace.addEventListener('click', e => {if (!e.ctrlKey && !e.metaKey) navegar(nodo.clave);});
        item.append(enlace); lista.append(item);
    }
    elemento.append(lista);
}
