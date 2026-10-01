@props(['provincias' => [], 'filos' => [], 'preparaciones' => [], 'metodos' => [], 'biomas' => [], 'hayFiltrosActivos' => false])

<details class="research-sidebar" x-data="portalFiltros" wire:ignore.self>
    <summary title="Mostrar u ocultar filtros">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span>Filtros de investigación</span>
        @if($hayFiltrosActivos)<i aria-label="Hay filtros activos"></i>@endif
        <svg class="research-sidebar-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </summary>
    <form wire:submit="actualizarFiltros" class="research-filter-form" aria-label="Filtros de investigación">
        <p class="research-filter-note">La selección se aplica a tarjetas, registros y análisis.</p>
        @if($errors->any())<p class="research-filter-error" role="alert">{{ $errors->first() }}</p>@endif
        <label><span>N.º de catálogo</span><input type="search" wire:model="filtroCatalogo" placeholder="MEPN-INV-1, MEPN-INV-2" maxlength="240"></label>
        <label><span>Taxón</span><input type="search" wire:model="filtroTaxon" placeholder="Género o especie" maxlength="120"></label>
        <label><span>Filo</span><select wire:model="filtroFiloId"><option value="">Todos los filos</option>@foreach($filos as $filo)<option value="{{ $filo['id'] }}">{{ $filo['nombre_cientifico'] }}</option>@endforeach</select></label>
        <label><span>Provincia</span><select wire:model="filtroProvincia"><option value="">Todas las provincias</option>@foreach($provincias as $provincia)<option value="{{ $provincia }}">{{ $provincia }}</option>@endforeach</select></label>
        <label><span>Localidad</span><input type="search" wire:model="filtroGeografias.0" placeholder="Cantón, parroquia o sitio" maxlength="120"></label>

        <details class="research-filter-section" open>
            <summary>Fecha y calidad del dato</summary>
            <div class="research-filter-fields">
                <div class="research-filter-pair"><label><span>Desde</span><input type="date" wire:model="filtroFechaDesde"></label><label><span>Hasta</span><input type="date" wire:model="filtroFechaHasta"></label></div>
                <label><span>Mes de colecta</span><select wire:model="filtroMes"><option value="">Todos los meses</option>@foreach(['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $indice => $mes)<option value="{{ $indice + 1 }}">{{ $mes }}</option>@endforeach</select></label>
                <label><span>Identificación</span><select wire:model="filtroIdentificacion"><option value="">Todos los rangos</option><option value="especie">Hasta especie</option><option value="superior">Rango superior</option></select></label>
                <label><span>Coordenadas</span><select wire:model="filtroSoloUbicacion"><option value="">Todos los registros</option><option value="1">Solo coordenadas públicas</option></select></label>
                <label><span>Completitud para análisis</span><select wire:model="filtroDatosCompletos"><option value="">Todos los registros</option><option value="1">Especie, fecha y coordenadas</option></select></label>
            </div>
        </details>

        <details class="research-filter-section">
            <summary>Ejemplar y colecta</summary>
            <div class="research-filter-fields">
                <label><span>Colector</span><input type="search" wire:model="filtroColector" placeholder="Nombre del colector" maxlength="120"></label>
                @if($preparaciones !== [])<fieldset><legend>Preparación</legend><div class="research-check-list">@foreach($preparaciones as $preparacion)<label><input type="checkbox" wire:model="filtroPreparaciones" value="{{ $preparacion }}">{{ $preparacion }}</label>@endforeach</div></fieldset>@endif
                @if($metodos !== [])<fieldset><legend>Método de recolección</legend><div class="research-check-list">@foreach($metodos as $metodo)<label><input type="checkbox" wire:model="filtroMetodos" value="{{ $metodo }}">{{ $metodo }}</label>@endforeach</div></fieldset>@endif
                @if($biomas !== [])<fieldset><legend>Bioma</legend><div class="research-check-list">@foreach($biomas as $bioma)<label><input type="checkbox" wire:model="filtroBiomas" value="{{ $bioma }}">{{ $bioma }}</label>@endforeach</div></fieldset>@endif
                <label><span>Hábitat o microhábitat</span><input type="search" wire:model="filtroHabitat" placeholder="Bosque, hojarasca…" maxlength="120"></label>
                <label><span>Condición de tipo</span><input type="search" wire:model="filtroTipo" placeholder="Holotype, paratype…" maxlength="120"></label>
                <label><span>Casta</span><input type="search" wire:model="filtroCasta" placeholder="Worker, queen…" maxlength="120"></label>
                <label><span>Estadio de vida</span><input type="search" wire:model="filtroEstadio" placeholder="Adult, larva…" maxlength="120"></label>
            </div>
        </details>

        <details class="research-filter-section">
            <summary>Rango espacial</summary>
            <div class="research-filter-fields">
                <p>Usa estos límites o selecciona un área en el mapa.</p>
                <div class="research-filter-pair"><label><span>Latitud mín.</span><input type="number" step="any" min="-90" max="90" wire:model="filtroLatMin"></label><label><span>Latitud máx.</span><input type="number" step="any" min="-90" max="90" wire:model="filtroLatMax"></label></div>
                <div class="research-filter-pair"><label><span>Longitud mín.</span><input type="number" step="any" min="-180" max="180" wire:model="filtroLonMin"></label><label><span>Longitud máx.</span><input type="number" step="any" min="-180" max="180" wire:model="filtroLonMax"></label></div>
                <div class="research-filter-pair"><label><span>Elevación desde</span><input type="number" wire:model="filtroElevDesde" placeholder="m s. n. m."></label><label><span>Hasta</span><input type="number" wire:model="filtroElevHasta" placeholder="m s. n. m."></label></div>
            </div>
        </details>
        <div class="research-filter-actions" x-ref="acciones"><button type="submit" wire:loading.attr="disabled">Aplicar filtros</button><button type="button" wire:click="limpiarFiltros" wire:loading.attr="disabled">Limpiar</button><span wire:loading role="status">Actualizando…</span></div>
    </form>
</details>
