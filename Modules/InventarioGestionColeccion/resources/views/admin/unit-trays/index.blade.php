<div class="space-y-6 p-4 sm:p-6">
    <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">
        Asignación de unit trays
    </flux:heading>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($warningMessage)
        <flux:callout variant="warning" icon="exclamation-triangle" dismissible>{{ $warningMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    {{-- Contenido dependiente de la caja: se bloquea visualmente mientras cambia --}}
    <div class="relative space-y-6">
        {{-- Overlay de carga al cambiar de caja: evita ver datos de la caja anterior --}}
        <div
            wire:loading.flex
            wire:target="cajaSeleccionada"
            wire:key="overlay-cambio-caja"
            class="absolute inset-0 z-20 hidden items-center justify-center rounded-lg bg-bg-main/70 backdrop-blur-sm"
        >
            <div class="flex items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3 shadow-sm">
                <flux:icon name="arrow-path" class="size-5 animate-spin text-blue-navy" />
                <span class="text-sm font-medium text-text-primary">Cargando unit trays…</span>
            </div>
        </div>

    {{-- Paso 1: Unit trays en una caja disponible --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
        <flux:heading size="lg" class="text-blue-navy">1. Unit trays en una caja</flux:heading>

        <flux:field>
            <flux:label>Caja disponible</flux:label>
            <flux:select
                wire:model.live="cajaSeleccionada"
                wire:loading.attr="disabled"
                wire:target="cajaSeleccionada"
                placeholder="Selecciona una caja..."
            >
                @foreach($cajas as $caja)
                    <flux:select.option value="{{ $caja['id'] }}">{{ $caja['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        @if($cajaSeleccionada !== '')
            {{-- Contexto de la caja seleccionada + acción de crear --}}
            <div class="flex flex-col gap-3 rounded-lg bg-bg-main p-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2 text-sm text-text-primary">
                    <flux:icon name="archive-box" class="size-5 text-blue-navy" />
                    <span>Trabajando en <span class="font-medium">{{ $cajaSeleccionadaLabel }}</span></span>
                </div>
                <flux:button
                    variant="primary"
                    icon="plus"
                    wire:click="crearUnitTray"
                    class="w-full min-h-[44px] sm:w-auto"
                >
                    Nuevo unit tray
                </flux:button>
            </div>

            <p class="text-xs text-text-secondary">
                Los unit trays se numeran solos y se ordenan por su taxonomía (subfamilia → género → especie).
            </p>

            {{-- Tabla (desktop) --}}
            <div class="hidden md:block rounded-lg border border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-blue-navy">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-white">Taxonomía</th>
                                <th class="px-4 py-3 text-left font-medium text-white">N.°</th>
                                <th class="px-4 py-3 text-left font-medium text-white">Especímenes</th>
                                <th class="px-4 py-3 text-left font-medium text-white">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($unitTrays as $tray)
                                <tr class="hover:bg-bg-main transition-colors {{ $unitTraySeleccionado === $tray['unitTrayId'] ? 'bg-bg-main' : '' }}">
                                    <td class="px-4 py-3">
                                        <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-resumen
                                            :subfamilia="$tray['subfamilia']"
                                            :genero="$tray['genero']"
                                            :especie="$tray['especie']"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-text-secondary">{{ $tray['numero'] }}</td>
                                    <td class="px-4 py-3 text-text-secondary">{{ $tray['totalEspecimenes'] }}</td>
                                    <td class="px-4 py-3">
                                        <flux:button
                                            size="sm"
                                            variant="filled"
                                            wire:click="seleccionarUnitTray('{{ $tray['unitTrayId'] }}')"
                                            class="min-h-[44px]"
                                        >
                                            Asignar especímenes
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-text-secondary">
                                        Esta caja no tiene unit trays todavía.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tarjetas (móvil) --}}
            <div class="md:hidden space-y-3">
                @forelse($unitTrays as $tray)
                    <div class="rounded-lg border border-border bg-surface p-4 shadow-sm space-y-3 {{ $unitTraySeleccionado === $tray['unitTrayId'] ? 'ring-1 ring-blue-navy' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-resumen
                                :subfamilia="$tray['subfamilia']"
                                :genero="$tray['genero']"
                                :especie="$tray['especie']"
                            />
                            <span class="shrink-0 text-xs text-text-secondary">N.° {{ $tray['numero'] }}</span>
                        </div>
                        <dl class="space-y-1.5 text-sm">
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Especímenes">
                                {{ $tray['totalEspecimenes'] }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        </dl>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <flux:button
                                variant="filled"
                                wire:click="seleccionarUnitTray('{{ $tray['unitTrayId'] }}')"
                                class="w-full min-h-[44px]"
                            >
                                Asignar especímenes
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-border p-8 text-center text-text-secondary">
                        Esta caja no tiene unit trays todavía.
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    {{-- Paso 2: Asignar especímenes al unit tray seleccionado --}}
    @if($unitTraySeleccionado !== '')
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
            <flux:heading size="lg" class="text-blue-navy">2. Especímenes a asignar</flux:heading>
            <p class="text-sm text-text-secondary">
                Busca por código de catálogo o nombre científico y marca los especímenes que pertenecen al unit tray seleccionado. La clasificación de la caja se recalcula automáticamente.
            </p>

            <flux:field>
                <flux:label>Buscar espécimen</flux:label>
                <flux:input
                    wire:model.live.debounce.400ms="busquedaEspecimen"
                    icon="magnifying-glass"
                    placeholder="Código de catálogo o nombre científico..."
                />
                <flux:description>
                    La lista muestra los ya asignados a este tray más las primeras coincidencias. Refina la búsqueda para encontrar otros.
                </flux:description>
            </flux:field>

            <div
                wire:loading.flex
                wire:target="busquedaEspecimen"
                class="items-center gap-2 text-sm text-text-secondary"
            >
                <flux:icon name="arrow-path" class="size-4 animate-spin text-blue-navy" />
                <span>Buscando especímenes…</span>
            </div>

            <div class="max-h-96 overflow-y-auto rounded-lg border border-border divide-y divide-border">
                @forelse($especimenes as $especimen)
                    <label
                        wire:key="especimen-{{ $especimen['id'] }}"
                        class="flex items-center gap-3 px-4 py-3 hover:bg-bg-main cursor-pointer min-h-[44px]"
                    >
                        <flux:checkbox
                            wire:model.live="especimenesSeleccionados"
                            value="{{ $especimen['id'] }}"
                        />
                        <span class="shrink-0 font-medium text-text-primary">{{ $especimen['codigoCatalogo'] }}</span>
                        <span class="font-serif italic text-text-secondary">{{ $especimen['taxonNombre'] }}</span>
                        @if($especimen['unitTrayId'] === $unitTraySeleccionado)
                            <flux:badge size="sm" color="green" icon="check" class="ml-auto shrink-0">Ya en este tray</flux:badge>
                        @elseif($especimen['unitTrayId'])
                            <flux:badge size="sm" color="amber" class="ml-auto shrink-0">En otro unit tray</flux:badge>
                        @endif
                    </label>
                @empty
                    <div class="px-4 py-6 text-center text-text-secondary">
                        @if(trim($busquedaEspecimen) !== '')
                            Ningún espécimen coincide con «{{ $busquedaEspecimen }}».
                        @else
                            No hay especímenes registrados.
                        @endif
                    </div>
                @endforelse
            </div>

            {{-- Confirmación inline, junto a la acción: el callout superior queda fuera de
                 vista tras desplazar la lista, así que el feedback se repite aquí. --}}
            @if($successMessage)
                <div class="flex items-center gap-2 rounded-lg bg-success/10 px-3 py-2 text-sm font-medium text-bio-green">
                    <flux:icon name="check-circle" class="size-5" />
                    <span>{{ $successMessage }}</span>
                </div>
            @endif

            <div class="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-text-secondary">
                    <span class="font-medium text-text-primary">{{ count($especimenesSeleccionados) }}</span>
                    {{ count($especimenesSeleccionados) === 1 ? 'espécimen seleccionado' : 'especímenes seleccionados' }}
                </p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:button
                        variant="ghost"
                        wire:click="cancelarSeleccion"
                        class="w-full min-h-[44px] sm:w-auto"
                    >
                        Cancelar
                    </flux:button>
                    <flux:button
                        variant="primary"
                        icon="check"
                        wire:click="asignarEspecimenes"
                        wire:loading.attr="disabled"
                        wire:target="asignarEspecimenes"
                        class="w-full min-h-[44px] sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="asignarEspecimenes">Guardar asignación</span>
                        <span wire:loading wire:target="asignarEspecimenes">Guardando…</span>
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
    </div>
</div>
