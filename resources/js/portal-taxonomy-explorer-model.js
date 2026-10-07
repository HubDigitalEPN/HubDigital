/** Bosque público: cada filo tiene su propia raíz, sin un nodo artificial común. */
export function distribuirBosque(nodos, expandidos = [], anchoDisponible = 720) {
    const abiertos = new Set(expandidos);
    const hijos = new Map();
    for (const nodo of nodos) {
        const clave = nodo.padre ?? '';
        if (!hijos.has(clave)) hijos.set(clave, []);
        hijos.get(clave).push(nodo);
    }
    for (const grupo of hijos.values()) grupo.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es', {numeric: true, sensitivity: 'base'}) || a.clave.localeCompare(b.clave));
    const raices = hijos.get('') ?? [];
    const separacion = 32, pasoVertical = 150, margen = 24;
    const anchoNodo = Math.max(148, Math.min(188, Math.floor((anchoDisponible - margen * 2 - Math.max(0, raices.length - 1) * separacion) / Math.max(1, raices.length))));
    const ramas = nodo => abiertos.has(nodo.clave) ? (hijos.get(nodo.clave) ?? []) : [];
    const medir = nodo => Math.max(1, ramas(nodo).reduce((total, hijo) => total + medir(hijo), 0));
    const unidades = raices.reduce((total, nodo) => total + medir(nodo), 0);
    const ancho = Math.max(anchoDisponible, unidades * (anchoNodo + separacion) - separacion + margen * 2);
    const posiciones = [], conexiones = [];
    let profundidadMaxima = 0;
    const colocar = (nodo, inicio, amplitud, profundidad, color) => {
        const x = inicio + amplitud / 2 - anchoNodo / 2;
        const y = margen + profundidad * pasoVertical;
        const altoNodo = profundidad === 0 ? 116 : 104;
        const posicion = {...nodo, x, y, ancho: anchoNodo, alto: altoNodo, profundidad, color};
        posiciones.push(posicion);
        profundidadMaxima = Math.max(profundidadMaxima, profundidad);
        let siguiente = inicio;
        for (const hijo of ramas(nodo)) {
            const espacio = medir(hijo) * (anchoNodo + separacion);
            const destino = colocar(hijo, siguiente, espacio, profundidad + 1, color);
            const origenX = x + anchoNodo / 2, destinoX = destino.x + anchoNodo / 2;
            const origenY = y + altoNodo, destinoY = destino.y, mitad = origenY + (destinoY - origenY) / 2;
            conexiones.push({d: `M ${origenX} ${origenY} V ${mitad} H ${destinoX} V ${destinoY}`, color});
            siguiente += espacio;
        }
        return posicion;
    };
    let inicio = (ancho - unidades * (anchoNodo + separacion)) / 2;
    for (const raiz of raices) {
        const espacio = medir(raiz) * (anchoNodo + separacion);
        colocar(raiz, inicio, espacio, 0, colorRaiz(raiz.nombre));
        inicio += espacio;
    }
    return {ancho, alto: Math.max(228, margen * 2 + 116 + profundidadMaxima * pasoVertical), nodos: posiciones, conexiones};
}

function colorRaiz(nombre) {
    const conocidos = {Arthropoda: '#17699b', Mollusca: '#d17d28', Annelida: '#568c59', Nematomorpha: '#b94e6b', Nematoda: '#8c62a5'};
    if (conocidos[nombre]) return conocidos[nombre];
    const paleta = ['#17699b', '#3f8d90', '#8c62a5', '#568c59', '#b94e6b'];
    const huella = [...nombre].reduce((valor, letra) => (valor * 31 + letra.codePointAt(0)) >>> 0, 0);
    return paleta[huella % paleta.length];
}

