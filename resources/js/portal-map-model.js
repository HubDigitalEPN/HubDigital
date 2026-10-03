/** La coordenada publicada se conserva sin desplazarla a centros de cuadrícula. */
export function coordenadasPublicas(punto) {
    if (!punto || !['number', 'string'].includes(typeof punto.lat) || !['number', 'string'].includes(typeof punto.lon)) return null;
    if ((typeof punto.lat === 'string' && punto.lat.trim() === '') || (typeof punto.lon === 'string' && punto.lon.trim() === '')) return null;
    const lat = Number(punto.lat);
    const lon = Number(punto.lon);
    return Number.isFinite(lat) && Number.isFinite(lon) && Math.abs(lat) <= 90 && Math.abs(lon) <= 180 ? {lat, lon} : null;
}

export function radioRegistros(cantidad) {
    const total = Number(cantidad);
    return Math.min(17, 4 + Math.sqrt(Number.isFinite(total) && total > 0 ? total : 0) * .7);
}

export const COLORES_FILOS = Object.freeze({
    Arthropoda: '#17699b', Mollusca: '#d17d28', Annelida: '#568c59',
    Nematoda: '#8c62a5', Nematomorpha: '#b94e6b',
});

export function colorFilo(filo) { return COLORES_FILOS[filo] ?? '#71828d'; }

/** Las proporciones preservan los grupos minoritarios en ubicaciones compartidas. */
export function composicionFilos(filos = {}) {
    return Object.entries(filos).filter(([, cantidad]) => Number.isFinite(Number(cantidad)) && Number(cantidad) > 0)
        .map(([filo, cantidad]) => ({filo, cantidad: Number(cantidad), color: colorFilo(filo)}))
        .sort((a, b) => a.filo.localeCompare(b.filo));
}

export function fondoFilos(filos = {}) {
    const partes = composicionFilos(filos);
    if (partes.length < 2) return partes[0]?.color ?? colorFilo('Sin filo');
    const total = partes.reduce((suma, parte) => suma + parte.cantidad, 0);
    let acumulado = 0;
    return `conic-gradient(${partes.map(parte => {
        const inicio = acumulado / total * 100;
        acumulado += parte.cantidad;
        return `${parte.color} ${inicio}% ${acumulado / total * 100}%`;
    }).join(', ')})`;
}

function sumarFilos(destino, fuente = {}) {
    for (const {filo, cantidad} of composicionFilos(fuente)) destino[filo] = (destino[filo] ?? 0) + cantidad;
    return destino;
}

export function prepararPuntosMapa(puntos, filoActivo = '') {
    return puntos.flatMap(punto => {
        const coordenadas = coordenadasPublicas(punto);
        const cantidad = Number(filoActivo ? punto?.filos?.[filoActivo] || 0 : punto?.total);
        if (!coordenadas || !Number.isFinite(cantidad) || cantidad <= 0) return [];
        const filos = filoActivo ? {[filoActivo]: cantidad} : sumarFilos({}, punto.filos);
        return [{...coordenadas, cantidad, radio: radioRegistros(cantidad), filos}];
    });
}

export const ZOOM_UBICACIONES_ORIGINALES = 9;
const RADIO_AGRUPACION_PIXELES = 44;

/** Proyección visual EPSG:3857; nunca sustituye la coordenada fuente WGS84. */
function proyectarMercator({lat, lon}) {
    const latitudVisual = Math.max(-85.0511287798, Math.min(85.0511287798, lat));
    const seno = Math.sin(latitudVisual * Math.PI / 180);
    return {x: (lon + 180) / 360, y: .5 - Math.log((1 + seno) / (1 - seno)) / (4 * Math.PI)};
}

/** El índice de vecindad sirve para buscar cercanía; no dibuja ni redondea cuadrículas. */
function subdividirPorCercania(miembros, zoom) {
    const escala = 256 * 2 ** zoom;
    const indice = new Map();
    const grupos = [];
    for (const punto of miembros) {
        const x = punto.x * escala; const y = punto.y * escala;
        const celdaX = Math.floor(x / RADIO_AGRUPACION_PIXELES);
        const celdaY = Math.floor(y / RADIO_AGRUPACION_PIXELES);
        let elegido = null; let distancia = RADIO_AGRUPACION_PIXELES ** 2;
        for (let dx = -1; dx <= 1; dx++) for (let dy = -1; dy <= 1; dy++) {
            for (const candidato of indice.get(`${celdaX + dx}:${celdaY + dy}`) ?? []) {
                const grupo = grupos[candidato];
                const d = (x - grupo.x) ** 2 + (y - grupo.y) ** 2;
                if (d <= distancia && (elegido === null || d < distancia || candidato < elegido)) {
                    elegido = candidato; distancia = d;
                }
            }
        }
        if (elegido === null) {
            elegido = grupos.length;
            grupos.push({x, y, miembros: []});
            const clave = `${celdaX}:${celdaY}`;
            if (!indice.has(clave)) indice.set(clave, []);
            indice.get(clave).push(elegido);
        }
        grupos[elegido].miembros.push(punto);
    }
    return grupos.map(grupo => grupo.miembros);
}

