export function crearBusquedaUniversal() {
    return {
        texto: '', consulta: '', filas: [], pagina: 1, hayMas: false, cargando: false, abierto: false, sinResultados: false, error: '', version: 0,
        init() { this.texto = this.$wire.filtroUniversal || ''; },
        async buscarTexto() {
            this.cerrar();
            this.consulta = this.texto.trim();
            this.error = '';
            if (this.consulta) {
                await this.buscar();
            } else if (this.$wire.filtroUniversal) {
                await this.aplicar('', '');
            } else {
                this.error = 'Escribe un dato para buscar.';
            }
        },
        cerrar() {
            this.version++;
            this.cargando = this.abierto = this.hayMas = this.sinResultados = false;
            this.filas = [];
            this.pagina = 1;
        },
        volverAEntrada() {
            this.cerrar();
            this.$refs.entrada.focus();
        },
        async buscar(pagina = 1) {
            const version = ++this.version;
            this.error = ''; this.cargando = this.abierto = true;
            this.sinResultados = false;
            try {
                const datos = await this.$wire.buscarUniversal(this.consulta.trim(), pagina);
                if (version !== this.version) return;
                this.filas = datos.filas; this.pagina = datos.pagina; this.hayMas = datos.hay_mas;
                this.sinResultados = this.filas.length === 0;
            } catch { if (version === this.version) { this.filas = []; this.error = 'No se pudo completar la búsqueda. Inténtalo de nuevo.'; } }
            finally { if (version === this.version) this.cargando = false; }
        },
        async seleccionar(id = '') {
            const texto = this.consulta.trim();
            if (!texto || this.cargando) return;
            this.texto = texto; this.volverAEntrada();
            await this.aplicar(texto, id);
        },
        async aplicar(texto, id) {
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
