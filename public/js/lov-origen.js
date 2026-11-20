// Reutiliza el popup APEX y añade selección validada y búsqueda provincial paginada.
window.hubDigitalOriginLov = config => {
    const state = window.hubDigitalLov(config);
    const base = {abrir: state.abrir, cerrar: state.cerrar, buscar: state.buscar, mostrarMas: state.mostrarMas};
    if (config.busquedaRemota) {
        Object.defineProperty(state, 'filtradas', {get() { return this.resultados; }});
    }
    if (config.busquedaPorCampo) {
        Object.defineProperty(state, 'etiqueta', {get() {
            if (Object.hasOwn(this.etiquetas, this.valor)) return this.etiquetas[this.valor];
            return config.admiteTexto && this.valor ? this.valor : config.placeholder;
        }});
    }
    return Object.assign(state, {
        resultados: [], cargando: false, errorBusqueda: false, hayMas: false,
        etiquetas: Object.assign(Object.create(null), Object.fromEntries(config.opciones.map(opcion => [opcion.valor, opcion.etiqueta]))),
        pagina: 0, peticion: 0, temporizador: null,
        puedeUsarTexto() {
            const texto = this.busqueda.trim();
            return config.admiteTexto && !this.cargando && !this.errorBusqueda && !!texto
                && texto.length <= config.maxBusqueda && !this.resultados.some(opcion => opcion.valor === texto);
        },
        abrir() {
            if (config.busquedaRemota) this.resultados = [];
            base.abrir.call(this);
            if (config.busquedaRemota && this.abierto) this.cargar(false);
        },
        cerrar() {
            clearTimeout(this.temporizador);
            this.peticion++;
            this.cargando = false;
            base.cerrar.call(this);
        },
        destroy() {
            clearTimeout(this.temporizador);
            this.peticion++;
        },
        buscar() {
            base.buscar.call(this);
            if (!config.busquedaRemota) return;
            clearTimeout(this.temporizador);
            this.peticion++;
            this.resultados = [];
            this.cargando = true;
            this.errorBusqueda = false;
            this.temporizador = setTimeout(() => this.cargar(false), 250);
        },
        mostrarMas() {
            if (!config.busquedaRemota) return base.mostrarMas.call(this);
            if (this.hayMas && !this.cargando) this.cargar(true);
        },
        async cargar(ampliar) {
            const peticion = ++this.peticion;
            const pagina = ampliar ? this.pagina + 1 : 0;
            this.cargando = true;
            this.errorBusqueda = false;
            try {
                const argumentos = config.busquedaPorCampo ? [config.campo, this.busqueda, pagina] : [this.busqueda, pagina];
                const respuesta = await this.$wire.$call(config.busquedaRemota, ...argumentos);
                if (peticion !== this.peticion || !this.abierto) return;
                this.resultados = ampliar ? [...this.resultados, ...respuesta.opciones] : respuesta.opciones;
                this.pagina = pagina;
                this.hayMas = respuesta.hayMas;
                this.limite = this.resultados.length;
                if (!ampliar) this.activa = 0;
                // Mantiene la etiqueta seleccionada incluso si no aparece en la página de resultados.
                config.opciones = [...new Map([...config.opciones, ...respuesta.opciones].map(o => [o.valor, o])).values()];
                if (config.busquedaPorCampo) {
                    for (const opcion of respuesta.opciones) this.etiquetas[opcion.valor] = opcion.etiqueta;
                }
            } catch {
                if (peticion !== this.peticion || !this.abierto) return;
                this.errorBusqueda = true;
                this.hayMas = false;
                window.dispatchEvent(new CustomEvent('show-toast', {detail: {
                    variant: 'warning', message: config.busquedaPorCampo
                        ? 'No se pudo consultar la lista de valores. Intenta buscar de nuevo.'
                        : 'No se pudo consultar la lista de localidades. Intenta buscar de nuevo.',
                }}));
            } finally {
                if (peticion === this.peticion) this.cargando = false;
            }
        },
        async escoger(valor = this.filtradas[this.activa]?.valor, textoLibre = false) {
            const permitido = textoLibre ? this.puedeUsarTexto() && valor === this.busqueda.trim()
                : (config.permiteVaciar && valor === '') || config.opciones.some(o => o.valor === valor);
            if (config.disabled || this.cargando || !permitido) return;
            this.cerrar();
            try {
                if (config.accion) await this.$wire.$call(config.accion, valor);
                else await this.$wire.$set(config.campo, valor, !config.busquedaPorCampo);
                await this.$nextTick();
            } catch {
                window.dispatchEvent(new CustomEvent('show-toast', {detail: {
                    variant: 'warning', message: 'No se pudo guardar la selección. Intenta seleccionarla de nuevo.',
                }}));
            }
            requestAnimationFrame(() => document.getElementById(`lov-${config.campo}-boton`)?.focus({preventScroll: true}));
        },
    });
};
