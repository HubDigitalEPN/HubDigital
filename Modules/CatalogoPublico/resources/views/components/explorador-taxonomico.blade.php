<dialog id="explorador-taxonomico" class="taxonomy-explorer" x-data="portalExploradorTaxonomico" x-ref="dialogo" wire:ignore
    aria-labelledby="titulo-explorador-taxonomico" aria-describedby="ayuda-explorador-taxonomico"
    x-on:abrir-explorador-taxonomico.window="abrir($event.detail?.invocador)"
    x-on:cancel.stop.prevent="cerrar()" x-on:keydown.escape.stop.prevent="cerrar()"
    x-on:close="alCerrar()" x-on:click="cerrarDesdeFondo($event)">
    <header class="taxonomy-explorer-header">
        <div>
            <h2 id="titulo-explorador-taxonomico">Explorador de Estructura Taxonómica</h2>
            <p role="status" aria-live="polite" x-text="listo ? 'Mostrando el catálogo de ' + numero(total) + ' registros' : 'Consultando el catálogo…'"></p>
        </div>
        <button type="button" class="taxonomy-explorer-close" x-on:click="cerrar()" aria-label="Cerrar explorador taxonómico">×</button>
    </header>
    <div class="taxonomy-explorer-body">
        <form class="taxonomy-explorer-search" x-on:submit.prevent="confirmar()">
            <label for="busqueda-explorador-taxonomico">Buscar un taxón</label>
            <input id="busqueda-explorador-taxonomico" x-ref="busqueda" type="search" maxlength="120" autocomplete="off"
                x-model="busqueda" x-on:input="buscar()" x-on:keydown="confirmarConTeclado($event)"
                placeholder="Nombre científico…" aria-describedby="ayuda-busqueda-taxonomica">
            <p id="ayuda-busqueda-taxonomica">Escribe para buscar ramas. Enter selecciona un nombre exacto y cierra el explorador.</p>
        </form>
        <p class="taxonomy-explorer-status" x-show="buscando || aplicando || cargandoRamas.length" role="status" aria-live="polite">
            <span class="atlas-spinner" aria-hidden="true"></span><span x-text="aplicando ? 'Actualizando el mapa y los gráficos…' : (buscando ? 'Consultando taxones…' : 'Cargando la rama…')"></span>
        </p>
        <p class="taxonomy-explorer-error" x-show="error" role="alert"><span x-text="error"></span>
            <button type="button" x-show="!nodos.length" x-on:click="consultar()">Reintentar</button>
        </p>
        <p class="taxonomy-explorer-status" x-show="hayMas">Se muestran las primeras 50 coincidencias. Escribe un nombre más específico para acotar la búsqueda.</p>
        <div class="taxonomy-explorer-forest-heading" x-show="nodos.length">
            <span>Linajes publicados <small x-text="cantidadFilos + (cantidadFilos === 1 ? ' filo' : ' filos')"></small></span>
            <button type="button" class="taxonomy-explorer-reset" x-show="expandidos.length" x-on:click="volverAFilos()">Contraer ramas</button>
            <span class="taxonomy-explorer-pan-hint" x-show="bosque.ancho > ancho + 1">Desplázate horizontalmente para ver todas las ramas</span>
        </div>
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
                            :class="{'is-expanded': expandidos.includes(nodo.clave)}" :disabled="buscando || cargandoRamas.includes(nodo.clave)"
                            :aria-label="(expandidos.includes(nodo.clave) ? 'Colapsar ' : 'Expandir ') + nodo.nombre"
                            :aria-expanded="expandidos.includes(nodo.clave)" x-on:click.stop="alternar(nodo)">
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m5 3 5 5-5 5"/></svg>
                        </button>
                        <button type="button" class="taxonomy-explorer-name" :disabled="buscando" :title="nodo.nombre"
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
    <footer class="taxonomy-explorer-footer">
        <p id="ayuda-explorador-taxonomico">Un clic filtra el mapa al fondo; doble clic cierra el explorador. Con teclado: Mayús + Enter filtra y cierra.</p>
        <button type="button" x-on:click="cerrar()">Cerrar Explorador</button>
    </footer>
</dialog>
