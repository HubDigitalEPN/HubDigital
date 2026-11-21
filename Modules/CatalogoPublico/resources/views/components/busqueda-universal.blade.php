@props(['activo' => false])
<div @class(['research-universal', 'research-filter-active' => $activo]) wire:key="catalogo-busqueda-universal"
    x-data="portalBusquedaUniversal" x-effect="texto = $wire.filtroUniversal || ''" x-on:keydown.escape.stop="cerrar()">
    <label for="catalogo-universal">Búsqueda universal</label>
    <div class="research-universal-input">
        <input id="catalogo-universal" x-ref="entrada" type="search" x-model="texto" maxlength="120" placeholder="Texto, fechas o números"
            x-on:input="cerrar(); error = ''" x-on:keydown.enter.prevent="buscarTexto()" aria-controls="catalogo-universal-resultados">
        <button type="button" x-on:click="buscarTexto()" aria-label="Buscar en el catálogo" title="Buscar en el catálogo"
            aria-controls="catalogo-universal-resultados" x-bind:aria-expanded="abierto.toString()">
            <flux:icon name="magnifying-glass" class="size-5" aria-hidden="true" />
        </button>
    </div>
    <small>Busca valores públicos de cualquier campo del catálogo.</small>
    <p x-show="error" x-text="error" role="alert" class="research-filter-error"></p>
    <section id="catalogo-universal-resultados" class="research-universal-resultados" aria-label="Resultados de búsqueda"
        x-show="abierto" x-cloak x-bind:aria-busy="cargando.toString()">
        <header>
            <p role="status" x-text="cargando ? 'Buscando…' : sinResultados ? 'No se encontraron registros.' : filas.length ? filas.length + (filas.length === 1 ? ' resultado en esta página' : ' resultados en esta página') : ''"></p>
            <button type="button" x-on:click="volverAEntrada()" aria-label="Cerrar resultados">×</button>
        </header>
        <button class="research-universal-all" type="button" x-show="filas.length" x-bind:disabled="cargando" x-on:click="seleccionar()">Aplicar todos los resultados</button>
        <div class="research-universal-results" x-show="filas.length">
            <template x-for="fila in filas" :key="fila.id">
                <button type="button" x-bind:disabled="cargando" x-on:click="seleccionar(fila.id)" class="research-universal-row">
                    <strong x-text="fila.codigo || 'Registro público'"></strong>
                    <span x-text="fila.taxon || 'Taxón no divulgado'"></span>
                    <small x-text="[fila.fecha, fila.localidad].filter(Boolean).join(' · ')"></small>
                </button>
            </template>
        </div>
        <footer x-show="pagina > 1 || hayMas">
            <button type="button" x-bind:disabled="cargando || pagina <= 1" x-on:click="buscar(pagina - 1)">Anterior</button>
            <button type="button" x-bind:disabled="cargando || !hayMas" x-on:click="buscar(pagina + 1)">Siguiente</button>
        </footer>
    </section>
</div>
