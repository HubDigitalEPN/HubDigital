@props(['provincias' => [], 'localidades' => [], 'filos' => [], 'preparaciones' => [], 'metodos' => [], 'biomas' => [], 'hayFiltrosActivos' => false, 'aplicados' => []])
@php
    $activo = static function (string $campo) use ($aplicados): bool {
        $valor = $aplicados[$campo] ?? '';
        return is_array($valor) ? array_any($valor, static fn ($item) => trim((string) $item) !== '') : trim((string) $valor) !== '';
    };
    $colectaActiva = array_any(['filtroColector', 'filtroPreparaciones', 'filtroMetodos', 'filtroBiomas', 'filtroHabitat', 'filtroTipo', 'filtroDisposicion', 'filtroCasta', 'filtroEstadio'], $activo);
@endphp

<details class="research-sidebar" wire:key="catalogo-filtros" x-data="portalFiltros" wire:ignore.self wire:loading.attr="inert">
    <summary title="Mostrar u ocultar filtros">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span>Filtros de investigación</span>
        @if($hayFiltrosActivos)<i aria-label="Hay filtros activos"></i>@endif
        <svg class="research-sidebar-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </summary>
    <form wire:submit="aplicarBorrador" class="research-filter-form" aria-label="Filtros de investigación">
        <fieldset class="research-filter-lock" wire:loading.attr="disabled">
        <p class="research-filter-note">Los filtros se aplican al cambiar un dato, en tarjetas, registros y análisis.</p>
        @if($errors->any())<p class="research-filter-error" role="alert">{{ $errors->first() }}</p>@endif
        <label @class(['research-filter-active' => $activo('filtroCatalogo')])><span>N.º de catálogo</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroCatalogo" aria-invalid="{{ $errors->has('filtroCatalogo') ? 'true' : 'false' }}" aria-describedby="error-filtroCatalogo" placeholder="MEPN-INV-1, MEPN-INV-2" maxlength="240"></label>
        <label @class(['research-filter-active' => $activo('filtroTaxon')])><span>Taxón</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroTaxon" aria-invalid="{{ $errors->has('filtroTaxon') ? 'true' : 'false' }}" aria-describedby="error-filtroTaxon" placeholder="Nombre científico en cualquier rango" maxlength="120"></label>
        <fieldset @class(['research-filter-active' => $activo('filtroFilos')])><legend>Filo</legend><small>Selecciona uno o varios; sin selección se incluyen todos.</small><div class="research-check-list">@foreach($filos as $filo)<label><input type="checkbox" wire:model.live="borradorFiltros.filtroFilos" value="{{ $filo['id'] }}">{{ $filo['nombre_cientifico'] }}</label>@endforeach</div></fieldset>
        <details @class(['research-filter-section', 'research-filter-active' => $activo('filtroProvincias')])><summary>Provincia @if($activo('filtroProvincias'))<small>({{ count($aplicados['filtroProvincias']) }})</small>@endif</summary><fieldset><legend class="sr-only">Provincias</legend><small>Puedes combinar varias provincias.</small><div class="research-check-list">@foreach($provincias as $provincia)<label><input type="checkbox" wire:model.live="borradorFiltros.filtroProvincias" value="{{ $provincia }}">{{ ucfirst($provincia) }}</label>@endforeach</div></fieldset></details>
        <div @class(['research-localities', 'research-filter-active' => $activo('filtroGeografias')])>
            <span id="etiqueta-localidad">Localidad</span>
            <button type="button" class="research-locality-picker" x-ref="elegirLocalidad" x-on:click="abrirLocalidades()" aria-labelledby="etiqueta-localidad valor-localidad" aria-haspopup="dialog" aria-controls="localidades-dialogo" aria-invalid="{{ $errors->has('filtroGeografias') ? 'true' : 'false' }}" aria-describedby="error-filtroGeografias">
                <span id="valor-localidad">{{ implode(' · ', $this->borradorFiltros['filtroGeografias'] ?: []) ?: 'Todas las localidades' }}</span><span aria-hidden="true">⌕</span>
            </button>
        </div>

        <div class="research-filter-pair">
            <label @class(['research-filter-active' => $activo('filtroLatitud') || $activo('filtroLatMin') || $activo('filtroLatMax')])><span>Latitud</span><input type="number" step="any" min="-90" max="90" wire:model.live.debounce.400ms="borradorFiltros.filtroLatitud" aria-invalid="{{ $errors->has('filtroLatitud') ? 'true' : 'false' }}" placeholder="−90 a 90"></label>
            <label @class(['research-filter-active' => $activo('filtroLongitud') || $activo('filtroLonMin') || $activo('filtroLonMax')])><span>Longitud</span><input type="number" step="any" min="-180" max="180" wire:model.live.debounce.400ms="borradorFiltros.filtroLongitud" aria-invalid="{{ $errors->has('filtroLongitud') ? 'true' : 'false' }}" placeholder="−180 a 180"></label>
        </div>
        @if(($aplicados['filtroLatMin'] ?? '') !== ($aplicados['filtroLatMax'] ?? '') || ($aplicados['filtroLonMin'] ?? '') !== ($aplicados['filtroLonMax'] ?? ''))<p class="research-filter-active research-filter-note">Área seleccionada en el mapa</p>@endif
        @if($activo('filtroFechaDesde') || $activo('filtroFechaHasta'))<p class="research-filter-active research-filter-note">Periodo: {{ $aplicados['filtroFechaDesde'] ?? '' }} — {{ $aplicados['filtroFechaHasta'] ?? '' }}</p>@endif
        @if($activo('filtroMes'))<p class="research-filter-active research-filter-note">Mes seleccionado: {{ ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][(int) $aplicados['filtroMes'] - 1] ?? $aplicados['filtroMes'] }}</p>@endif
        @if($activo('filtroElevDesde') || $activo('filtroElevHasta'))<p class="research-filter-active research-filter-note">Elevación: {{ $aplicados['filtroElevDesde'] ?? '' }} — {{ $aplicados['filtroElevHasta'] ?? '' }} m</p>@endif

        <details @class(['research-filter-section', 'research-filter-active' => $colectaActiva]) @if($colectaActiva) open @endif>
            <summary>Ejemplar y colecta</summary>
            <div class="research-filter-fields">
                <label @class(['research-filter-active' => $activo('filtroColector')])><span>Colector</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroColector" aria-invalid="{{ $errors->has('filtroColector') ? 'true' : 'false' }}" aria-describedby="error-filtroColector" placeholder="Nombre del colector" maxlength="120"></label>
                @if($preparaciones !== [])<fieldset @class(['research-filter-active' => $activo('filtroPreparaciones')])><legend>Preparación</legend><div class="research-check-list">@foreach($preparaciones as $preparacion)<label><input type="checkbox" wire:model.live.debounce.400ms="borradorFiltros.filtroPreparaciones" aria-invalid="{{ $errors->has('filtroPreparaciones') ? 'true' : 'false' }}" aria-describedby="error-filtroPreparaciones" value="{{ $preparacion }}">{{ $preparacion }}</label>@endforeach</div></fieldset>@endif
                @if($metodos !== [])<fieldset @class(['research-filter-active' => $activo('filtroMetodos')])><legend>Método de recolección</legend><div class="research-check-list">@foreach($metodos as $metodo)<label title="Código original: {{ $metodo }}"><input type="checkbox" wire:model.live.debounce.400ms="borradorFiltros.filtroMetodos" aria-invalid="{{ $errors->has('filtroMetodos') ? 'true' : 'false' }}" aria-describedby="error-filtroMetodos" value="{{ $metodo }}"><span>{{ \Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico::etiqueta($metodo) }} <small>({{ $metodo }})</small></span></label>@endforeach</div></fieldset>@endif
                @if($biomas !== [])<fieldset @class(['research-filter-active' => $activo('filtroBiomas')])><legend>Bioma</legend><div class="research-check-list">@foreach($biomas as $bioma)<label><input type="checkbox" wire:model.live.debounce.400ms="borradorFiltros.filtroBiomas" aria-invalid="{{ $errors->has('filtroBiomas') ? 'true' : 'false' }}" aria-describedby="error-filtroBiomas" value="{{ $bioma }}">{{ $bioma }}</label>@endforeach</div></fieldset>@endif
                <label @class(['research-filter-active' => $activo('filtroHabitat')])><span>Hábitat o microhábitat</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroHabitat" aria-invalid="{{ $errors->has('filtroHabitat') ? 'true' : 'false' }}" aria-describedby="error-filtroHabitat" placeholder="Bosque, hojarasca…" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroTipo')])><span>Condición de tipo</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroTipo" aria-invalid="{{ $errors->has('filtroTipo') ? 'true' : 'false' }}" aria-describedby="error-filtroTipo" placeholder="Holotype, paratype…" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroDisposicion')])><span>Disposición del material</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroDisposicion" aria-invalid="{{ $errors->has('filtroDisposicion') ? 'true' : 'false' }}" aria-describedby="error-filtroDisposicion" placeholder="En la colección, prestado…" maxlength="120"><small>La disposición y el estado nomenclatural se consultan por separado.</small></label>
                <label @class(['research-filter-active' => $activo('filtroCasta')])><span>Casta</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroCasta" aria-invalid="{{ $errors->has('filtroCasta') ? 'true' : 'false' }}" aria-describedby="error-filtroCasta" placeholder="Worker, queen…" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroEstadio')])><span>Estadio de vida</span><input type="search" wire:model.live.debounce.400ms="borradorFiltros.filtroEstadio" aria-invalid="{{ $errors->has('filtroEstadio') ? 'true' : 'false' }}" aria-describedby="error-filtroEstadio" placeholder="Adulto, larva…" maxlength="120"><small>Acepta la etiqueta visible o el código original, como Adulto/adult.</small></label>
            </div>
        </details>

        @foreach($errors->messages() as $campo => $mensajesError)
            <p id="error-{{ $campo }}" class="research-filter-error" role="alert">{{ $mensajesError[0] }} La selección anterior se conserva.</p>
        @endforeach
        @if($this->avisoFiltrosDependientes !== '')<p class="research-filter-note" role="status">{{ $this->avisoFiltrosDependientes }}</p>@endif
        <div class="research-filter-actions" x-ref="acciones"><button type="button" wire:click="$wire.limpiarFiltros()" wire:loading.attr="disabled">Limpiar Filtros</button><span wire:loading role="status">Actualizando…</span></div>
        </fieldset>
    </form>
    <dialog id="localidades-dialogo" class="research-locality-dialog" x-ref="dialogoLocalidades" wire:ignore.self aria-labelledby="titulo-localidades" x-on:cancel.prevent="cerrarLocalidades()" x-on:close="restaurarFocoLocalidades()" x-on:click="if ($event.target === $el && ($event.clientX < $el.getBoundingClientRect().left || $event.clientX > $el.getBoundingClientRect().right || $event.clientY < $el.getBoundingClientRect().top || $event.clientY > $el.getBoundingClientRect().bottom)) cerrarLocalidades()">
        <div x-ref="datosLocalidades" wire:loading.attr="inert" data-localidades="{{ json_encode(array_values($localidades), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
            <header class="research-locality-heading"><h2 id="titulo-localidades">Elegir localidades</h2><button type="button" x-on:click="cerrarLocalidades()" aria-label="Cerrar selector de localidad">×</button></header>
            <label class="research-locality-search"><span>Buscar localidad</span><input type="search" x-ref="buscarLocalidad" x-model="busquedaLocalidad" x-on:keydown.arrow-down.prevent="($refs.opcionesLocalidades.querySelector('ul input') || $refs.opcionesLocalidades.querySelector('button'))?.focus()" autocomplete="off" placeholder="Escribe parte del nombre"></label>
            <p class="research-locality-status" role="status" x-text="`${localidadesEncontradas.length.toLocaleString('es-EC')} localidades disponibles`"></p>
            <p class="research-locality-status" x-show="localidadesEncontradas.length > 100">Se muestran las primeras 100. Escribe un nombre para acotar la lista.</p>
            <div class="research-locality-options" x-ref="opcionesLocalidades" x-on:keydown="navegarLocalidades($event)">
                <button type="button" x-on:click="localidadesElegidas = []">Todas las localidades</button>
                <template x-if="localidadesAbiertas"><ul><template x-for="localidad in localidadesMostradas" :key="localidad"><li><label><input type="checkbox" x-model="localidadesElegidas" :value="localidad"><span x-text="localidad"></span></label></li></template></ul></template>
                <p x-show="localidadesEncontradas.length === 0">No hay localidades que coincidan con la búsqueda.</p>
            </div>
            <button type="button" class="research-locality-apply" x-on:click="aplicarLocalidades()" x-text="localidadesElegidas.length ? 'Aplicar ' + localidadesElegidas.length + ' localidades' : 'Incluir todas las localidades'">Aplicar localidades</button>
        </div>
    </dialog>
</details>
