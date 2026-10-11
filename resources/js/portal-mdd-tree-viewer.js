/* Adaptación de TreeTips/RecursiveTreeTips de ASM Mammal Diversity Database.
 * Copyright (c) 2024 ASM Mammal Diversity Database. MIT.
 * Fuente y licencia: resources/vendor/mdd-tree/LICENSE.md.
 * Se conserva su árbol de listas anidadas, tarjetas, conectores y plegado.
 */
import {indicePublicoTaxonomico} from './portal-phylotree-data';

let instancia = 0;
const ZOOM_INICIAL = .78125; // Dos pasos de + desde el mínimo de 0,5.

export function crearVisorTaxonomico(elemento, acciones) {
    const escenario = document.createElement('div');
    escenario.className = 'portal-ete-stage portal-mdd-tree-stage'; escenario.setAttribute('x-ignore', '');
    const contenido = document.createElement('div'); contenido.className = 'portal-mdd-content';
    escenario.append(contenido); elemento.replaceChildren(escenario);
    const prefijo = 'portal-mdd-' + ++instancia;
    const entradas = new Map();
    let fuente, indice, datos, firma = '', factor = ZOOM_INICIAL, secuencia = 0, destruido = false;
    const numero = valor => Number(valor).toLocaleString('es-EC');
    const visibles = () => [...contenido.querySelectorAll('.portal-mdd-node')].filter(b => !b.closest('[hidden]')).map(b => entradas.get(b.dataset.taxonClave));

    function encuadrar() {
        contenido.style.transform = `scale(${factor})`;
        escenario.style.width = Math.max(elemento.clientWidth, contenido.offsetWidth * factor) + 'px';
        escenario.style.height = Math.max(elemento.clientHeight, contenido.offsetHeight * factor) + 'px';
        escenario.dataset.zoom = String(factor); acciones.zoom?.(factor); acciones.ajustado?.(factor === ZOOM_INICIAL);
    }
    function informar() {
        const cantidad = visibles().length + (entradas.get(datos.rama)?.boton.hidden ? 1 : 0);
        escenario.dataset.nodos = String(cantidad); escenario.dataset.relaciones = String(Math.max(0, cantidad - 1));
        acciones.dibujado(cantidad, 'jerarquia'); encuadrar();
    }
    function enfocar(clave) {
        const entrada = entradas.get(clave);
        if (entrada?.raiz && entrada.boton.hidden) {document.querySelector('#filo-explorador-taxonomico')?.focus(); return true;}
        if (!entrada || entrada.boton.closest('[hidden]')) return false;
        for (const e of entradas.values()) e.boton.tabIndex = e === entrada ? 0 : -1;
        entrada.boton.focus({preventScroll: true});
        const caja = entrada.boton.getBoundingClientRect(), vista = elemento.getBoundingClientRect();
        if (caja.top < vista.top) elemento.scrollTop -= vista.top - caja.top + 8;
        else if (caja.bottom > vista.bottom) elemento.scrollTop += caja.bottom - vista.bottom + 8;
        if (caja.left < vista.left) elemento.scrollLeft -= vista.left - caja.left + 8;
        else if (caja.right > vista.right) elemento.scrollLeft += caja.right - vista.right + 8;
        return true;
    }
    function rellenar(entrada) {
        if (entrada.creados) return;
        const fragmento = document.createDocumentFragment();
        for (const hijo of entrada.hijos) fragmento.append(crearEntrada(hijo, entrada));
        entrada.grupo.append(fragmento); entrada.creados = true;
    }
    function alternar(entrada, abrir = entrada.grupo.hidden) {
        if (!entrada.hijos.length || entrada.raiz) return;
        if (abrir) rellenar(entrada);
        entrada.grupo.hidden = !abrir;
        entrada.boton.setAttribute('aria-expanded', String(abrir)); entrada.signo.textContent = abrir ? '−' : '+';
        informar();
    }
    function teclado(evento, entrada) {
        const {key} = evento;
        if (key === 'ContextMenu' || key === 'F10' && evento.shiftKey) {
            evento.preventDefault(); acciones.menu(entrada.nodo, evento);
        } else if (['Enter', ' '].includes(key)) {
            evento.preventDefault(); entrada.boton.click();
        } else if (key === 'ArrowRight') {
            evento.preventDefault();
            if (acciones.seleccionarRama) {acciones.seleccionarRama(entrada.nodo); return;}
            if (!entrada.hijos.length) return;
            if (entrada.grupo.hidden) alternar(entrada, true);
            else enfocar(entrada.hijos[0].clave);
        } else if (key === 'ArrowLeft') {
            evento.preventDefault();
            if (!entrada.raiz && entrada.hijos.length && !entrada.grupo.hidden) alternar(entrada, false);
            else if (entrada.padre) enfocar(entrada.padre.nodo.clave);
        } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(key)) {
            evento.preventDefault(); const lista = visibles(), i = lista.indexOf(entrada);
            const destino = key === 'Home' ? 0 : key === 'End' ? lista.length - 1 : Math.max(0, Math.min(lista.length - 1, i + (key === 'ArrowDown' ? 1 : -1)));
            enfocar(lista[destino].nodo.clave);
        }
    }
    function crearEntrada(nodo, padre = null) {
        const raiz = !padre, ancestros = new Set(padre?.ancestros || []); ancestros.add(nodo.clave);
        const hijos = (indice.porPadre.get(nodo.clave) || []).filter(n => !ancestros.has(n.clave));
        const li = document.createElement('li'); li.className = 'portal-mdd-entry'; li.setAttribute('role', 'none');
        if (!raiz) {
            for (const nombre of ['vertical', 'horizontal']) {
                const linea = document.createElement('span'); linea.className = 'portal-mdd-' + nombre; linea.setAttribute('aria-hidden', 'true');
                linea.dataset.padre = padre.nodo.clave; linea.dataset.hijo = nodo.clave; li.append(linea);
            }
        }
        const boton = document.createElement('button'); boton.type = 'button'; boton.className = 'portal-mdd-node portal-ete-node' + (raiz ? ' portal-mdd-root is-root' : '');
        boton.setAttribute('role', 'treeitem'); boton.tabIndex = raiz ? 0 : -1;
        boton.dataset.taxonClave = nodo.clave; if (padre) boton.dataset.taxonPadre = padre.nodo.clave;
        boton.setAttribute('aria-level', String(raiz ? 1 : ancestros.size - 1));
        boton.setAttribute('aria-label', `${nodo.nombre}, ${nodo.etiqueta}, ${numero(nodo.total)} registros, ${hijos.length} hijos, ${raiz ? 'raíz de la vista' : 'hijo de ' + padre.nodo.nombre}`);
        const nombre = document.createElement('strong'); nombre.className = 'portal-mdd-name'; nombre.textContent = nodo.nombre;
        let signo;
        if (raiz && hijos.length) {boton.append(nombre); boton.hidden = true; boton.tabIndex = -1;}
        else {
            if (raiz) {boton.classList.remove('portal-mdd-root'); boton.classList.add('portal-mdd-terminal');}
            signo = document.createElement('span'); signo.className = 'portal-mdd-toggle'; signo.textContent = hijos.length ? '+' : '·'; signo.setAttribute('aria-hidden', 'true');
            const etiqueta = document.createElement('span'); etiqueta.className = 'portal-mdd-label';
            const rango = document.createElement('span'); rango.className = 'portal-mdd-rank'; rango.textContent = nodo.etiqueta;
            etiqueta.append(rango, nombre); boton.append(signo, etiqueta);
            const cuenta = document.createElement('span'); cuenta.className = 'portal-mdd-count'; cuenta.textContent = '· ' + numero(nodo.total); cuenta.setAttribute('aria-hidden', 'true'); boton.append(cuenta);
        }
        const grupo = document.createElement('ul'); grupo.className = 'portal-mdd-children'; grupo.setAttribute('role', 'group'); grupo.hidden = !raiz || !hijos.length;
        grupo.id = `${prefijo}-grupo-${++secuencia}`;
        if (hijos.length && !acciones.seleccionarRama) {
            boton.setAttribute('aria-expanded', String(raiz)); boton.setAttribute('aria-controls', grupo.id); boton.setAttribute('aria-owns', grupo.id);
        }
        const entrada = {nodo, padre, raiz, hijos, ancestros, li, boton, grupo, signo, creados: false}; entradas.set(nodo.clave, entrada);
        boton.addEventListener('click', evento => {
            enfocar(nodo.clave);
            if (acciones.seleccionarRama) acciones.seleccionarRama(nodo);
            else if (!raiz && hijos.length) alternar(entrada);
            else acciones.menu(nodo, evento);
        });
        boton.addEventListener('contextmenu', evento => acciones.menu(nodo, evento));
        boton.addEventListener('keydown', evento => teclado(evento, entrada));
        boton.addEventListener('focus', () => {for (const e of entradas.values()) e.boton.tabIndex = e === entrada ? 0 : -1;});
        if (!boton.hidden) li.append(boton);
        li.append(grupo);
        if (raiz) rellenar(entrada);
        return li;
    }
    return {
        async actualizar(nuevos) {
            if (destruido) return;
            if (fuente !== nuevos.nodos) {fuente = nuevos.nodos; indice = indicePublicoTaxonomico(fuente); firma = '';}
            const siguiente = JSON.stringify([nuevos.revision, nuevos.rama]);
            datos = nuevos;
            if (firma === siguiente) return encuadrar();
            const raiz = indice.porClave.get(datos.rama); if (!raiz) return;
            const inicio = performance.now(); factor = ZOOM_INICIAL; entradas.clear();
            const arbol = document.createElement('ul'); arbol.className = 'portal-mdd-tree'; arbol.setAttribute('role', 'tree'); arbol.setAttribute('aria-label', 'Árbol de ' + raiz.nombre);
            arbol.dataset.taxonClave = raiz.clave; arbol.dataset.taxonNombre = raiz.nombre;
            arbol.append(crearEntrada(raiz)); contenido.replaceChildren(arbol);
            const primero = visibles()[0]; if (primero) primero.boton.tabIndex = 0;
            escenario.dataset.motor = 'MDD TreeTips (MIT)'; escenario.dataset.eteVista = 'jerarquia';
            firma = siguiente; informar(); elemento.scrollLeft = elemento.scrollTop = 0;
            escenario.dataset.renderMs = String(Math.round((performance.now() - inicio) * 100) / 100);
            acciones.completo?.(false, true);
        },
        ajustar() {factor = ZOOM_INICIAL; encuadrar(); elemento.scrollLeft = elemento.scrollTop = 0;},
        zoom(paso) {
            if (!Number.isFinite(paso) || paso <= 0) return;
            const anterior = factor, x = elemento.scrollLeft, y = elemento.scrollTop;
            factor = Math.max(.5, Math.min(3, factor * paso)); encuadrar();
            elemento.scrollLeft = x * factor / anterior; elemento.scrollTop = y * factor / anterior;
        },
        enfocar,
        marcarSeleccion(clave) {
            for (const entrada of entradas.values()) {
                if (entrada.nodo.clave === clave) entrada.boton.setAttribute('aria-current', 'location');
                else entrada.boton.removeAttribute('aria-current');
            }
        },
        destruir() {destruido = true; entradas.clear(); elemento.replaceChildren();},
    };
}
