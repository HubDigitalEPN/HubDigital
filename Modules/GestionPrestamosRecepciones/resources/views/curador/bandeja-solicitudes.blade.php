<div class="space-y-6">

    <div>
        <flux:heading size="xl" level="1" class="font-display">Solicitudes</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">Solicitudes de préstamo enviadas por los investigadores.</flux:text>
    </div>

    {{-- Panel de filtros --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-bg-main border-b border-border">
            <flux:icon name="funnel" class="size-3.5 text-text-secondary" />
            <span class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Filtros</span>
            @if($busqueda !== '' || $estado !== '' || $busquedaInvestigador !== '')
                <button wire:click="limpiarFiltros" class="ml-auto text-xs font-medium text-science-blue hover:underline transition-colors">
                    Limpiar todo
                </button>
            @endif
        </div>
        <div class="px-4 py-3 space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <flux:input
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar por título o N.º solicitud..."
                    icon="magnifying-glass"
                    clearable
                    class="flex-1" />
                <flux:input
                    wire:model.live.debounce.300ms="busquedaInvestigador"
                    placeholder="Buscar por solicitante..."
                    icon="user"
                    clearable
                    class="flex-1" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:select wire:model.live="estado" class="w-44">
                    <flux:select.option value="">Todos los estados</flux:select.option>
                    <flux:select.option value="enviada">Enviada</flux:select.option>
                    <flux:select.option value="observada">Observada</flux:select.option>
                    <flux:select.option value="aprobada">Aprobada</flux:select.option>
                    <flux:select.option value="rechazada">Rechazada</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="ordenCampo" class="w-44">
                    <flux:select.option value="fecha">Por fecha</flux:select.option>
                    <flux:select.option value="titulo">Por título</flux:select.option>
                </flux:select>
                <flux:button
                    wire:click="toggleOrden"
                    variant="ghost"
                    icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                    title="{{ $ordenDireccion === 'asc' ? 'Ascendente' : 'Descendente' }}" />
            </div>
        </div>
    </div>

    @php $filtroActivo = $busqueda !== '' || $estado !== '' || $busquedaInvestigador !== ''; @endphp

    @if(count($solicitudes) === 0 && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center px-8 gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bg-main border border-border">
                <flux:icon name="inbox" class="size-8 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">Sin solicitudes</flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">No hay solicitudes enviadas por los investigadores todavía.</flux:text>
            </div>
        </div>

    @elseif(count($solicitudes) === 0)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center px-8 gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bg-main border border-border">
                <flux:icon name="magnifying-glass" class="size-8 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">Sin resultados</flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">No se encontraron solicitudes con los filtros aplicados.</flux:text>
            </div>
            <flux:button variant="ghost" wire:click="limpiarFiltros">Limpiar filtros</flux:button>
        </div>

    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[750px]">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                        <th class="px-4 py-3 text-left font-medium text-white w-64">Título</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Solicitante</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($solicitudes as $solicitud)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->numeroSolicitud }}
                            </td>
                            <td class="px-4 py-3 font-medium text-text-primary w-64">
                                <flux:tooltip content="{{ $solicitud->tituloEstudio }}">
                                    <span class="block truncate max-w-xs cursor-default">{{ $solicitud->tituloEstudio }}</span>
                                </flux:tooltip>
                            </td>
                            <td class="px-4 py-3 text-sm text-text-secondary">
                                {{ $solicitud->solicitanteNombre ?? $solicitud->investigadorId }}
                            </td>
                            <td class="px-4 py-3">
                                <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($solicitud->estado === 'enviada')
                                    <flux:button size="sm" variant="primary" icon="check-circle"
                                        wire:navigate href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->solicitudId) }}">
                                        Decidir
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="eye"
                                        wire:navigate href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->solicitudId) }}">
                                        Revisar
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

</div>
