import {urlSeleccionCatalogo} from './portal-history-model.js';

function deshacerTuplas(valor) {
    if (Array.isArray(valor) && valor.length === 2 && valor[1]?.s) return deshacerTuplas(valor[0]);
    if (Array.isArray(valor)) return valor.map(deshacerTuplas);
    if (valor && typeof valor === 'object') return Object.fromEntries(Object.entries(valor).map(([clave, dato]) => [clave, deshacerTuplas(dato)]));
    return valor;
}

function aplicarActualizacion(datos, ruta, valor) {
    const claves = ruta.split('.');
    if (claves.some(clave => ['__proto__', 'constructor', 'prototype'].includes(clave))) return;
    let destino = datos;
    for (let posicion = 0; posicion < claves.length - 1; posicion++) {
        const clave = claves[posicion];
        if (!destino[clave] || typeof destino[clave] !== 'object') destino[clave] = /^\d+$/.test(claves[posicion + 1]) ? [] : {};
        destino = destino[clave];
    }
    destino[claves.at(-1)] = deshacerTuplas(valor);
}

/** Reproduce los efectos de las acciones sobre los aliases públicos, sin ejecutar la aplicación. */
function aplicarLlamada(estado, llamada, datos, defectos, propiedades) {
    const params = llamada.params ?? [];
    const paginaInicial = () => { estado.pagina = 1; };
    const normalizarMetodos = valores => Array.isArray(valores) ? [...new Set(valores.map(valor => String(valor).trim().toLowerCase()))] : [];
    const sincronizarBorrador = seleccion => {
        datos.borradorFiltros = Object.fromEntries(Object.entries(propiedades)
            .filter(([, propiedad]) => propiedad.startsWith('filtro')).map(([alias, propiedad]) => [propiedad, seleccion[alias]]));
        datos.borradorFiltros.filtroFilos = [...new Set([...(seleccion.fphs || []), ...(seleccion.fph ? [seleccion.fph] : [])])];
        datos.borradorFiltros.filtroProvincias = [...new Set([...(seleccion.fprovs || []), ...(seleccion.fprov ? [seleccion.fprov] : [])])];
        datos.borradorFiltros.filtroLatitud = seleccion.flat === seleccion.flax ? seleccion.flat : '';
        datos.borradorFiltros.filtroLongitud = seleccion.flon === seleccion.flox ? seleccion.flon : '';
    };
    const copiarFiltros = filtros => {
        for (const [alias, propiedad] of Object.entries(propiedades)) {
            if (propiedad.startsWith('filtro') && Object.hasOwn(filtros, propiedad)) estado[alias] = filtros[propiedad];
        }
    };
    switch (llamada.method) {
    case 'restaurarSeleccionUrl': {
        const restaurado = {...defectos, ...params[0]};
        restaurado.fm = normalizarMetodos(restaurado.fm);
        if (restaurado.explorar) restaurado.nivel = restaurado.taxon = '';
        sincronizarBorrador(restaurado);
        return restaurado;
    }
    case 'cambiarVista':
        if (['tarjetas', 'registros', 'mapa'].includes(params[0])) { estado.vista = params[0]; paginaInicial(); }
        break;
    case 'navegar':
    case 'quitarTaxon':
        estado.nivel = llamada.method === 'quitarTaxon' ? '' : params[0];
        estado.taxon = llamada.method === 'quitarTaxon' ? '' : params[1];
        estado.explorar = '';
        paginaInicial();
        break;
    case 'explorarNivel':
        estado.explorar = params[0];
        estado.nivel = estado.taxon = '';
        paginaInicial();
        break;
    case 'volverAlArbol':
        estado.explorar = '';
        paginaInicial();
        break;
    case 'cambiarPagina':
        estado.pagina = Math.max(1, Number(params[0]));
        break;
    case 'seleccionarProvincia': {
        const provincias = [...new Set([...(estado.fprovs || []), ...(estado.fprov ? [estado.fprov] : [])])];
        const seleccionadas = provincias.includes(params[0]) ? provincias.filter(nombre => nombre !== params[0]) : [...provincias, params[0]];
        estado.fprov = seleccionadas.length === 1 ? seleccionadas[0] : '';
        estado.fprovs = seleccionadas.length > 1 ? seleccionadas : [];
        sincronizarBorrador(estado);
        paginaInicial();
        break;
    }
    case 'seleccionarMetodo':
        estado.fm = [String(params[0]).trim().toLowerCase()];
        paginaInicial();
        break;
    case 'seleccionarMes':
        if (Number.isInteger(params[0]) && params[0] >= 1 && params[0] <= 12) { estado.fmes = String(params[0]); paginaInicial(); }
        break;
    case 'seleccionarDecada':
        if (Number.isInteger(params[0]) && params[0] >= 0 && params[0] <= 2090 && params[0] % 10 === 0) {
            estado.ffd = `${String(params[0]).padStart(4, '0')}-01-01`;
            estado.ffh = `${String(params[0] + 9).padStart(4, '0')}-12-31`;
            paginaInicial();
        }
        break;
    case 'seleccionarAltitud':
        if (params.slice(0, 2).every(Number.isInteger) && params[0] >= -500 && params[1] <= 9000 && params[0] <= params[1]) {
            estado.fed = String(params[0]); estado.feh = String(params[1]); paginaInicial();
        }
        break;
    case 'seleccionarArea':
        if (params.length >= 4 && params.slice(0, 4).every(Number.isFinite) && params[0] >= -90 && params[1] <= 90
            && params[2] >= -180 && params[3] <= 180 && params[0] < params[1] && params[2] < params[3]) {
            [estado.flat, estado.flax, estado.flon, estado.flox] = params.slice(0, 4).map(String);
            paginaInicial();
        }
        break;
    case 'seleccionarFilo':
        const filos = [...new Set([...(estado.fphs || []), ...(estado.fph ? [estado.fph] : [])])];
        const nuevosFilos = filos.includes(params[0]) ? filos.filter(id => id !== params[0]) : [...filos, params[0]];
        estado.fph = nuevosFilos.length === 1 ? nuevosFilos[0] : '';
        estado.fphs = nuevosFilos.length > 1 ? nuevosFilos : [];
        sincronizarBorrador(estado);
        paginaInicial();
        break;
    case 'filtrarCompletos':
    case 'verGeorreferenciados':
        estado[llamada.method === 'filtrarCompletos' ? 'fap' : 'fgeo'] = '1';
        estado.vista = 'registros';
        paginaInicial();
        break;
    case 'explorarEspecie':
        if (typeof params[0] === 'string' && [...params[0]].length > 0 && [...params[0]].length <= 120) {
            estado.ft = params[0]; estado.nivel = estado.taxon = ''; estado.vista = 'registros'; paginaInicial();
        }
        break;
    case 'aplicarBorrador': {
        const borrador = datos.borradorFiltros ?? {};
        const latitudAnterior = estado.flat === estado.flax ? estado.flat : '';
        const longitudAnterior = estado.flon === estado.flox ? estado.flon : '';
        copiarFiltros(borrador);
        if (estado.fph && Array.isArray(estado.fphs)) estado.fphs = estado.fphs.filter(id => id !== estado.fph);
        if (estado.fprov && Array.isArray(estado.fprovs)) estado.fprovs = estado.fprovs.filter(nombre => nombre !== estado.fprov);
        if (Object.hasOwn(borrador, 'filtroLatitud') && borrador.filtroLatitud !== latitudAnterior) estado.flat = estado.flax = borrador.filtroLatitud;
        if (Object.hasOwn(borrador, 'filtroLongitud') && borrador.filtroLongitud !== longitudAnterior) estado.flon = estado.flox = borrador.filtroLongitud;
        estado.fm = normalizarMetodos(estado.fm);
        sincronizarBorrador(estado);
        paginaInicial();
        break;
    }
    case 'quitarLocalidad': {
        const localidades = datos.borradorFiltros?.filtroGeografias;
        if (Array.isArray(localidades) && Number.isInteger(params[0]) && params[0] >= 0 && params[0] < localidades.length) {
            datos.borradorFiltros.filtroGeografias = localidades.filter((_, indice) => indice !== params[0]);
            return aplicarLlamada(estado, {method: 'aplicarBorrador'}, datos, defectos, propiedades);
        }
        break;
    }
    case 'retirarCriterio': {
        const [clave, indice = -1] = params;
        const grupos = {periodo: ['ffd', 'ffh'], elevacion: ['fed', 'feh'], latitud: ['flat', 'flax'], longitud: ['flon', 'flox']};
        if (clave === 'jerarquia' && estado.taxon) estado.nivel = estado.taxon = estado.explorar = '';
        else if (Object.hasOwn(grupos, clave) && grupos[clave].some(alias => estado[alias])) {
            for (const alias of grupos[clave]) estado[alias] = '';
        } else {
            const alias = Object.entries(propiedades).find(([, propiedad]) => propiedad === clave && propiedad.startsWith('filtro'))?.[0];
            if (!alias) break;
            if (Array.isArray(estado[alias]) && Number.isInteger(indice) && indice >= 0 && indice < estado[alias].length) estado[alias] = estado[alias].filter((_, posicion) => posicion !== indice);
            else if (indice === -1 && !Array.isArray(estado[alias]) && estado[alias]) estado[alias] = '';
            else break;
        }
        sincronizarBorrador(estado);
        paginaInicial();
        break;
    }
    case 'aplicarFiltros': {
        const entrada = params[0] ?? {};
        // El formulario anterior sólo modifica este conjunto; los criterios nuevos permanecen.
        const campos = ['fc', 'fp', 'ft', 'fg', 'fco', 'ffd', 'ffh', 'fm', 'flat', 'flax', 'flon', 'flox', 'fed', 'feh', 'fb', 'fh', 'fsti', 'fd', 'fca', 'fes'];
        for (const alias of campos) estado[alias] = entrada[propiedades[alias]] ?? defectos[alias];
        if (Object.hasOwn(entrada, 'filtroLatitud')) estado.flat = estado.flax = entrada.filtroLatitud;
        if (Object.hasOwn(entrada, 'filtroLongitud')) estado.flon = estado.flox = entrada.filtroLongitud;
        paginaInicial();
        break;
    }
    case 'actualizarFiltros':
        estado.fm = normalizarMetodos(estado.fm);
        paginaInicial();
        break;
    case 'limpiarFiltros': {
        const limpio = {...defectos, vista: estado.vista};
        sincronizarBorrador(limpio);
        return limpio;
    }
    }
    return estado;
}

