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

export function prepararPuntosMapa(puntos, filoActivo = '') {
    return puntos.flatMap(punto => {
        const coordenadas = coordenadasPublicas(punto);
        const cantidad = Number(filoActivo ? punto?.filos?.[filoActivo] || 0 : punto?.total);
        if (!coordenadas || !Number.isFinite(cantidad) || cantidad <= 0) return [];
        return [{...coordenadas, cantidad, radio: radioRegistros(cantidad)}];
    });
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
