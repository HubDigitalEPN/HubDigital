import {crearVisorTaxonomico as crearArbol} from './portal-mdd-tree-viewer';
import {indicePublicoTaxonomico} from './portal-phylotree-data';
import {crearExploradorRama} from './portal-taxonomy-branch-viewer';
import {mismaFuenteTaxonomica} from './portal-taxonomy-three-cache';

/** Tarjetas, grafo y ficha/explorador; elegir una esfera la convierte en raíz 3D. */
export function crearVisorTaxonomico(elemento, acciones) {
    elemento.classList.add('portal-taxonomy-split-host');
    const marco = document.createElement('div'); marco.className = 'portal-taxonomy-split'; marco.setAttribute('x-ignore', '');
    const izquierda = document.createElement('div'); izquierda.className = 'portal-taxonomy-tree-pane';
    izquierda.setAttribute('aria-label', 'Jerarquía taxonómica: abre una rama para verla en 3D');
    const derecha = document.createElement('section'); derecha.className = 'portal-taxonomy-three-pane';
    derecha.setAttribute('aria-label', 'Árbol tridimensional de la rama seleccionada');
    const explorador = document.createElement('section'); explorador.className = 'portal-taxonomy-branch-pane';
    explorador.setAttribute('aria-label', 'Explorador de descendientes de la esfera seleccionada');
    marco.append(izquierda, derecha, explorador); elemento.replaceChildren(marco);
    let indice, fuente, datos, raiz, seleccionado, escena, cargando, destruido = false, revision = 0, revisionBusqueda = 0;
    const arbol = crearArbol(izquierda, {...acciones,
        zoom: factor => {if (!seleccionado) acciones.zoom?.(factor);},
        ajustado: valor => {if (!seleccionado) acciones.ajustado?.(valor);},
        seleccionarRama: nodo => abrirRama(nodo)});
    const rama = crearExploradorRama(explorador, {
        elegir: clave => escena?.localizar(clave),
        menu: (nodo, evento) => acciones.menu(nodo, evento),
    });

    async function inspeccionar(clave) {
        if (!indice?.porClave.has(clave) || destruido) return;
        await abrirRama(indice.porClave.get(clave));
        if (destruido || seleccionado !== clave) return;
        rama.actualizar(indice, clave); escena?.seleccionar(clave);
    }

    function marcarOrigen(clave) {
        const vistos = new Set(); let nodo = indice?.porClave.get(clave);
        while (nodo && nodo.clave !== raiz && nodo.padre !== raiz && !vistos.has(nodo.clave)) {
            vistos.add(nodo.clave); nodo = indice.porClave.get(nodo.padre);
        }
        arbol.marcarSeleccion(nodo?.clave);
    }

    function origenDelFilo(clave) {
        const vistos = new Set(); let nodo = indice?.porClave.get(clave);
        while (nodo?.padre && indice.porClave.has(nodo.padre) && !vistos.has(nodo.clave)) {
            vistos.add(nodo.clave); nodo = indice.porClave.get(nodo.padre);
        }
        return nodo?.clave;
    }


    async function abrirRama(nodo, desdeBusqueda = false) {
        if (!nodo || destruido) return;
        if (!desdeBusqueda) revisionBusqueda++;
        seleccionado = nodo.clave; marcarOrigen(nodo.clave); marco.classList.add('has-selection');
        rama.limpiar();
        const solicitud = ++revision;
        derecha.setAttribute('aria-busy', 'true');
        if (!escena) {
            if (!cargando) {
                const estado = document.createElement('p'); estado.className = 'portal-three-initial';
                estado.setAttribute('role', 'status'); estado.textContent = 'Preparando la vista 3D…'; derecha.replaceChildren(estado);
                cargando = import('./portal-taxonomy-three-scene').then(modulo => destruido ? null : modulo.crearEscenaTaxonomica(derecha, {
                    seleccionar: inspeccionar,
                    navegar: clave => abrirRama(indice?.porClave.get(clave)),
                    menu: (clave, evento, ancla) => acciones.menu(indice?.porClave.get(clave), evento, ancla),
                    zoom: factor => acciones.zoom?.(factor), ajustado: valor => acciones.ajustado?.(valor),
                }));
            }
            try {escena = await cargando;}
            catch {
                cargando = null;
                const mensaje = document.createElement('p'); mensaje.className = 'portal-three-initial'; mensaje.setAttribute('role', 'alert');
                mensaje.textContent = 'No se pudo activar la vista 3D. Puedes seguir explorando la jerarquía de la izquierda.';
                derecha.replaceChildren(mensaje); derecha.setAttribute('aria-busy', 'false'); return;
            }
        }
        if (!escena || destruido || solicitud !== revision) return;
        escena.mostrar(true);
        try {await escena.actualizar(fuente, nodo.clave, indice);}
        catch (error) {escena.error('No se pudo dibujar esta rama. Selecciónala de nuevo para reintentar.'); console.error('Árbol 3D:', error);}
        finally {if (solicitud === revision) derecha.setAttribute('aria-busy', 'false');}
    }
    return {
        async actualizar(nuevos) {
            if (destruido) return;
            const cambioFuente = datos?.nodos !== nuevos.nodos && !mismaFuenteTaxonomica(fuente, nuevos.nodos);
            if (cambioFuente) {indice = indicePublicoTaxonomico(nuevos.nodos); fuente = [...indice.porClave.values()];}
            const cambioRaiz = datos?.rama !== nuevos.rama;
            datos = nuevos; raiz = origenDelFilo(nuevos.rama);
            // Las tarjetas pertenecen siempre al filo, independientemente de la raíz 3D.
            await arbol.actualizar({...nuevos, nodos: fuente, rama: raiz});
            if (cambioRaiz || !indice.porClave.has(seleccionado)) {
                seleccionado = (nuevos.rama !== raiz ? indice.porClave.get(nuevos.rama)
                    : indice.porPadre.get(raiz)?.[0] || indice.porClave.get(raiz))?.clave;
                revision++; arbol.ajustar();
            }
            marcarOrigen(seleccionado);
            if (seleccionado && (cambioRaiz || cambioFuente || !escena)) await abrirRama(indice.porClave.get(seleccionado));
        },
        async localizar(clave) {
            if (!indice?.porClave.has(clave) || destruido) return false;
            const solicitud = ++revisionBusqueda;
            await abrirRama(indice.porClave.get(clave), true);
            if (destruido || solicitud !== revisionBusqueda || !escena?.contiene(clave)) return false;
            const ubicado = await escena.localizar(clave);
            if (!ubicado || destruido || solicitud !== revisionBusqueda) return false;
            marcarOrigen(clave); rama.actualizar(indice, clave);
            return true;
        },
        cancelarLocalizacion() {revisionBusqueda++;},
        limpiarBusqueda() {revisionBusqueda++; rama.limpiar(); escena?.limpiarSeleccion();},
        ajustar() {arbol.ajustar(); escena?.ajustar();},
        pantallaCompleta() {return escena?.pantallaCompleta();},
        zoom(paso) {if (seleccionado && escena) escena.zoom(paso); else arbol.zoom(paso);},
        rangoActivo() {return escena?.rangoActivo() || '';},
        enfocar(clave) {if (rama.enfocar(clave) || escena?.enfocar(clave)) return true; return arbol.enfocar(clave);},
        destruir() {destruido = true; revision++; revisionBusqueda++; arbol.destruir(); rama.destruir(); escena?.destruir(); elemento.classList.remove('portal-taxonomy-split-host'); elemento.replaceChildren();},
    };
}
