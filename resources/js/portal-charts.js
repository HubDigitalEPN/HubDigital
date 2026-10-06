import {configuracionGraficoPanel} from './portal-chart-model.js';

const preferencias = new Map();

const registrarGraficos = () => {
    window.Alpine.data('portalGrafico', (tipo, datos, titulo) => {
        let grafico = null;
        return {
            modo: preferencias.get(tipo) || (['decadas', 'estacionalidad', 'altitud'].includes(tipo) ? 'grafico' : 'barras'),
            tablaAbierta: false,
            alternar(evento) {
                if (evento.tipo !== tipo) return;
                this.modo = this.modo === 'barras' ? 'grafico' : 'barras';
                preferencias.set(tipo, this.modo);
                this.$nextTick(() => { if (this.modo === 'grafico') this.pintar(); });
            },
            init() { this.$nextTick(() => { if (this.modo === 'grafico') this.pintar(); }); },
            pintar() {
                const Chart = window.HubDigitalChart;
                if (!Chart || !this.$refs.lienzo) return;
                grafico?.destroy();
                const configuracion = configuracionGraficoPanel(tipo, datos, titulo, this.$wire);
                grafico = new Chart(this.$refs.lienzo, configuracion);
            },
            destroy() { grafico?.destroy(); grafico = null; },
        };
    });
};
if (window.Alpine) registrarGraficos();
else document.addEventListener('alpine:init', registrarGraficos, {once: true});