function representarGrupo(miembros) {
    const puntos = miembros.map(punto => punto.original);
    if (puntos.length === 1) return {tipo: 'ubicacion', ...puntos[0], ubicaciones: 1, puntos};
    let sur = Infinity; let oeste = Infinity; let norte = -Infinity; let este = -Infinity; let cantidad = 0;
    const filos = {};
    for (const punto of puntos) {
        sur = Math.min(sur, punto.lat); norte = Math.max(norte, punto.lat);
        oeste = Math.min(oeste, punto.lon); este = Math.max(este, punto.lon);
        cantidad += punto.cantidad;
        sumarFilos(filos, punto.filos);
    }
    // El símbolo del grupo se ancla a un miembro real y no anuncia una colecta
    // en un centro calculado. Solo sus puntos originales pueden abrir detalles.
    return {tipo: 'grupo', ancla: {lat: puntos[0].lat, lon: puntos[0].lon},
        cantidad, filos, ubicaciones: puntos.length, limites: [[sur, oeste], [norte, este]], puntos};
}

/**
 * Jerarquía visual reutilizable: cada nivel subdivide el anterior, sin fusionar
 * ubicaciones ya separadas. Se cachean como máximo nueve niveles del cliente.
 */
export function crearAgrupadorMapa(puntos, filoActivo = '') {
    const porCoordenada = new Map();
    for (const punto of prepararPuntosMapa(Array.isArray(puntos) ? puntos : [], filoActivo)) {
        const clave = `${punto.lat}:${punto.lon}`;
        const anterior = porCoordenada.get(clave);
        if (anterior) {
            anterior.cantidad += punto.cantidad;
            anterior.radio = radioRegistros(anterior.cantidad);
            sumarFilos(anterior.filos, punto.filos);
        } else porCoordenada.set(clave, {...punto});
    }
    const originales = [...porCoordenada.values()].sort((a, b) => a.lon - b.lon || a.lat - b.lat);
    const proyectados = originales.map(original => ({original, ...proyectarMercator(original)}));
    const particiones = [];
    const niveles = new Map();
    const detalle = originales.map(original => ({tipo: 'ubicacion', ...original, ubicaciones: 1, puntos: [original]}));
    return {
        originales,
        paraZoom(zoom) {
            const nivel = Number.isFinite(Number(zoom)) ? Math.max(0, Math.floor(Number(zoom))) : 0;
            if (nivel >= ZOOM_UBICACIONES_ORIGINALES) return detalle;
            if (niveles.has(nivel)) return niveles.get(nivel);
            for (let actual = particiones.length; actual <= nivel; actual++) {
                const anteriores = actual === 0 ? [proyectados] : particiones[actual - 1];
                particiones[actual] = anteriores.flatMap(miembros => subdividirPorCercania(miembros, actual));
            }
            const resultado = particiones[nivel].map(representarGrupo);
            niveles.set(nivel, resultado);
            return resultado;
        },
    };
}

export function etiquetaAgrupacionMapa(grupo) {
    return `Agrupación de ${grupo.ubicaciones.toLocaleString('es-EC')} ubicaciones originales · ${grupo.cantidad.toLocaleString('es-EC')} registros. Acercar para separarlas; el símbolo no representa una nueva coordenada de colecta.`;
}

export function crearGeojsonMapa(puntos, metadatos = {}) {
    return {
        ...metadatos,
        type: 'FeatureCollection',
        sistema_coordenadas: 'WGS84',
        features: puntos.flatMap(punto => {
            const coordenadas = coordenadasPublicas(punto);
            if (!coordenadas) return [];
            return [{
                type: 'Feature',
                geometry: {type: 'Point', coordinates: [coordenadas.lon, coordenadas.lat]},
                properties: {
                    registros: Number(punto.total), filos: punto.filos, taxones: punto.taxones,
                    ubicacion: 'Coordenadas públicas registradas; consulte la referencia de precisión de cada ejemplar',
                },
            }];
        }),
    };
}
