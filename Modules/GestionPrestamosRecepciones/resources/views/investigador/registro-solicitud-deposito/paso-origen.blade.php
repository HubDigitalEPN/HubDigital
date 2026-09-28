<div class="space-y-4">
    <div class="border-b border-blue-navy/10 pb-3 sm:flex sm:items-baseline sm:gap-4">
        <flux:heading size="lg" level="2" class="shrink-0 font-display tracking-tight text-blue-navy">{{ \App\Support\WizardCopy::text('origen.titulo') }}</flux:heading>
        <flux:text class="mt-1 text-sm leading-5 text-text-secondary sm:mt-0">Selecciona la ubicación de recolección y la documentación disponible.</flux:text>
    </div>

    <p class="text-sm font-semibold text-blue-navy">Zona de recolección</p>

        <div class="flex flex-wrap gap-3">
            <div class="w-full sm:w-80">
                <label for="provincia-recoleccion" class="mb-1 block text-sm font-medium text-blue-navy">Provincia <span class="text-error">*</span></label>
                <select id="provincia-recoleccion" wire:change="seleccionarProvincia($event.target.value)" class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-blue-navy">
                    <option value="">Selecciona una provincia</option>
                    @foreach($provinciasCatalogo as $provinciaOpcion)
                        <option value="{{ $provinciaOpcion['nombre'] }}" @selected($provincia === $provinciaOpcion['nombre'])>{{ $provinciaOpcion['nombre'] }}</option>
                    @endforeach
                </select>
                <flux:error name="provincia" />
            </div>
            <div class="w-full sm:w-80">
                <label for="localidad-recoleccion" class="mb-1 block text-sm font-medium text-blue-navy">Localidad <span class="text-error">*</span></label>
                <input type="search" wire:model.live.debounce.350ms="busquedaLocalidadOrigen" @disabled(!$provincia) maxlength="120"
                    aria-label="Buscar localidad en la provincia seleccionada" placeholder="Buscar localidad…"
                    class="mb-2 w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-blue-navy disabled:opacity-50" />
                <select id="localidad-recoleccion" wire:key="localidades-{{ $provincia }}" wire:change="seleccionarLocalidadOrigen($event.target.value)" @disabled(!$provincia) class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-blue-navy disabled:opacity-50">
                    <option value="">Selecciona una localidad</option>
                    @foreach($localidadesOrigenCatalogo as $opcion)
                        <option value="{{ $opcion->codigo }}" @selected($localidadOrigenCodigo === $opcion->codigo)>{{ \App\Support\CatalogoLocalidadesEcuador::etiqueta($opcion) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-text-secondary">Escribe el nombre para buscar más localidades de esta provincia.</p>
                <flux:error name="localidadOrigenCodigo" />
            </div>
        </div>

        <div class="space-y-2">
            <p class="text-sm font-semibold text-blue-navy">Situación regulatoria <span class="text-error">*</span></p>
            <flux:error name="situacionRegulatoria" />
            <div class="grid max-w-4xl gap-3 sm:grid-cols-2">
                @foreach([
                    ['valor' => 'Posee permisos del MAE', 'icono' => 'document-check', 'descripcion' => \App\Support\WizardCopy::text('origen.con_permisos')],
                    ['valor' => 'Sin permisos del MAE', 'icono' => 'exclamation-triangle', 'descripcion' => \App\Support\WizardCopy::text('origen.sin_permisos')],
                ] as $opcion)
                    <button type="button" wire:click="$set('situacionRegulatoria', '{{ $opcion['valor'] }}')" data-situacion="{{ $opcion['valor'] }}" aria-pressed="{{ $situacionRegulatoria === $opcion['valor'] ? 'true' : 'false' }}"
                        class="flex min-h-20 items-start gap-3 rounded-lg border p-3 text-left transition {{ $situacionRegulatoria === $opcion['valor'] ? 'border-blue-navy bg-blue-navy/5' : 'border-border hover:border-science-blue' }}">
                        <flux:icon :name="$opcion['icono']" class="mt-0.5 size-5 shrink-0 text-science-blue" />
                        <span><strong class="block text-sm text-blue-navy">{{ $opcion['valor'] }}</strong><small class="mt-1 block text-xs leading-4 text-text-secondary">{{ $opcion['descripcion'] }}</small></span>
                    </button>
                @endforeach
            </div>
        </div>
</div>
