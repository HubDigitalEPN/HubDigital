const normalizar = texto => String(texto).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();

export function crearLov(config) {
    return {
        abierto: false, busqueda: '', activa: 0,
        get valor() { return this.$wire.$get(config.campo) ?? ''; },
        get etiqueta() { return config.opciones.find(o => o.valor === this.valor)?.etiqueta ?? config.placeholder; },
        get filtradas() {
            const palabras = normalizar(this.busqueda).split(/\s+/).filter(Boolean);
            return config.opciones.filter(o => palabras.every(p => normalizar(o.etiqueta).includes(p)));
        },
        abrir() {
            this.busqueda = ''; this.activa = Math.max(0, this.filtradas.findIndex(o => o.valor === this.valor));
            this.abierto = true; this.$nextTick(() => this.$refs.busqueda?.focus());
        },
        cerrar() {
            this.abierto = false; this.$nextTick(() => this.$refs.invocador?.focus({preventScroll: true}));
        },
        mover(delta) {
            const total = this.filtradas.length;
            if (!total) return;
            this.activa = (this.activa + delta + total) % total;
            this.$nextTick(() => this.$refs.lista?.querySelector(`[data-indice="${this.activa}"]`)?.scrollIntoView({block: 'nearest'}));
        },
        async escoger(valor = this.filtradas[this.activa]?.valor) {
            if (!config.opciones.some(o => o.valor === valor)) return;
            this.cerrar();
            await this.$wire.$set(config.campo, valor, true);
            await this.$nextTick();
            requestAnimationFrame(() => document.getElementById(`lov-${config.campo}-boton`)?.focus({preventScroll: true}));
        },
    };
}
