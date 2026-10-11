const NS = 'http://www.w3.org/2000/svg';

/** El dibujo y las zonas de los nodos proceden de ETE, no de un layout JS. */
export function prepararZonasETE(dibujo, nodos) {
    const indice = new Map(nodos.map(n => [n.clave, n]));
    const [x, y, ancho, alto] = dibujo.viewBox;
    if (![x, y, ancho, alto].every(Number.isFinite) || ancho <= 0 || alto <= 0) throw new Error('Dibujo ETE inválido');
    return dibujo.nodos.flatMap(zona => {
        const nodo = indice.get(zona.clave), [x1, y1, x2, y2] = zona.caja;
        if (!nodo || !zona.caja.every(Number.isFinite) || x2 <= x1 || y2 <= y1) return [];
        return [{nodo, izquierda: (x1 - x) / ancho * 100, arriba: (y1 - y) / alto * 100,
            ancho: (x2 - x1) / ancho * 100, alto: (y2 - y1) / alto * 100,
            punto: zona.tipo === 'punto',
            centro: Array.isArray(zona.punto) && zona.punto.length === 2 && zona.punto.every(Number.isFinite)
                ? [(zona.punto[0] - x) / ancho * 100, (zona.punto[1] - y) / alto * 100] : null,
            recorte: zona.tipo === 'punto' ? 'circle(50%)' : Array.isArray(zona.poligono) && zona.poligono.length >= 3 && zona.poligono.every(p => Array.isArray(p) && p.length === 2 && p.every(Number.isFinite))
                ? 'polygon(' + zona.poligono.map(([px, py]) => `${(px - x1) / (x2 - x1) * 100}% ${(py - y1) / (y2 - y1) * 100}%`).join(',') + ')' : null}];
    });
}

/** La raíz, no el rectángulo del SVG, fija el centro de la vista. */
export function encuadreETE(dibujo, anchoPanel, altoPanel, escala = 1) {
    const {x: sx, y: sy} = typeof escala === 'number' ? {x: escala, y: escala} : escala;
    const [x, y, ancho, alto] = dibujo.viewBox;
    const raiz = dibujo.raiz || [x + ancho / 2, y + alto];
    if (dibujo.disposicion === 'lineal') {
        const izquierda = Math.max(12, (anchoPanel - ancho * sx) / 2), arriba = Math.max(12, (altoPanel - alto * sy) / 2);
        return {izquierda, arriba, ancho: Math.max(anchoPanel, ancho * sx + 24), alto: Math.max(altoPanel, alto * sy + 24),
            scrollX: Math.max(0, (ancho * sx + 24 - anchoPanel) / 2), scrollY: Math.max(0, (alto * sy + 24 - altoPanel) / 2)};
    }
    const rx = (raiz[0] - x) * sx, ry = (raiz[1] - y) * sy;
    const objetivoX = anchoPanel / 2;
    // El arco termina junto al borde inferior; reservar sólo el contenido
    // nativo que ETE deja bajo la raíz, sin limitar la escala por ese margen.
    const objetivoY = dibujo.vista === 'circular' ? altoPanel / 2 : Math.max(0, altoPanel - Math.max(24, (alto * sy - ry) + 12));
    const izquierda = Math.max(12, objetivoX - rx), arriba = Math.max(12, objetivoY - ry);
    const derecha = Math.max(12, anchoPanel - objetivoX - (ancho * sx - rx));
    const abajo = Math.max(12, altoPanel - objetivoY - (alto * sy - ry));
    return {izquierda, arriba, ancho: izquierda + ancho * sx + derecha, alto: arriba + alto * sy + abajo,
        scrollX: izquierda + rx - objetivoX, scrollY: arriba + ry - objetivoY};
}

/** Máximo tamaño visible conservando las proporciones del árbol y sus nombres. */
export function escalaAjustadaETE(dibujo, anchoPanel, altoPanel) {
    const [x, y, ancho, alto] = dibujo.viewBox;
    const raiz = dibujo.raiz || [x + ancho / 2, y + alto];
    const rx = raiz[0] - x, ry = raiz[1] - y;
    if (dibujo.disposicion === 'lineal') {
        const uniforme = Math.min(Math.max(1, anchoPanel - 24) / ancho, Math.max(1, altoPanel - 24) / alto);
        return {x: uniforme, y: uniforme};
    }
    const sx = Math.max(1, anchoPanel - 24) / (2 * Math.max(rx, ancho - rx));
    const sy = dibujo.vista === 'circular'
        ? Math.max(1, altoPanel - 24) / (2 * Math.max(ry, alto - ry))
        : Math.min(Math.max(1, altoPanel - 24) / alto, ry > 0 ? Math.max(1, altoPanel - 36) / ry : Infinity);
    const uniforme = Math.min(sx, sy);
    return {x: uniforme, y: uniforme};
}

