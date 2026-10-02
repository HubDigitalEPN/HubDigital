@props(['provincias' => [], 'filos' => [], 'preparaciones' => [], 'metodos' => [], 'biomas' => [], 'hayFiltrosActivos' => false])

<details class="research-sidebar" x-data="portalFiltros" wire:ignore.self>
    <summary title="Mostrar u ocultar filtros">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span>Filtros de investigación</span>
        @if($hayFiltrosActivos)<i aria-label="Hay filtros activos"></i>@endif
        <svg class="research-sidebar-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </summary>
    <form wire:submit="aplicarBorrador" class="research-filter-form" aria-label="Filtros de investigación">
        <p class="research-filter-note">La selección se aplica a tarjetas, registros y análisis.</p>
        <label><span>País</span><input type="search" wire:model="borradorFiltros.filtroPais" placeholder="País publicado" maxlength="120"></label>
        @if($errors->any())<p class="research-filter-error" role="alert">{{ $errors->first() }}</p>@endif
        <label><span>N.º de catálogo</span><input type="search" wire:model="borradorFiltros.filtroCatalogo" aria-invalid="{{ $errors->has('filtroCatalogo') ? 'true' : 'false' }}" aria-describedby="error-filtroCatalogo" placeholder="MEPN-INV-1, MEPN-INV-2" maxlength="240"></label>
        <label><span>Taxón</span><input type="search" wire:model="borradorFiltros.filtroTaxon" aria-invalid="{{ $errors->has('filtroTaxon') ? 'true' : 'false' }}" aria-describedby="error-filtroTaxon" placeholder="Nombre científico en cualquier rango" maxlength="120"></label>
        <label><span>Filo</span><select wire:model="borradorFiltros.filtroFiloId" aria-invalid="{{ $errors->has('filtroFiloId') ? 'true' : 'false' }}" aria-describedby="error-filtroFiloId"><option value="">Todos los filos</option>@foreach($filos as $filo)<option value="{{ $filo['id'] }}">{{ $filo['nombre_cientifico'] }}</option>@endforeach</select></label>
        <label><span>Provincia</span><select wire:model="borradorFiltros.filtroProvincia" aria-invalid="{{ $errors->has('filtroProvincia') ? 'true' : 'false' }}" aria-describedby="error-filtroProvincia"><option value="">Todas las provincias</option>@foreach($provincias as $provincia)<option value="{{ $provincia }}">{{ $provincia }}</option>@endforeach</select></label>
        <fieldset class="research-localities" x-data="{
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

        <details class="research-filter-section" open>
            <summary>Fecha y calidad del dato</summary>
            <div class="research-filter-fields">
                <div class="research-filter-pair"><label><span>Desde</span><input type="date" wire:model="borradorFiltros.filtroFechaDesde" aria-invalid="{{ $errors->has('filtroFechaDesde') ? 'true' : 'false' }}" aria-describedby="error-filtroFechaDesde"></label><label><span>Hasta</span><input type="date" wire:model="borradorFiltros.filtroFechaHasta" aria-invalid="{{ $errors->has('filtroFechaHasta') ? 'true' : 'false' }}" aria-describedby="error-filtroFechaHasta"></label></div>
                <label><span>Mes de colecta</span><select wire:model="borradorFiltros.filtroMes" aria-invalid="{{ $errors->has('filtroMes') ? 'true' : 'false' }}" aria-describedby="error-filtroMes"><option value="">Todos los meses</option>@foreach(['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $indice => $mes)<option value="{{ $indice + 1 }}">{{ $mes }}</option>@endforeach</select></label>
                <label><span>Identificación</span><select wire:model="borradorFiltros.filtroIdentificacion" aria-invalid="{{ $errors->has('filtroIdentificacion') ? 'true' : 'false' }}" aria-describedby="error-filtroIdentificacion"><option value="">Todos los rangos</option><option value="especie">Hasta especie</option><option value="superior">Rango superior</option></select></label>
                <label><span>Coordenadas</span><select wire:model="borradorFiltros.filtroSoloUbicacion" aria-invalid="{{ $errors->has('filtroSoloUbicacion') ? 'true' : 'false' }}" aria-describedby="error-filtroSoloUbicacion"><option value="">Todos los registros</option><option value="1">Solo coordenadas públicas</option></select></label>
                <label><span>Completitud para análisis</span><select wire:model="borradorFiltros.filtroDatosCompletos" aria-invalid="{{ $errors->has('filtroDatosCompletos') ? 'true' : 'false' }}" aria-describedby="error-filtroDatosCompletos"><option value="">Todos los registros</option><option value="1">Especie, fecha y coordenadas</option></select></label>
            </div>
        </details>

        <details class="research-filter-section">
            <summary>Ejemplar y colecta</summary>
            <div class="research-filter-fields">
                <label><span>Colector</span><input type="search" wire:model="borradorFiltros.filtroColector" aria-invalid="{{ $errors->has('filtroColector') ? 'true' : 'false' }}" aria-describedby="error-filtroColector" placeholder="Nombre del colector" maxlength="120"></label>
                @if($preparaciones !== [])<fieldset><legend>Preparación</legend><div class="research-check-list">@foreach($preparaciones as $preparacion)<label><input type="checkbox" wire:model="borradorFiltros.filtroPreparaciones" aria-invalid="{{ $errors->has('filtroPreparaciones') ? 'true' : 'false' }}" aria-describedby="error-filtroPreparaciones" value="{{ $preparacion }}">{{ $preparacion }}</label>@endforeach</div></fieldset>@endif
                @if($metodos !== [])<fieldset><legend>Método de recolección</legend><div class="research-check-list">@foreach($metodos as $metodo)<label><input type="checkbox" wire:model="borradorFiltros.filtroMetodos" aria-invalid="{{ $errors->has('filtroMetodos') ? 'true' : 'false' }}" aria-describedby="error-filtroMetodos" value="{{ $metodo }}">{{ $metodo }}</label>@endforeach</div></fieldset>@endif
                @if($biomas !== [])<fieldset><legend>Bioma</legend><div class="research-check-list">@foreach($biomas as $bioma)<label><input type="checkbox" wire:model="borradorFiltros.filtroBiomas" aria-invalid="{{ $errors->has('filtroBiomas') ? 'true' : 'false' }}" aria-describedby="error-filtroBiomas" value="{{ $bioma }}">{{ $bioma }}</label>@endforeach</div></fieldset>@endif
                <label><span>Hábitat o microhábitat</span><input type="search" wire:model="borradorFiltros.filtroHabitat" aria-invalid="{{ $errors->has('filtroHabitat') ? 'true' : 'false' }}" aria-describedby="error-filtroHabitat" placeholder="Bosque, hojarasca…" maxlength="120"></label>
                <label><span>Condición de tipo</span><input type="search" wire:model="borradorFiltros.filtroTipo" aria-invalid="{{ $errors->has('filtroTipo') ? 'true' : 'false' }}" aria-describedby="error-filtroTipo" placeholder="Holotype, paratype…" maxlength="120"></label>
                <label><span>Casta</span><input type="search" wire:model="borradorFiltros.filtroCasta" aria-invalid="{{ $errors->has('filtroCasta') ? 'true' : 'false' }}" aria-describedby="error-filtroCasta" placeholder="Worker, queen…" maxlength="120"></label>
                <label><span>Estadio de vida</span><input type="search" wire:model="borradorFiltros.filtroEstadio" aria-invalid="{{ $errors->has('filtroEstadio') ? 'true' : 'false' }}" aria-describedby="error-filtroEstadio" placeholder="Adult, larva…" maxlength="120"></label>
            </div>
        </details>

        <details class="research-filter-section">
            <summary>Rango espacial</summary>
            <div class="research-filter-fields">
                <p>Usa estos límites o selecciona un área en el mapa.</p>
                <div class="research-filter-pair"><label><span>Latitud mín.</span><input type="number" step="any" min="-90" max="90" wire:model="borradorFiltros.filtroLatMin" aria-invalid="{{ $errors->has('filtroLatMin') ? 'true' : 'false' }}" aria-describedby="error-filtroLatMin"></label><label><span>Latitud máx.</span><input type="number" step="any" min="-90" max="90" wire:model="borradorFiltros.filtroLatMax" aria-invalid="{{ $errors->has('filtroLatMax') ? 'true' : 'false' }}" aria-describedby="error-filtroLatMax"></label></div>
                <div class="research-filter-pair"><label><span>Longitud mín.</span><input type="number" step="any" min="-180" max="180" wire:model="borradorFiltros.filtroLonMin" aria-invalid="{{ $errors->has('filtroLonMin') ? 'true' : 'false' }}" aria-describedby="error-filtroLonMin"></label><label><span>Longitud máx.</span><input type="number" step="any" min="-180" max="180" wire:model="borradorFiltros.filtroLonMax" aria-invalid="{{ $errors->has('filtroLonMax') ? 'true' : 'false' }}" aria-describedby="error-filtroLonMax"></label></div>
                <div class="research-filter-pair"><label><span>Elevación desde</span><input type="number" wire:model="borradorFiltros.filtroElevDesde" aria-invalid="{{ $errors->has('filtroElevDesde') ? 'true' : 'false' }}" aria-describedby="error-filtroElevDesde" placeholder="m s. n. m."></label><label><span>Elevación hasta</span><input type="number" wire:model="borradorFiltros.filtroElevHasta" aria-invalid="{{ $errors->has('filtroElevHasta') ? 'true' : 'false' }}" aria-describedby="error-filtroElevHasta" placeholder="m s. n. m."></label></div>
            </div>
        </details>
        @foreach($errors->messages() as $campo => $mensajesError)
            <p id="error-{{ $campo }}" class="research-filter-error" role="alert">{{ $mensajesError[0] }} La selección anterior se conserva.</p>
        @endforeach
        <div class="research-filter-actions" x-ref="acciones"><button type="submit" wire:loading.attr="disabled">Aplicar filtros</button><button type="button" wire:click="limpiarFiltros" wire:loading.attr="disabled">Limpiar</button><span wire:loading role="status">Actualizando…</span></div>
    </form>
</details>
