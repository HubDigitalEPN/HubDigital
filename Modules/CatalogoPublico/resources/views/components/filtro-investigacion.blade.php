@props(['filos' => [], 'preparaciones' => [], 'metodos' => [], 'biomas' => [], 'hayFiltrosActivos' => false, 'aplicados' => []])
@php
    $filtroTexto = static fn (string $clave): string => \App\Support\PortalCopy::text('catalogo.'.$clave);
    $activo = static function (string $campo) use ($aplicados): bool {
        $valor = $aplicados[$campo] ?? '';
        return is_array($valor) ? array_any($valor, static fn ($item) => trim((string) $item) !== '') : trim((string) $valor) !== '';
    };
    $colectaActiva = array_any(['filtroColector', 'filtroPreparaciones', 'filtroMetodos', 'filtroBiomas', 'filtroHabitat', 'filtroTipo', 'filtroDisposicion', 'filtroCasta', 'filtroEstadio'], $activo);
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
        @if($errors->any())<p class="research-filter-error" role="alert">{{ $errors->first() }}</p>@endif
        <x-catalogopublico::busqueda-predictiva campo="filtroCatalogo" :etiqueta="$filtroTexto('numero_catalogo')" :placeholder="$filtroTexto('catalogo_placeholder')" :maxlength="240" :activo="$activo('filtroCatalogo')" />
        <x-catalogopublico::busqueda-predictiva campo="filtroTaxon" :etiqueta="$filtroTexto('taxon')" :placeholder="$filtroTexto('taxon_placeholder')" :activo="$activo('filtroTaxon')" />
        @php($panelFilos = $this->arbolFiloPanel)
        <fieldset @class(['research-filter-active' => $activo('filtroFilos') || $activo('filtroTaxonId')]) wire:key="panel-filos" x-data="portalFiltroFilo(@js($panelFilos), @js(route('portal.filos')))" x-on:filos-panel-actualizados="actualizar($event.detail)">
            <legend>{{ $filtroTexto('filo') }}</legend>
            <span hidden wire:key="filos-datos-{{ sha1(json_encode($panelFilos)) }}" x-init="$dispatch('filos-panel-actualizados', @js($panelFilos))"></span>
            <div class="research-phylum-tree research-check-list" role="tree" aria-label="{{ $filtroTexto('filo') }}" wire:ignore x-ref="arbolFilo" x-on:scroll.passive="actualizarVentana()" x-on:resize.window.debounce.100ms="actualizarVentana()">
                <div aria-hidden="true" :style="{height: espacioSuperior + 'px'}"></div>
                <template x-for="nodo in filasVisibles" :key="nodo.clave">
                    <div class="research-phylum-row" role="treeitem" :data-filo-clave="nodo.clave" :aria-level="nodo.profundidad + 1" :aria-expanded="nodo.tieneHijos ? expandidos.includes(nodo.clave) : null" :aria-selected="marcado(nodo)" :style="{'--nivel-filo': Math.min(nodo.profundidad, 5)}" :class="{'is-selected': marcado(nodo)}">
                        <button type="button" class="research-phylum-toggle" :class="{'is-leaf': !nodo.tieneHijos}" :disabled="!nodo.tieneHijos || cargando.includes(nodo.clave)" :aria-label="(expandidos.includes(nodo.clave) ? 'Contraer ' : 'Expandir ') + nodo.nombre" x-on:click="alternar(nodo)">
                            <svg viewBox="0 0 16 16" aria-hidden="true" :class="{'is-expanded': expandidos.includes(nodo.clave)}"><path d="m6 3 5 5-5 5"/></svg>
                        </button>
                        <label><input type="checkbox" :value="nodo.id" :checked="marcado(nodo)" :aria-label="nodo.nombre + ', ' + nodo.etiqueta" x-on:change="seleccionar(nodo, $event.target.checked, $event.target)" x-on:keydown="teclado($event, nodo)"><span x-text="nodo.nombre" :title="nodo.nombre + ' · ' + nodo.etiqueta"></span></label>
                        <small x-text="Number(nodo.total).toLocaleString('es-EC')"></small>
                    </div>
                </template>
                <div aria-hidden="true" :style="{height: espacioInferior + 'px'}"></div>
                <p class="research-filter-note" x-show="filas.length === 0">No hay filos públicos para el taxón seleccionado.</p>
            </div>
            <p class="research-filter-error" x-cloak x-show="error" role="alert" x-text="error"></p>
        </fieldset>

        @if(($aplicados['filtroLatMin'] ?? '') !== ($aplicados['filtroLatMax'] ?? '') || ($aplicados['filtroLonMin'] ?? '') !== ($aplicados['filtroLonMax'] ?? ''))<p class="research-filter-active research-filter-note">Área seleccionada en el mapa</p>@endif
        @if($activo('filtroFechaDesde') || $activo('filtroFechaHasta'))<p class="research-filter-active research-filter-note">Periodo: {{ $aplicados['filtroFechaDesde'] ?? '' }} — {{ $aplicados['filtroFechaHasta'] ?? '' }}</p>@endif
        @if($activo('filtroMes'))<p class="research-filter-active research-filter-note">Mes seleccionado: {{ ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][(int) $aplicados['filtroMes'] - 1] ?? $aplicados['filtroMes'] }}</p>@endif
        @if($activo('filtroElevDesde') || $activo('filtroElevHasta'))<p class="research-filter-active research-filter-note">Elevación: {{ $aplicados['filtroElevDesde'] ?? '' }} — {{ $aplicados['filtroElevHasta'] ?? '' }} m</p>@endif

        <details @class(['research-filter-section', 'research-filter-active' => $colectaActiva]) @if($colectaActiva) open @endif>
            <summary>{{ $filtroTexto('colecta') }}</summary>
            <div class="research-filter-fields">
                <label @class(['research-filter-active' => $activo('filtroColector')])><span>{{ $filtroTexto('colector') }}</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroColector" aria-invalid="{{ $errors->has('filtroColector') ? 'true' : 'false' }}" aria-describedby="error-filtroColector" placeholder="{{ $filtroTexto('colector_placeholder') }}" maxlength="120"></label>
                @if($preparaciones !== [])<fieldset @class(['research-filter-active' => $activo('filtroPreparaciones')])><legend>{{ $filtroTexto('preparacion') }}</legend><div class="research-check-list">@foreach($preparaciones as $preparacion)<label><input type="checkbox" wire:model.live.debounce.400ms="borradorFiltros.filtroPreparaciones" aria-invalid="{{ $errors->has('filtroPreparaciones') ? 'true' : 'false' }}" aria-describedby="error-filtroPreparaciones" value="{{ $preparacion }}">{{ $preparacion }}</label>@endforeach</div></fieldset>@endif
                @if($metodos !== [])<fieldset @class(['research-filter-active' => $activo('filtroMetodos')])><legend>{{ $filtroTexto('metodo') }}</legend><div class="research-check-list">@foreach($metodos as $metodo)<label title="Código original: {{ $metodo }}"><input type="checkbox" wire:model.live.debounce.400ms="borradorFiltros.filtroMetodos" aria-invalid="{{ $errors->has('filtroMetodos') ? 'true' : 'false' }}" aria-describedby="error-filtroMetodos" value="{{ $metodo }}"><span>{{ \Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico::etiqueta($metodo) }} <small>({{ $metodo }})</small></span></label>@endforeach</div></fieldset>@endif
                @if($biomas !== [])<fieldset @class(['research-filter-active' => $activo('filtroBiomas')])><legend>{{ $filtroTexto('bioma') }}</legend><div class="research-check-list">@foreach($biomas as $bioma)<label><input type="checkbox" wire:model.live.debounce.400ms="borradorFiltros.filtroBiomas" aria-invalid="{{ $errors->has('filtroBiomas') ? 'true' : 'false' }}" aria-describedby="error-filtroBiomas" value="{{ $bioma }}">{{ $bioma }}</label>@endforeach</div></fieldset>@endif
                <label @class(['research-filter-active' => $activo('filtroHabitat')])><span>{{ $filtroTexto('habitat') }}</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroHabitat" aria-invalid="{{ $errors->has('filtroHabitat') ? 'true' : 'false' }}" aria-describedby="error-filtroHabitat" placeholder="{{ $filtroTexto('habitat_placeholder') }}" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroTipo')])><span>{{ $filtroTexto('tipo') }}</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroTipo" aria-invalid="{{ $errors->has('filtroTipo') ? 'true' : 'false' }}" aria-describedby="error-filtroTipo" placeholder="{{ $filtroTexto('tipo_placeholder') }}" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroDisposicion')])><span>{{ $filtroTexto('disposicion') }}</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroDisposicion" aria-invalid="{{ $errors->has('filtroDisposicion') ? 'true' : 'false' }}" aria-describedby="error-filtroDisposicion" placeholder="{{ $filtroTexto('disposicion_placeholder') }}" maxlength="120"><small>{{ $filtroTexto('disposicion_ayuda') }}</small></label>
                <label @class(['research-filter-active' => $activo('filtroCasta')])><span>{{ $filtroTexto('casta') }}</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroCasta" aria-invalid="{{ $errors->has('filtroCasta') ? 'true' : 'false' }}" aria-describedby="error-filtroCasta" placeholder="{{ $filtroTexto('casta_placeholder') }}" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroEstadio')])><span>{{ $filtroTexto('estadio') }}</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroEstadio" aria-invalid="{{ $errors->has('filtroEstadio') ? 'true' : 'false' }}" aria-describedby="error-filtroEstadio" placeholder="{{ $filtroTexto('estadio_placeholder') }}" maxlength="120"><small>{{ $filtroTexto('estadio_ayuda') }}</small></label>
            </div>
        </details>

        @foreach($errors->messages() as $campo => $mensajesError)
            <p id="error-{{ $campo }}" class="research-filter-error" role="alert">{{ $mensajesError[0] }} La selección anterior se conserva.</p>
        @endforeach
        @if($this->avisoFiltrosDependientes !== '')<p class="research-filter-note" role="status">{{ $this->avisoFiltrosDependientes }}</p>@endif
        <div class="research-filter-actions" x-ref="acciones"><button type="button" wire:click="$wire.limpiarFiltros()" wire:loading.attr="disabled">{{ $filtroTexto('limpiar') }}</button><span wire:loading role="status">Actualizando…</span></div>
        </fieldset>
    </form>
</details>
