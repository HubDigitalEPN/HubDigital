export function crearBusquedaUniversal() {
    return {
        texto: '', consulta: '', filas: [], pagina: 1, hayMas: false, cargando: false, error: '', version: 0,
        init() { this.texto = this.$wire.filtroUniversal || ''; },
        async abrir() {
            this.consulta = this.texto.trim(); this.error = ''; this.filas = [];
            this.$refs.dialogo.showModal();
            this.$nextTick(() => this.$refs.consulta.focus());
            if (this.consulta) await this.buscar(1);
        },
        cerrar() { this.version++; this.cargando = false; this.$refs.dialogo.close(); this.$refs.entrada.focus(); },
        async buscar(pagina = 1) {
            const version = ++this.version;
            this.error = ''; this.cargando = true;
            try {
                const datos = await this.$wire.buscarUniversal(this.consulta.trim(), pagina);
                if (version !== this.version) return;
                this.filas = datos.filas; this.pagina = datos.pagina; this.hayMas = datos.hay_mas;
            } catch { if (version === this.version) { this.filas = []; this.error = 'No se pudo completar la búsqueda. Inténtalo de nuevo.'; } }
            finally { if (version === this.version) this.cargando = false; }
        },
        async seleccionar(id = '') {
            const texto = this.consulta.trim();
            if (!texto || this.cargando) return;
            this.texto = texto; this.cerrar();
            try { await this.$wire.aplicarUniversal(texto, id); }
            catch { this.error = 'No se pudo aplicar la selección.'; }
        },
    };
}

if (typeof document !== 'undefined') {
    const registrar = () => window.Alpine.data('portalBusquedaUniversal', crearBusquedaUniversal);
    if (window.Alpine) registrar();
    else document.addEventListener('alpine:init', registrar, {once: true});
}
