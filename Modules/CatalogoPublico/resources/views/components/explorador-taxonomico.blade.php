<dialog id="explorador-taxonomico" class="taxonomy-explorer" x-data="portalExploradorTaxonomico" x-ref="dialogo" wire:ignore
    aria-labelledby="titulo-explorador-taxonomico" closedby="closerequest" data-endpoint="{{ route('portal.explorador') }}" data-ete-endpoint="{{ route('portal.explorador-dibujo') }}"
    data-ficha-endpoint="{{ route('portal.ficha', ['taxon' => '__TAXON__']) }}"
    x-on:abrir-explorador-taxonomico.window="abrir($event.detail?.invocador)"
    x-on:portal-taxonomy-updated.window="if (abierto && !buscando) consultar(true, true)"
    x-on:registros-taxon-cerrados.window="enfocarNodo(seleccionado)"
    x-on:cancel.stop.prevent="escapar()" x-on:keydown.escape.stop.prevent="escapar()"
    x-on:close="alCerrar()" :style="{transform: `translate(${desplazamientoX}px, ${desplazamientoY}px)`, width: anchoVentana ? anchoVentana + 'px' : null, height: altoVentana ? altoVentana + 'px' : null}">
    <header class="taxonomy-explorer-header" tabindex="0" aria-label="Mover explorador: arrastra la cabecera o usa las flechas; Inicio lo centra"
        :class="{'is-dragging': arrastrando}" x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrarModal($event)"
        x-on:pointerup="terminarArrastre()" x-on:pointercancel="terminarArrastre()" x-on:lostpointercapture="terminarArrastre()" x-on:keydown="tecladoCabecera($event)">
        <div>
            <h2 id="titulo-explorador-taxonomico">Explorador de Estructura Taxonómica</h2>
            <div class="taxonomy-explorer-summary">
                <p role="status" aria-live="polite" x-text="listo ? 'Mostrando el catálogo de ' + numero(total) + ' registros' : 'Consultando el catálogo…'"></p>
                <template x-for="(filtro, indice) in filtrosArbol" :key="filtro.id">
                    <button type="button" class="taxonomy-explorer-pill" :disabled="buscando" x-on:click="quitarFiltroArbol(indice)" :aria-label="'Quitar filtro ' + filtro.nombre">
                        <span x-text="filtro.nombre"></span><span aria-hidden="true">×</span>
                    </button>
                </template>
            </div>
        </div>
        <div class="taxonomy-explorer-actions">
            <button type="button" class="taxonomy-explorer-close" x-on:click="cerrar()" aria-label="Cerrar explorador taxonómico">×</button>
        </div>
    </header>
    <p class="taxonomy-explorer-toast" x-cloak x-show="buscando || aplicando || mostrandoMapa || cargandoRamas.length" role="status" aria-live="polite">
        <span class="atlas-spinner" aria-hidden="true"></span><span x-text="aplicando || mostrandoMapa ? 'Actualizando el mapa y los gráficos…' : (buscando ? 'Consultando taxones…' : 'Cargando la rama…')"></span>
    </p>
    <div class="taxonomy-explorer-body" x-ref="cuerpo">
        <div class="taxonomy-navigator taxonomy-navigator-phylotree" x-show="modo === 'ramas'" :aria-busy="(buscando || cargandoRamas.length > 0) ? 'true' : 'false'">
            <div class="taxonomy-phylotree-controls">
                <div class="taxonomy-phylum-select">
                    <label for="filo-explorador-taxonomico">Filo</label>
                    <select id="filo-explorador-taxonomico" :value="filoActual" :disabled="buscando || !filos.length" x-on:change="elegirFilo($event.target.value)">
                        <template x-for="filo in filos" :key="filo.clave"><option :value="filo.clave" :selected="filo.clave === filoActual" x-text="filo.nombre"></option></template>
                    </select>
                </div>
                <div class="taxonomy-phylotree-search">
                    <form class="taxonomy-explorer-search" x-on:submit.prevent="confirmar()" x-on:click.outside="sugerenciasAbiertas = false"
                        x-on:quitar-filtro-taxonomico="quitarFiltroVisor($event.detail.indice)">
                        <label class="sr-only" for="busqueda-explorador-taxonomico">Buscar un taxón</label>
                        <div class="taxonomy-search-field" :class="{'has-filters': filtrosVisor.length}">
                            <template x-for="(filtro, indice) in filtrosVisor" :key="filtro.clave">
                                <button type="button" class="taxonomy-search-token" x-on:click="$dispatch('quitar-filtro-taxonomico', {indice})"
                                    :aria-label="'Quitar filtro ' + filtro.nombre" :title="'Quitar filtro ' + filtro.nombre">
                                    <span x-text="filtro.nombre"></span><span aria-hidden="true">×</span>
                                </button>
                            </template>
                        <input id="busqueda-explorador-taxonomico" x-ref="busqueda" type="search" maxlength="120" autocomplete="off"
                            role="combobox" aria-autocomplete="list" aria-controls="sugerencias-explorador" :aria-expanded="sugerenciasAbiertas"
                            :aria-activedescendant="sugerenciasAbiertas && sugerenciaActiva >= 0 ? 'sugerencia-explorador-' + sugerenciaActiva : null"
                            x-model="busqueda" x-on:input="buscar()" x-on:keydown="tecladoBusqueda($event)"
                            :placeholder="filtrosVisor.length ? 'Buscar dentro de ' + filtrosVisor.at(-1).nombre : 'Buscar un taxón'"
                            aria-describedby="alcance-busqueda-taxonomica">
                        </div>
                        <span id="alcance-busqueda-taxonomica" class="sr-only" aria-live="polite"
                            x-text="filtrosVisor.length ? 'Búsqueda limitada a ' + filtrosVisor.at(-1).nombre + ' y sus descendientes. Usa la equis para quitar el filtro.' : 'Búsqueda en todos los taxones publicados.'"></span>
                        <ul id="sugerencias-explorador" class="taxonomy-explorer-suggestions" role="listbox" aria-label="Taxones sugeridos" x-cloak x-show="sugerenciasAbiertas">
                            <template x-for="(sugerencia, indice) in sugerencias" :key="sugerencia.id">
                                <li :id="'sugerencia-explorador-' + indice" role="option" :aria-selected="indice === sugerenciaActiva">
                                    <button type="button" tabindex="-1" x-on:click="filtrarArbol(sugerencia)" :class="{'is-active': indice === sugerenciaActiva}">
                                        <strong x-text="sugerencia.nombre"></strong><span x-text="sugerencia.etiqueta + ' · ' + numero(sugerencia.total) + ' registros'"></span>
                                        <small x-text="sugerencia.linaje"></small>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </form>
                    <p class="taxonomy-explorer-error" x-show="error" role="alert"><span x-text="error"></span>
                        <button type="button" x-show="!nodos.length" x-on:click="consultar()">Reintentar</button>
                    </p>
                    <p class="taxonomy-explorer-status" x-show="hayMas">Se muestran las primeras 50 coincidencias. Escribe un nombre más específico para acotar la búsqueda.</p>
                </div>
                <div class="taxonomy-navigator-zoom" role="group" aria-label="Zoom del árbol">
                    <button type="button" :disabled="!visorDisponible || visorCargando || buscando || zoomActual <= zoomMinimo" x-on:click="zoomETE(1 / 1.25)" aria-label="Alejar árbol" title="Alejar árbol"><span aria-hidden="true">−</span></button>
                    <button type="button" :disabled="!visorDisponible || visorCargando || buscando || zoomActual >= zoomMaximo" x-on:click="zoomETE(1.25)" aria-label="Acercar árbol" title="Acercar árbol"><span aria-hidden="true">+</span></button>
                </div>
            </div>
            <section class="taxonomy-navigator-main" x-ref="ramas" aria-label="Taxones de la rama seleccionada">
                <div class="portal-ete-host" x-ref="ete" wire:ignore x-show="nodos.length && visorDisponible"
                    aria-label="Jerarquía taxonómica con árbol de diversidad y visualización alternativa de esferas 3D" :aria-busy="(buscando || visorCargando) ? 'true' : 'false'"></div>
                <p class="taxonomy-navigator-start" x-show="listo && nodos.length && !visorDisponible">Escoge un filo para explorar sus ramas.</p>
                <p class="portal-ete-loading" x-cloak x-show="visorDisponible && visorCargando" role="status">Abriendo visor…</p>
                <p class="taxonomy-navigator-empty" x-show="listo && !buscando && !nodos.length" role="status">No hay taxones publicados para esta selección.</p>
                <p class="taxonomy-explorer-error" x-show="visorError" role="alert"><span x-text="visorError"></span>
                    <button type="button" x-on:click="actualizarVisor()">Reintentar</button></p>
            </section>
        </div>
        <div class="taxonomy-explorer-forest" x-show="modo === 'diagrama'" x-ref="lienzo" :aria-busy="buscando ? 'true' : 'false'">
            <p class="taxonomy-explorer-empty" x-show="!buscando && !error && !nodos.length">No hay taxones publicados para esta selección.</p>
            <div class="taxonomy-explorer-canvas" x-show="nodos.length" :style="{width: bosque.ancho * escala + 'px', height: bosque.alto * escala + 'px'}">
            <div class="taxonomy-explorer-board" role="tree" aria-label="Estructura taxonómica por filos"
                data-orientacion="horizontal"
                :style="{width: bosque.ancho + 'px', height: bosque.alto + 'px', transform: 'scale(' + escala + ')'}">
                <template x-for="grupo in bosque.grupos" :key="grupo.clave">
                    <div class="taxonomy-explorer-group" aria-hidden="true" :style="{left: grupo.x + 'px', top: grupo.y + 'px', width: grupo.ancho + 'px', height: grupo.alto + 'px', '--taxon-color': grupo.color}">
                        <span x-text="'Hijos de ' + grupo.nombre + ' · ' + grupo.total"></span>
                    </div>
                </template>
                <svg x-ref="conexiones" :width="bosque.ancho" :height="bosque.alto" aria-hidden="true"></svg>
                <template x-for="nodo in bosque.nodos" :key="nodo.clave">
                    <div class="taxonomy-explorer-node" role="treeitem" :data-taxon-clave="nodo.clave"
                        :aria-level="nodo.profundidad + 1" :aria-selected="(destacado || seleccionado) === nodo.id"
                        :aria-expanded="nodo.tieneHijos ? expandidos.includes(nodo.clave) : null"
                        :aria-label="nodo.nombre + ', ' + nodo.etiqueta + ', ' + numero(nodo.total) + ' registros'"
                        :class="{'is-root': nodo.padre === null, 'is-selected': (destacado || seleccionado) === nodo.id, 'is-match': nodo.coincide}"
                        :style="{left: nodo.x + 'px', top: nodo.y + 'px', width: nodo.ancho + 'px', height: nodo.alto + 'px', '--taxon-color': nodo.color}"
                        x-on:contextmenu.prevent.stop="abrirMenu(nodo, $event)">
                        <button type="button" class="taxonomy-explorer-toggle" x-show="nodo.tieneHijos"
                            :class="{'is-expanded': expandidos.includes(nodo.clave)}" :disabled="buscando || mostrandoMapa || cargandoRamas.includes(nodo.clave)"
                            :aria-label="(expandidos.includes(nodo.clave) ? 'Colapsar ' : 'Expandir ') + nodo.nombre"
                            :aria-expanded="expandidos.includes(nodo.clave)" x-on:click.stop="alternar(nodo)">
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m5 3 5 5-5 5"/></svg>
                        </button>
                        <button type="button" class="taxonomy-explorer-name" :disabled="buscando || mostrandoMapa"
                            x-on:click.stop="seleccionar(nodo)" x-on:keydown="teclado($event, nodo)">
                            <small class="taxonomy-explorer-rank" x-text="nodo.etiqueta"></small>
                            <em x-text="nodo.nombre"></em>
                            <span class="taxonomy-explorer-node-count"><strong x-text="numero(nodo.total)"></strong><span aria-hidden="true">reg.</span><span class="sr-only" x-text="nodo.total === 1 ? 'registro' : 'registros'"></span></span>
                            <span class="taxonomy-explorer-selected-mark" x-show="(destacado || seleccionado) === nodo.id" aria-hidden="true">✓</span>
                        </button>
                    </div>
                </template>
            </div>
            </div>
        </div>
    </div>
    @foreach (['n' => 'el borde superior', 'e' => 'el borde derecho', 's' => 'el borde inferior', 'w' => 'el borde izquierdo', 'nw' => 'la esquina superior izquierda', 'ne' => 'la esquina superior derecha', 'sw' => 'la esquina inferior izquierda', 'se' => 'la esquina inferior derecha'] as $esquina => $nombreEsquina)
    <button type="button" class="taxonomy-explorer-resize taxonomy-explorer-resize-{{ $esquina }}" data-esquina="{{ $esquina }}" :class="{'is-resizing': redimensionando}"
        aria-label="Cambiar tamaño desde {{ $nombreEsquina }}: arrastra o usa las flechas; Inicio restaura el tamaño"
        x-on:pointerdown.prevent="iniciarRedimension($event)" x-on:pointermove="redimensionar($event)"
        x-on:pointerup="terminarRedimension()" x-on:pointercancel="terminarRedimension()" x-on:lostpointercapture="terminarRedimension()"
        x-on:keydown="tecladoRedimension($event)">
    </button>
    @endforeach
    <div class="taxonomy-explorer-context-menu" role="menu" aria-label="Acciones del taxón" x-ref="menu" x-cloak x-show="menuNodo"
        x-on:click.outside="cerrarMenu()" x-on:keydown="tecladoMenu($event)">
        <button type="button" role="menuitem" :disabled="aplicando" x-on:click="verEnMapa(menuNodo)">Ver en el mapa</button>
        @if(\App\Support\AccesoCatalogo::completo())<button type="button" role="menuitem" :disabled="aplicando" x-on:click="verRegistros(menuNodo)">Ver registros</button>@endif
    </div>
</dialog>
