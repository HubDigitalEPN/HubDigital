<div class="space-y-4">
    <div>
        <flux:heading size="lg" class="text-blue-navy font-semibold">Exportar especímenes seleccionados</flux:heading>
        <p class="text-sm text-text-secondary mt-1">
            Filtra, marca con las casillas los especímenes que quieras y descárgalos como Excel formateado.
            La selección se conserva aunque cambies de página.
        </p>
    </div>

    @if($mensaje)<flux:callout variant="success" icon="check-circle" dismissible>{{ $mensaje }}</flux:callout>@endif
    @if($error)<flux:callout variant="danger" icon="exclamation-triangle" dismissible>{{ $error }}</flux:callout>@endif

    {{-- Filtros --}}
    <div class="rounded-lg border border-border bg-bg-main p-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <flux:field><flux:label>Taxón</flux:label><flux:input wire:model="fTaxon" wire:keydown.enter="buscar" placeholder="Ej. Morpho" /></flux:field>
        <flux:field><flux:label>Familia</flux:label><flux:input wire:model="fFamilia" wire:keydown.enter="buscar" placeholder="Ej. Nymphalidae" /></flux:field>
        <flux:field><flux:label>Localidad</flux:label><flux:input wire:model="fLocalidad" wire:keydown.enter="buscar" /></flux:field>
        <flux:field><flux:label>Colector</flux:label><flux:input wire:model="fColector" wire:keydown.enter="buscar" /></flux:field>
        <flux:field><flux:label>N.º de catálogo</flux:label><flux:input wire:model="fCatalogNumber" wire:keydown.enter="buscar" /></flux:field>
        <div class="grid grid-cols-2 gap-2">
            <flux:field><flux:label>Desde</flux:label><flux:input type="date" wire:model="fFechaDesde" /></flux:field>
            <flux:field><flux:label>Hasta</flux:label><flux:input type="date" wire:model="fFechaHasta" /></flux:field>
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:button variant="primary" icon="magnifying-glass" wire:click="buscar"
                     wire:loading.attr="disabled" wire:target="buscar" class="w-full sm:w-auto">
            <span wire:loading.remove wire:target="buscar">Buscar</span>
            <span wire:loading wire:target="buscar">Buscando…</span>
        </flux:button>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <span class="text-sm text-text-secondary">
                <strong class="text-text-primary">{{ number_format($this->contarSeleccionados()) }}</strong> seleccionados
            </span>
            @if($buscado && $total > 0)
                <flux:button size="sm" variant="ghost" icon="check-circle"
                             wire:click="seleccionarTodosFiltrados" wire:loading.attr="disabled" wire:target="seleccionarTodosFiltrados">
                    Seleccionar todos ({{ number_format($total) }})
                </flux:button>
            @endif
            @if($this->contarSeleccionados() > 0)
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarSeleccion">Limpiar</flux:button>
            @endif
            <flux:button variant="filled" icon="arrow-down-tray" wire:click="exportar"
                         wire:loading.attr="disabled" wire:target="exportar" class="w-full sm:w-auto">
                <span wire:loading.remove wire:target="exportar">Exportar a Excel</span>
                <span wire:loading wire:target="exportar">Generando…</span>
            </flux:button>
        </div>
    </div>

    {{-- Resultados --}}
    @if($buscado)
        @if(count($especimenes) === 0)
            <div class="rounded-lg border border-border bg-surface p-6 text-center text-sm text-text-secondary">
                No hay especímenes que coincidan con el filtro.
            </div>
        @else
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                {{-- Escritorio --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-blue-navy">
                            <tr>
                                <th class="px-3 py-2.5 w-10"></th>
                                <th class="px-3 py-2.5 text-left font-medium text-white">Código</th>
                                <th class="px-3 py-2.5 text-left font-medium text-white">Taxón</th>
                                <th class="px-3 py-2.5 text-left font-medium text-white">Localidad</th>
                                <th class="px-3 py-2.5 text-left font-medium text-white">Fecha</th>
                                <th class="px-3 py-2.5 text-left font-medium text-white">Colector</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($especimenes as $e)
                                <tr class="hover:bg-bg-main transition-colors">
                                    <td class="px-3 py-2.5"><flux:checkbox wire:model.live="seleccionados.{{ $e['id'] }}" /></td>
                                    <td class="px-3 py-2.5 font-medium text-text-primary">{{ $e['codigoCatalogo'] }}</td>
                                    <td class="px-3 py-2.5 text-text-primary italic">{{ $e['taxonNombre'] ?? $e['taxonVerbatim'] ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-text-secondary">{{ $e['localidad'] ?: '—' }}</td>
                                    <td class="px-3 py-2.5 text-text-secondary">{{ $e['fechaColecta'] ?: '—' }}</td>
                                    <td class="px-3 py-2.5 text-text-secondary">{{ $e['colector'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Móvil --}}
                <div class="md:hidden divide-y divide-border">
                    @foreach($especimenes as $e)
                        <label class="flex items-start gap-3 p-4">
                            <flux:checkbox wire:model.live="seleccionados.{{ $e['id'] }}" class="mt-1" />
                            <div class="min-w-0">
                                <div class="font-medium text-text-primary break-words">{{ $e['codigoCatalogo'] }}</div>
                                <div class="text-sm italic text-text-primary">{{ $e['taxonNombre'] ?? $e['taxonVerbatim'] ?? '—' }}</div>
                                <div class="text-xs text-text-secondary mt-0.5">{{ $e['localidad'] ?: '—' }} · {{ $e['fechaColecta'] ?: '—' }} · {{ $e['colector'] ?: '—' }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>

                {{-- Paginación --}}
                <div class="flex items-center justify-between gap-3 border-t border-border p-3">
                    <span class="text-xs text-text-secondary">Página {{ $page }} de {{ $totalPaginas }} · {{ number_format($total) }} resultados</span>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="prevPage" :disabled="$page <= 1">Anterior</flux:button>
                        <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="nextPage" :disabled="$page >= $totalPaginas">Siguiente</flux:button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="rounded-lg border border-dashed border-border bg-bg-main p-6 text-center text-sm text-text-secondary">
            Ingresa un filtro y presiona <strong>Buscar</strong> para listar especímenes.
        </div>
    @endif
</div>
