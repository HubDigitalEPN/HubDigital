import L from 'leaflet';
import {crearGeojsonMapa, prepararPuntosMapa} from './portal-map-model';
import {nombreDescargaImagen} from './portal-image-model';

// El mapa del panel y los mapas de especie usan la misma copia local de Leaflet.
window.L = L;

const registrarDashboard = () => {
    window.Alpine.data('portalCatalogo', () => ({
        taxonAyuda: null,
        invocadorAyuda: null,
        etiquetasStats: {kingdom: 'reinos', phylum: 'filos', class: 'clases', order: 'órdenes', family: 'familias', genus: 'géneros', species: 'especies'},
        descripciones: {
            Annelida: 'Animales de cuerpo alargado y segmentado, como lombrices y sanguijuelas.',
            Arthropoda: 'Animales con cuerpo segmentado, apéndices articulados y exoesqueleto. Incluye insectos, arácnidos, crustáceos y miriápodos.',
            Mollusca: 'Animales de cuerpo blando, como caracoles, bivalvos y cefalópodos; muchos presentan una concha.',
            Nematoda: 'Gusanos de cuerpo cilíndrico no segmentado; comprende formas de vida libre y parásitas.',
            Nematomorpha: 'Gusanos delgados y alargados, conocidos como gusanos crin de caballo; sus larvas parasitan artrópodos.',
        },
        abrirTaxon(invocador, datos) {
            this.invocadorAyuda = invocador;
            this.taxonAyuda = datos;
            this.$nextTick(() => {
                if (!this.$refs.ayudaTaxon.open) this.$refs.ayudaTaxon.showModal();
                this.$refs.ayudaTaxon.querySelector('button')?.focus({preventScroll: true});
            });
        },
        cerrarTaxon() { this.$refs.ayudaTaxon.close(); },
        restaurarTaxon() {
            this.invocadorAyuda?.focus({preventScroll: true});
            this.taxonAyuda = null;
        },
    }));

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

    window.Alpine.data('portalVisorImagen', () => ({
        url: '',
        alt: '',
        filename: 'imagen',
        invocador: null,
        abrir(datos) {
            this.invocador = datos.invocador || document.activeElement;
            this.url = datos.url;
            this.alt = datos.alt || '';
            this.filename = nombreDescargaImagen(datos.nombreArchivo);
            if (!this.$el.open) this.$el.showModal();
            this.$nextTick(() => this.$refs.cerrar.focus({preventScroll: true}));
        },
        cerrar() { this.$el.close(); },
        restaurarFoco() { this.invocador?.focus({preventScroll: true}); },
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
            this.descargar(JSON.stringify(crearGeojsonMapa(datos, this.metadatos()), null, 2), 'geojson', 'application/geo+json');
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

    window.Alpine.data('portalMapaEspecie', (puntos, nombreTaxon) => {
        let mapa = null;
        let observador = null;
        return {
            errorMapa: false,
            init() {
                this.$nextTick(() => {
                    if (!this.$refs.mapaContainer || mapa) return;
                    const ubicaciones = prepararPuntosMapa(puntos);
                    if (ubicaciones.length === 0) return;
                    mapa = L.map(this.$refs.mapaContainer, {scrollWheelZoom: false, zoomControl: false});
                    L.control.zoom({zoomInTitle: 'Acercar', zoomOutTitle: 'Alejar'}).addTo(mapa);
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        maxZoom: 18,
                    }).on('tileerror', () => { this.errorMapa = true; })
                        .on('tileload', () => { this.errorMapa = false; }).addTo(mapa);
                    mapa.fitBounds(L.latLngBounds(ubicaciones.map(({lat, lon}) => [lat, lon])), {padding: [24, 24], maxZoom: 10});
                    for (const {lat, lon, cantidad, radio} of ubicaciones) {
                        const marcador = L.circleMarker([lat, lon], {
                            radius: radio, color: '#0e4975', weight: 1, fillColor: '#17699b', fillOpacity: .8,
                        }).addTo(mapa);
                        const popup = L.DomUtil.create('div');
                        const nombre = L.DomUtil.create('p', '', popup);
                        nombre.textContent = nombreTaxon;
                        nombre.style.fontWeight = '600';
                        const cantidadPublica = L.DomUtil.create('p', '', popup);
                        cantidadPublica.textContent = `${cantidad.toLocaleString('es-EC')} registros con coordenadas públicas ${lat}, ${lon}`;
                        marcador.bindPopup(popup);
                        const elemento = marcador.getElement();
                        if (elemento) {
                            elemento.setAttribute('tabindex', '0');
                            elemento.setAttribute('role', 'button');
                            elemento.setAttribute('aria-label', `Consultar ${cantidad.toLocaleString('es-EC')} registros de ${nombreTaxon} en ${lat}, ${lon}`);
                            elemento.addEventListener('keydown', evento => {
                                if (evento.key === 'Enter' || evento.key === ' ') { evento.preventDefault(); marcador.openPopup(); }
                            });
                        }
                    }
                    if (window.ResizeObserver) {
                        observador = new ResizeObserver(() => mapa?.invalidateSize());
                        observador.observe(this.$refs.mapaContainer);
                    }
                    requestAnimationFrame(() => mapa?.invalidateSize());
                });
            },
            destroy() {
                observador?.disconnect();
                mapa?.remove();
                mapa = null;
            },
        };
    });

    window.Alpine.data('portalDashboard', (celdas, filos) => {
        // Leaflet administra objetos mutables propios; no deben convertirse en proxies Alpine.
        let mapa = null;
        let capa = null;
        return {
        observador: null,
        maximizado: false,
        enfocarTrasCambio: false,
        filoActivo: '',
        colores: ['#17699b', '#d17d28', '#568c59', '#8c62a5', '#b94e6b', '#71828d', '#a18a29', '#3f8d90'],

        init() {
            this.$nextTick(() => {
                if (!this.$refs.mapa || mapa) return;
                mapa = L.map(this.$refs.mapa, {scrollWheelZoom: false, boxZoom: true, zoomControl: false});
                L.control.zoom({zoomInTitle: 'Acercar', zoomOutTitle: 'Alejar'}).addTo(mapa);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 18,
                }).addTo(mapa);
                L.control.scale({imperial: false}).addTo(mapa);
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
            });
        },

        destroy() {
            this.observador?.disconnect();
            document.documentElement.classList.remove('atlas-map-expanded');
            mapa?.remove();
            mapa = null;
            capa = null;
        },

        encuadrar() {
            const limites = L.latLngBounds(prepararPuntosMapa(celdas, this.filoActivo).map(({lat, lon}) => [lat, lon]));
            mapa?.fitBounds(limites?.isValid() ? limites : [[-5.1, -92.1], [1.9, -75]], {padding: [24, 24], maxZoom: 10});
        },

        actualizar(datos) {
            celdas = datos.celdas;
            filos = datos.filos;
            this.pintar(celdas, filos);
            this.encuadrar();
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

        alternarTamano() {
            if (this.maximizado) return this.minimizar();
            this.maximizado = true;
            document.documentElement.classList.add('atlas-map-expanded');
            this.$nextTick(() => mapa?.invalidateSize());
        },

        minimizar() {
            this.maximizado = false;
            document.documentElement.classList.remove('atlas-map-expanded');
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

        async abrirUbicacion(lat, lon, total, invocador) {
            window.dispatchEvent(new CustomEvent('iniciar-detalle-celda', {detail: {lat, lon, total, invocador}}));
            try {
                await this.$wire.abrirCelda(lat, lon);
                window.dispatchEvent(new CustomEvent('finalizar-detalle-celda'));
            } catch {
                window.dispatchEvent(new CustomEvent('error-detalle-celda'));
            }
        },

        pintar(celdas, filos) {
            if (!capa) return;
            capa.clearLayers();
            for (const {lat, lon, cantidad, radio} of prepararPuntosMapa(celdas, this.filoActivo)) {
                const marcador = L.circleMarker([lat, lon], {
                    pane: 'registros',
                    radius: radio,
                    color: '#0e4975', weight: 1, fillColor: '#17699b', fillOpacity: .8,
                }).addTo(capa);
                const elemento = marcador.getElement();
                const abrir = () => this.abrirUbicacion(lat, lon, cantidad, elemento);
                marcador.on('click', abrir);
                if (elemento) {
                    elemento.setAttribute('tabindex', '0');
                    elemento.setAttribute('role', 'button');
                    elemento.setAttribute('aria-label', `Ver ${cantidad.toLocaleString('es-EC')} registros con coordenadas ${lat}, ${lon}`);
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
