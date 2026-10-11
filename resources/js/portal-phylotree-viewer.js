import {phylotree} from 'phylotree';
import {indicePublicoTaxonomico, ramaPublicaPhylotree} from './portal-phylotree-data';

/** El trazado, las bifurcaciones y las posiciones son los de phylotree.js. */
export function crearVisorTaxonomico(elemento, acciones) {
    const escenario = document.createElement('div'); escenario.className = 'portal-phylotree-stage portal-ete-stage';
    escenario.setAttribute('x-ignore', '');
    elemento.replaceChildren(escenario);
    let datos, fuente, indice, display, firma = '', factor = 1, destruido = false;
    let masivo, preparandoCanvas, usaCanvas = false, solicitud = 0;
    const cache = new Map(), dibujos = new Map();
    const nombre = nodo => `${nodo.nombre} · ${nodo.etiqueta} · ${Number(nodo.total).toLocaleString('es-EC')}`;
    const colores = ['#17699b', '#7d5b99', '#258177', '#a46a22', '#b14e71', '#527e45'];
    const medidas = document.createElement('canvas').getContext('2d');
    function anchoEtiqueta(taxon, raiz) {
        const letra = (raiz ? 21 : 18) * factor;
        medidas.font = `bold italic ${letra}px Georgia`;
        let ancho = medidas.measureText(taxon.nombre).width;
        medidas.font = `${letra * .78}px system-ui`;
        ancho += medidas.measureText(`${taxon.etiqueta} · ${Number(taxon.total).toLocaleString('es-EC')}`).width + 10 * factor;
        if (raiz) ancho += medidas.measureText('Raíz · ').width;
        return ancho + 22;
    }
    function color(nodo) {
        let rama = nodo;
        while (rama.parent?.parent) rama = rama.parent;
        return colores[(rama.parent?.children.indexOf(rama) || 0) % colores.length];
    }
    function accesible(grupo, nodo, raiz) {
        const taxon = nodo.data.portal;
        grupo.classed('portal-phylotree-node', true).classed('portal-ete-node', true).classed('is-root', raiz)
            .attr('data-taxon-clave', taxon.clave).attr('role', 'button').attr('tabindex', 0)
            .attr('aria-label', `${taxon.nombre}, ${taxon.etiqueta}, ${Number(taxon.total).toLocaleString('es-EC')} registros${raiz ? ', raíz de la vista' : ', hijo de ' + nodo.parent.data.portal.nombre}`)
            .attr('aria-expanded', nodo.children?.length ? 'true' : null)
            .attr('data-taxon-padre', raiz ? null : nodo.parent.data.portal.clave)
            .on('click.portal', evento => {evento.stopPropagation(); if (!raiz) acciones.seleccionar(taxon);})
            .on('contextmenu.portal', evento => acciones.menu(taxon, evento))
            .on('keydown.portal', evento => {
                if (evento.key === 'ContextMenu' || evento.key === 'F10' && evento.shiftKey) {evento.preventDefault(); acciones.menu(taxon, evento);}
                else if (['Enter', ' ', 'ArrowRight'].includes(evento.key)) {evento.preventDefault(); if (!raiz) acciones.seleccionar(taxon);}
                else if (evento.key === 'ArrowLeft') {evento.preventDefault(); acciones.volver?.();}
                else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(evento.key)) {
                    evento.preventDefault(); const nodos = [...escenario.querySelectorAll('.portal-phylotree-node')], i = nodos.indexOf(grupo.node());
                    nodos[evento.key === 'Home' ? 0 : evento.key === 'End' ? nodos.length - 1 : Math.max(0, Math.min(nodos.length - 1, i + (evento.key === 'ArrowDown' ? 1 : -1)))]?.focus();
                }
            });
        grupo.style('--rama-color', color(nodo));
        const texto = grupo.select('text').style('font-size', (raiz ? 21 : 18) * factor + 'px');
        texto.text('');
        if (raiz) texto.append('tspan').attr('class', 'portal-phylotree-root-tag').text('Raíz · ');
        texto.append('tspan').attr('class', 'portal-phylotree-scientific').text(taxon.nombre);
        texto.append('tspan').attr('class', 'portal-phylotree-meta').attr('dx', 10 * factor).text(`${taxon.etiqueta} · ${Number(taxon.total).toLocaleString('es-EC')}`);
        grupo.selectAll('title').remove();
    }
    function dibujar(restaurar = false) {
        if (destruido || !datos || !elemento.clientWidth || !elemento.clientHeight) return;
        const siguiente = JSON.stringify([datos.revision, datos.rama]);
        if (siguiente === firma && !restaurar) { encuadrar(false); return; }
        const inicio = performance.now(), foco = escenario.contains(document.activeElement) ? document.activeElement.dataset.taxonClave : null;
        const rama = cache.get(datos.rama) || ramaPublicaPhylotree(indice, datos.rama, 1);
        if (!rama) return;
        cache.set(datos.rama, rama); while (cache.size > 24) cache.delete(cache.keys().next().value);
        datos.raiz = rama.raiz;
        escenario.replaceChildren(); display = null;
        const guardado = dibujos.get(siguiente);
        if (guardado) {
            escenario.append(guardado.svg); display = guardado.display;
        } else if (rama.hijos.length) {
            const tree = new phylotree({json: rama.json});
            display = tree.render({container: escenario, width: elemento.clientWidth, height: elemento.clientHeight,
                'font-size': 18 * factor, 'internal-names': true, 'show-scale': false,
                'is-radial': false, 'align-tips': false, 'show-menu': false,
                'top-bottom-spacing': 'fixed-step', 'left-right-spacing': 'fixed-step',
                'minimum-per-node-spacing': 30, 'maximum-per-node-spacing': 100,
                'minimum-per-level-spacing': 200, 'maximum-per-level-spacing': 4000,
                transitions: false, selectable: false, collapsible: false, brush: false, reroot: false, hide: false, zoom: false,
            });
            display.node_styler = (grupo, nodo) => accesible(grupo, nodo, !nodo.parent);
            display.edge_styler = (grupo, edge) => {
                grupo.attr('data-padre', edge.source.data.portal.clave).attr('data-hijo', edge.target.data.portal.clave)
                    .style('stroke', color(edge.target)).on('click', evento => {evento.stopPropagation(); acciones.seleccionar(edge.target.data.portal);});
                grupo.selectAll('title').remove();
            };
            display.nodeLabel(nodo => (!nodo.parent ? 'Raíz · ' : '') + nombre(nodo.data.portal));
            const espacioNombre = Math.max(...rama.nodos.map(n => anchoEtiqueta(n, n.clave === rama.raiz.clave))) + 30;
            display.spacing_x(Math.max(32, 38 * factor), true);
            display.spacing_y(Math.max(360, espacioNombre), true);
            display.update(false);
            const svg = display.svg.node();
            escenario.append(svg);
            svg.classList.add('portal-phylotree-svg'); svg.setAttribute('role', 'group');
            svg.setAttribute('aria-label', 'Raíz e hijos de ' + rama.raiz.nombre);
            // Decora las etiquetas en sus posiciones nativas; no modifica el trazado.
            for (const grupo of svg.querySelectorAll('.portal-phylotree-node')) {
                const taxon = indice.porClave.get(grupo.dataset.taxonClave), raiz = grupo.classList.contains('is-root');
                const letra = (raiz ? 21 : 18) * factor;
                const fondo = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                fondo.classList.add('portal-phylotree-label-bg'); fondo.setAttribute('x', 3 * factor); fondo.setAttribute('y', -letra * .75 - 5);
                fondo.setAttribute('width', anchoEtiqueta(taxon, raiz)); fondo.setAttribute('height', letra + 10); fondo.setAttribute('rx', 7);
                grupo.insertBefore(fondo, grupo.firstChild);
            }
            // Sólo encuadre del SVG nativo, sin cambiar nodos ni conectores.
            const caja = svg.querySelector('.phylotree-container').getBBox();
            const x = caja.x - 24, y = caja.y - 24, w = caja.width + 48, h = caja.height + 48;
            svg.setAttribute('viewBox', [x, y, w, h].join(' '));
            svg.setAttribute('width', w); svg.setAttribute('height', h);
            svg.style.width = w + 'px'; svg.style.height = h + 'px';
            escenario.style.width = Math.max(elemento.clientWidth, w) + 'px';
            escenario.style.height = Math.max(elemento.clientHeight, h) + 'px';
            dibujos.set(siguiente, {svg, display});
        } else {
            // Una raíz terminal no necesita bifurcaciones ni hijos ficticios.
            const boton = document.createElement('button'); boton.type = 'button'; boton.className = 'portal-phylotree-terminal portal-ete-node is-root';
            boton.dataset.taxonClave = rama.raiz.clave; boton.textContent = 'Raíz · ' + nombre(rama.raiz);
            boton.style.fontSize = 21 * factor + 'px'; boton.setAttribute('aria-label', `${rama.raiz.nombre}, ${rama.raiz.etiqueta}, ${rama.raiz.total} registros, raíz sin hijos`);
            boton.addEventListener('contextmenu', e => acciones.menu(rama.raiz, e));
            boton.addEventListener('keydown', e => {if (e.key === 'ContextMenu' || e.key === 'F10' && e.shiftKey) {e.preventDefault(); acciones.menu(rama.raiz, e);}});
            escenario.append(boton); escenario.style.width = '100%'; escenario.style.height = elemento.clientHeight + 'px';
            elemento.scrollLeft = elemento.scrollTop = 0;
        }
        escenario.dataset.motor = 'phylotree.js 2.6.0'; escenario.dataset.eteVista = 'jerarquia'; escenario.dataset.eteDisposicion = 'jerarquia';
        escenario.dataset.nodos = String(rama.nodos.length); escenario.dataset.relaciones = String(rama.relaciones.length);
        escenario.dataset.renderMs = String(Math.round((performance.now() - inicio) * 100) / 100);
        firma = siguiente;
        while (dibujos.size > 6) dibujos.delete(dibujos.keys().next().value);
        encuadrar(restaurar);
        if (foco) [...escenario.querySelectorAll('[data-taxon-clave]')].find(n=>n.dataset.taxonClave===foco)?.focus({preventScroll:true});
        acciones.ajustado?.(factor === 1); acciones.dibujado(rama.nodos.length, 'jerarquia');
    }
    function encuadrar(centrar) {
        const svg = escenario.querySelector('svg');
        if (svg) {
            const caja = svg.viewBox.baseVal;
            svg.style.width = caja.width * factor + 'px'; svg.style.height = caja.height * factor + 'px';
            escenario.style.width = Math.max(elemento.clientWidth, caja.width * factor) + 'px';
            escenario.style.height = Math.max(elemento.clientHeight, caja.height * factor) + 'px';
        } else {
            escenario.style.height = elemento.clientHeight + 'px';
            const terminal = escenario.querySelector('.portal-phylotree-terminal');
            if (terminal) terminal.style.fontSize = 21 * factor + 'px';
        }
        if (centrar) {
            elemento.scrollLeft = 0;
            const raiz = escenario.querySelector('.is-root');
            if (raiz) elemento.scrollTop = Math.max(0, raiz.getBoundingClientRect().top - elemento.getBoundingClientRect().top + elemento.scrollTop - elemento.clientHeight / 2);
        }
        escenario.dataset.zoom = String(factor); acciones.zoom?.(factor); acciones.ajustado?.(factor === 1);
    }
    return {
        async actualizar(nuevos) {
            const turno = ++solicitud;
            if (fuente !== nuevos.nodos) {fuente = nuevos.nodos; indice = indicePublicoTaxonomico(fuente); cache.clear(); dibujos.clear(); firma = '';}
            const rama = cache.get(nuevos.rama) || ramaPublicaPhylotree(indice, nuevos.rama, 1);
            if (rama) {cache.set(nuevos.rama, rama); while (cache.size > 24) cache.delete(cache.keys().next().value);}
            if ((rama?.hijos.length || 0) > 6) {
                preparandoCanvas ??= import('./portal-taxonomy-canvas-viewer').then(m => destruido ? null : m.crearVisorMasivo(elemento, acciones));
                try { masivo = await preparandoCanvas; }
                catch (error) {preparandoCanvas = null; throw error;}
                if (destruido || turno !== solicitud) return;
                usaCanvas = true; datos = nuevos;
                return masivo.actualizar(nuevos);
            }
            const cambiar = datos?.rama !== nuevos.rama || datos?.revision !== nuevos.revision;
            usaCanvas = false; if (!escenario.isConnected) elemento.replaceChildren(escenario);
            acciones.completo?.(false, true);
            datos = nuevos; if (cambiar) factor = 1; dibujar(cambiar);
        },
        ajustar() {if (usaCanvas) return masivo.ajustar(); factor = 1; encuadrar(true);},
        alternarCompleto() {if (usaCanvas) return masivo.alternarCompleto?.();},
        zoom(paso) {
            if (usaCanvas) return masivo.zoom(paso);
            if (!Number.isFinite(paso) || paso <= 0) return;
            const anterior = factor, x = elemento.scrollLeft, y = elemento.scrollTop + elemento.clientHeight / 2;
            factor = Math.max(.5, Math.min(3, factor * paso)); encuadrar(false);
            elemento.scrollLeft = x * factor / anterior;
            elemento.scrollTop = Math.max(0, y * factor / anterior - elemento.clientHeight / 2);
        },
        enfocar(clave) {if (usaCanvas) return masivo.enfocar(clave); const n = [...escenario.querySelectorAll('[data-taxon-clave]')].find(n=>n.dataset.taxonClave===clave); n?.focus({preventScroll:true}); return Boolean(n);},
        destruir() {destruido = true; solicitud++; cache.clear(); dibujos.clear(); masivo?.destruir(); display?.svg.remove(); elemento.replaceChildren();},
    };
}
