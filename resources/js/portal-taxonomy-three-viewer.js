import {indicePublicoTaxonomico} from './portal-phylotree-data';
import {crearExploradorRama} from './portal-taxonomy-branch-viewer';
import {mismaFuenteTaxonomica} from './portal-taxonomy-three-cache';

/** Una sola jerarquía a la izquierda; el panel derecho presenta la ficha de la raíz. */
export function crearVisorTaxonomico(elemento, acciones) {
    elemento.classList.add('portal-taxonomy-split-host');
    const marco = document.createElement('div'); marco.className = 'portal-taxonomy-split'; marco.setAttribute('x-ignore', '');
    const izquierda = document.createElement('div'); izquierda.className = 'portal-taxonomy-tree-pane';
    izquierda.setAttribute('aria-label', 'Explorador de la raíz seleccionada y todos sus descendientes');
    const derecha = document.createElement('section'); derecha.className = 'portal-taxonomy-three-pane';
    derecha.setAttribute('aria-label', 'Árbol tridimensional de la rama seleccionada');
    const explorador = document.createElement('section'); explorador.className = 'portal-taxonomy-branch-pane';
    explorador.setAttribute('aria-label', 'Fotografía y descripción del taxón seleccionado');
    marco.append(izquierda, derecha, explorador); elemento.replaceChildren(marco);
    let indice, fuente, datos, raiz, seleccionado, escena, cargando, origenMenu = '', destruido = false, arbolAmpliado = false, revision = 0, revisionBusqueda = 0;
    const rama = crearExploradorRama(izquierda, {
        elegir: clave => inspeccionar(clave),
        menu: (nodo, evento) => {origenMenu = 'arbol'; acciones.menu(nodo, evento);},
        dibujado: (total, vista) => acciones.dibujado(total, vista),
        ampliar: () => {
            arbolAmpliado = !arbolAmpliado;
            marco.classList.toggle('has-expanded-tree', arbolAmpliado);
            elemento.closest('.taxonomy-navigator')?.classList.toggle('has-expanded-tree', arbolAmpliado);
            rama.ampliar(arbolAmpliado); escena?.mostrar(!arbolAmpliado);
            if (!arbolAmpliado) abrirRama(indice?.porClave.get(seleccionado), false, Boolean(explorador.dataset.ficha));
        },
    }, explorador);

    async function inspeccionar(clave) {
        if (!indice?.porClave.has(clave) || destruido) return;
        await abrirRama(indice.porClave.get(clave), false, true);
        if (destruido || seleccionado !== clave) return;
        if (!arbolAmpliado) escena?.seleccionar(clave);
    }

    function origenDelFilo(clave) {
        const vistos = new Set(); let nodo = indice?.porClave.get(clave);
        while (nodo?.padre && indice.porClave.has(nodo.padre) && !vistos.has(nodo.clave)) {
            vistos.add(nodo.clave); nodo = indice.porClave.get(nodo.padre);
        }
        return nodo?.clave;
    }


    async function abrirRama(nodo, desdeBusqueda = false, mostrarFicha = false) {
        if (!nodo || destruido) return;
        if (!desdeBusqueda) revisionBusqueda++;
        seleccionado = nodo.clave; marco.classList.add('has-selection');
        rama.actualizar(indice, nodo.clave, mostrarFicha);
        const solicitud = ++revision;
        if (arbolAmpliado) {escena?.mostrar(false); derecha.setAttribute('aria-busy', 'false'); return;}
        derecha.setAttribute('aria-busy', 'true');
        if (!escena) {
            if (!cargando) {
                const estado = document.createElement('p'); estado.className = 'portal-three-initial';
                estado.setAttribute('role', 'status'); estado.textContent = 'Preparando la vista 3D…'; derecha.replaceChildren(estado);
                cargando = import('./portal-taxonomy-three-scene').then(modulo => destruido ? null : modulo.crearEscenaTaxonomica(derecha, {
                    seleccionar: inspeccionar,
                    navegar: clave => abrirRama(indice?.porClave.get(clave), false, true),
                    menu: (clave, evento, ancla) => {origenMenu = 'escena'; acciones.menu(indice?.porClave.get(clave), evento, ancla);},
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
        if (arbolAmpliado) {escena.mostrar(false); return;}
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
            // Una navegación explícita puede volver al mismo padre tras elegir
            // una esfera. Una revalidación de datos conserva la raíz elegida.
            const cambioRaiz = datos?.rama !== nuevos.rama || datos?.navegacion !== nuevos.navegacion;
            datos = nuevos; raiz = origenDelFilo(nuevos.rama);
            if (cambioRaiz || !indice.porClave.has(seleccionado)) {
                seleccionado = (nuevos.rama !== raiz ? indice.porClave.get(nuevos.rama)
                    : indice.porPadre.get(raiz)?.[0] || indice.porClave.get(raiz))?.clave;
                revision++;
            }
            if (seleccionado && (cambioRaiz || cambioFuente || !escena))
                await abrirRama(indice.porClave.get(seleccionado), false, cambioFuente && !cambioRaiz && Boolean(explorador.dataset.ficha));
        },
        async localizar(clave) {
            if (!indice?.porClave.has(clave) || destruido) return false;
            const solicitud = ++revisionBusqueda;
            await abrirRama(indice.porClave.get(clave), true, true);
            if (arbolAmpliado) return !destruido && solicitud === revisionBusqueda;
            if (destruido || solicitud !== revisionBusqueda || !escena?.contiene(clave)) return false;
            const ubicado = await escena.localizar(clave);
            if (!ubicado || destruido || solicitud !== revisionBusqueda) return false;
            return true;
        },
        cancelarLocalizacion() {revisionBusqueda++;},
        limpiarBusqueda() {revisionBusqueda++; escena?.limpiarSeleccion();},
        ajustar() {escena?.ajustar();},
        pantallaCompleta() {return escena?.pantallaCompleta();},
        zoom(paso) {escena?.zoom(paso);},
        rangoActivo() {return escena?.rangoActivo() || '';},
        enfocar(clave) {
            const desdeEscena = origenMenu === 'escena' && !arbolAmpliado;
            origenMenu = '';
            if (desdeEscena && escena?.enfocar(clave)) return true;
            return rama.enfocar(clave) || (!arbolAmpliado && escena?.enfocar(clave));
        },
        destruir() {destruido = true; revision++; revisionBusqueda++; rama.destruir(); escena?.destruir(); elemento.closest('.taxonomy-navigator')?.classList.remove('has-expanded-tree'); elemento.classList.remove('portal-taxonomy-split-host'); elemento.replaceChildren();},
    };
}
