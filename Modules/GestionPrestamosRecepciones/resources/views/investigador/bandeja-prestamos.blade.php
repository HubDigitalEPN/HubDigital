<div class="p-6 space-y-6">

    <flux:heading size="xl" level="1" class="font-display">Mis préstamos</flux:heading>

    {{-- Barra de filtros --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por N.º préstamo..."
                icon="magnifying-glass"
                clearable />
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="estado" class="w-52">
                <flux:select.option value="">Todos los estados</flux:select.option>
                <flux:select.option value="activo">Activo</flux:select.option>
                <flux:select.option value="prorroga_solicitada">Prórroga solicitada</flux:select.option>
                <flux:select.option value="vencido">Vencido</flux:select.option>
                <flux:select.option value="en_revision">En revisión</flux:select.option>
                <flux:select.option value="cerrado">Cerrado</flux:select.option>
                <flux:select.option value="cerrado_con_observacion">Cerrado con observación</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="ordenCampo" class="w-52">
                <flux:select.option value="fecha_inicio">Ordenar por fecha inicio</flux:select.option>
                <flux:select.option value="fecha_vencimiento">Ordenar por vencimiento</flux:select.option>
            </flux:select>
            <flux:button
                wire:click="toggleOrden"
                variant="ghost"
                icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                title="{{ $ordenDireccion === 'asc' ? 'Ascendente' : 'Descendente' }}" />
            @if($busqueda !== '' || $estado !== '')
                <flux:button wire:click="limpiarFiltros" variant="ghost" icon="x-mark" title="Limpiar filtros" />
            @endif
        </div>
    </div>

    @php $filtroActivo = $busqueda !== '' || $estado !== ''; @endphp

    @if($prestamos->isEmpty() && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="archive-box" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin préstamos activos</flux:heading>
            <flux:text class="text-text-secondary mt-1">Tus préstamos aparecerán aquí una vez que el acta sea validada.</flux:text>
        </div>
    @elseif($prestamos->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="magnifying-glass" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin resultados</flux:heading>
            <flux:text class="text-text-secondary mt-1">No se encontraron préstamos con los filtros aplicados.</flux:text>
            <flux:button variant="ghost" class="mt-4" wire:click="limpiarFiltros">
                Limpiar filtros
            </flux:button>
        </div>
    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º préstamo</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Inicio</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Vencimiento</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($prestamos as $prestamo)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $prestamo->acta?->numero_prestamo ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-gestionprestamosrecepciones::prestamo-status-badge :estado="$prestamo->estado" />
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $prestamo->iniciado_en?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $prestamo->fecha_fin?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if($prestamo->estado === 'en_transito')
                                    <flux:button size="sm" variant="primary" icon="clipboard-document-check"
                                        wire:navigate href="{{ route('prestamos.investigador.prestamo.verificacion-entrega', $prestamo->id) }}">
                                        Reportar recepción
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="eye"
                                        wire:navigate href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->id) }}">
                                        Ver
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
