@props(['provincias' => [], 'filos' => [], 'preparaciones' => [], 'metodos' => [], 'biomas' => [], 'hayFiltrosActivos' => false, 'aplicados' => []])
@php
    $activo = static function (string $campo) use ($aplicados): bool {
        $valor = $aplicados[$campo] ?? '';
        return is_array($valor) ? array_any($valor, static fn ($item) => trim((string) $item) !== '') : trim((string) $valor) !== '';
    };
    $colectaActiva = array_any(['filtroColector', 'filtroPreparaciones', 'filtroMetodos', 'filtroBiomas', 'filtroHabitat', 'filtroTipo', 'filtroDisposicion', 'filtroCasta', 'filtroEstadio'], $activo);
@endphp

<details class="research-sidebar" x-data="portalFiltros" wire:ignore.self>
    <summary title="Mostrar u ocultar filtros">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span>Filtros de investigación</span>
        @if($hayFiltrosActivos)<i aria-label="Hay filtros activos"></i>@endif
        <svg class="research-sidebar-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </summary>
    <form wire:submit="aplicarBorrador" class="research-filter-form" aria-label="Filtros de investigación">
        <p class="research-filter-note">La selección se aplica a tarjetas, registros y análisis.</p>
        @if($errors->any())<p class="research-filter-error" role="alert">{{ $errors->first() }}</p>@endif
        <label @class(['research-filter-active' => $activo('filtroCatalogo')])><span>N.º de catálogo</span><input type="search" wire:model="borradorFiltros.filtroCatalogo" aria-invalid="{{ $errors->has('filtroCatalogo') ? 'true' : 'false' }}" aria-describedby="error-filtroCatalogo" placeholder="MEPN-INV-1, MEPN-INV-2" maxlength="240"></label>
        <label @class(['research-filter-active' => $activo('filtroTaxon')])><span>Taxón</span><input type="search" wire:model="borradorFiltros.filtroTaxon" aria-invalid="{{ $errors->has('filtroTaxon') ? 'true' : 'false' }}" aria-describedby="error-filtroTaxon" placeholder="Nombre científico en cualquier rango" maxlength="120"></label>
        <label @class(['research-filter-active' => $activo('filtroFiloId')])><span>Filo</span><select wire:model="borradorFiltros.filtroFiloId" aria-invalid="{{ $errors->has('filtroFiloId') ? 'true' : 'false' }}" aria-describedby="error-filtroFiloId"><option value="">Todos los filos</option>@foreach($filos as $filo)<option value="{{ $filo['id'] }}">{{ $filo['nombre_cientifico'] }}</option>@endforeach</select></label>
        <label @class(['research-filter-active' => $activo('filtroProvincia')])><span>Provincia</span><select wire:model="borradorFiltros.filtroProvincia" aria-invalid="{{ $errors->has('filtroProvincia') ? 'true' : 'false' }}" aria-describedby="error-filtroProvincia"><option value="">Todas las provincias</option>@foreach($provincias as $provincia)<option value="{{ $provincia }}">{{ ucfirst($provincia) }}</option>@endforeach</select></label>
        <label @class(['research-filter-active' => $activo('filtroProvinciaExcluida')])><span>Excluir provincia</span><select wire:model="borradorFiltros.filtroProvinciaExcluida"><option value="">No excluir provincia</option>@foreach($provincias as $provincia)<option value="{{ $provincia }}">{{ ucfirst($provincia) }}</option>@endforeach</select><small>Solo compara provincias públicas informadas.</small></label>
        <fieldset @class(['research-localities', 'research-filter-active' => $activo('filtroGeografias')]) x-data="{
            localidades: $wire.entangle('borradorFiltros.filtroGeografias'),
            agregarLocalidad() {
                const grupo = this.$el.closest('.research-localities');
                this.localidades = [...(this.localidades.length ? this.localidades : ['']), ''];
                this.$nextTick(() => { const campos = grupo.querySelectorAll('input'); campos[campos.length - 1]?.focus(); });
            },
            quitarLocalidad(indice) {
                const grupo = this.$el.closest('.research-localities');
                this.localidades = this.localidades.filter((_, i) => i !== indice);
                this.$nextTick(() => { const campos = grupo.querySelectorAll('input'); campos[Math.min(indice, campos.length - 1)]?.focus(); });
            }
        }">
            <legend>Localidades</legend>
            <template x-for="(localidad, indice) in (localidades.length ? localidades : [''])" :key="indice">
                <div class="research-locality-row">
                    <label><span x-text="'Localidad ' + (indice + 1)"></span><input type="search" x-model="localidades[indice]" aria-invalid="{{ $errors->has('filtroGeografias') ? 'true' : 'false' }}" aria-describedby="error-filtroGeografias" placeholder="Cantón, parroquia o sitio" maxlength="120"></label>
                    <button type="button" x-on:click="quitarLocalidad(indice)" :aria-label="'Quitar localidad ' + (indice + 1)" title="Quitar localidad">×</button>
                </div>
            </template>
            <button type="button" class="research-add-locality" x-on:click="agregarLocalidad()">Añadir localidad</button>
        </fieldset>

        <div class="research-filter-pair">
            <label @class(['research-filter-active' => $activo('filtroLatitud') || $activo('filtroLatMin') || $activo('filtroLatMax')])><span>Latitud</span><input type="number" step="any" min="-90" max="90" wire:model="borradorFiltros.filtroLatitud" aria-invalid="{{ $errors->has('filtroLatitud') ? 'true' : 'false' }}" placeholder="−90 a 90"></label>
            <label @class(['research-filter-active' => $activo('filtroLongitud') || $activo('filtroLonMin') || $activo('filtroLonMax')])><span>Longitud</span><input type="number" step="any" min="-180" max="180" wire:model="borradorFiltros.filtroLongitud" aria-invalid="{{ $errors->has('filtroLongitud') ? 'true' : 'false' }}" placeholder="−180 a 180"></label>
        </div>
        @if(($aplicados['filtroLatMin'] ?? '') !== ($aplicados['filtroLatMax'] ?? '') || ($aplicados['filtroLonMin'] ?? '') !== ($aplicados['filtroLonMax'] ?? ''))<p class="research-filter-active research-filter-note">Área seleccionada en el mapa</p>@endif
        @if($activo('filtroFechaDesde') || $activo('filtroFechaHasta'))<p class="research-filter-active research-filter-note">Periodo: {{ $aplicados['filtroFechaDesde'] ?? '' }} — {{ $aplicados['filtroFechaHasta'] ?? '' }}</p>@endif
        @if($activo('filtroMes'))<p class="research-filter-active research-filter-note">Mes seleccionado: {{ ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][(int) $aplicados['filtroMes'] - 1] ?? $aplicados['filtroMes'] }}</p>@endif
        @if($activo('filtroElevDesde') || $activo('filtroElevHasta'))<p class="research-filter-active research-filter-note">Elevación: {{ $aplicados['filtroElevDesde'] ?? '' }} — {{ $aplicados['filtroElevHasta'] ?? '' }} m</p>@endif

        <details @class(['research-filter-section', 'research-filter-active' => $colectaActiva]) @if($colectaActiva) open @endif>
            <summary>Ejemplar y colecta</summary>
            <div class="research-filter-fields">
                <label @class(['research-filter-active' => $activo('filtroColector')])><span>Colector</span><input type="search" wire:model="borradorFiltros.filtroColector" aria-invalid="{{ $errors->has('filtroColector') ? 'true' : 'false' }}" aria-describedby="error-filtroColector" placeholder="Nombre del colector" maxlength="120"></label>
                @if($preparaciones !== [])<fieldset @class(['research-filter-active' => $activo('filtroPreparaciones')])><legend>Preparación</legend><div class="research-check-list">@foreach($preparaciones as $preparacion)<label><input type="checkbox" wire:model="borradorFiltros.filtroPreparaciones" aria-invalid="{{ $errors->has('filtroPreparaciones') ? 'true' : 'false' }}" aria-describedby="error-filtroPreparaciones" value="{{ $preparacion }}">{{ $preparacion }}</label>@endforeach</div></fieldset>@endif
                @if($metodos !== [])<fieldset @class(['research-filter-active' => $activo('filtroMetodos')])><legend>Método de recolección</legend><div class="research-check-list">@foreach($metodos as $metodo)<label title="Código original: {{ $metodo }}"><input type="checkbox" wire:model="borradorFiltros.filtroMetodos" aria-invalid="{{ $errors->has('filtroMetodos') ? 'true' : 'false' }}" aria-describedby="error-filtroMetodos" value="{{ $metodo }}"><span>{{ \Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico::etiqueta($metodo) }} <small>({{ $metodo }})</small></span></label>@endforeach</div></fieldset>@endif
                @if($biomas !== [])<fieldset @class(['research-filter-active' => $activo('filtroBiomas')])><legend>Bioma</legend><div class="research-check-list">@foreach($biomas as $bioma)<label><input type="checkbox" wire:model="borradorFiltros.filtroBiomas" aria-invalid="{{ $errors->has('filtroBiomas') ? 'true' : 'false' }}" aria-describedby="error-filtroBiomas" value="{{ $bioma }}">{{ $bioma }}</label>@endforeach</div></fieldset>@endif
                <label @class(['research-filter-active' => $activo('filtroHabitat')])><span>Hábitat o microhábitat</span><input type="search" wire:model="borradorFiltros.filtroHabitat" aria-invalid="{{ $errors->has('filtroHabitat') ? 'true' : 'false' }}" aria-describedby="error-filtroHabitat" placeholder="Bosque, hojarasca…" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroTipo')])><span>Condición de tipo</span><input type="search" wire:model="borradorFiltros.filtroTipo" aria-invalid="{{ $errors->has('filtroTipo') ? 'true' : 'false' }}" aria-describedby="error-filtroTipo" placeholder="Holotype, paratype…" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroDisposicion')])><span>Disposición del material</span><input type="search" wire:model="borradorFiltros.filtroDisposicion" aria-invalid="{{ $errors->has('filtroDisposicion') ? 'true' : 'false' }}" aria-describedby="error-filtroDisposicion" placeholder="En la colección, prestado…" maxlength="120"><small>La disposición y el estado nomenclatural se consultan por separado.</small></label>
                <label @class(['research-filter-active' => $activo('filtroCasta')])><span>Casta</span><input type="search" wire:model="borradorFiltros.filtroCasta" aria-invalid="{{ $errors->has('filtroCasta') ? 'true' : 'false' }}" aria-describedby="error-filtroCasta" placeholder="Worker, queen…" maxlength="120"></label>
                <label @class(['research-filter-active' => $activo('filtroEstadio')])><span>Estadio de vida</span><input type="search" wire:model="borradorFiltros.filtroEstadio" aria-invalid="{{ $errors->has('filtroEstadio') ? 'true' : 'false' }}" aria-describedby="error-filtroEstadio" placeholder="Adulto, larva…" maxlength="120"><small>Acepta la etiqueta visible o el código original, como Adulto/adult.</small></label>
            </div>
        </details>

        @foreach($errors->messages() as $campo => $mensajesError)
            <p id="error-{{ $campo }}" class="research-filter-error" role="alert">{{ $mensajesError[0] }} La selección anterior se conserva.</p>
        @endforeach
        <div class="research-filter-actions" x-ref="acciones"><button type="submit" wire:loading.attr="disabled">Aplicar filtros</button><button type="button" wire:click="limpiarFiltros" wire:loading.attr="disabled">Limpiar</button><span wire:loading role="status">Actualizando…</span></div>
    </form>
</details>
