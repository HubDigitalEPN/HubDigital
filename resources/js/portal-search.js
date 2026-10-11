import {consultaPredictiva, completarBusqueda} from './portal-search-model.js';

const registrarBusqueda = () => {
    window.Alpine.data('portalBusquedaTexto', (campo, endpoint) => ({
        texto: '', sugerencias: [], activa: -1, abierto: false, cargando: false, aviso: '',
        temporizador: null, solicitud: null, version: 0,
        init() {
            this.texto = this.$wire[campo] || '';
            this.$watch(() => this.$wire[campo], valor => {
                this.texto = valor || '';
                this.cancelar();
                this.cerrar();
            });
        },
        cancelar() {
            clearTimeout(this.temporizador);
            this.solicitud?.abort();
            this.version++;
            this.cargando = false;
        },
        cerrar() { this.abierto = false; this.activa = -1; },
        restaurar() { this.texto = this.$wire[campo] || ''; this.cancelar(); this.cerrar(); },
        buscar() {
            this.cancelar(); this.cerrar(); this.sugerencias = []; this.aviso = '';
            const consulta = consultaPredictiva(campo, this.texto);
            if (consulta.length < 2) return;
            const version = this.version;
            this.temporizador = setTimeout(async () => {
                this.solicitud = new AbortController(); this.cargando = true;
                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('campo', campo); url.searchParams.set('texto', consulta);
                    const respuesta = await fetch(url, {signal: this.solicitud.signal, headers: {Accept: 'application/json'}});
                    if (!respuesta.ok) throw new Error('sugerencias');
                    const datos = await respuesta.json();
                    if (version !== this.version) return;
                    this.sugerencias = Array.isArray(datos.sugerencias) ? datos.sugerencias.filter(valor => typeof valor === 'string') : [];
                    this.abierto = this.sugerencias.length > 0;
                    this.aviso = this.sugerencias.length ? `${this.sugerencias.length} sugerencias. Usa las flechas y Enter.` : 'Sin sugerencias. Puedes buscar el fragmento con Enter.';
                } catch (error) {
                    if (error.name !== 'AbortError' && version === this.version) this.aviso = 'No se pudieron cargar las sugerencias. Puedes buscar con Enter.';
                } finally { if (version === this.version) this.cargando = false; }
            }, 250);
        },
        mover(paso) {
            if (!this.sugerencias.length) return;
            this.abierto = true;
            this.activa = (this.activa + paso + this.sugerencias.length) % this.sugerencias.length;
        },
        elegir(indice) {
            const sugerencia = this.sugerencias[indice];
            if (typeof sugerencia !== 'string') return;
            this.texto = completarBusqueda(campo, this.texto, sugerencia);
            this.cancelar(); this.cerrar(); this.$refs.entrada.focus();
        },
        async aplicar(evento) {
            if (evento.isComposing || evento.repeat) return;
            if (this.abierto && this.activa >= 0) this.elegir(this.activa);
            this.cancelar(); this.cerrar();
            await this.$wire.aplicarBusquedaTexto(campo, this.texto);
        },
        destroy() { this.cancelar(); },
    }));
};
if (window.Alpine) registrarBusqueda();
else document.addEventListener('alpine:init', registrarBusqueda, {once: true});
