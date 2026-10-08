<dialog id="explorador-taxonomico" class="taxonomy-explorer" x-data="portalExploradorTaxonomico" x-ref="dialogo" wire:ignore
    aria-labelledby="titulo-explorador-taxonomico" closedby="closerequest"
    x-on:abrir-explorador-taxonomico.window="abrir($event.detail?.invocador)"
    x-on:cancel.stop.prevent="cerrar()" x-on:keydown.escape.stop.prevent="cerrar()"
    x-on:close="alCerrar()">
    <header class="taxonomy-explorer-header">
        <div>
            <h2 id="titulo-explorador-taxonomico">Explorador de Estructura Taxonómica</h2>
            <p role="status" aria-live="polite" x-text="listo ? 'Mostrando el catálogo de ' + numero(total) + ' registros' : 'Consultando el catálogo…'"></p>
        </div>
        <div class="taxonomy-explorer-actions">
            <button type="button" class="taxonomy-explorer-reset" :disabled="!expandidos.length" x-on:click="volverAFilos()"
                aria-label="Contraer ramas" title="Contraer ramas">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 3 4 4 4-4M12 7v10m-4 4 4-4 4 4M3 12h4m10 0h4"/></svg>
            </button>
            <button type="button" class="taxonomy-explorer-map" :disabled="aplicando || mostrandoMapa || !listo" x-on:click="verEnMapa()">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2zM9 3v16m6-14v16"/></svg>
                Ver en el mapa
            </button>
            <button type="button" class="taxonomy-explorer-close" x-on:click="cerrar()" aria-label="Cerrar explorador taxonómico">×</button>
        </div>
    </header>
    <p class="taxonomy-explorer-toast" x-cloak x-show="buscando || aplicando || mostrandoMapa || cargandoRamas.length" role="status" aria-live="polite">
        <span class="atlas-spinner" aria-hidden="true"></span><span x-text="aplicando || mostrandoMapa ? 'Actualizando el mapa y los gráficos…' : (buscando ? 'Consultando taxones…' : 'Cargando la rama…')"></span>
    </p>
    <div class="taxonomy-explorer-body" x-ref="cuerpo">
        <form class="taxonomy-explorer-search" x-on:submit.prevent="confirmar()">
            <label class="sr-only" for="busqueda-explorador-taxonomico">Buscar un taxón</label>
            <input id="busqueda-explorador-taxonomico" x-ref="busqueda" type="search" maxlength="120" autocomplete="off"
                x-model="busqueda" x-on:input="buscar()" x-on:keydown="confirmarConTeclado($event)"
                placeholder="Buscar un taxón">
        </form>
        <p class="taxonomy-explorer-error" x-show="error" role="alert"><span x-text="error"></span>
            <button type="button" x-show="!nodos.length" x-on:click="consultar()">Reintentar</button>
        </p>
        <p class="taxonomy-explorer-status" x-show="hayMas">Se muestran las primeras 50 coincidencias. Escribe un nombre más específico para acotar la búsqueda.</p>
        <div class="taxonomy-explorer-forest" x-ref="lienzo" :aria-busy="buscando">
            <p class="taxonomy-explorer-empty" x-show="!buscando && !error && !nodos.length" x-text="busqueda.trim() ? 'No hay taxones que coincidan con la búsqueda.' : 'No hay filos publicados para los filtros actuales.'"></p>
            <div class="taxonomy-explorer-board" x-show="nodos.length" role="tree" aria-label="Estructura taxonómica por filos"
                :style="{width: bosque.ancho + 'px', height: bosque.alto + 'px'}">
                <svg x-ref="conexiones" :width="bosque.ancho" :height="bosque.alto" aria-hidden="true"></svg>
                <template x-for="nodo in bosque.nodos" :key="nodo.clave">
                    <div class="taxonomy-explorer-node" role="treeitem" :data-taxon-clave="nodo.clave"
                        :aria-level="nodo.profundidad + 1" :aria-selected="(destacado || seleccionado) === nodo.id"
                        :aria-expanded="nodo.tieneHijos ? expandidos.includes(nodo.clave) : null"
                        :aria-label="nodo.nombre + ', ' + nodo.etiqueta + ', ' + numero(nodo.total) + ' registros'"
                        :class="{'is-root': nodo.padre === null, 'is-selected': (destacado || seleccionado) === nodo.id, 'is-match': nodo.coincide}"
                        :style="{left: nodo.x + 'px', top: nodo.y + 'px', width: nodo.ancho + 'px', height: nodo.alto + 'px', '--taxon-color': nodo.color}">
                        <button type="button" class="taxonomy-explorer-toggle" x-show="nodo.tieneHijos"
                            :class="{'is-expanded': expandidos.includes(nodo.clave)}" :disabled="buscando || mostrandoMapa || cargandoRamas.includes(nodo.clave)"
                            :aria-label="(expandidos.includes(nodo.clave) ? 'Colapsar ' : 'Expandir ') + nodo.nombre"
                            :aria-expanded="expandidos.includes(nodo.clave)" x-on:click.stop="alternar(nodo)">
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m5 3 5 5-5 5"/></svg>
                        </button>
                        <button type="button" class="taxonomy-explorer-name" :disabled="buscando || mostrandoMapa" :title="nodo.nombre"
                            x-on:click.stop="seleccionar(nodo)" x-on:dblclick.stop="seleccionar(nodo, true)" x-on:keydown="teclado($event, nodo)">
                            <small class="taxonomy-explorer-rank" x-text="nodo.etiqueta"></small>
                            <em x-text="nodo.nombre"></em>
                            <span class="taxonomy-explorer-node-count"><strong x-text="numero(nodo.total)"></strong><span x-text="nodo.total === 1 ? 'registro' : 'registros'"></span></span>
                            <span class="taxonomy-explorer-selected-mark" x-show="(destacado || seleccionado) === nodo.id" aria-hidden="true">✓</span>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</dialog>
