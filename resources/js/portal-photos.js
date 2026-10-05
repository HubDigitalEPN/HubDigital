import {consultaFotografias, seleccionarTaxonExacto, fotografiasDeObservaciones, fotografiasLocalesValidas, combinarFotografias, crearCacheFotografias, candidatasEcuador, LUGAR_ECUADOR_INATURALIST, descripcionFotografias} from './portal-photo-model.js';

const cache = crearCacheFotografias();
const solicitudesRecientes = [];

/** GET público, sin cookies ni token, con seis segundos de límite por petición. */
async function solicitarJSON(ruta, parametros, cancelar) {
    if (cancelar.aborted) throw new Error('cancelado');
    const ahora = Date.now();
    while (solicitudesRecientes.length && solicitudesRecientes[0] <= ahora - 60000) solicitudesRecientes.shift();
    if (solicitudesRecientes.length >= 60) throw new Error('limite-fuente');
    solicitudesRecientes.push(ahora);
    const controlador = new AbortController();
    const abortar = () => controlador.abort();
    cancelar.addEventListener('abort', abortar, {once: true});
    if (cancelar.aborted) controlador.abort();
    const limite = setTimeout(abortar, 6000);
    try {
        const url = new URL(`https://api.inaturalist.org/v1/${ruta}`);
        url.search = new URLSearchParams(parametros).toString();
        const respuesta = await fetch(url, {method: 'GET', credentials: 'omit', mode: 'cors', redirect: 'error',
            referrerPolicy: 'no-referrer', headers: {Accept: 'application/json'}, signal: controlador.signal});
        if (!respuesta.ok || Number(respuesta.headers.get('content-length')) > 2000000) throw new Error('fuente-no-disponible');
        const texto = await respuesta.text();
        if (texto.length > 2000000) throw new Error('respuesta-demasiado-grande');
        const datos = JSON.parse(texto);
        if (!Array.isArray(datos.results)) throw new Error('respuesta-incompleta');
        return datos;
    } finally {
        clearTimeout(limite);
        cancelar.removeEventListener('abort', abortar);
    }
}

export function crearEstadoFotografias(taxon, fotosLocales = [], limite = 4) {
    const cantidad = Number.isInteger(limite) ? Math.max(1, Math.min(4, limite)) : 4;
    let consulta = consultaFotografias(taxon);
    const identidadDesconocida = taxon?._rango_desconocido || !consulta && (taxon?.rank || taxon?.rango || Array.isArray(taxon?.ancestros) || Array.isArray(taxon?.jerarquia));
    let locales = identidadDesconocida ? [] : fotografiasLocalesValidas(fotosLocales, consulta);
    let controlador = null;
    let observador = null;
    let sesion = 0;
    let destruido = false;
    let iniciado = false;
    return {
        fotos: locales,
        cargando: false,
        descripcion: descripcionFotografias(locales),
        error: '',
        init() {
            if (iniciado || destruido || !consulta?.api || locales.length >= cantidad) return;
            iniciado = true;
            // Las ayudas ocultas no disparan consultas hasta hacerse visibles.
            if (typeof IntersectionObserver !== 'undefined' && this.$el) {
                observador = new IntersectionObserver(entradas => {
                    if (!entradas.some(entrada => entrada.isIntersecting)) return;
                    observador?.disconnect();
                    observador = null;
                    this.cargar();
                });
                observador.observe(this.$el);
            } else { this.cargar(); }
        },
        async cargar() {
            if (destruido || !consulta?.api || this.cargando || locales.length >= cantidad) return;
            const turno = ++sesion;
            const claveCache = `${consulta.clave}:${cantidad}`;
            const existente = cache.obtener(claveCache);
            if (existente !== null) {
                this.fotos = combinarFotografias(locales, existente);
                this.descripcion = descripcionFotografias(this.fotos);
                this.error = '';
                return;
            }
            controlador?.abort();
            controlador = new AbortController();
            const señal = controlador.signal;
            this.cargando = true;
            this.error = '';
            try {
                const candidatos = await solicitarJSON('taxa', {q: consulta.name, rank: consulta.rank, per_page: '30', is_active: 'true'}, señal);
                if (destruido || turno !== sesion || señal.aborted) return;
                const seleccionado = seleccionarTaxonExacto(candidatos, consulta);
                let externas = [];
                if (seleccionado) {
                    const parametros = {taxon_id: String(seleccionado.id), quality_grade: 'research',
                        photos: 'true', photo_license: 'cc0,cc-by,cc-by-sa', per_page: '12', rank: consulta.rank === 'subspecies' ? 'subspecies' : 'species', taxon_is_active: 'true'};
                    const ecuatorianas = await solicitarJSON('observations', {...parametros, place_id: String(LUGAR_ECUADOR_INATURALIST)}, señal);
                    if (destruido || turno !== sesion || señal.aborted) return;
                    let observaciones = ecuatorianas.results.slice(0, 12);
                    if (locales.length + candidatasEcuador(observaciones, seleccionado, locales) < cantidad) {
                        const globales = await solicitarJSON('observations', {...parametros, photo_license: 'cc0'}, señal);
                        if (destruido || turno !== sesion || señal.aborted) return;
                        observaciones = [...observaciones, ...globales.results.slice(0, 12)];
                    }
                    const ids = [...new Set([seleccionado.id, ...observaciones.map(observacion => observacion.taxon?.id)
                        .filter(id => Number.isSafeInteger(id) && id > 0)])];
                    const detalles = await solicitarJSON(`taxa/${ids.join(',')}`, {}, señal);
                    externas = fotografiasDeObservaciones(observaciones, detalles.results, seleccionado, consulta);
                }
                if (destruido || turno !== sesion || señal.aborted) return;
                cache.guardar(claveCache, externas);
                this.fotos = combinarFotografias(locales, externas);
                this.descripcion = descripcionFotografias(this.fotos);
            } catch {
                if (destruido || turno !== sesion) return;
                this.fotos = locales;
                this.descripcion = descripcionFotografias(locales);
                this.error = 'La fuente de fotografías no está disponible en este momento.';
            } finally {
                if (!destruido && turno === sesion) this.cargando = false;
            }
        },
        destroy() {
            destruido = true;
            sesion++;
            controlador?.abort();
            observador?.disconnect();
            observador = null;
            controlador = null;
            // No conservar respuestas completas, observaciones ni clasificadores en Alpine.
            consulta = null;
            locales = [];
        },
    };
}

if (typeof window !== 'undefined') window.portalFotografias = crearEstadoFotografias;
