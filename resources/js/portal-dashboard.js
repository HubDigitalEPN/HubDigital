import L from 'leaflet';
import contexto from '../data/ecuador-contexto.json';

// El mapa del panel y los mapas de especie usan la misma copia local de Leaflet.
window.L = L;

const registrarDashboard = () => {
    window.Alpine.data('portalFiltros', () => ({
        observador: null,
        actualizar: null,
        init() {
            this.$el.open = window.matchMedia('(min-width: 701px)').matches;
            this.actualizar = () => {
                const rect = this.$el.getBoundingClientRect();
                this.$el.style.setProperty('--filtros-left', `${rect.left}px`);
                this.$el.style.setProperty('--filtros-width', `${rect.width}px`);
            };
            this.observador = new ResizeObserver(this.actualizar);
            this.observador.observe(this.$el);
            window.addEventListener('resize', this.actualizar);
            window.addEventListener('scroll', this.actualizar, {passive: true});
            this.$nextTick(this.actualizar);
        },
        destroy() {
            this.observador?.disconnect();
            window.removeEventListener('resize', this.actualizar);
            window.removeEventListener('scroll', this.actualizar);
        },
    }));

    window.Alpine.data('portalPanel', (tipo, titulo, datos) => ({
        abierto: false,
        aviso: '',
        cerrar() { this.abierto = false; this.$refs.boton.focus(); },
        mover(evento) {
            const botones = [...this.$refs.menu.querySelectorAll('button:not(:disabled)')];
            const i = botones.indexOf(document.activeElement);
            const siguiente = evento.key === 'Home' ? 0 : evento.key === 'End' ? botones.length - 1 : (i + (evento.key === 'ArrowUp' ? -1 : 1) + botones.length) % botones.length;
            botones[siguiente]?.focus();
        },
        descargar(contenido, extension, mime) {
            const url = URL.createObjectURL(new Blob([contenido], {type: mime}));
            const enlace = document.createElement('a');
            enlace.href = url; enlace.download = `${tipo}-coleccion.${extension}`;
            enlace.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
            this.cerrar(); this.aviso = 'Descarga preparada.';
        },
        metadatos() {
            return {titulo, fuente: 'Laboratorio de Invertebrados — Escuela Politécnica Nacional', consulta: window.location.href, consultado: new Date().toISOString(), alcance: 'Registros públicos de la selección aplicada; no representa abundancia natural.', panel: tipo};
        },
        json() { this.descargar(JSON.stringify({...this.metadatos(), datos}, null, 2), 'json', 'application/json;charset=utf-8'); },
        geojson() {
            this.descargar(JSON.stringify({type: 'FeatureCollection', ...this.metadatos(), resolucion_grados: 0.25, features: datos.map(c => ({type: 'Feature', geometry: {type: 'Point', coordinates: [Number(c.lon), Number(c.lat)]}, properties: {registros: c.total, filos: c.filos, ubicacion: 'Centro de cuadrícula redondeada; no es una coordenada individual'}}))}, null, 2), 'geojson', 'application/geo+json');
        },
        cita() {
            const m = this.metadatos();
            this.descargar(`${m.fuente}. ${titulo}. Consulta: ${new Date().toLocaleDateString('es-EC')}.\n${m.consulta}\n${m.alcance}\nLos filtros constan en el enlace; los datos pueden actualizarse. Conserve también la exportación para reproducir el análisis.\n`, 'txt', 'text/plain;charset=utf-8');
        },
        async enlace() {
            try { await navigator.clipboard.writeText(window.location.href); this.aviso = 'Enlace con filtros copiado.'; }
            catch { this.descargar(window.location.href, 'url.txt', 'text/plain;charset=utf-8'); this.aviso = 'Se descargó el enlace porque el navegador no permitió copiarlo.'; }
            this.cerrar();
        },
        indice() { this.abierto = false; this.$refs.indice.showModal(); },
    }));

    window.Alpine.data('portalDashboard', (celdas, filos) => {
        // Leaflet administra objetos mutables propios; no deben convertirse en proxies Alpine.
        let mapa = null;
        let capa = null;
        return {
        observador: null,
        maximizado: false,
        enfocarTrasCambio: false,
        alPantallaCompleta: null,
        filoActivo: '',
        colores: ['#17699b', '#d17d28', '#568c59', '#8c62a5', '#b94e6b', '#71828d', '#a18a29', '#3f8d90'],

        init() {
            this.$nextTick(() => {
                if (!this.$refs.mapa || mapa) return;
                mapa = L.map(this.$refs.mapa, {scrollWheelZoom: false, boxZoom: true});
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 18,
                }).addTo(mapa);
                L.control.scale({imperial: false}).addTo(mapa);
                L.geoJSON(contexto, {
                    style: feature => ({
                        color: feature.properties.name === 'Ecuador' ? '#61879b' : '#b8c9ce',
                        weight: feature.properties.name === 'Ecuador' ? 1.6 : 1,
                        fillColor: feature.properties.name === 'Ecuador' ? '#d6e8df' : '#eef1ed',
                        fillOpacity: .72,
                        interactive: false,
                    }),
                }).addTo(mapa);
                mapa.attributionControl.addAttribution('Límites: Natural Earth (dominio público)');
                mapa.createPane('registros').style.zIndex = '450';
                capa = L.featureGroup().addTo(mapa);
                this.encuadrar();
                this.pintar(celdas, filos);
                mapa.on('boxzoomend', ({boxZoomBounds: limites}) => {
                    if (!limites) return;
                    this.$wire.seleccionarArea(
                        Number(limites.getSouth().toFixed(6)), Number(limites.getNorth().toFixed(6)),
                        Number(limites.getWest().toFixed(6)), Number(limites.getEast().toFixed(6)),
                    );
                });
                if (window.ResizeObserver) {
                    this.observador = new ResizeObserver(() => mapa?.invalidateSize());
                    this.observador.observe(this.$refs.mapa);
                }
                this.alPantallaCompleta = () => {
                    if (!document.fullscreenElement && this.maximizado) this.minimizar();
                };
                document.addEventListener('fullscreenchange', this.alPantallaCompleta);
            });
        },

        destroy() {
            this.observador?.disconnect();
            document.removeEventListener('fullscreenchange', this.alPantallaCompleta);
            document.documentElement.classList.remove('atlas-map-expanded');
            mapa?.remove();
            mapa = null;
            capa = null;
        },

        encuadrar() {
            mapa?.fitBounds([[-5.1, -92.1], [1.9, -75]], {padding: [20, 20], maxZoom: 7});
        },

        actualizar(datos) {
            celdas = datos.celdas;
            filos = datos.filos;
            this.pintar(celdas, filos);
            if (this.enfocarTrasCambio) this.$nextTick(() => {
                this.$refs.panelMapa.scrollIntoView({block: 'start', behavior: 'instant'});
                this.$refs.mapa.focus({preventScroll: true});
                this.enfocarTrasCambio = false;
            });
        },

        recordarAccion(evento) {
            const boton = evento.target.closest('button[wire\\:click]');
            if (boton && /^(seleccionar|filtrar|explorarEspecie)/.test(boton.getAttribute('wire:click'))) {
                this.enfocarTrasCambio = true;
                this.$refs.panelMapa.scrollIntoView({block: 'start', behavior: 'instant'});
                this.$refs.mapa.focus({preventScroll: true});
            }
        },

        async alternarTamano() {
            if (this.maximizado) return this.minimizar();
            this.maximizado = true;
            document.documentElement.classList.add('atlas-map-expanded');
            try { await document.documentElement.requestFullscreen?.(); } catch { /* La vista fija sigue ocupando el viewport. */ }
            this.$nextTick(() => mapa?.invalidateSize());
        },

        minimizar() {
            this.maximizado = false;
            document.documentElement.classList.remove('atlas-map-expanded');
            if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
            this.$nextTick(() => { mapa?.invalidateSize(); this.$refs.maximizar.focus({preventScroll: true}); });
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
            if (!capa) return;
            capa.clearLayers();
            for (const celda of celdas) {
                const lat = Number(celda.lat);
                const lon = Number(celda.lon);
                if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) continue;
                const entradas = Object.entries(celda.filos || {}).sort((a, b) => Number(b[1]) - Number(a[1]));
                const cantidad = this.filoActivo ? Number(celda.filos?.[this.filoActivo] || 0) : Number(celda.total);
                if (cantidad <= 0) continue;
                const dominante = this.filoActivo || entradas[0]?.[0] || 'Sin filo';
                const marcador = L.circleMarker([lat, lon], {
                    pane: 'registros',
                    radius: Math.min(17, 4 + Math.sqrt(cantidad) * .7),
                    color: '#163a55', weight: 1, fillColor: this.color(dominante, filos), fillOpacity: .8,
                }).addTo(capa);
                const abrir = () => this.$wire.abrirCelda(lat, lon);
                marcador.on('click', abrir);
                const elemento = marcador.getElement();
                if (elemento) {
                    elemento.setAttribute('tabindex', '0');
                    elemento.setAttribute('role', 'button');
                    elemento.setAttribute('aria-label', `Ver ${cantidad.toLocaleString('es-EC')} registros en la cuadrícula ${lat}, ${lon}`);
                    elemento.addEventListener('keydown', evento => {
                        if (evento.key === 'Enter' || evento.key === ' ') { evento.preventDefault(); abrir(); }
                    });
                }
            }
        },
        };
    });
};

if (window.Alpine) registrarDashboard();
else document.addEventListener('alpine:init', registrarDashboard, {once: true});
