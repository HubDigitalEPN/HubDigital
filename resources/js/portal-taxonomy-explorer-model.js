/** Bosque público: cada filo tiene su propia raíz, sin un nodo artificial común. */
export function distribuirBosque(nodos, expandidos = [], anchoDisponible = 720, raizActiva = '') {
    const abiertos = new Set(expandidos);
    const hijos = new Map();
    for (const nodo of nodos) {
        const clave = nodo.padre ?? '';
        if (!hijos.has(clave)) hijos.set(clave, []);
        hijos.get(clave).push(nodo);
    }
    for (const grupo of hijos.values()) grupo.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es', {numeric: true, sensitivity: 'base'}) || a.clave.localeCompare(b.clave));
    const raices = [...(hijos.get('') ?? [])];
    // Una raíz abierta comienza arriba a la izquierda. Las tarjetas mantienen
    // su tamaño: las ramas numerosas ocupan filas y columnas, nunca se giran.
    if (abiertos.has(raizActiva)) raices.sort((a, b) => (b.clave === raizActiva) - (a.clave === raizActiva));
    const margen = 24, anchoNodo = 182, altoNodo = 36, paso = 28, separacion = 24;
    const ancho = Math.max(anchoDisponible, anchoNodo + margen * 2);
    const ramas = nodo => abiertos.has(nodo.clave) ? (hijos.get(nodo.clave) ?? []) : [];
    const posiciones = [], conexiones = [], grupos = [];
    const colocar = (nodo, x, y, anchoRegion, profundidad, color) => {
        const posicion = {...nodo, x, y, ancho: anchoNodo, alto: altoNodo, profundidad, color};
        posiciones.push(posicion);
        const descendientes = ramas(nodo);
        if (!descendientes.length) return {posicion, fondo: y + altoNodo};
        if (descendientes.length <= 3 && anchoRegion >= anchoNodo * 2 + paso) {
            let siguiente = y, fondo = y + altoNodo;
            for (const hijo of descendientes) {
                const rama = colocar(hijo, x + anchoNodo + paso, siguiente, anchoRegion - anchoNodo - paso, profundidad + 1, color);
                conexiones.push({d: `M ${x + anchoNodo} ${y + altoNodo / 2} H ${x + anchoNodo + paso / 2} V ${siguiente + altoNodo / 2} H ${rama.posicion.x}`, color});
                fondo = Math.max(fondo, rama.fondo); siguiente = fondo + separacion;
            }
            return {posicion, fondo};
        }
        // Los hijos comparten un recinto identificado con su padre. Una cadena
        // de ancestros ocupa la primera fila; su descendencia usa todo el ancho.
        const origenX = anchoRegion >= anchoNodo * 2 ? x : margen;
        const region = anchoRegion >= anchoNodo * 2 ? anchoRegion : ancho - margen * 2;
        const columnas = Math.max(1, Math.floor((region + 16) / (anchoNodo + 16)));
        const comienzo = y + altoNodo + 30;
        const grupo = {clave: nodo.clave, nombre: nodo.nombre, total: descendientes.length, x: origenX - 10, y: comienzo - 26, ancho: region + 16, alto: 0, color};
        grupos.push(grupo);
        let siguiente = comienzo, columna = 0, fondo = comienzo;
        const destinos = [];
        for (const hijo of descendientes) {
            const expandido = ramas(hijo).length > 0;
            if (expandido && columna) { siguiente += altoNodo + 8; columna = 0; }
            const destinoX = origenX + columna * (anchoNodo + 16);
            const rama = colocar(hijo, destinoX, siguiente, expandido ? region : anchoNodo, profundidad + 1, color);
            destinos.push(rama.posicion); fondo = Math.max(fondo, rama.fondo);
            if (expandido) { siguiente = rama.fondo + separacion; columna = 0; }
            else if (++columna === columnas) { siguiente += altoNodo + 8; columna = 0; }
        }
        const tronco = origenX - 14;
        conexiones.push({d: `M ${x + anchoNodo / 2} ${y + altoNodo} V ${comienzo - 28} H ${tronco} V ${destinos.at(-1).y - 4}`, color});
        for (const destino of destinos) conexiones.push({d: `M ${tronco} ${destino.y - 4} H ${destino.x + anchoNodo / 2} V ${destino.y}`, color});
        grupo.alto = fondo - grupo.y + 12;
        return {posicion, fondo: fondo + 12};
    };
    let inicio = margen;
    for (const raiz of raices) {
        const rama = colocar(raiz, margen, inicio, ancho - margen * 2, 0, colorRaiz(raiz.nombre));
        inicio = rama.fondo + separacion;
    }
    return {ancho, alto: Math.max(180, inicio - separacion + margen), nodos: posiciones, conexiones, grupos};
}

function colorRaiz(nombre) {
    const conocidos = {Arthropoda: '#17699b', Mollusca: '#d17d28', Annelida: '#568c59', Nematomorpha: '#b94e6b', Nematoda: '#8c62a5'};
    if (conocidos[nombre]) return conocidos[nombre];
    const paleta = ['#17699b', '#3f8d90', '#8c62a5', '#568c59', '#b94e6b'];
    const huella = [...nombre].reduce((valor, letra) => (valor * 31 + letra.codePointAt(0)) >>> 0, 0);
    return paleta[huella % paleta.length];
}

export function escalaBosque(bosque, ancho, alto) {
    return Math.min(1, Math.max(1, ancho - 16) / bosque.ancho, Math.max(1, alto - 16) / bosque.alto);
}

export function filosPublicos(nodos) {
    return nodos.filter(n => n.padre == null).sort((a, b) =>
        (b.nombre === 'Arthropoda') - (a.nombre === 'Arthropoda') || a.nombre.localeCompare(b.nombre, 'es'));
}

