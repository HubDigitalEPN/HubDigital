import L from 'leaflet';
import contexto from '../data/ecuador-contexto.json';

// El mapa del panel y los mapas de especie usan la misma copia local de Leaflet.
window.L = L;

const registrarDashboard = () => {
    window.Alpine.data('portalDashboard', (celdas, filos) => ({
        mapa: null,
        capa: null,
        observador: null,
        filoActivo: '',
        colores: ['#17699b', '#d17d28', '#568c59', '#8c62a5', '#b94e6b', '#71828d', '#a18a29', '#3f8d90'],

        init() {
            this.$nextTick(() => {
                if (!this.$refs.mapa || this.mapa) return;
                this.mapa = L.map(this.$refs.mapa, {scrollWheelZoom: false, preferCanvas: true, boxZoom: true});
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 18,
                }).addTo(this.mapa);
                L.control.scale({imperial: false}).addTo(this.mapa);
                L.geoJSON(contexto, {
                    style: feature => ({
                        color: feature.properties.name === 'Ecuador' ? '#61879b' : '#b8c9ce',
                        weight: feature.properties.name === 'Ecuador' ? 1.6 : 1,
                        fillColor: feature.properties.name === 'Ecuador' ? '#d6e8df' : '#eef1ed',
                        fillOpacity: .72,
                        interactive: false,
                    }),
                }).addTo(this.mapa);
                this.mapa.attributionControl.addAttribution('Límites: Natural Earth (dominio público)');
                this.capa = L.layerGroup().addTo(this.mapa);
                this.encuadrar();
                this.pintar(celdas, filos);
                this.mapa.on('boxzoomend', ({boxZoomBounds: limites}) => {
                    if (!limites) return;
                    this.$wire.seleccionarArea(
                        Number(limites.getSouth().toFixed(6)), Number(limites.getNorth().toFixed(6)),
                        Number(limites.getWest().toFixed(6)), Number(limites.getEast().toFixed(6)),
                    );
                });
                if (window.ResizeObserver) {
                    this.observador = new ResizeObserver(() => this.mapa?.invalidateSize());
                    this.observador.observe(this.$refs.mapa);
                }
            });
        },

        destroy() {
            this.observador?.disconnect();
            this.mapa?.remove();
            this.mapa = null;
        },

        encuadrar() {
            this.mapa?.fitBounds([[-5.1, -81.3], [1.9, -75]], {padding: [20, 20], maxZoom: 7});
        },

        color(filo, filos) {
            if (filo === 'Sin filo') return '#71828d';
            return this.colores[Math.max(0, Object.keys(filos).indexOf(filo)) % this.colores.length];
        },

        seleccionarFilo(filo) {
            this.filoActivo = this.filoActivo === filo ? '' : filo;
            this.pintar(celdas, filos);
        },

        pintar(celdas, filos) {
            if (!this.capa) return;
            this.capa.clearLayers();
            for (const celda of celdas) {
                const lat = Number(celda.lat);
                const lon = Number(celda.lon);
                if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) continue;
                const entradas = Object.entries(celda.filos || {}).sort((a, b) => Number(b[1]) - Number(a[1]));
                const cantidad = this.filoActivo ? Number(celda.filos?.[this.filoActivo] || 0) : Number(celda.total);
                if (cantidad <= 0) continue;
                const dominante = this.filoActivo || entradas[0]?.[0] || 'Sin filo';
                const marcador = L.circleMarker([lat, lon], {
                    radius: Math.min(17, 4 + Math.sqrt(cantidad) * .7),
                    color: '#163a55', weight: 1, fillColor: this.color(dominante, filos), fillOpacity: .8,
                }).addTo(this.capa);
                const detalle = document.createElement('div');
                const titulo = document.createElement('strong');
                titulo.textContent = `${cantidad.toLocaleString('es-EC')} registros en la cuadrícula`;
                detalle.append(titulo);
                for (const [nombre, total] of entradas.slice(0, 6)) {
                    const linea = document.createElement('div');
                    linea.textContent = `${nombre}: ${Number(total).toLocaleString('es-EC')}`;
                    detalle.append(linea);
                }
                marcador.bindPopup(detalle);
            }
        },
    }));
};

if (window.Alpine) registrarDashboard();
else document.addEventListener('alpine:init', registrarDashboard, {once: true});
