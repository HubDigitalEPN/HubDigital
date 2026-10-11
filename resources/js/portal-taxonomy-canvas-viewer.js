import {init, use} from 'echarts/core';
import {TreeChart} from 'echarts/charts';
import {CanvasRenderer} from 'echarts/renderers';
import {indicePublicoTaxonomico, ramaPublicaPhylotree} from './portal-phylotree-data';

use([TreeChart, CanvasRenderer]);

/** Canvas y trazado orthogonal nativos de Apache ECharts; el canvas mide sólo el viewport. */
export function crearVisorMasivo(elemento, acciones) {
    const escenario = document.createElement('div');
    escenario.className = 'portal-ete-stage portal-phylotree-stage portal-canvas-stage';
    escenario.setAttribute('x-ignore', '');
    const marco = document.createElement('div'); marco.className = 'portal-canvas-viewport';
    const dibujo = document.createElement('div'); dibujo.className = 'portal-canvas-drawing';
    const accesibles = document.createElement('div'); accesibles.className = 'portal-canvas-accessible';
    marco.append(dibujo, accesibles); escenario.append(marco);
    let chart, fuente, indice, rama, firma = '', factor = 1, ancho = 0, alto = 0, frame = null, destruido = false;
    let posiciones = [], porClave = new Map();
    const colores = ['#17699b', '#7d5b99', '#258177', '#a46a22', '#b14e71', '#527e45'];
    const registros = n => Number(n.total).toLocaleString('es-EC');
    const nombreEnLineas = nombre => {
        const partes = [], letras = [...nombre];
        while (letras.length > 26) {
            let corte = letras.slice(0, 27).lastIndexOf(' '); if (corte < 10) corte = 26;
            partes.push(letras.splice(0, corte).join('')); while (letras[0] === ' ') letras.shift();
        }
        partes.push(letras.join('')); return partes.join('\n');
    };
    const altoFila = () => Math.max(58, ...rama.hijos.map(n => nombreEnLineas(n.nombre).split('\n').length * 22 + 36));
    function datosMotor(nodo, nivel = 0, color = colores[0]) {
        const raiz = nivel === 0;
        return {...nodo, id: nodo.portal.clave, value: nodo.portal.total,
            itemStyle: {color, borderColor: '#fff', borderWidth: 2},
            lineStyle: {color, width: 1.8},
            label: {color, fontSize: raiz ? 21 : 18, backgroundColor: raiz ? '#e6f2fa' : '#fff',
                borderColor: raiz ? '#88b2cc' : '#d9e5ee', borderWidth: 1, borderRadius: 7,
                padding: [6, 9], formatter: () => `${nombreEnLineas((raiz ? 'Raíz · ' : '') + nodo.name)}\n{meta|${nodo.portal.etiqueta} · ${registros(nodo.portal)} registros}`},
            children: nodo.children?.map((h, i) => datosMotor(h, nivel + 1, raiz ? colores[i % colores.length] : color)),
        };
    }
    function enfocar(clave) {
        const p = porClave.get(clave); if (!p) return false;
        elemento.scrollLeft = Math.max(0, (p.x + 24) * factor - 24);
        elemento.scrollTop = Math.max(0, (p.y + 24) * factor - elemento.clientHeight / 2);
        pintarViewport(); accesibles.querySelector(`[data-taxon-clave="${clave}"]`)?.focus({preventScroll: true});
        return true;
    }
    function teclado(evento, nodo) {
        if (evento.key === 'ContextMenu' || evento.key === 'F10' && evento.shiftKey) {evento.preventDefault(); acciones.menu(nodo, evento);}
        else if (['Enter', ' ', 'ArrowRight'].includes(evento.key)) {evento.preventDefault(); if (nodo.clave !== rama.raiz.clave) acciones.seleccionar(nodo);}
        else if (evento.key === 'ArrowLeft') {evento.preventDefault(); acciones.volver?.();}
        else if (['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(evento.key)) {
            evento.preventDefault(); const i = posiciones.findIndex(p => p.nodo.clave === nodo.clave);
            const destino = posiciones[evento.key === 'Home' ? 0 : evento.key === 'End' ? posiciones.length - 1 : Math.max(0, Math.min(posiciones.length - 1, i + (evento.key === 'ArrowUp' ? -1 : 1)))];
            if (destino) enfocar(destino.nodo.clave);
        }
    }
    function pintarViewport() {
        if (!chart || destruido || !escenario.isConnected) return;
        marco.style.width = elemento.clientWidth + 'px'; marco.style.height = elemento.clientHeight + 'px';
        if (chart.getWidth() !== elemento.clientWidth || chart.getHeight() !== elemento.clientHeight) {
            chart.resize({width: elemento.clientWidth, height: elemento.clientHeight, silent: true});
            const anchoArbol = Math.max(350, elemento.clientWidth - 360);
            alto = Math.max(elemento.clientHeight, rama.hijos.length * altoFila() + 48);
            ancho = Math.max(elemento.clientWidth, anchoArbol + 360);
            chart.setOption({series: [{id: 'taxonomia', width: anchoArbol, height: alto - 48}]}, {silent: true});
            escenario.style.width = Math.max(elemento.clientWidth, ancho * factor) + 'px';
            escenario.style.height = Math.max(elemento.clientHeight, alto * factor) + 'px';
            posicionesMotor();
        }
        const modelo = chart.getModel().getSeriesByIndex(0);
        chart.getViewOfSeriesModel(modelo).group.attr({x: -elemento.scrollLeft, y: -elemento.scrollTop, scaleX: factor, scaleY: factor});
        chart.getZr().refreshImmediately();
        const foco = accesibles.contains(document.activeElement) ? document.activeElement.dataset.taxonClave : null;
        accesibles.replaceChildren();
        for (const p of posiciones) {
            const x = (p.x + 24) * factor - elemento.scrollLeft, y = (p.y + 24) * factor - elemento.scrollTop;
            if (y < -40 || y > elemento.clientHeight + 40 || x < -320 * factor || x > elemento.clientWidth) continue;
            const n = p.nodo, raiz = n.clave === rama.raiz.clave;
            const boton = document.createElement('button'); boton.type = 'button'; boton.className = 'portal-canvas-hit portal-ete-node' + (raiz ? ' is-root' : '');
            boton.dataset.taxonClave = n.clave; if (!raiz) boton.dataset.taxonPadre = n.padre;
            boton.setAttribute('aria-label', `${n.nombre}, ${n.etiqueta}, ${registros(n)} registros, ${raiz ? 'raíz de la vista' : 'hijo de ' + indice.porClave.get(n.padre)?.nombre}`);
            if (raiz && rama.hijos.length) boton.setAttribute('aria-expanded', 'true');
            const altura = nombreEnLineas((raiz ? 'Raíz · ' : '') + n.nombre).split('\n').length * (raiz ? 25 : 22) + 32;
            boton.style.cssText = `left:${x - 9 * factor}px;top:${y - altura * factor / 2}px;width:${310 * factor}px;height:${altura * factor}px`;
            boton.addEventListener('click', () => {if (!raiz) acciones.seleccionar(n);});
            boton.addEventListener('contextmenu', e => acciones.menu(n, e)); boton.addEventListener('keydown', e => teclado(e, n));
            accesibles.append(boton);
        }
        if (foco) accesibles.querySelector(`[data-taxon-clave="${foco}"]`)?.focus({preventScroll: true});
        escenario.dataset.zoom = String(factor); acciones.zoom?.(factor); acciones.ajustado?.(factor === 1);
    }
    function posicionesMotor() {
        const data = chart.getModel().getSeriesByIndex(0).getData();
        posiciones = [];
        for (let i = 0; i < data.count(); i++) {
            const n = data.getRawDataItem(i), p = data.getItemLayout(i);
            if (n?.portal && p && Number.isFinite(p.x) && Number.isFinite(p.y)) posiciones.push({nodo: n.portal, x: p.x, y: p.y, expandido: data.tree.getNodeByDataIndex(i).isExpand});
        }
        porClave = new Map(posiciones.map(p => [p.nodo.clave, p]));
    }
    function encuadrar(centrar) {
        escenario.style.width = Math.max(elemento.clientWidth, ancho * factor) + 'px';
        escenario.style.height = Math.max(elemento.clientHeight, alto * factor) + 'px';
        if (centrar) {
            elemento.scrollLeft = 0;
            elemento.scrollTop = Math.max(0, (porClave.get(rama.raiz.clave).y + 24) * factor - elemento.clientHeight / 2);
        }
        pintarViewport();
    }
    function alScroll() {
        if (frame !== null || !escenario.isConnected) return;
        frame = requestAnimationFrame(() => {frame = null; pintarViewport();});
    }
    elemento.addEventListener('scroll', alScroll, {passive: true});
    return {
        async actualizar(datos) {
            if (destruido) return;
            const cambiarFuente = fuente !== datos.nodos;
            if (cambiarFuente) {fuente = datos.nodos; indice = indicePublicoTaxonomico(fuente); firma = '';}
            if (!escenario.isConnected) elemento.replaceChildren(escenario);
            const siguiente = JSON.stringify([datos.revision, datos.rama]);
            if (siguiente === firma) return encuadrar(false);
            const inicio = performance.now(); factor = 1; rama = ramaPublicaPhylotree(indice, datos.rama, 1);
            if (!rama) return;
            // Espaciado nativo de ECharts en un plano desplazable, sin reducir las letras.
            const anchoArbol = Math.max(350, elemento.clientWidth - 360);
            ancho = Math.max(elemento.clientWidth, anchoArbol + 360);
            alto = Math.max(elemento.clientHeight, rama.hijos.length * altoFila() + 48);
            marco.style.width = elemento.clientWidth + 'px'; marco.style.height = elemento.clientHeight + 'px';
            // Cambiar la raíz elimina padres del modelo anterior. Vaciar la serie
            // evita que ECharts reutilice símbolos cuyo padre ya no está presente.
            if (chart) chart.clear();
            else chart = init(dibujo, null, {renderer: 'canvas', width: elemento.clientWidth, height: elemento.clientHeight});
            chart.setOption({animation: false, series: [{id: 'taxonomia', type: 'tree', data: [datosMotor(rama.json)],
                layout: 'orthogonal', orient: 'LR', left: 24, top: 24, right: null, bottom: null,
                width: anchoArbol, height: alto - 48, initialTreeDepth: -1, expandAndCollapse: false,
                roam: false, symbol: 'circle', symbolSize: 7, edgeShape: 'polyline', edgeForkPosition: '90%',
                label: {show: true, position: 'right', distance: 9, fontFamily: 'Georgia', fontStyle: 'italic', fontWeight: 'bold',
                    width: 270, align: 'left', verticalAlign: 'middle',
                    rich: {meta: {fontFamily: 'system-ui', fontStyle: 'normal', fontWeight: 'normal', fontSize: 14, color: '#466077', lineHeight: 22}}},
                leaves: {label: {position: 'right'}}, emphasis: {disabled: true}, select: {disabled: true}, animation: false,
            }]}, {notMerge: true, silent: true});
            posicionesMotor();
            escenario.__portalTree = chart;
            escenario.dataset.motor = 'Apache ECharts 6.0.0'; escenario.dataset.nodos = String(posiciones.length);
            escenario.dataset.nodosTotal = String(rama.nodos.length); escenario.dataset.completo = 'true';
            escenario.dataset.relaciones = String(rama.relaciones.length); escenario.dataset.eteVista = 'jerarquia';
            firma = siguiente; encuadrar(true);
            escenario.dataset.renderMs = String(Math.round((performance.now() - inicio) * 100) / 100);
            acciones.dibujado(rama.nodos.length, 'jerarquia');
            acciones.completo?.(false, true);
        },
        ajustar() {factor = 1; encuadrar(true);},
        zoom(paso) {
            if (!Number.isFinite(paso) || paso <= 0) return;
            const anterior = factor, x = elemento.scrollLeft, y = elemento.scrollTop + elemento.clientHeight / 2;
            factor = Math.max(.5, Math.min(3, factor * paso)); encuadrar(false);
            elemento.scrollLeft = x * factor / anterior;
            elemento.scrollTop = Math.max(0, y * factor / anterior - elemento.clientHeight / 2);
            pintarViewport();
        },
        enfocar,
        destruir() {destruido = true; if (frame !== null) cancelAnimationFrame(frame); elemento.removeEventListener('scroll', alScroll); chart?.dispose();},
    };
}
