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
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="5" rx="1"/><path d="M12 8v5M5 13h14M5 13v5m14-5v5M3 21h4m10 0h4M9 11l3-3 3 3"/></svg>
            </button>
            <button type="button" class="taxonomy-explorer-reset taxonomy-explorer-fit" :disabled="!nodos.length" x-on:click="ajustarArbol()"
                :aria-pressed="ajustado" :aria-label="ajustado ? 'Restaurar tamaño del árbol' : 'Ajustar árbol a la vista'"
                :title="ajustado ? 'Restaurar tamaño del árbol' : 'Ajustar árbol a la vista'">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5M9 9l-6-6m12 6 6-6M9 15l-6 6m12-6 6 6"/></svg>
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
        <div class="taxonomy-explorer-forest" x-ref="lienzo" :aria-busy="buscando" x-on:scroll="ocultarAyuda()">
            <p class="taxonomy-explorer-empty" x-show="!buscando && !error && !nodos.length" x-text="busqueda.trim() ? 'No hay taxones que coincidan con la búsqueda.' : 'No hay filos publicados para los filtros actuales.'"></p>
            <div class="taxonomy-explorer-canvas" x-show="nodos.length" :style="{width: bosque.ancho * escala + 'px', height: bosque.alto * escala + 'px'}">
            <div class="taxonomy-explorer-board" role="tree" aria-label="Estructura taxonómica por filos"
                :style="{width: bosque.ancho + 'px', height: bosque.alto + 'px', transform: 'scale(' + escala + ')'}">
                <svg x-ref="conexiones" :width="bosque.ancho" :height="bosque.alto" aria-hidden="true"></svg>
                <template x-for="nodo in bosque.nodos" :key="nodo.clave">
                    <div class="taxonomy-explorer-node" role="treeitem" :data-taxon-clave="nodo.clave"
                        :aria-level="nodo.profundidad + 1" :aria-selected="(destacado || seleccionado) === nodo.id"
                        :aria-expanded="nodo.tieneHijos ? expandidos.includes(nodo.clave) : null"
                        :aria-label="nodo.nombre + ', ' + nodo.etiqueta + ', ' + numero(nodo.total) + ' registros'"
                        :class="{'is-root': nodo.padre === null, 'is-selected': (destacado || seleccionado) === nodo.id, 'is-match': nodo.coincide}"
                        :style="{left: nodo.x + 'px', top: nodo.y + 'px', width: nodo.ancho + 'px', height: nodo.alto + 'px', '--taxon-color': nodo.color}"
                        x-on:mouseenter="mostrarAyuda(nodo, $el)" x-on:mouseleave="ocultarAyuda()"
                        x-on:focusin="mostrarAyuda(nodo, $event.target)" x-on:focusout="ocultarAyuda()">
                        <button type="button" class="taxonomy-explorer-toggle" x-show="nodo.tieneHijos"
                            :class="{'is-expanded': expandidos.includes(nodo.clave)}" :disabled="buscando || mostrandoMapa || cargandoRamas.includes(nodo.clave)"
                            :aria-label="(expandidos.includes(nodo.clave) ? 'Colapsar ' : 'Expandir ') + nodo.nombre"
                            :aria-expanded="expandidos.includes(nodo.clave)" x-on:click.stop="alternar(nodo)">
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m5 3 5 5-5 5"/></svg>
                        </button>
                        <button type="button" class="taxonomy-explorer-name" :disabled="buscando || mostrandoMapa"
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
    </div>
    <aside id="ayuda-nodo-explorador" x-ref="ayuda" class="taxonomy-explorer-tooltip" x-cloak x-show="ayuda" role="tooltip">
        <strong><em x-text="ayuda?.nombre"></em></strong>
        <span x-text="ayuda?.etiqueta"></span>
        <p x-text="numero(ayuda?.total || 0) + ((ayuda?.total || 0) === 1 ? ' registro público asociado' : ' registros públicos asociados')"></p>
        <p x-text="ayuda?.hijos ? numero(ayuda.hijos) + (ayuda.hijos === 1 ? ' rama descendiente publicada' : ' ramas descendientes publicadas') : 'Sin ramas descendientes publicadas'"></p>
        <p class="taxonomy-explorer-tooltip-lineage" x-text="ayuda?.linaje"></p>
    </aside>
</dialog>
