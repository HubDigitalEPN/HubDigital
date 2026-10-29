@props(['filos' => [], 'preparaciones' => [], 'metodos' => [], 'biomas' => [], 'hayFiltrosActivos' => false, 'aplicados' => []])
@php
    $filtroTexto = static fn (string $clave): string => \App\Support\PortalCopy::text('catalogo.'.$clave);
    $activo = static function (string $campo) use ($aplicados): bool {
        $valor = $aplicados[$campo] ?? '';
        return is_array($valor) ? array_any($valor, static fn ($item) => trim((string) $item) !== '') : trim((string) $valor) !== '';
    };
@endphp

<details class="research-sidebar" open wire:key="catalogo-filtros" x-data="portalFiltros" wire:ignore.self wire:loading.attr="inert">
    <summary title="Mostrar u ocultar filtros">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span>{{ $filtroTexto('filtros') }}</span>
        @if($hayFiltrosActivos)<i aria-label="Hay filtros activos"></i>@endif
        <svg class="research-sidebar-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </summary>
    <form wire:submit="aplicarBorrador" class="research-filter-form" aria-label="{{ $filtroTexto('filtros') }}">
        <fieldset class="research-filter-lock" wire:loading.attr="disabled">
        @if($errors->any())<p data-hub-feedback="error" class="research-filter-error" role="alert">{{ $errors->first() }}</p>@endif
        <x-catalogopublico::busqueda-predictiva campo="filtroCatalogo" :etiqueta="$filtroTexto('numero_catalogo')" :placeholder="$filtroTexto('catalogo_placeholder')" :maxlength="240" :activo="$activo('filtroCatalogo')" />
        <x-catalogopublico::busqueda-predictiva campo="filtroTaxon" :etiqueta="$filtroTexto('taxon')" :placeholder="$filtroTexto('taxon_placeholder')" :activo="$activo('filtroTaxon')" />
        @php($panelFilos = $this->arbolFiloPanel)
        <fieldset @class(['research-filter-active' => $activo('filtroFilos') || $activo('filtroTaxonId') || $activo('filtroTaxonIds')]) wire:key="panel-filos" x-data="portalFiltroFilo(@js($panelFilos), @js(route('portal.filos')))" x-on:filos-panel-actualizados="actualizar($event.detail)">
            <legend>{{ $filtroTexto('filo') }}</legend>
            <span hidden wire:key="filos-datos-{{ sha1(json_encode($panelFilos)) }}" x-init="$dispatch('filos-panel-actualizados', @js($panelFilos))"></span>
            <div class="research-phylum-tree research-check-list" role="tree" aria-multiselectable="true" aria-label="{{ $filtroTexto('filo') }}" wire:ignore x-ref="arbolFilo" x-on:scroll.passive="actualizarVentana()" x-on:resize.window.debounce.100ms="actualizarVentana()">
                <div aria-hidden="true" :style="{height: espacioSuperior + 'px'}"></div>
                <template x-for="nodo in filasVisibles" :key="nodo.clave">
                    <div class="research-phylum-row" role="treeitem" :data-filo-clave="nodo.clave" :aria-level="nodo.profundidad + 1" :aria-expanded="nodo.tieneHijos ? expandidos.includes(nodo.clave) : null" :aria-selected="marcado(nodo)" :style="{'--nivel-filo': Math.min(nodo.profundidad, 5)}" :class="{'is-selected': marcado(nodo), 'is-lineage': enLinaje(nodo)}">
                        <button type="button" class="research-phylum-toggle" :class="{'is-leaf': !nodo.tieneHijos}" :disabled="!nodo.tieneHijos || cargando.includes(nodo.clave)" :aria-label="(expandidos.includes(nodo.clave) ? 'Contraer ' : 'Expandir ') + nodo.nombre" x-on:click="alternar(nodo)">
                            <svg viewBox="0 0 16 16" aria-hidden="true" :class="{'is-expanded': expandidos.includes(nodo.clave)}"><path d="m6 3 5 5-5 5"/></svg>
                        </button>
                        <label><input type="checkbox" :value="nodo.id" :checked="marcado(nodo)" :aria-label="nodo.nombre + ', ' + nodo.etiqueta" x-on:change="seleccionar(nodo, $event.target.checked, $event.target)" x-on:keydown="teclado($event, nodo)"><span x-text="nodo.nombre" :title="nodo.nombre + ' · ' + nodo.etiqueta"></span></label>

                    </div>
                </template>
                <div aria-hidden="true" :style="{height: espacioInferior + 'px'}"></div>
                <p class="research-filter-note" x-show="filas.length === 0">No hay filos públicos para el taxón seleccionado.</p>
            </div>

        </fieldset>

        @if(($aplicados['filtroLatMin'] ?? '') !== ($aplicados['filtroLatMax'] ?? '') || ($aplicados['filtroLonMin'] ?? '') !== ($aplicados['filtroLonMax'] ?? ''))<p class="research-filter-active research-filter-note">Área seleccionada en el mapa</p>@endif
        @if($activo('filtroFechaDesde') || $activo('filtroFechaHasta'))<p class="research-filter-active research-filter-note">Periodo: {{ $aplicados['filtroFechaDesde'] ?? '' }} — {{ $aplicados['filtroFechaHasta'] ?? '' }}</p>@endif
        @if($activo('filtroMes'))<p class="research-filter-active research-filter-note">Mes seleccionado: {{ ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][(int) $aplicados['filtroMes'] - 1] ?? $aplicados['filtroMes'] }}</p>@endif
        @if($activo('filtroElevDesde') || $activo('filtroElevHasta'))<p class="research-filter-active research-filter-note">Elevación: {{ $aplicados['filtroElevDesde'] ?? '' }} — {{ $aplicados['filtroElevHasta'] ?? '' }} m</p>@endif

        <x-catalogopublico::busqueda-universal :activo="$activo('filtroUniversal') || $activo('filtroRegistroId')" />

        @foreach($errors->messages() as $campo => $mensajesError)
            <p id="error-{{ $campo }}" class="research-filter-error" role="alert">{{ $mensajesError[0] }} La selección anterior se conserva.</p>
        @endforeach
        @if($this->avisoFiltrosDependientes !== '')<p class="research-filter-note" role="status">{{ $this->avisoFiltrosDependientes }}</p>@endif
        <div class="research-filter-actions" x-ref="acciones"><button type="button" wire:click="$wire.limpiarFiltros()" wire:loading.attr="disabled">{{ $filtroTexto('limpiar') }}</button><span wire:loading role="status">Actualizando…</span></div>
        </fieldset>
    </form>
</details>