/** Una ruta visible y una página de hijos: el DOM no crece con todo el catálogo. */
export function panelTaxonomico(nodos, expandidos = [], activa = '', busqueda = '', pagina = 1, capacidad = 20, niveles = Infinity) {
    const porClave = new Map(nodos.map(n => [n.clave, n])), porPadre = new Map();
    for (const nodo of nodos) {
        const padre = nodo.padre ?? '';
        if (!porPadre.has(padre)) porPadre.set(padre, []);
        porPadre.get(padre).push(nodo);
    }
    for (const grupo of porPadre.values()) grupo.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es', {numeric: true, sensitivity: 'base'}) || a.clave.localeCompare(b.clave));
    const actual = porClave.get(activa) ?? null, ruta = [], visitados = new Set();
    for (let nodo = actual; nodo && !visitados.has(nodo.clave); nodo = porClave.get(nodo.padre)) {
        visitados.add(nodo.clave); ruta.unshift(nodo);
    }
    const abiertos = new Set([...expandidos, ...ruta.slice(0, -1).map(n => n.clave)]);
    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const consulta = normalizar(busqueda.trim()), todos = porPadre.get(actual?.clave ?? '') ?? [];
    const coincidencias = consulta ? todos.filter(n => normalizar(n.nombre).includes(consulta)) : todos;
    const limite = Math.max(1, Math.min(36, Math.floor(capacidad) || 1));
    const paginas = Math.max(1, Math.ceil(coincidencias.length / limite));
    const numero = Math.max(1, Math.min(paginas, Math.floor(pagina) || 1));
    const filas = [];
    const agregar = (nodo, profundidad, color) => {
        filas.push({...nodo, profundidad, color, expandido: abiertos.has(nodo.clave)});
        if (!abiertos.has(nodo.clave) || profundidad >= niveles - 1) return;
        const hijos = porPadre.get(nodo.clave) ?? [];
        const siguiente = ruta.find(n => n.padre === nodo.clave);
        const indice = siguiente ? hijos.findIndex(n => n.clave === siguiente.clave) : 0;
        const inicio = Math.max(0, Math.min(hijos.length - 8, indice - 3));
        for (const hijo of Number.isFinite(niveles) ? hijos : hijos.slice(inicio, inicio + 8)) agregar(hijo, profundidad + 1, color);
        if (!Number.isFinite(niveles) && hijos.length > 8) filas.push({clave: 'mas-' + nodo.clave, mas: true, nodo, profundidad: profundidad + 1, total: hijos.length, color});
    };
    for (const raiz of porPadre.get('') ?? []) agregar(raiz, 0, colorRaiz(raiz.nombre));
    return {actual, ruta, filas, hijos: coincidencias.slice((numero - 1) * limite, numero * limite),
        total: todos.length, coincidencias: coincidencias.length, pagina: numero, paginas, capacidad: limite,
        desde: coincidencias.length ? (numero - 1) * limite + 1 : 0, hasta: Math.min(numero * limite, coincidencias.length),
        color: colorRaiz(ruta[0]?.nombre || '')};
}

/** Coordenadas de viewport: coloca las acciones al lado del taxón y dentro de su vista. */
export function posicionMenuTaxonomico(ancla, menu, limite) {
    const margen = 8;
    let x = ancla.right + margen;
    if (x + menu.width > limite.right - margen) x = ancla.left - menu.width - margen;
    const y = (ancla.top + ancla.bottom - menu.height) / 2;
    return {
        x: Math.max(limite.left + margen, Math.min(x, limite.right - menu.width - margen)),
        y: Math.max(limite.top + margen, Math.min(y, limite.bottom - menu.height - margen)),
    };
}

