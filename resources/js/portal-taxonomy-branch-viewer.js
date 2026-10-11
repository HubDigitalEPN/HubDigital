import {ramaExploradorTaxonomico, filasExploradorTaxonomico, ventanaExploradorTaxonomico} from './portal-taxonomy-branch-model';
import {rangoVisual3D} from './portal-taxonomy-three-ranks';

const numero = valor => Number(valor).toLocaleString('es-EC');
const crear = (tag, clase, texto) => {const el = document.createElement(tag); el.className = clase; if (texto) el.textContent = texto; return el;};
const icono = nombre => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', nombre === 'carpeta' ? 'M3 6h6l2 2h10v11H3z' : nombre === 'flecha' ? 'm9 5 7 7-7 7' : 'M6 4h12v16H6zM9 8h6M9 12h6M9 16h4');
    svg.append(path); return svg;
};

/** Explorador de carpetas con DOM acotado, selección y teclado de un árbol accesible. */
export function crearExploradorRama(elemento, acciones) {
    const cabecera = crear('header', 'portal-branch-header');
    const perfil = crear('div', 'portal-branch-profile'), foto = crear('figure', 'portal-branch-photo');
    const imagen = crear('img', ''), credito = crear('div', 'portal-branch-credit'), descripcion = crear('p', 'portal-branch-description');
    const creditosDetalle = crear('details', ''), creditosTitulo = crear('summary', '', 'Créditos de la fotografía'), creditosTexto = crear('div', '');
    creditosDetalle.append(creditosTitulo, creditosTexto); credito.append(creditosDetalle);
    const ampliarFoto = crear('button', 'portal-branch-photo-open'); ampliarFoto.type = 'button'; ampliarFoto.disabled = true;
    ampliarFoto.setAttribute('aria-haspopup', 'dialog'); ampliarFoto.setAttribute('aria-label', 'Ampliar fotografía'); ampliarFoto.title = 'Ampliar fotografía';
    imagen.decoding = 'async'; ampliarFoto.append(imagen); foto.append(ampliarFoto);
    const informacion = crear('button', 'portal-branch-info', 'Información'); informacion.type = 'button'; informacion.hidden = true;
    informacion.setAttribute('aria-haspopup', 'dialog'); informacion.title = 'Ver fotografía y descripción del taxón';
    const herramientas = crear('div', 'portal-branch-tools'); herramientas.append(credito, informacion);
    const estadoPerfil = crear('p', 'portal-branch-profile-status'); estadoPerfil.setAttribute('role', 'status');
    const reintentar = crear('button', 'portal-branch-retry', 'Reintentar ficha'); reintentar.type = 'button'; reintentar.hidden = true;
    perfil.append(foto, herramientas, estadoPerfil, reintentar);
    cabecera.append(perfil);
    const vista = crear('div', 'portal-branch-tree'); vista.setAttribute('role', 'tree');
    const contenido = crear('div', 'portal-branch-rows'); contenido.setAttribute('role', 'none'); vista.append(contenido);
    elemento.append(cabecera, vista);
    const fotoModal = crear('dialog', 'portal-photo-dialog'); fotoModal.setAttribute('aria-label', 'Fotografía ampliada del taxón');
    const barraFoto = crear('div', 'portal-photo-bar'), tituloFoto = crear('h3', 'portal-photo-title');
    const cerrarFoto = crear('button', 'portal-photo-close', '×'); cerrarFoto.type = 'button'; cerrarFoto.setAttribute('aria-label', 'Cerrar fotografía ampliada'); cerrarFoto.title = 'Cerrar fotografía ampliada';
    barraFoto.append(tituloFoto, cerrarFoto);
    const fotoGrande = crear('img', 'portal-photo-image'), creditosGrandes = crear('p', 'portal-photo-credit');
    fotoModal.append(barraFoto, fotoGrande, descripcion, creditosGrandes); elemento.append(fotoModal);
    let rama = {nodos: [], porClave: new Map()}, expandidos = new Set(), filas = [], elegido = '', foco = '', destruido = false, frame = null;
    const entradas = new Map(), altura = 40;
    let solicitudFicha = null, revisionFicha = 0, raizFicha = null;
    const endpoint = elemento.closest('dialog')?.dataset.fichaEndpoint;
    const dialogo = elemento.closest('dialog');
    foto.hidden = descripcion.hidden = credito.hidden = true;
    function cerrarAmpliacion() {if (fotoModal.open) fotoModal.close(); fotoGrande.removeAttribute('src');}
    let origenAmpliacion = ampliarFoto;
    function abrirAmpliacion(evento) {
        if (!raizFicha || informacion.hidden) return;
        origenAmpliacion = evento.currentTarget;
        tituloFoto.textContent = raizFicha.nombre; fotoGrande.hidden = foto.hidden || ampliarFoto.disabled;
        if (!fotoGrande.hidden) {fotoGrande.src = imagen.src; fotoGrande.alt = imagen.alt;}
        creditosGrandes.textContent = creditosTexto.textContent; fotoModal.showModal(); cerrarFoto.focus();
    }
    ampliarFoto.addEventListener('click', abrirAmpliacion); informacion.addEventListener('click', abrirAmpliacion);
    cerrarFoto.addEventListener('click', cerrarAmpliacion);
    fotoModal.addEventListener('keydown', evento => {if (evento.key === 'Escape') evento.stopPropagation();});
    fotoModal.addEventListener('cancel', evento => {evento.preventDefault(); evento.stopPropagation(); cerrarAmpliacion();});
    fotoModal.addEventListener('close', evento => {evento.stopPropagation(); fotoGrande.removeAttribute('src'); if (!destruido && dialogo?.open && origenAmpliacion.isConnected && !origenAmpliacion.disabled) origenAmpliacion.focus({preventScroll: true});});
    fotoModal.addEventListener('click', evento => {if (evento.target !== fotoModal) return; const r = fotoModal.getBoundingClientRect(); if (evento.clientX < r.left || evento.clientX > r.right || evento.clientY < r.top || evento.clientY > r.bottom) cerrarAmpliacion();});
    dialogo?.addEventListener('close', cerrarAmpliacion);

    async function cargarFicha(nodo) {
        solicitudFicha?.abort(); solicitudFicha = new AbortController(); const revision = ++revisionFicha;
        ampliarFoto.disabled = true; informacion.hidden = credito.hidden = true; cerrarAmpliacion();
        raizFicha = nodo; elemento.dataset.ficha = nodo.nombre; foto.hidden = descripcion.hidden = true; imagen.removeAttribute('src'); imagen.style.visibility = 'hidden'; creditosTexto.replaceChildren(); creditosDetalle.open = false;
        reintentar.hidden = true; estadoPerfil.hidden = false; estadoPerfil.textContent = 'Cargando ficha…'; perfil.setAttribute('aria-busy', 'true');
        try {
            if (!endpoint || !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(nodo.id || '')) throw new Error('Ficha no disponible');
            const respuesta = await fetch(endpoint.replace('__TAXON__', encodeURIComponent(nodo.id)), {signal: solicitudFicha.signal, cache: 'no-store', credentials: 'same-origin', headers: {Accept: 'application/json'}});
            if (!respuesta.ok) throw new Error('Ficha no disponible');
            const ficha = await respuesta.json();
            if (destruido || revision !== revisionFicha) return;
            descripcion.textContent = ficha.descripcion || ''; descripcion.hidden = !ficha.descripcion;
            informacion.hidden = !(ficha.descripcion || ficha.foto);
            if (ficha.foto) {
                const url = new URL(ficha.foto, location.href);
                if (url.origin !== location.origin) throw new Error('Origen de foto inválido');
                imagen.alt = ficha.alt || `Fotografía representativa de ${nodo.nombre}`;
                ampliarFoto.setAttribute('aria-label', `Ampliar fotografía de ${nodo.nombre}`);
                imagen.onload = () => {if (revision === revisionFicha) {ampliarFoto.disabled = false; imagen.style.visibility = 'visible'; foto.hidden = false; estadoPerfil.hidden = true;}};
                imagen.onerror = () => {if (revision === revisionFicha) {foto.hidden = true; estadoPerfil.hidden = false; estadoPerfil.textContent = 'La fotografía no está disponible.'; reintentar.hidden = false;}};
                credito.hidden = !ficha.credito && !ficha.fuente; creditosTexto.textContent = ficha.credito || '';
                if (ficha.fuente) {
                    const fuente = new URL(ficha.fuente);
                    if (fuente.protocol === 'https:') {const link = crear('a', '', 'Fuente'); link.href = fuente.href; link.target = '_blank'; link.rel = 'noopener noreferrer'; creditosTexto.append(document.createTextNode(' · '), link);}
                }
                foto.hidden = false; imagen.src = url.href;
            }
            estadoPerfil.hidden = !!(ficha.foto || ficha.descripcion);
            if (!ficha.foto && !ficha.descripcion) estadoPerfil.textContent = 'Todavía no hay una ficha publicada para este taxón.';
        } catch (error) {
            if (error.name === 'AbortError' || destruido || revision !== revisionFicha) return;
            estadoPerfil.hidden = false; estadoPerfil.textContent = 'No se pudo cargar la ficha. La rama sigue disponible.'; reintentar.hidden = false;
        } finally {if (!destruido && revision === revisionFicha) perfil.setAttribute('aria-busy', 'false');}
    }
    reintentar.addEventListener('click', () => {if (raizFicha) cargarFicha(raizFicha);});

    function limpiar() {
        revisionFicha++; solicitudFicha?.abort(); raizFicha = null;
        ampliarFoto.disabled = true; informacion.hidden = credito.hidden = true; cerrarAmpliacion(); delete elemento.dataset.ficha;
        imagen.onload = imagen.onerror = null; imagen.removeAttribute('src');
        rama = {nodos: [], porClave: new Map()}; filas = []; foco = elegido = '';
        for (const el of entradas.values()) el.remove(); entradas.clear();
        for (const clave of ['raiz', 'nombre', 'nodos', 'filas', 'dibujados', 'seleccion']) delete elemento.dataset[clave];
        elemento.classList.add('is-empty'); elemento.classList.remove('is-leaf');
        vista.hidden = foto.hidden = descripcion.hidden = reintentar.hidden = true;
        estadoPerfil.hidden = false; estadoPerfil.textContent = 'Selecciona una esfera del octree.'; perfil.setAttribute('aria-busy', 'false');
    }

    function enfocar(clave) {
        const i = filas.findIndex(fila => fila.nodo.clave === clave); if (i < 0) return;
        foco = clave;
        if (i * altura < vista.scrollTop) vista.scrollTop = i * altura;
        else if ((i + 1) * altura > vista.scrollTop + vista.clientHeight) vista.scrollTop = (i + 1) * altura - vista.clientHeight;
        dibujar(); entradas.get(clave)?.focus({preventScroll: true}); return true;
    }
    function marcar(clave) {
        elegido = clave;
        for (const [k, row] of entradas) row.setAttribute('aria-selected', String(k === clave));
        elemento.dataset.seleccion = clave;
    }
    function elegir(nodo) {marcar(nodo.clave); cargarFicha(nodo); acciones.elegir(nodo.clave);}
    function alternar(nodo) {
        if (!nodo.hijos.length) return;
        if (expandidos.has(nodo.clave)) expandidos.delete(nodo.clave); else expandidos.add(nodo.clave);
        distribuir(); enfocar(nodo.clave);
    }
    function teclado(evento, nodo) {
        const i = filas.findIndex(fila => fila.nodo.clave === nodo.clave), fila = filas[i];
        if (!fila) return;
        const {key} = evento;
        if (key === 'ContextMenu' || key === 'F10' && evento.shiftKey) {evento.preventDefault(); evento.stopPropagation(); acciones.menu(nodo, evento); return;}
        if (!['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter', ' '].includes(key)) return;
        evento.preventDefault(); evento.stopPropagation();
        if (key === 'ArrowDown' || key === 'ArrowUp') return enfocar(filas[Math.max(0, Math.min(filas.length - 1, i + (key === 'ArrowDown' ? 1 : -1)))].nodo.clave);
        if (key === 'Home' || key === 'End') return enfocar(filas[key === 'Home' ? 0 : filas.length - 1].nodo.clave);
        if (key === 'ArrowRight') {
            if (nodo.hijos.length && !fila.abierto) alternar(nodo);
            else if (fila.abierto && filas[i + 1]?.nodo.padreIndice === rama.porClave.get(nodo.clave)) enfocar(filas[i + 1].nodo.clave);
        } else if (key === 'ArrowLeft') {
            if (nodo.hijos.length && fila.abierto) alternar(nodo);
            else if (nodo.padreIndice >= 0) enfocar(rama.nodos[nodo.padreIndice].clave);
        } else if (key === 'Enter') {elegir(nodo); alternar(nodo);}
        else elegir(nodo);
    }
    function crearFila(nodo) {
        const row = crear('div', 'portal-branch-row'); row.setAttribute('role', 'treeitem'); row.dataset.taxonClave = nodo.clave;
        const toggle = crear('span', 'portal-branch-toggle'); toggle.append(icono('flecha')); toggle.setAttribute('aria-hidden', 'true');
        const tipo = crear('span', 'portal-branch-icon'); tipo.append(icono(nodo.hijos.length ? 'carpeta' : 'hoja')); tipo.setAttribute('aria-hidden', 'true');
        const label = crear('span', 'portal-branch-name', nodo.nombre), cuenta = crear('span', 'portal-branch-count', numero(nodo.total));
        const color = rangoVisual3D(nodo); row.style.setProperty('--branch-rank', color.color);
        label.title = `${nodo.nombre} · ${nodo.etiqueta}`; cuenta.title = numero(nodo.total) + ' registros publicados'; cuenta.setAttribute('aria-hidden', 'true');
        row.setAttribute('aria-label', `${nodo.nombre}, ${nodo.etiqueta}, ${numero(nodo.total)} registros, ${numero(nodo.hijos.length)} hijos`);
        row.setAttribute('aria-level', String(nodo.profundidad + 1)); row.setAttribute('aria-posinset', String(nodo.posicion)); row.setAttribute('aria-setsize', String(nodo.hermanos));
        row.append(toggle, tipo, label, cuenta);
        row.addEventListener('click', evento => {enfocar(nodo.clave); if (evento.target.closest('.portal-branch-toggle')) alternar(nodo); else elegir(nodo);});
        row.addEventListener('dblclick', evento => {if (!evento.target.closest('.portal-branch-toggle')) alternar(nodo);});
        row.addEventListener('contextmenu', evento => {evento.preventDefault(); evento.stopPropagation(); enfocar(nodo.clave); acciones.menu(nodo, evento);});
        row.addEventListener('keydown', evento => teclado(evento, nodo));
        row.addEventListener('focus', () => {foco = nodo.clave; for (const [k, el] of entradas) el.tabIndex = k === foco ? 0 : -1;});
        return row;
    }
    function dibujar() {
        if (destruido) return;
        const ventana = ventanaExploradorTaxonomico(filas.length, vista.scrollTop, vista.clientHeight, altura);
        const indices = [];
        for (let i = ventana.inicio; i < ventana.fin; i++) indices.push(i);
        // Un elemento enfocado no desaparece del DOM al desplazar con el mouse.
        if (vista.contains(document.activeElement)) {
            const i = filas.findIndex(f => f.nodo.clave === foco); if (i >= 0 && !indices.includes(i)) indices.push(i);
        }
        indices.sort((a, b) => a - b);
        const enfocado = vista.contains(document.activeElement) ? document.activeElement : null;
        const visibles = new Set(indices.map(i => filas[i].nodo.clave));
        for (const [clave, el] of entradas) if (!visibles.has(clave)) {el.remove(); entradas.delete(clave);}
        let siguiente = contenido.firstChild;
        for (const i of indices) {
            const {nodo, abierto, coincide} = filas[i];
            let row = entradas.get(nodo.clave);
            if (!row) {row = crearFila(nodo); entradas.set(nodo.clave, row); contenido.append(row);}
            if (row !== siguiente) contenido.insertBefore(row, siguiente);
            siguiente = row.nextSibling;
            row.style.top = i * altura + 'px'; row.style.setProperty('--branch-depth', String(Math.min(nodo.profundidad, 6)));
            row.tabIndex = foco === nodo.clave ? 0 : -1;
            row.setAttribute('aria-selected', String(elegido === nodo.clave)); row.classList.toggle('is-match', coincide);
            if (nodo.hijos.length) row.setAttribute('aria-expanded', String(abierto)); else row.removeAttribute('aria-expanded');
            row.querySelector('.portal-branch-toggle').classList.toggle('is-leaf', !nodo.hijos.length);
        }
        if (enfocado?.isConnected && document.activeElement !== enfocado) enfocado.focus({preventScroll: true});
        elemento.dataset.dibujados = String(entradas.size);
    }
    function distribuir() {
        filas = filasExploradorTaxonomico(rama, expandidos);
        if (!filas.some(f => f.nodo.clave === foco)) foco = filas[0]?.nodo.clave || '';
        contenido.style.height = filas.length * altura + 'px';
        elemento.dataset.filas = String(filas.length);
        dibujar();
    }
    const resize = new ResizeObserver(dibujar); resize.observe(vista);
    vista.addEventListener('scroll', () => {if (frame === null) frame = requestAnimationFrame(() => {frame = null; dibujar();});}, {passive: true});
    limpiar();
    return {
        actualizar(indice, clave) {
            if (destruido) return;
            rama = ramaExploradorTaxonomico(indice, clave); const raiz = rama.nodos[0];
            for (const el of entradas.values()) el.remove(); entradas.clear();
            if (!raiz) {elemento.hidden = true; return;}
            elemento.hidden = false; elemento.classList.remove('is-empty');
            elemento.dataset.raiz = clave; elemento.dataset.nombre = raiz.nombre; elemento.dataset.nodos = String(rama.nodos.length);
            vista.setAttribute('aria-label', `Raíz ${raiz.nombre} y todos sus descendientes`);
            const hoja = !raiz.hijos.length; elemento.classList.toggle('is-leaf', hoja);
            vista.hidden = hoja;
            cargarFicha(raiz);
            vista.scrollTop = 0; foco = elegido = clave; expandidos = new Set([clave]); distribuir();
            elemento.dataset.seleccion = clave;
        },
        marcar,
        limpiar,
        enfocar,
        destruir() {destruido = true; revisionFicha++; solicitudFicha?.abort(); dialogo?.removeEventListener('close', cerrarAmpliacion); cerrarAmpliacion(); imagen.onload = imagen.onerror = null; resize.disconnect(); if (frame !== null) cancelAnimationFrame(frame); entradas.clear(); elemento.replaceChildren();},
    };
}