export function crearExploradorTaxonomico(reloj = {
    // Los temporizadores nativos del navegador deben conservar su contexto global.
    setTimeout: (tarea, retraso) => globalThis.setTimeout(tarea, retraso),
    clearTimeout: id => globalThis.clearTimeout(id),
}) {
    let temporizador = null, version = 0, sesion = 0, observador = null, invocador = null;
    let pendiente = null, procesando = false, revisionSeleccion = 0;
    let ramasCargando = new Set();
    return {
        abierto: false, busqueda: '', nodos: [], expandidos: [], cargados: [], cargandoRamas: [],
        seleccionado: '', destacado: '', total: 0, listo: false, buscando: false, aplicando: false, hayMas: false, error: '',
        ancho: 720, bosque: {ancho: 720, alto: 180, nodos: [], conexiones: []},
        init() {
            if (globalThis.ResizeObserver) {
                observador = new ResizeObserver(() => this.distribuir());
                observador.observe(this.$refs.lienzo);
            }
        },
        destroy() { this.cancelarBusqueda(); version++; sesion++; observador?.disconnect(); },
        numero(valor) { return Number(valor).toLocaleString('es-EC'); },
        get cantidadFilos() { return this.nodos.filter(nodo => nodo.padre === null).length; },
        cancelarBusqueda() { if (temporizador !== null) reloj.clearTimeout(temporizador); temporizador = null; },
        async abrir(origen) {
            invocador = origen ?? document.activeElement;
            sesion++; version++;
            this.cancelarBusqueda();
            this.busqueda = ''; this.nodos = []; this.expandidos = []; this.cargados = [];
            ramasCargando = new Set(); this.cargandoRamas = [];
            this.error = ''; this.hayMas = false; this.listo = false; this.destacado = ''; this.abierto = true;
            this.$refs.dialogo.showModal();
            this.$nextTick(() => { this.distribuir(); this.$refs.busqueda.focus(); });
            await this.consultar();
        },
        cerrar() { this.$refs.dialogo.close(); },
        alCerrar() {
            this.abierto = false; sesion++; version++;
            this.cancelarBusqueda(); pendiente = null;
            invocador?.focus?.({preventScroll: true}); invocador = null;
        },
        cerrarDesdeFondo(evento) {
            if (evento.target !== this.$refs.dialogo) return;
            const caja = this.$refs.dialogo.getBoundingClientRect();
            if (evento.clientX < caja.left || evento.clientX > caja.right || evento.clientY < caja.top || evento.clientY > caja.bottom) this.cerrar();
        },
        buscar() {
            this.cancelarBusqueda(); version++;
            this.buscando = true; this.error = '';
            temporizador = reloj.setTimeout(() => { temporizador = null; this.consultar(); }, 300);
        },
        async consultar() {
            this.cancelarBusqueda();
            const solicitud = ++version;
            const revision = revisionSeleccion;
            this.buscando = true; this.error = '';
            try {
                const datos = await this.$wire.consultarExploradorTaxonomico(null, this.busqueda.trim());
                if (solicitud !== version || !this.abierto) return;
                this.nodos = datos.nodos; this.expandidos = datos.expandidos;
                this.cargados = []; ramasCargando = new Set(); this.cargandoRamas = [];
                if (revision === revisionSeleccion && !this.aplicando) { this.total = datos.total; this.seleccionado = datos.seleccionado; }
                this.listo = true;
                this.hayMas = datos.hayMas; this.distribuir(null, true);
            } catch {
                if (solicitud === version && this.abierto) this.error = 'No se pudo consultar la taxonomía. Vuelve a intentarlo.';
            } finally { if (solicitud === version) this.buscando = false; }
        },
        async alternar(nodo) {
            if (!nodo.tieneHijos || this.buscando || ramasCargando.has(nodo.clave)) return;
            const posicion = this.bosque.nodos.find(item => item.clave === nodo.clave);
            const ancla = posicion ? {clave: nodo.clave, x: posicion.x, desplazamiento: this.$refs.lienzo.scrollLeft || 0} : null;
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
                    const datos = await this.$wire.consultarExploradorTaxonomico(nodo.clave, '');
                    if (solicitud !== version || !this.abierto) return;
                    const conocidos = new Map(this.nodos.map(item => [item.clave, item]));
                    for (const hijo of datos.nodos) conocidos.set(hijo.clave, conocidos.get(hijo.clave) ?? hijo);
                    this.nodos = [...conocidos.values()];
                    this.cargados = [...this.cargados, nodo.clave];
                } catch {
                    if (solicitud === version && this.abierto) this.error = `No se pudo cargar la rama ${nodo.nombre}. Pulsa su flecha para reintentar.`;
                    return;
                } finally {
                    if (solicitud === version) { ramasCargando.delete(nodo.clave); this.cargandoRamas = [...ramasCargando]; }
                }
            }
            this.expandidos = [...this.expandidos, nodo.clave]; this.distribuir(ancla);
        },
        volverAFilos() {
            this.expandidos = []; this.distribuir(null, true);
            this.$nextTick(() => this.$refs.dialogo.querySelector('.is-root .taxonomy-explorer-name')?.focus({preventScroll: true}));
        },
        distribuir(ancla = null, mostrarResultado = false) {
            this.ancho = this.$refs?.lienzo?.clientWidth || this.ancho;
            this.bosque = distribuirBosque(this.nodos, this.expandidos, this.ancho);
            this.$nextTick(() => {
                const svg = this.$refs.conexiones;
                if (svg) {
                    const paths = this.bosque.conexiones.map(({d, color}) => {
                        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        path.setAttribute('d', d); path.setAttribute('stroke', color); return path;
                    });
                    svg.replaceChildren(...paths);
                }
                const lienzo = this.$refs.lienzo;
                if (ancla) {
                    const posicion = this.bosque.nodos.find(item => item.clave === ancla.clave);
                    if (posicion) lienzo.scrollLeft = Math.max(0, ancla.desplazamiento + posicion.x - ancla.x);
                } else if (mostrarResultado) {
                    const coincidencia = this.bosque.nodos.find(item => item.coincide);
                    lienzo.scrollLeft = coincidencia ? Math.max(0, coincidencia.x + coincidencia.ancho / 2 - this.ancho / 2) : 0;
                    lienzo.scrollTop = coincidencia ? Math.max(0, coincidencia.y + coincidencia.alto / 2 - (lienzo.clientHeight || 300) / 2) : 0;
                }
            });
        },
        seleccionar(nodo, cerrar = false) {
            if (this.buscando) return;
            this.error = ''; this.destacado = nodo.id; revisionSeleccion++;
            if (pendiente?.id === nodo.id && pendiente.sesion === sesion) {
                pendiente.cerrar ||= cerrar; return;
            }
            if (!procesando && this.seleccionado === nodo.id) {
                if (cerrar) this.finalizarSeleccion(nodo.hoja);
                return;
            }
            pendiente = {id: nodo.id, cerrar, sesion};
            return this.procesarSeleccion();
        },
        confirmarConTeclado(evento) {
            if (evento.key !== 'Enter' || evento.isComposing || evento.keyCode === 229) return;
            evento.preventDefault();
            if (evento.repeat) return;
            return this.confirmar();
        },
        confirmar() {
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
                        datos = accion.id ? await this.$wire.seleccionarTaxonExplorador(accion.id)
                            : await this.$wire.confirmarTaxonExplorador(accion.nombre);
                    } catch { datos = {error: 'No se pudo actualizar el mapa. Selecciona el taxón para reintentar.'}; }
                    if (!datos || (!datos.error && typeof datos.seleccionado !== 'string')) datos = {error: 'No se pudo actualizar el mapa. Selecciona el taxón para reintentar.'};
                    if (accion.sesion === sesion && this.abierto) {
                        if (datos.error) this.error = datos.error;
                        else { this.seleccionado = datos.seleccionado; this.total = datos.total; this.error = ''; }
                    }
                    if (pendiente === accion) {
                        pendiente = null;
                        this.destacado = '';
                        if (!datos.error && accion.sesion === sesion && this.abierto && accion.cerrar) this.finalizarSeleccion(datos.hoja);
                    }
                }
            } finally { procesando = false; this.aplicando = false; }
        },
        finalizarSeleccion(hoja) {
            const actual = sesion;
            const revision = revisionSeleccion;
            this.$nextTick(() => {
                if (actual !== sesion || revision !== revisionSeleccion || !this.abierto) return;
                if (hoja) window.dispatchEvent(new CustomEvent('encuadrar-taxonomia'));
                this.cerrar();
            });
        },
        teclado(evento, nodo) {
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
