import L from 'leaflet';
import {crearGeojsonMapa, prepararPuntosMapa, crearAgrupadorMapa, etiquetaAgrupacionMapa, ZOOM_UBICACIONES_ORIGINALES} from './portal-map-model';
import {colorFilo, composicionFilos, fondoFilos} from './portal-map-model';
import {nombreDescargaImagen} from './portal-image-model';
import {crearHistorialCatalogo} from './portal-history-model';
import {enlaceRecuperacionCatalogo, vigilarPeticionCatalogo} from './portal-request-model';

// Leaflet 1.9 redondea latLngToLayerPoint a enteros. Mantener la proyección
// fraccionaria evita un residual visual sin alterar latitud, longitud ni CRS.
const CirculoCoordenadaOriginal = L.CircleMarker.extend({
    _project() {
        this._point = this._map.project(this._latlng, this._map.getZoom()).subtract(this._map.getPixelOrigin());
        this._updateBounds();
    },
});
const IconoCoordenadaOriginal = L.Marker.extend({
    _animateZoom({zoom, center}) {
        // La implementación base también redondea durante zoomanim.
        this._setPos(this._map._latLngToNewLayerPoint(this._latlng, zoom, center));
    },
    update() {
        if (this._icon && this._map) {
            const punto = this._map.project(this._latlng, this._map.getZoom()).subtract(this._map.getPixelOrigin());
            this._setPos(punto);
            // z-index requiere un entero; solo el orden de capas se redondea.
            this._zIndex = Math.round(punto.y) + this.options.zIndexOffset;
            this._resetZIndex();
        }
        return this;
    },
});

// El mapa del panel y los mapas de especie usan la misma copia local de Leaflet.
window.L = L;

/** Un grupo solo acerca el mapa a sus miembros; nunca abre una coordenada inventada. */
function agregarAgrupacionMapa(capa, mapa, grupo) {
    const boton = L.DomUtil.create('button', 'atlas-map-cluster-button');
    boton.type = 'button';
    boton.style.background = fondoFilos(grupo.filos);
    const etiqueta = etiquetaAgrupacionMapa(grupo);
    boton.setAttribute('aria-label', etiqueta);
    boton.title = etiqueta;
    const numero = L.DomUtil.create('span', 'atlas-map-cluster-number', boton);
    numero.textContent = grupo.ubicaciones.toLocaleString('es-EC');
    numero.setAttribute('aria-hidden', 'true');
    const unidad = L.DomUtil.create('span', 'atlas-map-cluster-unit', boton);
    unidad.textContent = 'ubic.';
    unidad.setAttribute('aria-hidden', 'true');
    L.DomEvent.disableClickPropagation(boton);
    boton.addEventListener('click', evento => {
        evento.preventDefault();
        const zoomAnterior = mapa.getZoom();
        mapa.fitBounds(grupo.limites, {padding: [16, 16], maxZoom: Math.min(ZOOM_UBICACIONES_ORIGINALES, zoomAnterior + 2), animate: false});
        // En un contenedor muy estrecho fitBounds puede conservar el zoom.
        if (mapa.getZoom() <= zoomAnterior) mapa.setZoom(Math.min(ZOOM_UBICACIONES_ORIGINALES, zoomAnterior + 1), {animate: false});
        // El botón se reemplaza al subdividir: el foco no queda en un nodo eliminado.
        mapa.getContainer().focus({preventScroll: true});
    });
    return L.marker([grupo.ancla.lat, grupo.ancla.lon], {
        pane: 'registros', interactive: false, keyboard: false,
        icon: L.divIcon({html: boton, className: 'atlas-map-cluster', iconSize: [48, 48], iconAnchor: [24, 24]}),
    }).addTo(capa);
}