function svgSeguro(texto) {
    const raiz = new DOMParser().parseFromString(texto, 'image/svg+xml').documentElement;
    if (raiz.localName !== 'svg' || raiz.namespaceURI !== NS) throw new Error('SVG ETE inválido');
    const permitidos = new Set(['svg', 'g', 'path', 'polyline', 'polygon', 'line', 'rect', 'circle', 'ellipse', 'text', 'tspan', 'defs', 'clipPath']);
    for (const elemento of [raiz, ...raiz.querySelectorAll('*')]) {
        if (!permitidos.has(elemento.localName)) { elemento.remove(); continue; }
        for (const atributo of [...elemento.attributes]) {
            if (/^on|href/i.test(atributo.name) || (/url\s*\(/i.test(atributo.value) && !/^url\(#[\w-]+\)$/.test(atributo.value))) elemento.removeAttribute(atributo.name);
        }
    }
    raiz.removeAttribute('width'); raiz.removeAttribute('height');
    raiz.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    raiz.setAttribute('aria-hidden', 'true');
    return document.importNode(raiz, true);
}

export function crearVisorETE(elemento, acciones) {
    let version = 0, peticion = null, firma = '', firmaDibujada = '', actual = null, datosActuales = null, factor = 1, ajuste = true, destruido = false;
    let tamanoAnterior = '', distribucionAnterior = '', escalaActual = {x: 1, y: 1}, escalaLibre = {x: 1, y: 1};
    const cache = new Map();
    const escenario = document.createElement('div'); escenario.className = 'portal-ete-stage';
    const plano = document.createElement('div'); plano.className = 'portal-ete-plane'; plano.append(escenario);
    elemento.replaceChildren(plano);
    function dimensionar(centrar = false) {
        if (!actual) return;
        // Medir una sola vez, antes de escribir. El tamaño del SVG y las barras
        // de desplazamiento nunca deben convertirse en nuevas medidas del panel.
        const anchoPanel = elemento.clientWidth, altoPanel = elemento.clientHeight;
        if (anchoPanel <= 0 || altoPanel <= 0) return;
        const tamano = anchoPanel + ':' + altoPanel;
        if (tamano !== tamanoAnterior) { factor = 1; ajuste = true; centrar = true; }
        const [, , ancho, alto] = actual.viewBox;
        const base = ajuste ? escalaAjustadaETE(actual, anchoPanel, altoPanel) : escalaLibre;
        const escala = {x: base.x * factor, y: base.y * factor};
        const distribucion = JSON.stringify([firmaDibujada, tamano, escala]);
        if (!centrar && distribucion === distribucionAnterior) return;
        const encuadre = encuadreETE(actual, anchoPanel, altoPanel, escala);
        escenario.style.width = ancho * escala.x + 'px'; escenario.style.height = alto * escala.y + 'px';
        plano.style.width = Math.max(anchoPanel, Math.ceil(encuadre.ancho - .001)) + 'px';
        plano.style.height = Math.max(altoPanel, Math.ceil(encuadre.alto - .001)) + 'px';
        escenario.style.left = encuadre.izquierda + 'px'; escenario.style.top = encuadre.arriba + 'px';
        if (centrar) { elemento.scrollLeft = encuadre.scrollX; elemento.scrollTop = encuadre.scrollY; }
        tamanoAnterior = tamano; distribucionAnterior = distribucion; escalaActual = escala;
        acciones.ajustado?.(ajuste && factor === 1);
    }
    function mostrar(dibujo, datos) {
        const svg = svgSeguro(dibujo.svg), zonas = prepararZonasETE(dibujo, datos.nodos), botones = [];
        for (const zona of zonas) {
            const boton = document.createElement('button'), nodo = zona.nodo;
            boton.type = 'button'; boton.className = 'portal-ete-node'; boton.dataset.taxonClave = nodo.clave;
            boton.setAttribute('aria-label', `${nodo.nombre}, ${nodo.etiqueta}, ${Number(nodo.total).toLocaleString('es-EC')} registros`);
            if (nodo.tieneHijos) boton.setAttribute('aria-expanded', String(datos.expandidos.includes(nodo.clave)));
            if (nodo.clave === datos.seleccion) boton.setAttribute('aria-current', 'location');
            // Sin title, hint ni tooltip: el texto ya forma parte de la face ETE.
            Object.assign(boton.style, {left: zona.izquierda + '%', top: zona.arriba + '%', width: zona.ancho + '%', height: zona.alto + '%'});
            if (zona.punto) {
                boton.classList.add('portal-ete-node-point');
                boton.style.left = (zona.izquierda + zona.ancho / 2) + '%';
                boton.style.top = (zona.arriba + zona.alto / 2) + '%';
            }
            if (zona.recorte) boton.style.clipPath = zona.recorte;
            boton.addEventListener('click', () => acciones.seleccionar(nodo));
            boton.addEventListener('contextmenu', evento => acciones.menu(nodo, evento));
            boton.addEventListener('keydown', evento => {
                if (evento.key === 'ContextMenu' || evento.key === 'F10' && evento.shiftKey) { evento.preventDefault(); acciones.menu(nodo, evento); }
                else if (evento.key === 'ArrowRight' && !datos.expandidos.includes(nodo.clave) || evento.key === 'ArrowLeft' && datos.expandidos.includes(nodo.clave)) { evento.preventDefault(); acciones.seleccionar(nodo); }
            });
            botones.push(boton);
            // Las faces de un padre ya no sustituyen su punto de bifurcación.
            // Mantenerlo clicable sin añadir otro paso a la navegación por Tab.
            if (!zona.punto && zona.centro && nodo.tieneHijos && datos.expandidos.includes(nodo.clave)) {
                const punto = boton.cloneNode(false);
                punto.classList.add('portal-ete-node-point'); punto.tabIndex = -1;
                Object.assign(punto.style, {left: zona.centro[0] + '%', top: zona.centro[1] + '%', clipPath: 'circle(50%)'});
                punto.addEventListener('click', () => acciones.seleccionar(nodo));
                punto.addEventListener('contextmenu', evento => acciones.menu(nodo, evento));
                botones.push(punto);
            }
        }
        const foco = elemento.contains(document.activeElement) ? document.activeElement?.closest('.portal-ete-node')?.dataset.taxonClave : null;
        escenario.replaceChildren(svg, ...botones); escenario.dataset.eteVista = dibujo.vista;
        escenario.dataset.eteDisposicion = dibujo.disposicion || 'radial';
        actual = dibujo; datosActuales = datos; firmaDibujada = firma;
        factor = 1; ajuste = true; dimensionar(true);
        if (foco) botones.find(b => b.dataset.taxonClave === foco)?.focus({preventScroll: true});
        acciones.dibujado(dibujo.total, dibujo.vista);
    }
    return {
        async actualizar(datos) {
            const siguiente = JSON.stringify([datos.revision, datos.taxon, datos.rama, datos.vista, datos.escala, [...datos.expandidos].sort()]);
            if (siguiente === firma) {
                // Una notificación de tamaño durante la petición no debe
                // cancelarla, reiniciarla ni mostrar el dibujo de la rama anterior.
                if (!actual || firmaDibujada !== firma) return;
                const seleccionCambio = datosActuales?.seleccion !== datos.seleccion;
                datosActuales = datos;
                escenario.querySelectorAll('button').forEach(boton => {
                    if (boton.dataset.taxonClave === datos.seleccion) boton.setAttribute('aria-current', 'location');
                    else boton.removeAttribute('aria-current');
                });
                if (seleccionCambio) { factor = 1; ajuste = true; }
                dimensionar(seleccionCambio); return;
            }
            firma = siguiente; const solicitud = ++version;
            peticion?.abort(); peticion = new AbortController();
            if (cache.has(firma)) { mostrar(cache.get(firma), datos); acciones.cargando(false); return; }
            acciones.cargando(true);
            const url = new URL(datos.endpoint, location.origin);
            if (datos.taxon) url.searchParams.set('taxon', datos.taxon);
            if (datos.rama) url.searchParams.set('rama', datos.rama);
            url.searchParams.set('vista', datos.vista); url.searchParams.set('escala', String(datos.escala));
            datos.expandidos.forEach(clave => url.searchParams.append('expandidos[]', clave));
            try {
                const respuesta = await fetch(url, {headers: {Accept: 'application/json'}, signal: peticion.signal});
                if (!respuesta.ok) throw new Error('ETE no disponible');
                const dibujo = await respuesta.json();
                if (destruido || solicitud !== version) return;
                cache.set(siguiente, dibujo);
                while (cache.size > 16 || [...cache.values()].reduce((suma, valor) => suma + valor.svg.length, 0) > 12 * 1024 * 1024) cache.delete(cache.keys().next().value);
                mostrar(dibujo, datos);
            } catch (error) {
                if (solicitud === version && error.name !== 'AbortError') { firma = ''; acciones.error('No se pudo abrir el árbol ETE. Reintenta la vista.'); }
            } finally { if (solicitud === version) acciones.cargando(false); }
        },
        ajustar() { factor = 1; ajuste = true; dimensionar(true); },
        natural() { factor = 1; escalaLibre = {x: 1, y: 1}; ajuste = false; dimensionar(true); },
        zoom(paso) {
            if (!actual || !Number.isFinite(paso) || paso <= 0) return;
            escalaLibre = {x: Math.max(.001, Math.min(20, escalaActual.x * paso)), y: Math.max(.001, Math.min(20, escalaActual.y * paso))};
            ajuste = false; factor = 1; dimensionar(true);
        },
        enfocar(clave) {
            const boton = [...escenario.querySelectorAll('button')].find(b => b.dataset.taxonClave === clave);
            boton?.focus({preventScroll: true}); return Boolean(boton);
        },
        destruir() { destruido = true; version++; peticion?.abort(); cache.clear(); actual = datosActuales = null; elemento.replaceChildren(); },
    };
}