export function enlaceRecuperacionCatalogo(href, configuracion, request) {
    let estado = {...configuracion.estado};
    const propiedades = configuracion.propiedades ?? {vista: 'vista', nivel: 'nivel', taxon: 'taxon'};
    for (const componente of request.payload?.components ?? []) {
        const snapshot = JSON.parse(componente.snapshot ?? '{}');
        if (!Object.hasOwn(snapshot.data ?? {}, 'vista')) continue;
        const datos = deshacerTuplas(snapshot.data);
        for (const [clave, valor] of Object.entries(componente.updates ?? {})) aplicarActualizacion(datos, clave, valor);
        const llamadas = componente.calls ?? [];
        for (const [alias, propiedad] of Object.entries(propiedades)) {
            if (Object.hasOwn(datos, propiedad)) estado[alias] = datos[propiedad];
        }
        if (Object.keys(componente.updates ?? {}).some(clave => clave.startsWith('borradorFiltros.'))) {
            for (const campo of Object.keys(componente.updates ?? {})) {
                if (campo === 'borradorFiltros.filtroFilos' || campo.startsWith('borradorFiltros.filtroFilos.')) datos.borradorFiltros.filtroFiloId = '';
                if (campo === 'borradorFiltros.filtroProvincias' || campo.startsWith('borradorFiltros.filtroProvincias.')) datos.borradorFiltros.filtroProvincia = '';
                if (campo === 'borradorFiltros.filtroFiloId') datos.borradorFiltros.filtroFilos = [];
                if (campo === 'borradorFiltros.filtroProvincia') datos.borradorFiltros.filtroProvincias = [];
            }
            estado = aplicarLlamada(estado, {method: 'aplicarBorrador'}, datos, configuracion.defectos, propiedades);
        }
        for (const llamada of llamadas) {
            estado = aplicarLlamada(estado, llamada, datos, configuracion.defectos, propiedades);
        }
    }
    return urlSeleccionCatalogo(href, estado, configuracion.defectos).href;
}

