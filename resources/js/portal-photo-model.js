/** Identidad y procedencia: una fotografía externa nunca identifica material local. */
const RANGOS = ['kingdom', 'subkingdom', 'phylum', 'subphylum', 'superclass', 'class', 'subclass', 'infraclass', 'superorder', 'order', 'suborder', 'infraorder', 'superfamily', 'epifamily', 'family', 'subfamily', 'supertribe', 'tribe', 'subtribe', 'genus', 'subgenus', 'species', 'subspecies', 'variety', 'form'];
const ALIAS = {reino: 'kingdom', subreino: 'subkingdom', filo: 'phylum', subfilo: 'subphylum', superclase: 'superclass', clase: 'class', subclase: 'subclass', infraclase: 'infraclass', superorden: 'superorder', orden: 'order', suborden: 'suborder', infraorden: 'infraorder', superfamilia: 'superfamily', epifamilia: 'epifamily', familia: 'family', subfamilia: 'subfamily', supertribu: 'supertribe', tribu: 'tribe', subtribu: 'subtribe', genero: 'genus', 'género': 'genus', subgenero: 'subgenus', 'subgénero': 'subgenus', especie: 'species', subespecie: 'subspecies', variedad: 'variety', forma: 'form'};
const LICENCIAS = {
    cc0: {nombre: 'CC0 1.0', url: 'https://creativecommons.org/publicdomain/zero/1.0/'},
    'cc-by': {nombre: 'CC BY 4.0', url: 'https://creativecommons.org/licenses/by/4.0/'},
    'cc-by-sa': {nombre: 'CC BY-SA 4.0', url: 'https://creativecommons.org/licenses/by-sa/4.0/'},
};
const DOMINIO_PUBLICO_LOCAL = [
    {nombre: 'Dominio público (PD-self / PD-user)', url: 'https://commons.wikimedia.org/wiki/Template:PD-self'},
    {nombre: 'Dominio público (PD-self)', url: 'https://commons.wikimedia.org/wiki/Template:PD-self'},
    {nombre: 'Dominio público (PD-user-w)', url: 'https://commons.wikimedia.org/wiki/Template:PD-user-w'},
    {nombre: 'Dominio público (PD-USGov-NIH)', url: 'https://commons.wikimedia.org/wiki/Template:PD-USGov-NIH'},
];
// GET /v1/places/autocomplete?q=Ecuador: id 7512, nombre Ecuador, admin_level 0.
export const LUGAR_ECUADOR_INATURALIST = 7512;

function esReferenciaEcuador(observacion) {
    return Array.isArray(observacion?.place_ids) && observacion.place_ids.includes(LUGAR_ECUADOR_INATURALIST);
}

export function candidatasEcuador(observaciones, seleccionado, locales = []) {
    if (!idPositivo(seleccionado?.id)) return 0;
    const ids = new Set();
    for (const observacion of (Array.isArray(observaciones) ? observaciones : []).slice(0, 12)) {
        if (!esReferenciaEcuador(observacion) || observacion.quality_grade !== 'research' || !['species', 'subspecies'].includes(observacion.taxon?.rank)
            || observacion.taxon.is_active !== true || !(observacion.taxon.id === seleccionado.id || observacion.taxon.ancestor_ids?.includes(seleccionado.id))) continue;
        for (const foto of (Array.isArray(observacion.photos) ? observacion.photos : []).slice(0, 12)) {
            if (!locales.some(local => local.id === `inaturalist-${foto.id}`)
                && urlFotoINaturalist(foto) && (foto.license_code === 'cc0' || foto.attribution_name || foto.attribution)) ids.add(foto.id);
        }
        if (ids.size >= 4) return 4;
    }
    return ids.size;
}

export function nombreTaxonExacto(nombre) {
    return typeof nombre === 'string' ? nombre.normalize('NFC').trim().replace(/\s+/gu, ' ').toLowerCase() : '';
}

function rangoPublico(rango) {
    const clave = nombreTaxonExacto(rango);
    return RANGOS.includes(clave) ? clave : ALIAS[clave] ?? null;
}

function idPositivo(id) {
    return typeof id === 'number' && Number.isSafeInteger(id) && id > 0;
}

