export function filasFilo(nodos, expandidos) {
    const abiertos = new Set(expandidos), grupos = new Map();
    for (const nodo of nodos) {
        const padre = nodo.padre ?? '';
        if (!grupos.has(padre)) grupos.set(padre, []);
        grupos.get(padre).push(nodo);
    }
    for (const grupo of grupos.values()) grupo.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es') || a.clave.localeCompare(b.clave));
    const filas = [];
    const agregar = (nodo, profundidad) => {
        filas.push({...nodo, profundidad});
        if (abiertos.has(nodo.clave)) for (const hijo of grupos.get(nodo.clave) || []) agregar(hijo, profundidad + 1);
    };
    for (const raiz of grupos.get('') || []) agregar(raiz, 0);
    return filas;
}

export function crearFiltroFilo(inicial, endpoint) {
    let version = 0, contexto = null;
    return {
        nodos: [], expandidos: [], cargados: [], cargando: [], filos: [], seleccionado: '', filas: [], busquedaTaxon: '', error: '',
        init() { this.actualizar(inicial); },
        actualizar(datos) {
            if (contexto !== datos.contexto) {
                version++; this.nodos = []; this.expandidos = []; this.cargados = []; this.cargando = []; contexto = datos.contexto;
            }
            this.error = ''; this.busquedaTaxon = datos.busquedaTaxon;
            this.filos = datos.filos; this.seleccionado = datos.seleccionado;
            this.integrar(datos);
            this.expandidos = [...new Set([...this.expandidos, ...datos.expandidos])];
            for (const raiz of this.nodos.filter(nodo => nodo.padre === null && this.filos.includes(nodo.id))) {
                if (!this.expandidos.includes(raiz.clave)) this.alternar(raiz);
            }
            this.distribuir();
        },
        integrar(datos) {
            const nodos = new Map(this.nodos.map(nodo => [nodo.clave, nodo]));
            for (const nodo of datos.nodos) nodos.set(nodo.clave, nodo);
            for (const [clave, hijos] of Object.entries(datos.precarga || {})) {
                for (const hijo of hijos) nodos.set(hijo.clave, hijo);
                if (!this.cargados.includes(clave)) this.cargados.push(clave);
            }
            this.nodos = [...nodos.values()];
        },
        distribuir() { this.filas = filasFilo(this.nodos, this.expandidos); },
        marcado(nodo) { return nodo.padre === null ? this.filos.includes(nodo.id) : this.seleccionado === nodo.id; },
        async alternar(nodo) {
            if (!nodo.tieneHijos || this.cargando.includes(nodo.clave)) return;
            if (this.expandidos.includes(nodo.clave)) {
                this.expandidos = this.expandidos.filter(clave => clave !== nodo.clave); this.distribuir(); return;
            }
            const solicitud = version;
            if (!this.cargados.includes(nodo.clave)) {
                this.cargando = [...this.cargando, nodo.clave]; this.error = '';
                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('padre', nodo.clave);
                    if (this.busquedaTaxon) url.searchParams.set('busquedaTaxon', this.busquedaTaxon);
                    const respuesta = await fetch(url, {headers: {Accept: 'application/json'}});
                    if (!respuesta.ok) throw new Error('Rama no disponible');
                    const datos = await respuesta.json();
                    if (solicitud !== version) return;
                    this.integrar(datos); this.cargados = [...new Set([...this.cargados, nodo.clave])];
                } catch { if (solicitud === version) this.error = 'No se pudo abrir la rama. Pulsa la flecha para reintentar.'; return; }
                finally { if (solicitud === version) this.cargando = this.cargando.filter(clave => clave !== nodo.clave); }
            }
            if (solicitud === version) { this.expandidos = [...new Set([...this.expandidos, nodo.clave])]; this.distribuir(); }
        },
        async seleccionar(nodo, marcado, entrada) {
            if (marcado && nodo.tieneHijos && !this.expandidos.includes(nodo.clave)) this.alternar(nodo);
            try {
                const datos = await this.$wire.seleccionarNodoFilo(nodo.id);
                if (datos?.contexto) this.actualizar(datos);
                if (entrada) this.$nextTick(() => { entrada.checked = this.marcado(nodo); });
            }
            catch {
                if (entrada) entrada.checked = this.marcado(nodo);
                this.error = 'No se pudo aplicar el filtro. La selección anterior se conserva.'; this.distribuir();
            }
        },
        teclado(evento, nodo) {
            let destino;
            const indice = this.filas.findIndex(fila => fila.clave === nodo.clave);
            if (evento.key === 'ArrowRight') {
                if (nodo.tieneHijos && !this.expandidos.includes(nodo.clave)) this.alternar(nodo);
                else destino = this.filas.find(fila => fila.padre === nodo.clave);
            } else if (evento.key === 'ArrowLeft') {
                if (this.expandidos.includes(nodo.clave)) this.alternar(nodo);
                else destino = this.filas.find(fila => fila.clave === nodo.padre);
            } else if (evento.key === 'ArrowDown') destino = this.filas[indice + 1];
            else if (evento.key === 'ArrowUp') destino = this.filas[indice - 1];
            else if (evento.key === 'Home') destino = this.filas[0];
            else if (evento.key === 'End') destino = this.filas.at(-1);
            else return;
            evento.preventDefault();
            if (destino) this.$nextTick(() => this.$el.querySelector(`[data-filo-clave="${destino.clave}"] input`)?.focus());
        },
    };
}
