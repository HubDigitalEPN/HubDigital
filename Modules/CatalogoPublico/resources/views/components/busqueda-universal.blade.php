@props(['activo' => false])
<div @class(['research-universal', 'research-filter-active' => $activo]) wire:key="catalogo-busqueda-universal" x-data="portalBusquedaUniversal" x-effect="texto = $wire.filtroUniversal || ''">
    <label for="catalogo-universal">Búsqueda universal</label>
    <div class="research-universal-input">
        <input id="catalogo-universal" x-ref="entrada" type="search" x-model="texto" maxlength="120" placeholder="Texto, fechas o números" x-on:keydown.enter.prevent="abrir()" aria-haspopup="dialog">
        <button type="button" x-on:click="abrir()" aria-label="Abrir búsqueda universal" title="Buscar en el catálogo"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="10" cy="10" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15 15 5 5" stroke="currentColor" stroke-width="2"/></svg></button>
    </div>
    <small>Busca valores públicos de cualquier campo del catálogo.</small>
    <p x-show="error" x-text="error" role="alert" class="research-filter-error"></p>
        <dialog x-ref="dialogo" class="research-universal-dialog" aria-labelledby="universal-titulo" x-on:cancel.prevent="cerrar()" x-on:keydown.escape.stop.prevent="cerrar()" wire:ignore>
            <header><h2 id="universal-titulo">Buscar en el catálogo</h2><button type="button" x-on:click="cerrar()" aria-label="Cerrar búsqueda">×</button></header>
            <div class="research-universal-query">
                <label for="universal-consulta">Texto, fecha o número</label>
                <div><input id="universal-consulta" x-ref="consulta" type="search" maxlength="120" x-model="consulta" x-on:input="filas = []; hayMas = false; version++; cargando = false" x-on:keydown.enter.prevent="buscar(1)" placeholder="Ej.: Pichincha 2020"><button type="button" x-on:click="buscar(1)" :disabled="cargando">Buscar</button></div>
                <small>Combina palabras, fechas (dd/mm/aaaa o aaaa-mm-dd) y números.</small>
            </div>
            <p role="status" class="research-universal-status" x-text="cargando ? 'Buscando…' : error || (filas.length ? filas.length + (filas.length === 1 ? ' resultado en esta página' : ' resultados en esta página') : 'Sin resultados. Escribe una búsqueda y pulsa Buscar.')"></p>
            <button class="research-universal-all" type="button" x-show="filas.length" :disabled="cargando" x-on:click="seleccionar()">Aplicar todos los resultados de «<span x-text="consulta"></span>»</button>
            <div class="research-universal-results" :aria-busy="cargando">
                <template x-for="fila in filas" :key="fila.id">
                    <button type="button" :disabled="cargando" x-on:click="seleccionar(fila.id)" class="research-universal-row">
                        <strong x-text="fila.codigo || 'Registro público'"></strong><span x-text="fila.taxon || 'Taxón no divulgado'"></span><small x-text="[fila.fecha, fila.localidad].filter(Boolean).join(' · ')"></small>
                    </button>
                </template>
            </div>
            <footer><button type="button" :disabled="cargando || pagina <= 1" x-on:click="buscar(pagina - 1)">Anterior</button><span x-text="'Página ' + pagina"></span><button type="button" :disabled="cargando || !hayMas" x-on:click="buscar(pagina + 1)">Siguiente</button></footer>
        </dialog>
</div>