export function consultaFotografias(taxon) {
    if (!taxon || typeof taxon !== 'object' || Array.isArray(taxon)) return null;
    if (taxon._rango_desconocido) return null;
    const linaje = {};
    const fuente = taxon.ancestros ?? taxon.jerarquia ?? taxon.taxonomia ?? taxon;
    if (Array.isArray(fuente)) {
        const ultimo = fuente.at(-1);
        if (ultimo && nombreTaxonExacto(ultimo.nombre ?? ultimo.name) && !rangoPublico(ultimo.rango ?? ultimo.rank ?? ultimo.nivel)) return null;
    }
    for (const [clave, valor] of Object.entries(fuente && typeof fuente === 'object' ? fuente : {})) {
        const rango = rangoPublico(typeof valor === 'object' && valor ? valor.rango ?? valor.rank ?? valor.nivel : clave);
        const nombre = typeof valor === 'object' && valor ? valor.nombre ?? valor.name ?? valor.taxon : valor;
        if (rango && nombreTaxonExacto(nombre)) linaje[rango] = nombre.trim().normalize('NFC').replace(/\s+/gu, ' ');
    }
    const rangoDeclarado = taxon.rank ?? taxon.rango;
    const explicito = rangoPublico(rangoDeclarado);
    if (rangoDeclarado !== undefined && !explicito) return null;
    let rango = explicito ?? [...RANGOS].reverse().find(nivel => linaje[nivel]);
    let nombre = explicito ? taxon.name ?? taxon.nombre ?? linaje[rango] : linaje[rango];
    if (!rango || !nombreTaxonExacto(nombre)) return null;
    if (linaje[rango] && nombreTaxonExacto(linaje[rango]) !== nombreTaxonExacto(nombre)) return null;
    // No eliminar autoridades, notas ni epítetos: un nombre distinto no es una coincidencia.
    nombre = nombre.trim().normalize('NFC').replace(/\s+/gu, ' ');
    linaje[rango] = nombre;
    return {rank: rango, name: nombre, linaje, api: !['subgenus', 'infraclass', 'subkingdom'].includes(rango), clave: JSON.stringify([rango, nombreTaxonExacto(nombre), RANGOS.map(nivel => nombreTaxonExacto(linaje[nivel]))])};
}

export function seleccionarTaxonExacto(respuesta, consulta) {
    if (!consulta || !Array.isArray(respuesta?.results) || respuesta.results.length > 30 || Number(respuesta.total_results) > 30) return null;
    const candidatos = respuesta.results.filter(taxon => idPositivo(taxon?.id) && taxon.is_active === true
        && taxon.rank === consulta.rank && nombreTaxonExacto(taxon.name) === nombreTaxonExacto(consulta.name));
    return candidatos.length === 1 ? candidatos[0] : null;
}

/** El endpoint /taxa/{ids} devuelve ancestros reales; no deducirlos del binomio. */
export function linajeFotografia(taxon) {
    if (!taxon || !Array.isArray(taxon.ancestors) || taxon.ancestors.length >= 30) return null;
    const nodos = [...taxon.ancestors, taxon];
    const vistos = new Set();
    const linaje = {};
    for (let indice = 0; indice < nodos.length; indice++) {
        const nodo = nodos[indice];
        if (!idPositivo(nodo?.id) || vistos.has(nodo.id) || nodo.is_active !== true || !nombreTaxonExacto(nodo.name) || typeof nodo.rank !== 'string') return null;
        if (indice > 0 && nodo.parent_id !== nodos[indice - 1].id) return null;
        vistos.add(nodo.id);
        if (RANGOS.includes(nodo.rank)) {
            if (linaje[nodo.rank]) return null;
            linaje[nodo.rank] = nodo.name;
        }
    }
    return {nodos, ids: vistos, nombres: linaje};
}

function coincideLinaje(linaje, consulta) {
    return Object.entries(consulta.linaje).every(([rango, nombre]) => nombreTaxonExacto(linaje.nombres[rango]) === nombreTaxonExacto(nombre));
}

function nombrePublico(rango, consulta, linaje) {
    // Un ancestro ausente del DTO público no se recupera para mostrarlo mediante otra fuente.
    if (RANGOS.indexOf(rango) < RANGOS.indexOf(consulta.rank) && !consulta.linaje[rango]) return '';
    return linaje.nombres[rango] ?? '';
}

export function urlFotoINaturalist(foto) {
    if (!idPositivo(foto?.id) || !LICENCIAS[foto.license_code] || foto.hidden === true
        || (Array.isArray(foto.flags) && foto.flags.length > 0)
        || (Array.isArray(foto.moderator_actions) && foto.moderator_actions.length > 0)) return null;
    try {
        const url = new URL(foto.url);
        // La documentación oficial identifica este host para fotos con licencias abiertas.
        if (url.protocol !== 'https:' || url.hostname !== 'inaturalist-open-data.s3.amazonaws.com'
            || url.username || url.password || url.port || url.search || url.hash) return null;
        const patron = new RegExp(`^/photos/${foto.id}/(square|thumb|small|medium|large|original)\\.(jpg|jpeg|png)$`);
        if (!patron.test(url.pathname)) return null;
        url.pathname = url.pathname.replace(/\/(square|thumb|small|large|original)\./u, '/medium.');
        return url.href;
    } catch { return null; }
}