// Una entrada del catálogo sin snapshot puede visitarse desde otra página.
// En ese caso no existe un componente que restaurar: abrir el enlace completo.
window.addEventListener('popstate', evento => {
    const entrada = evento.state?.portalCatalogo;
    if (entrada?.ruta === window.location.pathname && !evento.state?.alpine?.snapshotIdx
        && !document.querySelector('[data-catalogo-historial]')) window.location.reload();
});

const registrarDashboard = () => {
    window.Alpine.data('portalCatalogo', () => {
        let historial = null;
        let alVolver = null;
        let soltarInterceptor = null;
        return {
        errorHistorial: '',
        errorConsulta: '',
        enlaceReintento: '',
        init() {
            const raizCatalogo = this.$el;
            const configuracion = JSON.parse(raizCatalogo.dataset.catalogoHistorial);
            soltarInterceptor = this.$wire.$interceptRequest(interceptor => {
                const actual = JSON.parse(raizCatalogo.dataset.catalogoHistorial);
                this.enlaceReintento = enlaceRecuperacionCatalogo(window.location.href, actual, interceptor.request);
                this.errorConsulta = '';
                vigilarPeticionCatalogo(interceptor, mensaje => { this.errorConsulta = mensaje; });
            });
            historial = crearHistorialCatalogo({
                history: window.history, location: window.location, ...configuracion,
                restaurar: (estado, secuencia) => this.$wire.restaurarSeleccionUrl(estado, secuencia),
                onError: () => { this.errorHistorial = 'No se pudo restaurar la selección. Recarga la página para abrir el enlace actual.'; },
            });
            alVolver = evento => {
                // wire:navigate gestiona los saltos entre páginas cuando guarda un snapshot.
                if (evento.state?.alpine?.snapshotIdx) return;
                this.errorHistorial = '';
                historial.volver(window.location.href);
                raizCatalogo.querySelectorAll('dialog[open]').forEach(dialogo => dialogo.close());
            };
            window.addEventListener('popstate', alVolver);
        },
        actualizarHistorial(evento) { historial?.recibir(evento.estado, evento.restauracion, evento.version); },
        destroy() {
            historial?.destroy();
            soltarInterceptor?.();
            window.removeEventListener('popstate', alVolver);
        },
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
            this.taxonAyuda = {...datos, fotografia: datos.fotografia || ''};
            this.$nextTick(() => {
                if (!this.$refs.ayudaTaxon.open) this.$refs.ayudaTaxon.showModal();
                this.$refs.ayudaTaxon.querySelector('button')?.focus({preventScroll: true});
            });
        },
        cerrarTaxon() { this.$refs.ayudaTaxon.close(); },
        restaurarTaxon() {
            this.invocadorAyuda?.focus({preventScroll: true});
            this.taxonAyuda = null;
            this.invocadorAyuda = null;
        },
    }});

    window.Alpine.data('portalFiltros', () => ({
        observador: null,
        actualizar: null,
        localidadesAbiertas: false,
        localidades: [],
        busquedaLocalidad: '',
        get localidadesEncontradas() {
            const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
            const consulta = normalizar(this.busquedaLocalidad);
            return this.localidades.filter(nombre => normalizar(nombre).includes(consulta));
        },
        get localidadesMostradas() { return this.localidadesEncontradas.slice(0, 100); },
        abrirLocalidades() {
            this.localidades = JSON.parse(this.$refs.datosLocalidades.dataset.localidades || '[]');
            this.busquedaLocalidad = '';
            this.localidadesAbiertas = true;
            this.posicionarLocalidades();
            this.$refs.dialogoLocalidades.showModal();
            this.$nextTick(() => this.$refs.buscarLocalidad.focus({preventScroll: true}));
        },
        posicionarLocalidades() {
            const margen = 16;
            const bordeFiltros = this.$el.getBoundingClientRect().right + margen;
            const ancho = Math.min(420, window.innerWidth - margen * 2);
            const cabeAlLado = window.innerWidth - bordeFiltros >= 240;
            const anchoDisponible = cabeAlLado ? Math.min(ancho, window.innerWidth - bordeFiltros - margen) : ancho;
            const izquierda = cabeAlLado
                ? Math.max(bordeFiltros, Math.min((window.innerWidth - anchoDisponible) / 2 - 40, window.innerWidth - anchoDisponible - margen))
                : (window.innerWidth - anchoDisponible) / 2;
            this.$refs.dialogoLocalidades.style.width = `${anchoDisponible}px`;
            this.$refs.dialogoLocalidades.style.left = `${izquierda}px`;
        },
        cerrarLocalidades() { this.$refs.dialogoLocalidades.close(); },
        restaurarFocoLocalidades() {
            this.localidadesAbiertas = false;
            this.$refs.elegirLocalidad.focus({preventScroll: true});
        },
        elegirLocalidad(nombre) {
            if (nombre !== '' && !this.localidades.includes(nombre)) return;
            this.cerrarLocalidades();
            this.$wire.$set('borradorFiltros.filtroGeografias', nombre === '' ? [] : [nombre]);
        },
        navegarLocalidades(evento) {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(evento.key)) return;
            const botones = [...this.$refs.opcionesLocalidades.querySelectorAll('button')];
            const actual = botones.indexOf(evento.target);
            if (actual < 0) return;
            evento.preventDefault();
            const indice = evento.key === 'Home' ? 0 : evento.key === 'End' ? botones.length - 1
                : Math.max(0, Math.min(botones.length - 1, actual + (evento.key === 'ArrowDown' ? 1 : -1)));
            botones[indice].focus();
        },
        init() {
            this.$el.open = window.matchMedia('(min-width: 701px)').matches;
            this.actualizar = () => {
                const rect = this.$el.getBoundingClientRect();
                this.$el.style.setProperty('--filtros-left', `${rect.left}px`);
                this.$el.style.setProperty('--filtros-width', `${rect.width}px`);
                if (this.localidadesAbiertas) this.posicionarLocalidades();
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
                        const marcador = new CirculoCoordenadaOriginal([lat, lon], {
                            radius: radio, color: '#0e4975', weight: 1, fillColor: '#17699b', fillOpacity: .8,
                        }).addTo(mapa);
                        const popup = L.DomUtil.create('div');
                        const nombre = L.DomUtil.create('p', '', popup);
                        nombre.textContent = nombreTaxon;
                        nombre.style.fontWeight = '600';
                        const cantidadPublica = L.DomUtil.create('p', '', popup);
                        cantidadPublica.textContent = `${cantidad.toLocaleString('es-EC')} ${cantidad === 1 ? 'registro' : 'registros'} con coordenadas públicas ${lat}, ${lon}`;
                        marcador.bindPopup(popup);
                        const elemento = marcador.getElement();
                        if (elemento) {
                            elemento.setAttribute('tabindex', '0');
                            elemento.setAttribute('role', 'button');
                            elemento.setAttribute('aria-label', `Consultar ${cantidad.toLocaleString('es-EC')} ${cantidad === 1 ? 'registro' : 'registros'} de ${nombreTaxon} en ${lat}, ${lon}`);
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

    window.Alpine.data('portalDashboard', (celdas = [], filos = {}) => {
        // Leaflet administra objetos mutables propios; no deben convertirse en proxies Alpine.
        let mapa = null;
        let capa = null;
        let teselas = null;
        const teselasFallidas = new Set();
        let agrupador = crearAgrupadorMapa(celdas);
        let pintadoPendiente = null;
        // El tooltip vive fuera del mapa, sin directivas Alpine: un x-ref
        // teletransportado pierde su raíz al clonarse durante el morph de Livewire.
        let ayudaMapa = null;
        let invocadorAyudaMapa = null;
        return {
        observador: null,
        maximizado: false,
        enfocarTrasCambio: false,
        zoomMapa: 0,
        errorTeselas: false,
        mostrarAyudaMapa(texto, invocador) {
            this.ocultarAyudaMapa();
            if (!invocador) return;
            if (!ayudaMapa) {
                ayudaMapa = document.createElement('div');
                ayudaMapa.className = 'atlas-floating-tooltip';
                ayudaMapa.id = this.$id('atlas-map-tooltip');
                ayudaMapa.setAttribute('role', 'tooltip');
                document.body.appendChild(ayudaMapa);
            }
            invocadorAyudaMapa = invocador;
            invocador.setAttribute('aria-describedby', ayudaMapa.id);
            ayudaMapa.textContent = texto;
            ayudaMapa.hidden = false;
            const origen = invocador.getBoundingClientRect();
            const ayuda = ayudaMapa.getBoundingClientRect();
            const margen = 8;
            const izquierda = Math.max(margen, Math.min(origen.left + origen.width / 2 - ayuda.width / 2, window.innerWidth - ayuda.width - margen));
            const encima = origen.top - ayuda.height - margen;
            const superior = Math.max(margen, Math.min(encima >= margen ? encima : origen.bottom + margen, window.innerHeight - ayuda.height - margen));
            ayudaMapa.style.left = `${izquierda}px`;
            ayudaMapa.style.top = `${superior}px`;
        },
        ocultarAyudaMapa() {
            invocadorAyudaMapa?.removeAttribute('aria-describedby');
            invocadorAyudaMapa = null;
            if (ayudaMapa) { ayudaMapa.hidden = true; ayudaMapa.textContent = ''; }
        },
        reintentarTeselas() { teselasFallidas.clear(); this.errorTeselas = false; teselas?.redraw(); },
        colores: ['#17699b', '#d17d28', '#568c59', '#8c62a5', '#b94e6b', '#71828d', '#a18a29', '#3f8d90'],

        init() {
            this.$nextTick(() => {
                if (!this.$refs.mapa || mapa) return;
                mapa = L.map(this.$refs.mapa, {scrollWheelZoom: false, boxZoom: true, zoomControl: false});
                L.control.zoom({zoomInTitle: 'Acercar', zoomOutTitle: 'Alejar'}).addTo(mapa);
                teselas = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 18,
                }).on('tileerror', ({coords}) => { teselasFallidas.add(`${coords.z}/${coords.x}/${coords.y}`); this.errorTeselas = true; })
                    .on('tileload tileunload', ({coords}) => { teselasFallidas.delete(`${coords.z}/${coords.x}/${coords.y}`); this.errorTeselas = teselasFallidas.size > 0; }).addTo(mapa);
                L.control.scale({imperial: false}).addTo(mapa);
                mapa.createPane('registros').style.zIndex = '450';
                capa = L.featureGroup().addTo(mapa);
                this.encuadrar();
                this.pintar();
                // El zoom solo utiliza el índice cliente; no solicita datos a Livewire.
                mapa.on('zoomend', () => this.programarPintado());
                mapa.on('movestart zoomstart', () => this.ocultarAyudaMapa());
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
            this.ocultarAyudaMapa();
            ayudaMapa?.remove();
            ayudaMapa = null;
            this.observador?.disconnect();
            if (pintadoPendiente !== null) cancelAnimationFrame(pintadoPendiente);
            pintadoPendiente = null;
            document.documentElement.classList.remove('atlas-map-expanded');
            mapa?.remove();
            mapa = null;
            capa = null;
            teselas = null;
            teselasFallidas.clear();
            agrupador = null;
        },

        encuadrar() {
            const limites = L.latLngBounds((agrupador?.originales ?? []).map(({lat, lon}) => [lat, lon]));
            mapa?.fitBounds(limites?.isValid() ? limites : [[-5.1, -92.1], [1.9, -75]], {padding: [24, 24], maxZoom: 10});
        },

        actualizar(datos) {
            this.ocultarAyudaMapa();
            celdas = datos.celdas;
            filos = datos.filos;
            agrupador = crearAgrupadorMapa(celdas);
            this.encuadrar();
            this.programarPintado();
            if (this.enfocarTrasCambio) this.$nextTick(() => {
                this.$refs.panelMapa.scrollIntoView({block: 'start', behavior: 'instant'});
                this.$refs.mapa.focus({preventScroll: true});
                this.enfocarTrasCambio = false;
            });
        },

        recordarAccion(evento) {
            const boton = evento.target.closest('button[wire\\:click]');
            if (boton && /^(?:\$wire\.)?(seleccionar|filtrar|explorarEspecie)/.test(boton.getAttribute('wire:click'))) {
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
            return colorFilo(filo);
        },

        async abrirUbicacion(lat, lon, total, invocador) {
            this.ocultarAyudaMapa();
            window.dispatchEvent(new CustomEvent('iniciar-detalle-celda', {detail: {lat, lon, total, invocador}}));
            try {
                await this.$wire.abrirCelda(lat, lon);
                window.dispatchEvent(new CustomEvent('finalizar-detalle-celda'));
            } catch {
                window.dispatchEvent(new CustomEvent('error-detalle-celda'));
            }
        },

        programarPintado() {
            if (pintadoPendiente !== null || !mapa) return;
            pintadoPendiente = requestAnimationFrame(() => {
                pintadoPendiente = null;
                this.pintar();
            });
        },

        pintar() {
            if (!capa || !mapa || !agrupador) return;
            this.ocultarAyudaMapa();
            this.zoomMapa = mapa.getZoom();
            capa.clearLayers();
            for (const nodo of agrupador.paraZoom(this.zoomMapa)) {
                if (nodo.tipo === 'grupo') {
                    agregarAgrupacionMapa(capa, mapa, nodo);
                    continue;
                }
                const {lat, lon, cantidad, radio} = nodo;
                const partes = composicionFilos(nodo.filos);
                const marcador = partes.length > 1 ? new IconoCoordenadaOriginal([lat, lon], {
                    pane: 'registros', keyboard: false,
                    icon: L.divIcon({html: '', className: 'atlas-map-mixed', iconSize: [radio * 2, radio * 2], iconAnchor: [radio, radio]}),
                }).addTo(capa) : new CirculoCoordenadaOriginal([lat, lon], {
                    pane: 'registros',
                    radius: radio,
                    color: colorFilo(partes[0]?.filo), weight: 1, fillColor: colorFilo(partes[0]?.filo), fillOpacity: .85,
                }).addTo(capa);
                const elemento = marcador.getElement();
                const abrir = () => this.abrirUbicacion(lat, lon, cantidad, elemento);
                const composicion = partes.map(({filo, cantidad}) => `${filo}: ${cantidad.toLocaleString('es-EC')}`).join(', ');
                const descripcion = `Ubicación original: ${cantidad.toLocaleString('es-EC')} ${cantidad === 1 ? 'registro' : 'registros'} con coordenadas ${lat}, ${lon}.${composicion ? ' ' + composicion + '.' : ''} Abrir detalle.`;
                marcador.on('click', abrir);
                // Leaflet normaliza el hover tanto para los círculos SVG como
                // para los iconos de ubicaciones con varios filos.
                marcador.on('mouseover', () => this.mostrarAyudaMapa(descripcion, elemento));
                marcador.on('mouseout', () => this.ocultarAyudaMapa());
                if (elemento) {
                    if (partes.length > 1) elemento.style.background = fondoFilos(nodo.filos);
                    elemento.setAttribute('tabindex', '0');
                    elemento.setAttribute('role', 'button');
                    elemento.setAttribute('aria-label', descripcion);
                    elemento.addEventListener('focus', () => this.mostrarAyudaMapa(descripcion, elemento));
                    elemento.addEventListener('blur', () => this.ocultarAyudaMapa());
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