export function crearExploradorTaxonomico(reloj = {
    // Los temporizadores nativos del navegador deben conservar su contexto global.
    setTimeout: (tarea, retraso) => globalThis.setTimeout(tarea, retraso),
    clearTimeout: id => globalThis.clearTimeout(id),
}, cargarModuloVisor = () => import('./portal-taxonomy-three-viewer')) {
    let temporizador = null, version = 0, sesion = 0, observador = null, invocador = null;
    let pendiente = null, procesando = false, revisionSeleccion = 0;
    let ramasCargando = new Set();
    let contexto = null, ultimaBusqueda = '', restaurarFoco = true, ajustePendiente = null;
    const tamanosObservados = new WeakMap();
    let posicionGuardada = {x: 0, y: 0, cuerpo: 0};
    let visor = null, cargandoVisor = null, revisionVisor = 0, revisionNavegacion = 0, destruido = false, arrastre = null, redimension = null;
    return {
        abierto: false, busqueda: '', nodos: [], expandidos: [], cargados: [], cargandoRamas: [],
        seleccionado: '', destacado: '', total: 0, listo: false, buscando: false, aplicando: false, mostrandoMapa: false, hayMas: false, error: '',
        ancho: 720, bosque: {ancho: 720, alto: 180, nodos: [], conexiones: [], grupos: []},
        ajustado: false, escala: 1, raizActiva: '', filtrosArbol: [], filtrosVisor: [],
        modo: 'ramas', ramaActiva: '', ramaBusqueda: '', ramaPagina: 1, anchoModal: 1000, jerarquiaAbierta: false,
        panel: panelTaxonomico([]), visorDisponible: false, visorCargando: false, visorError: '', taxonesDibujados: 0,
        tipoDibujo: '', permiteVistaCompleta: false, arbolCompleto: true, zoomActual: .78125, zoomMinimo: .5, zoomMaximo: 256,
        desplazamientoX: 0, desplazamientoY: 0, arrastrando: false,
        anchoVentana: 0, altoVentana: 0, redimensionando: false, lateralPlegado: false,
        vistaETE: 'auto', escalaETE: 120, revisionETE: 0,
        sugerencias: [], sugerenciaActiva: -1, sugerenciasAbiertas: false, menuNodo: null, filos: [],
        get filoActual() { return this.panel.ruta[0]?.clave || ''; },
        get hijosRama() { return this.nodos.filter(n => n.padre === this.ramaActiva).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')); },
        async elegirFilo(clave) {
            const elegido = this.filos.find(n => n.clave === clave);
            if (!elegido || this.buscando) return;
            this.filtrosVisor = []; this.busqueda = ''; this.sugerencias = []; this.sugerenciasAbiertas = false;
            if (this.filtrosArbol.length) {
                this.filtrosArbol = []; this.nodos = []; this.expandidos = []; this.cargados = [];
                this.busqueda = ''; this.sugerenciasAbiertas = false; contexto = this.contextoActual();
                await this.consultar();
            }
            const nodo = this.nodos.find(n => n.id === elegido.id);
            if (nodo) await this.navegarEnVisor(nodo);
        },
        init() {
            if (globalThis.ResizeObserver) {
                observador = new ResizeObserver(entradas => {
                    if (!this.abierto) return;
                    let cambio = false;
                    for (const entrada of entradas) {
                        const caja = Array.isArray(entrada.borderBoxSize) ? entrada.borderBoxSize[0] : entrada.borderBoxSize;
                        const rect = caja ? null : entrada.target.getBoundingClientRect();
                        const tamano = Math.round(caja?.inlineSize ?? rect.width) + ':' + Math.round(caja?.blockSize ?? rect.height);
                        if (tamano === tamanosObservados.get(entrada.target)) continue;
                        tamanosObservados.set(entrada.target, tamano); cambio = true;
                    }
                    if (!cambio || ajustePendiente !== null) return;
                    ajustePendiente = requestAnimationFrame(() => {
                        ajustePendiente = null;
                        if (!this.abierto) return;
                        this.moverModal(this.desplazamientoX, this.desplazamientoY);
                        this.distribuir();
                    });
                });
                // Comparar cada elemento consigo mismo y observar su marco,
                // independiente de la aparición de barras de desplazamiento.
                for (const elemento of [this.$refs.dialogo, this.$refs.ete || this.$refs.lienzo]) {
                    if (elemento) observador.observe(elemento, {box: 'border-box'});
                }
            }
        },
        destroy() { destruido = true; this.cancelarBusqueda(); version++; sesion++; observador?.disconnect(); visor?.destruir(); if (ajustePendiente !== null) cancelAnimationFrame(ajustePendiente); },
        numero(valor) { return Number(valor).toLocaleString('es-EC'); },
        moverModal(x, y) {
            const rect = this.$refs.dialogo.getBoundingClientRect();
            const baseX = rect.left - this.desplazamientoX, baseY = rect.top - this.desplazamientoY;
            this.desplazamientoX = Math.max(8 - baseX, Math.min(innerWidth - rect.width - baseX - 8, x));
            this.desplazamientoY = Math.max(8 - baseY, Math.min(innerHeight - rect.height - baseY - 8, y));
        },
        iniciarArrastre(evento) {
            if (evento.button !== 0 || evento.target.closest('button, input, select, a')) return;
            arrastre = {id: evento.pointerId, x: evento.clientX, y: evento.clientY, inicialX: this.desplazamientoX, inicialY: this.desplazamientoY};
            this.arrastrando = true; evento.currentTarget.setPointerCapture(evento.pointerId);
        },
        arrastrarModal(evento) {
            if (arrastre?.id === evento.pointerId) this.moverModal(arrastre.inicialX + evento.clientX - arrastre.x, arrastre.inicialY + evento.clientY - arrastre.y);
        },
        terminarArrastre() { arrastre = null; this.arrastrando = false; },
        redimensionarModal(ancho, alto, origen = this.$refs.dialogo.getBoundingClientRect(), esquina = 'se') {
            const maxAncho = Math.max(1, innerWidth - 16), maxAlto = Math.max(1, innerHeight - 16);
            const minAncho = Math.min(460, maxAncho), minAlto = Math.min(360, maxAlto);
            const oeste = esquina.includes('w'), norte = esquina.includes('n');
            // El borde o la esquina opuestos permanecen fijos al alcanzar un límite.
            const anclaX = oeste ? Math.max(minAncho + 8, Math.min(innerWidth - 8, origen.left + origen.width)) : Math.max(8, Math.min(innerWidth - minAncho - 8, origen.left));
            const anclaY = norte ? Math.max(minAlto + 8, Math.min(innerHeight - 8, origen.top + origen.height)) : Math.max(8, Math.min(innerHeight - minAlto - 8, origen.top));
            this.anchoVentana = Math.max(minAncho, Math.min(oeste ? anclaX - 8 : innerWidth - anclaX - 8, ancho));
            this.altoVentana = Math.max(minAlto, Math.min(norte ? anclaY - 8 : innerHeight - anclaY - 8, alto));
            const izquierda = oeste ? anclaX - this.anchoVentana : anclaX;
            const arriba = norte ? anclaY - this.altoVentana : anclaY;
            // El centrado CSS excluye la barra vertical de la página.
            const anchoCentrado = globalThis.document?.documentElement?.clientWidth || innerWidth;
            this.desplazamientoX = izquierda - (anchoCentrado - this.anchoVentana) / 2;
            this.desplazamientoY = arriba - (innerHeight - this.altoVentana) / 2;
        },
        iniciarRedimension(evento) {
            if (evento.button !== 0) return;
            redimension = {id: evento.pointerId, x: evento.clientX, y: evento.clientY, esquina: evento.currentTarget.dataset.esquina || 'se', rect: this.$refs.dialogo.getBoundingClientRect()};
            evento.currentTarget.focus({preventScroll: true});
            this.redimensionando = true; evento.currentTarget.setPointerCapture(evento.pointerId);
        },
        redimensionar(evento) {
            if (redimension?.id !== evento.pointerId) return;
            const sx = redimension.esquina.includes('w') ? -1 : redimension.esquina.includes('e') ? 1 : 0;
            const sy = redimension.esquina.includes('n') ? -1 : redimension.esquina.includes('s') ? 1 : 0;
            this.redimensionarModal(redimension.rect.width + (evento.clientX - redimension.x) * sx,
                redimension.rect.height + (evento.clientY - redimension.y) * sy, redimension.rect, redimension.esquina);
        },
        terminarRedimension() { redimension = null; this.redimensionando = false; },
        tecladoRedimension(evento) {
            if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home'].includes(evento.key)) return;
            evento.preventDefault();
            const rect = this.$refs.dialogo.getBoundingClientRect();
            if (evento.key === 'Home') { this.anchoVentana = this.altoVentana = 0; this.desplazamientoX = this.desplazamientoY = 0; return; }
            const esquina = evento.currentTarget?.dataset.esquina || 'se';
            const sx = esquina.includes('w') ? -1 : esquina.includes('e') ? 1 : 0;
            const sy = esquina.includes('n') ? -1 : esquina.includes('s') ? 1 : 0;
            this.redimensionarModal(rect.width + (evento.key === 'ArrowLeft' ? -40 : evento.key === 'ArrowRight' ? 40 : 0) * sx,
                rect.height + (evento.key === 'ArrowUp' ? -40 : evento.key === 'ArrowDown' ? 40 : 0) * sy, rect, esquina);
        },
        alternarLateral() {
            this.cerrarMenu(); this.sugerenciasAbiertas = false;
            this.lateralPlegado = !this.lateralPlegado;
            this.$nextTick(() => this.distribuir());
        },
        tecladoCabecera(evento) {
            if (evento.target !== evento.currentTarget || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home'].includes(evento.key)) return;
            evento.preventDefault();
            if (evento.key === 'Home') return this.moverModal(0, 0);
            this.moverModal(this.desplazamientoX + (evento.key === 'ArrowLeft' ? -12 : evento.key === 'ArrowRight' ? 12 : 0),
                this.desplazamientoY + (evento.key === 'ArrowUp' ? -12 : evento.key === 'ArrowDown' ? 12 : 0));
        },
        ajustarArbol() {
            if (this.$refs.ete && this.modo === 'ramas') {
                this.ajustado = true; visor?.ajustar();
                return;
            }
            this.ajustado = !this.ajustado;
            this.distribuir(null, true);
        },
        async pantallaCompletaOctree() {
            try {await visor?.pantallaCompleta();}
            catch {this.visorError = 'No se pudo activar la pantalla completa. Vuelve a intentarlo.';}
        },
        contextoActual() {
            return JSON.stringify(this.filtrosArbol.map(filtro => filtro.id));
        },
        actualizarPanel() {
            if (this.$refs.ete && !this.nodos.some(n => n.clave === this.ramaActiva)) {
                this.ramaActiva = filosPublicos(this.nodos)[0]?.clave || '';
            }
            const ancho = Math.max(1, (this.$refs.ramas?.clientWidth || 744) - 24), alto = this.$refs.ramas?.clientHeight || 360;
            this.anchoModal = this.$refs.dialogo.clientWidth || 1000;
            const columnas = Math.max(1, Math.floor((ancho + 12) / 202));
            const filas = Math.max(1, Math.floor((alto + 12) / 76));
            this.panel = panelTaxonomico(this.nodos, this.expandidos, this.ramaActiva, this.ramaBusqueda, this.ramaPagina, columnas * filas, this.$refs.ete ? 2 : Infinity);
            this.ramaPagina = this.panel.pagina;
            if (!this.panel.actual) this.ramaActiva = '';
            this.visorDisponible = Boolean(this.panel.actual);
            if (!this.visorDisponible) this.taxonesDibujados = 0;
        },
        async actualizarVisor() {
            const solicitud = ++revisionVisor;
            if (destruido || !this.$refs.ete || !this.abierto || !this.nodos.length || !this.visorDisponible || this.modo !== 'ramas') {
                this.visorCargando = false; return;
            }
            this.visorError = '';
            this.visorCargando = true;
            try {
                if (!visor) {
                    cargandoVisor ??= cargarModuloVisor().then(modulo => destruido ? null : modulo.crearVisorTaxonomico(this.$refs.ete, {
                        seleccionar: nodo => this.navegarEnVisor(nodo),
                        volver: () => this.irARama(this.nodos.find(n => n.clave === this.panel.actual?.padre) || null),
                        ajustar: () => this.ajustarArbol(),
                        menu: (nodo, evento, ancla) => this.abrirMenu(nodo, evento, ancla),
                        error: mensaje => {this.visorError = mensaje;},
                        ajustado: valor => {this.ajustado = valor;},
                        zoom: valor => {this.zoomActual = valor;},
                        limitesZoom: (min, max) => {this.zoomMinimo = min; this.zoomMaximo = max;},
                        completo: (permite, completo) => {this.permiteVistaCompleta = permite; this.arbolCompleto = completo;},
                        dibujado: (total, vista) => {this.taxonesDibujados = total; this.tipoDibujo = vista;},
                    }));
                    try {visor = await cargandoVisor;}
                    catch {if (solicitud === revisionVisor) this.visorError = 'No se pudo abrir el visor. Vuelve a intentarlo.'; cargandoVisor = null;}
                }
                if (!visor || solicitud !== revisionVisor || !this.abierto || this.modo !== 'ramas') return;
                await visor.actualizar({nodos: this.nodos, expandidos: this.expandidos, rama: this.ramaActiva, navegacion: revisionNavegacion,
                    seleccion: this.nodos.find(nodo => nodo.id === this.seleccionado)?.clave || '',
                    taxon: this.filtrosArbol.at(-1)?.id || '', vista: this.vistaETE, escala: this.escalaETE,
                    revision: this.revisionETE, endpoint: this.$refs.dialogo.dataset.eteEndpoint});
            } catch (error) {
                console.error('Visor taxonómico:', error);
                if (solicitud === revisionVisor) this.visorError = 'No se pudo dibujar esta rama. Vuelve a intentarlo.';
            } finally { if (solicitud === revisionVisor) this.visorCargando = false; }
        },
        cambiarVistaETE() { this.cerrarMenu(); this.actualizarVisor(); },
        zoomETE(paso) { this.ajustado = false; visor?.zoom(paso); },
        async alternarArbolCompleto() {
            try { await visor?.alternarCompleto(); }
            catch (error) { console.error('Visor taxonómico:', error); this.visorError = 'No se pudo expandir el árbol. Vuelve a intentarlo.'; }
        },
        async navegarEnVisor(nodo) {
            if (this.buscando || this.aplicando || this.mostrandoMapa) return;
            this.seleccionado = nodo.id;
            await this.irARama(nodo);
        },
        async seleccionarEnVisor(nodo) {
            if (this.buscando || this.aplicando || this.mostrandoMapa) return;
            revisionNavegacion++;
            this.seleccionado = nodo.id;
            // La primera apertura de un filo lo sitúa al principio del panel.
            if (!this.ramaActiva && nodo.padre === null) this.ramaActiva = nodo.clave;
            await this.alternar(nodo);
            // Pulsar de nuevo una hoja también recupera el encuadre, aunque
            // no cambien la selección ni los nodos del dibujo almacenado.
            if (!nodo.tieneHijos) this.ajustarArbol();
            this.distribuir();
        },
        cambiarRepresentacion() {
            this.modo = this.modo === 'ramas' ? 'diagrama' : 'ramas'; this.cerrarMenu();
            this.$nextTick(() => this.distribuir());
        },
        buscarHijos() { this.ramaPagina = 1; this.actualizarPanel(); },
        paginarRama(paso) {
            this.ramaPagina = Math.max(1, Math.min(this.panel.paginas, this.panel.pagina + paso));
            this.actualizarPanel(); this.$refs.ramas.scrollTop = 0;
        },
        async irARama(nodo = null) {
            if (this.buscando || this.mostrandoMapa) return;
            revisionNavegacion++;
            if (this.ramaActiva !== (nodo?.clave || '')) this.ajustado = true;
            this.ramaActiva = nodo?.clave || ''; this.ramaBusqueda = ''; this.ramaPagina = 1;
            if (nodo?.tieneHijos && !this.expandidos.includes(nodo.clave)) await this.alternar(nodo);
            this.distribuir(); if (this.$refs.ramas) this.$refs.ramas.scrollTop = 0;
        },
        async navegarRama(nodo) {
            if (this.buscando || this.mostrandoMapa || ramasCargando.has(nodo.clave)) return;
            if (this.ramaActiva === nodo.clave && this.expandidos.includes(nodo.clave)) {
                await this.alternar(nodo); return this.irARama(this.nodos.find(n => n.clave === nodo.padre) || null);
            }
            this.seleccionado = nodo.id; await this.irARama(nodo);
        },
        async alternarRama(nodo) {
            if (!nodo.tieneHijos || this.buscando || this.mostrandoMapa || ramasCargando.has(nodo.clave)) return;
            const cerrar = this.expandidos.includes(nodo.clave);
            await this.alternar(nodo);
            if (cerrar && this.panel.ruta.some(n => n.clave === nodo.clave)) return this.irARama(this.nodos.find(n => n.clave === nodo.padre) || null);
            if (!cerrar) return this.irARama(nodo);
            this.actualizarPanel();
        },
        tecladoPanel(evento, nodo) {
            if (evento.key === 'ContextMenu' || (evento.key === 'F10' && evento.shiftKey)) {
                evento.preventDefault(); this.abrirMenu(nodo, evento); return;
            }
            if (evento.key === 'ArrowRight') { evento.preventDefault(); return this.irARama(nodo); }
            if (evento.key === 'ArrowLeft') { evento.preventDefault(); return this.irARama(this.nodos.find(n => n.clave === nodo.padre) || null); }
            const lista = evento.currentTarget.closest('.taxonomy-navigator-tree') ? this.panel.filas.filter(n => !n.mas) : this.panel.hijos;
            const indice = lista.findIndex(n => n.clave === nodo.clave);
            let destino;
            if (evento.key === 'ArrowDown') destino = lista[indice + 1];
            else if (evento.key === 'ArrowUp') destino = lista[indice - 1];
            else if (evento.key === 'Home') destino = lista[0];
            else if (evento.key === 'End') destino = lista.at(-1);
            else return;
            evento.preventDefault(); if (destino) this.enfocarNodo(destino.id);
        },
        integrarPrecarga(datos) {
            const conocidos = new Map(this.nodos.map(nodo => [nodo.clave, nodo]));
            const cargados = new Set(this.cargados);
            for (const [clave, hijos] of Object.entries(datos.precarga || {})) {
                for (const hijo of hijos) conocidos.set(hijo.clave, hijo);
                cargados.add(clave);
            }
            this.nodos = [...conocidos.values()]; this.cargados = [...cargados];
        },
        clavesConservadas() {
            const seleccionado = this.nodos.find(nodo => nodo.id === this.seleccionado);
            return [...new Set([...this.expandidos, ...(this.ramaActiva ? [this.ramaActiva] : []), ...(seleccionado ? [seleccionado.clave] : [])])];
        },
        async pedir(padre = null, busqueda = '', conservar = [], revalidar = false) {
            const endpoint = this.$refs.dialogo.dataset?.endpoint;
            if (!endpoint) return this.$wire.consultarExploradorTaxonomico(padre, busqueda, ...(conservar.length ? [conservar] : []));
            const url = new URL(endpoint, window.location.origin);
            if (padre) url.searchParams.set('padre', padre);
            if (busqueda) url.searchParams.set('busqueda', busqueda);
            if (this.$refs.ete && !padre && !busqueda) url.searchParams.set('completo', '1');
            const taxon = busqueda && this.$refs.ete && this.modo === 'ramas'
                ? this.filtrosVisor.at(-1)?.id : this.filtrosArbol.at(-1)?.id;
            if (taxon) url.searchParams.set('taxon', taxon);
            conservar.forEach(clave => url.searchParams.append('conservar[]', clave));
            const respuesta = await fetch(url, {headers: {Accept: 'application/json', ...(revalidar ? {'X-Portal-Revalidate': '1'} : {})}});
            if (!respuesta.ok) throw new Error('Consulta pública no disponible');
            const datos = await respuesta.json();
            datos.revision = respuesta.headers.get('X-Portal-Revision');
            return datos;
        },
        restaurarPosicion() {
            if (this.$refs.ete && this.modo === 'ramas') { visor?.ajustar(); return; }
            const lienzo = this.$refs.ete || this.$refs.lienzo;
            lienzo.scrollLeft = posicionGuardada.x;
            lienzo.scrollTop = posicionGuardada.y;
            if (this.$refs.cuerpo) this.$refs.cuerpo.scrollTop = posicionGuardada.cuerpo;
        },
        cancelarBusqueda() { if (temporizador !== null) reloj.clearTimeout(temporizador); temporizador = null; },
        async abrir(origen) {
            if (this.abierto) return;
            invocador = origen ?? document.activeElement;
            sesion++; version++;
            this.cancelarBusqueda();
            const actual = this.contextoActual();
            const conservar = this.listo && contexto === actual;
            if (contexto !== actual) {
                this.filtrosVisor = [];
                this.nodos = []; this.expandidos = []; this.cargados = []; this.listo = false;
                this.ramaActiva = ''; this.ramaBusqueda = ''; this.ramaPagina = 1;
                this.ajustado = false; this.escala = 1;
                posicionGuardada = {x: 0, y: 0, cuerpo: 0};
            }
            contexto = actual;
            ramasCargando = new Set(); this.cargandoRamas = [];
            this.error = ''; this.destacado = ''; this.abierto = true; restaurarFoco = true;
            this.$refs.dialogo.showModal();
            this.$nextTick(() => {
                visor?.ajustar(); this.distribuir();
                (this.lateralPlegado ? this.$refs.plegarLateral : this.$refs.busqueda)?.focus({preventScroll: true});
                if (conservar) this.$nextTick(() => this.restaurarPosicion());
            });
            await this.consultar(conservar);
        },
        cerrar(devolverFoco = true) {
            const completo = globalThis.document?.fullscreenElement;
            if (completo && this.$refs.dialogo.contains?.(completo)) document.exitFullscreen().catch(() => {});
            this.cerrarMenu(); this.sugerenciasAbiertas = false;
            const lienzo = this.$refs.ete || this.$refs.lienzo;
            posicionGuardada = {x: lienzo.scrollLeft || 0, y: lienzo.scrollTop || 0, cuerpo: this.$refs.cuerpo?.scrollTop || 0};
            restaurarFoco = devolverFoco;
            this.$refs.dialogo.close();
        },
        alCerrar() {
            this.terminarArrastre(); this.terminarRedimension();
            this.abierto = false; sesion++; version++;
            revisionVisor++; this.visorCargando = false;
            visor?.cancelarLocalizacion?.();
            this.cancelarBusqueda(); pendiente = null;
            this.buscando = false; this.mostrandoMapa = false; this.destacado = '';
            ramasCargando = new Set(); this.cargandoRamas = [];
            if (restaurarFoco) invocador?.focus?.({preventScroll: true}); invocador = null;
        },
        buscar() {
            this.cancelarBusqueda(); version++;
            this.sugerencias = []; this.sugerenciaActiva = -1; this.sugerenciasAbiertas = false;
            if (this.busqueda.trim().length < 2) { this.buscando = false; return; }
            this.buscando = true; this.error = '';
            temporizador = reloj.setTimeout(() => { temporizador = null; this.predecir(); }, 300);
        },
        async predecir() {
            const solicitud = ++version;
            this.buscando = true;
            try {
                const datos = await this.pedir(null, this.busqueda.trim());
                if (solicitud !== version || !this.abierto) return;
                const unicos = new Map(datos.nodos.filter(nodo => nodo.coincide).map(nodo => [nodo.id, nodo]));
                const rango = visor?.rangoActivo?.();
                const normalizar = texto => String(texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
                this.sugerencias = [...unicos.values()].sort((a, b) => rango
                    ? Number(normalizar(b.etiqueta) === rango) - Number(normalizar(a.etiqueta) === rango) : 0);
                this.sugerenciasAbiertas = this.sugerencias.length > 0;
                this.hayMas = datos.hayMas;
            } catch { if (solicitud === version && this.abierto) this.error = 'No se pudieron cargar las sugerencias. Vuelve a intentarlo.'; }
            finally { if (solicitud === version) this.buscando = false; }
        },
        async filtrarArbol(nodo) {
            if (this.$refs.ete && this.modo === 'ramas') return this.localizarEnVisor(nodo);
            this.cancelarBusqueda(); version++;
            this.ajustado = true;
            const anterior = this.filtrosArbol.at(-1);
            if (anterior && !String(nodo.linaje || '').split(' → ').includes(anterior.nombre)) this.filtrosArbol = [];
            if (!this.filtrosArbol.some(filtro => filtro.id === nodo.id)) this.filtrosArbol = [...this.filtrosArbol, {id: nodo.id, nombre: nodo.nombre}];
            this.busqueda = ''; this.sugerenciasAbiertas = false; this.sugerencias = []; this.seleccionado = nodo.id;
            this.nodos = []; this.expandidos = []; this.cargados = []; contexto = this.contextoActual();
            this.ramaActiva = ''; this.ramaBusqueda = ''; this.ramaPagina = 1;
            await this.consultar(); this.$refs.busqueda.focus({preventScroll: true});
        },
        async localizarEnVisor(nodo) {
            this.cancelarBusqueda(); const solicitud = ++version;
            this.buscando = false; this.error = ''; this.sugerenciasAbiertas = false;
            const encontrado = this.nodos.find(item => item.id === nodo.id && item.clave === nodo.clave);
            if (!encontrado) {this.error = 'Este taxón ya no está en el catálogo cargado. Actualiza el explorador.'; return;}
            try {
                const porClave = new Map(this.nodos.map(item => [item.clave, item])), vistos = new Set(); let filo = encontrado;
                while (filo.padre && porClave.has(filo.padre) && !vistos.has(filo.clave)) {
                    vistos.add(filo.clave); filo = porClave.get(filo.padre);
                }
                if (filo.clave !== this.filoActual) {
                    this.ramaActiva = filo.clave; this.actualizarPanel(); await this.actualizarVisor();
                }
                if (!visor || this.visorCargando) await this.actualizarVisor();
                if (solicitud !== version || !this.abierto || !visor) return;
                const ubicado = await visor.localizar(encontrado.clave);
                if (solicitud !== version || !this.abierto) return;
                if (!ubicado) {this.error = 'No se pudo localizar este taxón. Vuelve a intentarlo.'; return;}
                const anterior = this.filtrosVisor.at(-1);
                if (anterior && anterior.clave !== encontrado.clave && !vistos.has(anterior.clave)) this.filtrosVisor = [];
                if (!this.filtrosVisor.some(filtro => filtro.clave === encontrado.clave))
                    this.filtrosVisor = [...this.filtrosVisor, {id: encontrado.id, clave: encontrado.clave, nombre: encontrado.nombre}];
                this.seleccionado = encontrado.id; this.busqueda = ''; this.sugerencias = []; this.sugerenciaActiva = -1;
                this.$refs.busqueda.focus({preventScroll: true});
            } catch (error) {
                console.error('Búsqueda en el visor:', error);
                if (solicitud === version && this.abierto) this.error = 'No se pudo localizar este taxón. Vuelve a intentarlo.';
            }
        },
        async quitarFiltroVisor(indice) {
            const retirado = this.filtrosVisor[indice];
            if (!retirado) return;
            const nodo = this.nodos.find(item => item.id === retirado.id && item.clave === retirado.clave);
            // Alpine retira el botón x-for al borrar la etiqueta; conserva el campo
            // antes de que su ámbito desconectado deje de resolver $refs.
            const campoBusqueda = this.$refs.busqueda;
            this.cancelarBusqueda(); const solicitud = ++version;
            visor?.cancelarLocalizacion?.();
            this.filtrosVisor = this.filtrosVisor.slice(0, indice); this.sugerencias = []; this.sugerenciasAbiertas = false; this.sugerenciaActiva = -1;
            this.buscando = false; this.error = '';
            const ultimo = this.filtrosVisor.at(-1);
            // Las etiquetas delimitan la búsqueda; la raíz vuelve al padre publicado
            // del taxón retirado, aunque ese padre no tenga una etiqueta propia.
            const destino = this.nodos.find(item => item.clave === nodo?.padre)
                || this.nodos.find(item => item.clave === (ultimo?.clave || this.filoActual));
            this.seleccionado = '';
            try {
                if (!visor || this.visorCargando) await this.actualizarVisor();
                if (solicitud !== version || !this.abierto || !visor) return;
                const ubicado = destino && await visor.localizar(destino.clave);
                if (solicitud !== version || !this.abierto) return;
                if (ubicado) this.seleccionado = destino.id;
                else this.error = 'No se pudo restaurar la selección. Vuelve a intentarlo.';
            } catch (error) {
                console.error('Restaurar filtro del visor:', error);
                if (solicitud === version && this.abierto) this.error = 'No se pudo restaurar la selección. Vuelve a intentarlo.';
            }
            if (solicitud !== version || !this.abierto) return;
            campoBusqueda?.focus({preventScroll: true});
            if (this.busqueda.trim().length >= 2) this.buscar();
        },
        async quitarFiltroArbol(indice) {
            this.ajustado = true;
            this.filtrosArbol = this.filtrosArbol.slice(0, indice);
            this.seleccionado = this.filtrosArbol.at(-1)?.id || ''; this.busqueda = '';
            this.ramaActiva = ''; this.ramaBusqueda = ''; this.ramaPagina = 1;
            this.nodos = []; this.expandidos = []; this.cargados = []; contexto = this.contextoActual();
            await this.consultar(); this.$refs.busqueda.focus({preventScroll: true});
        },
        moverSugerencia(paso) {
            if (!this.sugerencias.length) return;
            this.sugerenciasAbiertas = true; this.sugerenciaActiva = (this.sugerenciaActiva + paso + this.sugerencias.length) % this.sugerencias.length;
        },
        tecladoBusqueda(evento) {
            if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') { evento.preventDefault(); this.moverSugerencia(evento.key === 'ArrowDown' ? 1 : -1); }
            else if (evento.key === 'Escape' && this.sugerenciasAbiertas) { evento.preventDefault(); evento.stopPropagation(); this.sugerenciasAbiertas = false; }
            else this.confirmarConTeclado(evento);
        },
        enfocarNodo(id) {
            this.$nextTick(() => {
                const nodo = this.nodos.find(item => item.id === id);
                const zona = this.modo === 'ramas' ? '.taxonomy-navigator' : '.taxonomy-explorer-board';
                const boton = this.modo === 'ramas' ? '.taxonomy-navigator-name' : '.taxonomy-explorer-name';
                const destino = nodo && this.$refs.dialogo.querySelector(`${zona} [data-taxon-clave="${nodo.clave}"] ${boton}`);
                if (!destino && nodo && this.visorDisponible) visor?.enfocar(nodo.clave);
                if (destino) destino.focus({preventScroll: true});
                else if (!nodo || !this.visorDisponible) this.$refs.busqueda?.focus({preventScroll: true});
            });
        },
        escapar() {
            const completo = globalThis.document?.fullscreenElement;
            if (completo && this.$refs.dialogo.contains?.(completo)) {document.exitFullscreen().catch(() => {}); return;}
            if (this.menuNodo) { const id = this.menuNodo.id; this.cerrarMenu(); this.enfocarNodo(id); }
            else if (this.sugerenciasAbiertas) this.sugerenciasAbiertas = false;
            else this.cerrar();
        },
        tecladoMenu(evento) {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(evento.key)) return;
            evento.preventDefault(); evento.stopPropagation();
            const botones = [...this.$refs.menu.querySelectorAll('button:not(:disabled)')];
            const indice = botones.indexOf(document.activeElement);
            const siguiente = evento.key === 'Home' ? 0 : evento.key === 'End' ? botones.length - 1 : (indice + (evento.key === 'ArrowDown' ? 1 : -1) + botones.length) % botones.length;
            botones[siguiente]?.focus();
        },
        async consultar(conservar = false, revalidar = false) {
            this.cancelarBusqueda();
            const solicitud = ++version;
            const revision = revisionSeleccion;
            const navegacion = revisionNavegacion;
            this.buscando = true; this.error = '';
            try {
                const datos = await this.pedir(null, '', conservar ? this.clavesConservadas() : [], revalidar);
                if (solicitud !== version || !this.abierto) return;
                if (datos.revision) this.revisionETE = datos.revision;
                else if (revalidar) this.revisionETE++;
                this.nodos = datos.nodos;
                this.filtrosVisor = this.filtrosVisor.filter(filtro => this.nodos.some(nodo => nodo.id === filtro.id && nodo.clave === filtro.clave));
                if (!this.filtrosArbol.length || !this.filos.length) this.filos = filosPublicos(this.nodos);
                const vigentes = new Set(this.nodos.map(nodo => nodo.clave));
                this.expandidos = conservar ? this.expandidos.filter(clave => vigentes.has(clave)) : datos.expandidos;
                this.cargados = conservar ? this.cargados.filter(clave => vigentes.has(clave)) : [];
                this.integrarPrecarga(datos);
                if (datos.completo) this.cargados = this.nodos.filter(nodo => nodo.tieneHijos).map(nodo => nodo.clave);
                if (!conservar && navegacion === revisionNavegacion) this.ramaActiva = this.nodos.find(nodo => nodo.id === datos.seleccionado)?.clave || '';
                ramasCargando = new Set(); this.cargandoRamas = [];
                if (revision === revisionSeleccion && !this.aplicando) {
                    this.total = datos.total;
                    if (!conservar && navegacion === revisionNavegacion) this.seleccionado = datos.seleccionado;
                    else if (!this.nodos.some(nodo => nodo.id === this.seleccionado)) this.seleccionado = '';
                }
                this.listo = true;
                ultimaBusqueda = this.busqueda.trim();
                this.hayMas = datos.hayMas; this.distribuir(null, !conservar);
                if (conservar) this.$nextTick(() => this.restaurarPosicion());
            } catch {
                if (solicitud === version && this.abierto) this.error = 'No se pudo consultar la taxonomía. Vuelve a intentarlo.';
            } finally { if (solicitud === version) this.buscando = false; }
        },
        async alternar(nodo) {
            if (!nodo.tieneHijos || this.buscando || this.mostrandoMapa || ramasCargando.has(nodo.clave)) return;
            const esRaiz = nodo.padre === null;
            if (esRaiz) this.raizActiva = nodo.clave;
            const posicion = this.bosque.nodos.find(item => item.clave === nodo.clave);
            const ancla = posicion ? {clave: nodo.clave, x: posicion.x, y: posicion.y, desplazamiento: this.$refs.lienzo.scrollLeft || 0, vertical: this.$refs.lienzo.scrollTop || 0} : null;
            if (this.expandidos.includes(nodo.clave)) {
                this.expandidos = this.expandidos.filter(clave => clave !== nodo.clave);
                this.distribuir(ancla); return;
            }
            const solicitud = version;
            if (!this.cargados.includes(nodo.clave)) {
                ramasCargando.add(nodo.clave);
                this.cargandoRamas = [...ramasCargando];
                this.error = '';
                try {
                    const datos = await this.pedir(nodo.clave);
                    if (solicitud !== version || !this.abierto) return;
                    const conocidos = new Map(this.nodos.map(item => [item.clave, item]));
                    for (const hijo of datos.nodos) conocidos.set(hijo.clave, conocidos.get(hijo.clave) ?? hijo);
                    this.nodos = [...conocidos.values()];
                    this.cargados = [...this.cargados, nodo.clave];
                    this.integrarPrecarga(datos);
                } catch {
                    if (solicitud === version && this.abierto) this.error = `No se pudo cargar la rama ${nodo.nombre}. Pulsa su flecha para reintentar.`;
                    return;
                } finally {
                    if (solicitud === version) { ramasCargando.delete(nodo.clave); this.cargandoRamas = [...ramasCargando]; }
                }
            }
            this.expandidos = [...this.expandidos, nodo.clave]; this.distribuir(esRaiz ? null : ancla, esRaiz);
        },
        distribuir(ancla = null, mostrarResultado = false) {
            this.actualizarPanel();
            if (this.modo === 'ramas' && this.$refs.ete) { this.$nextTick(() => this.actualizarVisor()); return; }
            if (this.modo === 'ramas' && this.$refs.ramas) return;
            this.ancho = this.$refs?.lienzo?.clientWidth || this.ancho;
            this.bosque = distribuirBosque(this.nodos, this.expandidos, this.ancho, this.raizActiva);
            this.escala = this.ajustado ? escalaBosque(this.bosque, this.ancho, this.$refs.lienzo.clientHeight || 300) : 1;
            this.$nextTick(() => {
                const svg = this.$refs.conexiones;
                if (svg) {
                    const paths = this.bosque.conexiones.map(({d, color, transform}) => {
                        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        path.setAttribute('d', d); path.setAttribute('stroke', color); if (transform) path.setAttribute('transform', transform); return path;
                    });
                    svg.replaceChildren(...paths);
                }
                const lienzo = this.$refs.lienzo;
                if (this.ajustado) {
                    lienzo.scrollLeft = 0; lienzo.scrollTop = 0;
                } else if (ancla) {
                    const posicion = this.bosque.nodos.find(item => item.clave === ancla.clave);
                    if (posicion) { lienzo.scrollLeft = Math.max(0, ancla.desplazamiento + posicion.x - ancla.x); lienzo.scrollTop = Math.max(0, ancla.vertical + posicion.y - ancla.y); }
                } else if (mostrarResultado) {
                    lienzo.scrollLeft = 0; lienzo.scrollTop = 0;
                }
            });
        },
        seleccionar(nodo, cerrar = false) {
            if (!cerrar) { this.seleccionado = nodo.id; return this.alternar(nodo); }
            return this.verEnMapa(nodo);
        },
        seleccionarMapa(nodo, cerrar = false) {
            if (this.buscando || this.mostrandoMapa) return;
            // El nombre selecciona y abre; volver a pulsarlo nunca contrae la rama.
            const apertura = !cerrar && nodo.tieneHijos && !this.expandidos.includes(nodo.clave) ? this.alternar(nodo) : null;
            this.error = ''; this.destacado = nodo.id; revisionSeleccion++;
            if (pendiente?.id === nodo.id && pendiente.sesion === sesion) {
                pendiente.cerrar ||= cerrar; return apertura;
            }
            if (!procesando && this.seleccionado === nodo.id) {
                if (cerrar) this.finalizarSeleccion(nodo.hoja);
                return apertura;
            }
            pendiente = {id: nodo.id, cerrar, sesion};
            const seleccion = this.procesarSeleccion();
            return apertura ? Promise.all([seleccion, apertura]) : seleccion;
        },
        confirmarConTeclado(evento) {
            if (evento.key !== 'Enter' || evento.isComposing || evento.keyCode === 229) return;
            evento.preventDefault();
            if (evento.repeat) return;
            return this.confirmar();
        },
        async confirmar() {
            this.cancelarBusqueda();
            if (!this.busqueda.trim() && this.$refs.ete && this.modo === 'ramas') {
                const filtro = this.filtrosVisor.at(-1), nodo = filtro && this.nodos.find(n => n.clave === filtro.clave);
                if (nodo) return this.localizarEnVisor(nodo);
                return;
            }
            if (this.buscando || !this.sugerencias.length) await this.predecir();
            const nodo = this.sugerencias[this.sugerenciaActiva >= 0 ? this.sugerenciaActiva : 0];
            if (nodo) return this.filtrarArbol(nodo);
        },
        confirmarMapa() {
            this.cancelarBusqueda(); version++; this.buscando = false;
            ramasCargando = new Set(); this.cargandoRamas = [];
            revisionSeleccion++;
            pendiente = {nombre: this.busqueda.trim(), cerrar: true, sesion};
            return this.procesarSeleccion();
        },
        async procesarSeleccion() {
            if (procesando) return;
            procesando = true; this.aplicando = true;
            try {
                while (pendiente) {
                    const accion = pendiente;
                    let datos;
                    try {
                        const aplicar = this.$wire.verTaxonExploradorEnMapa || this.$wire.seleccionarTaxonExplorador;
                        datos = accion.id ? await aplicar.call(this.$wire, accion.id)
                            : await this.$wire.confirmarTaxonExplorador(accion.nombre);
                    } catch { datos = {error: 'No se pudo actualizar el mapa. Selecciona el taxón para reintentar.'}; }
                    if (!datos || (!datos.error && typeof datos.seleccionado !== 'string')) datos = {error: 'No se pudo actualizar el mapa. Selecciona el taxón para reintentar.'};
                    if (accion.sesion === sesion && this.abierto) {
                        if (datos.error) this.error = datos.error;
                        else { this.seleccionado = datos.seleccionado; this.error = ''; }
                    }
                    if (pendiente === accion) {
                        pendiente = null;
                        this.destacado = '';
                        if (!datos.error && accion.sesion === sesion && this.abierto && accion.cerrar) this.finalizarSeleccion(accion.mapa || datos.hoja, !!accion.mapa);
                    }
                }
            } finally { procesando = false; this.aplicando = false; }
        },
        async verEnMapa(nodo = null) {
            if (!this.listo || this.aplicando || this.mostrandoMapa) return;
            this.cerrarMenu();
            if (nodo) { pendiente = {id: nodo.id, cerrar: true, sesion, mapa: true}; revisionSeleccion++; return this.procesarSeleccion(); }
            const actual = sesion, revision = revisionSeleccion;
            this.mostrandoMapa = true; this.error = '';
            try {
                if (this.$wire.vista !== 'mapa') await this.$wire.cambiarVista('mapa');
                if (actual === sesion && revision === revisionSeleccion && this.abierto) this.finalizarSeleccion(true, true);
            } catch {
                if (actual === sesion && this.abierto) this.error = 'No se pudo abrir el mapa. Vuelve a intentarlo.';
            } finally { if (actual === sesion) this.mostrandoMapa = false; }
        },
        finalizarSeleccion(hoja, mostrarMapa = false) {
            const actual = sesion;
            const revision = revisionSeleccion;
            this.$nextTick(() => {
                if (actual !== sesion || revision !== revisionSeleccion || !this.abierto) return;
                this.cerrar(!mostrarMapa);
                if (hoja) window.dispatchEvent(new CustomEvent('encuadrar-taxonomia', {detail: {mostrar: mostrarMapa}}));
            });
        },
        abrirMenu(nodo, evento, ancla = null) {
            evento.preventDefault?.();
            if (!nodo || !this.abierto) return;
            const origen = ancla || evento.currentTarget?.getBoundingClientRect();
            if (!origen) return;
            const desdeMouse = evento.type === 'contextmenu' && Number.isFinite(evento.clientX) && Number.isFinite(evento.clientY);
            const x = desdeMouse ? evento.clientX : origen.left;
            const y = desdeMouse ? evento.clientY : origen.bottom;
            this.menuNodo = nodo;
            const actual = sesion;
            // x-show revela el menú en el siguiente frame. Medir antes devuelve
            // ancho/alto cero y evita tanto el cambio de lado como el enfoque.
            this.$nextTick(() => requestAnimationFrame(() => {
                if (!this.abierto || actual !== sesion || this.menuNodo?.id !== nodo.id) return;
                const dialogo = this.$refs.dialogo, caja = dialogo.getBoundingClientRect(), menu = this.$refs.menu;
                if (!menu) return;
                const vista = ancla?.limite || caja, tamano = menu.getBoundingClientRect();
                const limite = {left: Math.max(vista.left, caja.left), top: Math.max(vista.top, caja.top),
                    right: Math.min(vista.right, caja.right), bottom: Math.min(vista.bottom, caja.bottom)};
                const posicion = posicionMenuTaxonomico(ancla || {left: x, right: x, top: y, bottom: y + tamano.height}, tamano, limite);
                menu.style.left = (posicion.x - caja.left - dialogo.clientLeft) + 'px';
                menu.style.top = (posicion.y - caja.top - dialogo.clientTop) + 'px';
                menu.querySelector('button')?.focus();
            }));
        },
        cerrarMenu() { this.menuNodo = null; },
        verRegistros(nodo) {
            if (!nodo) return;
            this.seleccionado = nodo.id;
            this.cerrarMenu();
            this.$dispatch('abrir-registros-taxon', {id: nodo.id});
        },
        teclado(evento, nodo) {
            if (evento.key === 'ContextMenu' || (evento.key === 'F10' && evento.shiftKey)) { this.abrirMenu(nodo, evento); return; }
            const orden = this.bosque.nodos;
            const indice = orden.findIndex(item => item.clave === nodo.clave);
            let destino = null;
            if (evento.key === 'ArrowRight') {
                if (nodo.tieneHijos && !this.expandidos.includes(nodo.clave)) this.alternar(nodo);
                else destino = orden.find(item => item.padre === nodo.clave);
            } else if (evento.key === 'ArrowLeft') {
                if (this.expandidos.includes(nodo.clave)) this.alternar(nodo);
                else destino = orden.find(item => item.clave === nodo.padre);
            } else if (evento.key === 'ArrowDown') destino = orden[indice + 1];
            else if (evento.key === 'ArrowUp') destino = orden[indice - 1];
            else if (evento.key === 'Home') destino = orden[0];
            else if (evento.key === 'End') destino = orden.at(-1);
            else if (evento.key === 'Enter' && evento.shiftKey) this.seleccionar(nodo, true);
            else return;
            evento.preventDefault();
            if (destino) this.$refs.dialogo.querySelector(`[data-taxon-clave="${destino.clave}"] .taxonomy-explorer-name`)?.focus();
        },
    };
}