export function fotografiasDeObservaciones(observaciones, taxones, seleccionado, consulta) {
    if (!consulta || !idPositivo(seleccionado?.id) || !Array.isArray(taxones)) return [];
    const detalles = new Map();
    for (const taxon of taxones.slice(0, 30)) {
        if (detalles.has(taxon?.id)) return []; // Un ID ambiguo invalida el lote.
        detalles.set(taxon?.id, taxon);
    }
    const raiz = detalles.get(seleccionado.id);
    const linajeRaiz = linajeFotografia(raiz);
    if (!linajeRaiz || raiz.rank !== consulta.rank || nombreTaxonExacto(raiz.name) !== nombreTaxonExacto(consulta.name) || !coincideLinaje(linajeRaiz, consulta)) return [];
    const fotos = [];
    const ids = new Set();
    const acotadas = (Array.isArray(observaciones) ? observaciones : []).slice(0, 24);
    const ordenadas = [...acotadas.filter(esReferenciaEcuador), ...acotadas.filter(observacion => !esReferenciaEcuador(observacion))];
    for (const observacion of ordenadas) {
        if (!idPositivo(observacion?.id) || observacion.quality_grade !== 'research' || !['species', 'subspecies'].includes(observacion.taxon?.rank) || observacion.taxon.is_active !== true) continue;
        const taxon = detalles.get(observacion.taxon.id);
        const linaje = linajeFotografia(taxon);
        if (!linaje || !['species', 'subspecies'].includes(taxon.rank) || taxon.rank !== observacion.taxon.rank || nombreTaxonExacto(taxon.name) !== nombreTaxonExacto(observacion.taxon.name)
            || !linaje.ids.has(raiz.id) || !coincideLinaje(linaje, consulta)) continue;
        if (['species', 'subspecies'].includes(consulta.rank) && taxon.id !== raiz.id) continue;
        const familia = linaje.nombres.family;
        const genero = linaje.nombres.genus;
        // La identificación de referencia exige especie, género y familia documentados.
        if (!familia || !genero || !linaje.nombres.phylum || !linaje.nombres.class || !linaje.nombres.order) continue;
        for (const foto of (Array.isArray(observacion.photos) ? observacion.photos : []).slice(0, 12)) {
            const url = urlFotoINaturalist(foto);
            const autor = foto?.attribution_name || foto?.attribution;
            const creditoEcuador = esReferenciaEcuador(observacion);
            if (!url || ids.has(foto.id) || (!creditoEcuador && foto.license_code !== 'cc0')
                || (foto.license_code !== 'cc0' && (typeof autor !== 'string' || !autor.trim()))) continue;
            ids.add(foto.id);
            const family = nombrePublico('family', consulta, linaje);
            const genus = nombrePublico('genus', consulta, linaje);
            const autorFuente = typeof autor === 'string' ? autor.trim() : '';
            const descripcion = `Fotografía de referencia identificada como ${taxon.name}.${family ? ` Familia ${family}.` : ''}${genus ? ` Género ${genus}.` : ''}${creditoEcuador ? ' Observación registrada en Ecuador.' : ''} Fuente: observación de iNaturalist con grado de investigación; no corresponde a un ejemplar de la colección.`;
            fotos.push({id: `inaturalist-${foto.id}`, url, species: taxon.name, genus, family,
                alt: `Fotografía de referencia de ${taxon.name}.`, autor: creditoEcuador ? autorFuente : '', autor_fuente: autorFuente,
                credito_ecuador: creditoEcuador, pais_referencia: creditoEcuador ? 'Ecuador' : '',
                fuente: `https://www.inaturalist.org/observations/${observacion.id}`,
                fuente_url: `https://www.inaturalist.org/observations/${observacion.id}`,
                foto_url: `https://www.inaturalist.org/photos/${foto.id}`,
                taxon_url: `https://www.inaturalist.org/taxa/${taxon.id}`,
                licencia: LICENCIAS[foto.license_code].nombre, licencia_url: LICENCIAS[foto.license_code].url,
                descripcion, foto_real: true, morfologia: true, representativa: true});
            if (fotos.length === 4) return fotos;
        }
    }
    return fotos;
}