/** Interceptor de Livewire: una sola espera acotada, sin reintentos automáticos. */
export function vigilarPeticionCatalogo({request, onSend, onFinish, onFailure, onError}, avisar, programar = setTimeout, cancelar = clearTimeout) {
    let temporizador = null;
    let vencida = false;
    let finalizada = false;
    const signal = request.controller?.signal;
    const estaCancelada = () => request.isCancelled?.() || signal?.aborted;
    const limpiar = () => {
        if (temporizador !== null) cancelar(temporizador);
        temporizador = null;
        signal?.removeEventListener('abort', limpiar);
    };
    onSend(() => {
        if (estaCancelada()) return;
        signal?.addEventListener('abort', limpiar, {once: true});
        temporizador = programar(() => {
            if (finalizada || estaCancelada()) return;
            vencida = true;
            avisar('La consulta tardó demasiado. Puedes reintentar conservando tu selección.');
            request.cancel();
        }, 30000);
    });
    onFinish(() => { finalizada = true; limpiar(); });
    onFailure(() => { if (!vencida && !estaCancelada()) avisar('No se pudo conectar con el catálogo. Puedes reintentar conservando tu selección.'); });
    onError(({response, preventDefault}) => {
        if (response.status < 500) return;
        preventDefault();
        avisar('El catálogo no pudo completar la consulta. Puedes reintentar conservando tu selección.');
    });
}