export function fotografiasLocalesValidas(fotos, consulta) {
    const vistas = new Set();
    return (Array.isArray(fotos) ? fotos : []).filter(foto => {
        if (!foto || foto.foto_real !== true || foto.morfologia !== true || typeof foto.url !== 'string' || vistas.has(foto.url)) return false;
        if (!/^\/images\/taxonomia\/fotografias\/[a-z0-9_-]+\.webp(?:\?v=[a-z0-9_-]+)?$/iu.test(foto.url)) return false;
        if (consulta && (!Object.entries(consulta.linaje).every(([rango, nombre]) => nombreTaxonExacto(foto[rango]) === nombreTaxonExacto(nombre)))) return false;
        if (!['species', 'licencia', 'licencia_url'].every(campo => typeof foto[campo] === 'string' && foto[campo].trim()) || !foto.fuente && !foto.fuente_url) return false;
        const dominioPublico = ['CC0 1.0', 'CC0', 'Dominio público', 'Public domain', ...DOMINIO_PUBLICO_LOCAL.map(licencia => licencia.nombre)].includes(foto.licencia);
        if (!dominioPublico && foto.credito_ecuador !== true) return false;
        if (!dominioPublico && (typeof foto.autor !== 'string' || !foto.autor.trim())) return false;
        const licenciasLocales = [...Object.values(LICENCIAS), ...DOMINIO_PUBLICO_LOCAL, {nombre: 'CC0', url: LICENCIAS.cc0.url},
            {nombre: 'Dominio público', url: 'https://creativecommons.org/publicdomain/mark/1.0/'},
            {nombre: 'Public domain', url: 'https://creativecommons.org/publicdomain/mark/1.0/'}];
        if (!licenciasLocales.some(licencia => foto.licencia === licencia.nombre && foto.licencia_url.replace(/\/$/u, '') === licencia.url.replace(/\/$/u, ''))) return false;
        try {
            const fuente = new URL(foto.fuente_url || foto.fuente);
            if (fuente.protocol !== 'https:' || fuente.username || fuente.password || fuente.port
                || !['commons.wikimedia.org', 'www.antweb.org', 'antweb.org', 'www.inaturalist.org'].includes(fuente.hostname)) return false;
        } catch { return false; }
        vistas.add(foto.url);
        return true;
    }).slice(0, 4).map(foto => {
        const family = consulta ? nombrePublico('family', consulta, {nombres: foto}) : foto.family ?? '';
        const genus = consulta ? nombrePublico('genus', consulta, {nombres: foto}) : foto.genus ?? '';
        const oculto = family !== (foto.family ?? '') || genus !== (foto.genus ?? '');
        return {...foto, family, genus, alt: `Fotografía de referencia de ${foto.species}.`,
            descripcion: oculto ? `Fotografía de referencia identificada como ${foto.species}.` : foto.descripcion ?? '',
            autor: foto.credito_ecuador === true ? foto.autor || '' : '', autor_fuente: foto.autor_fuente || foto.autor || ''};
    });
}

export function descripcionFotografias(fotos) {
    if (!fotos.length) return 'No hay una fotografía identificada disponible para este taxón.';
    return [...new Set(fotos.map(foto => foto.descripcion).filter(descripcion => typeof descripcion === 'string' && descripcion.trim()))]
        .join(' ') || 'Fotografías identificadas de referencia externa; los ejemplares de la colección se consultan en sus registros.';
}

export function combinarFotografias(locales, externas) {
    const vistas = new Set();
    const urls = new Set();
    return [...locales, ...externas].filter(foto => {
        const clave = foto.id ?? foto.url;
        if (vistas.has(clave) || urls.has(foto.url)) return false;
        vistas.add(clave);
        urls.add(foto.url);
        return true;
    }).slice(0, 4);
}

/** Solo cuatro metadatos por entrada, hasta 32 selecciones y cinco minutos de vida. */
export function crearCacheFotografias(reloj = () => Date.now()) {
    const entradas = new Map();
    return {
        obtener(clave) {
            const entrada = entradas.get(clave);
            if (!entrada || entrada.expira <= reloj()) { entradas.delete(clave); return null; }
            entradas.delete(clave);
            entradas.set(clave, entrada);
            return entrada.fotos.map(foto => ({...foto}));
        },
        guardar(clave, fotos) {
            entradas.delete(clave);
            entradas.set(clave, {expira: reloj() + 300000, fotos: fotos.slice(0, 4).map(foto => ({...foto}))});
            while (entradas.size > 32) entradas.delete(entradas.keys().next().value);
        },
    };
}
