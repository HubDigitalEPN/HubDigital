<div class="p-6 space-y-6">

    <flux:heading size="xl" level="1" class="font-display">Bandeja de solicitudes</flux:heading>

    {{-- Barra de filtros --}}
    <div class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <flux:input
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por título o N.º solicitud..."
                icon="magnifying-glass"
                clearable
                class="flex-1" />
            <flux:input
                wire:model.live.debounce.300ms="busquedaInvestigador"
                placeholder="Buscar por investigador..."
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
                <flux:select.option value="fecha">Ordenar por fecha</flux:select.option>
                <flux:select.option value="titulo">Ordenar por título</flux:select.option>
            </flux:select>
            <flux:button
                wire:click="toggleOrden"
                variant="ghost"
                icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                title="{{ $ordenDireccion === 'asc' ? 'Ascendente' : 'Descendente' }}" />
            @if($busqueda !== '' || $estado !== '' || $busquedaInvestigador !== '')
                <flux:button wire:click="limpiarFiltros" variant="ghost" icon="x-mark" title="Limpiar filtros" />
            @endif
        </div>
    </div>

    @php $filtroActivo = $busqueda !== '' || $estado !== '' || $busquedaInvestigador !== ''; @endphp

    @if($solicitudes->isEmpty() && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="inbox" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin solicitudes</flux:heading>
            <flux:text class="text-text-secondary mt-1">No hay solicitudes enviadas por los investigadores todavía.</flux:text>
        </div>
    @elseif($solicitudes->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="magnifying-glass" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin resultados</flux:heading>
            <flux:text class="text-text-secondary mt-1">No se encontraron solicitudes con los filtros aplicados.</flux:text>
            <flux:button variant="ghost" class="mt-4" wire:click="limpiarFiltros">
                Limpiar filtros
            </flux:button>
        </div>
    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Título</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Investigador</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($solicitudes as $solicitud)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->numero_solicitud }}
                            </td>
                            <td class="px-4 py-3 font-medium text-text-primary">
                                {{ $solicitud->titulo_estudio }}
                            </td>
                            <td class="px-4 py-3 text-sm text-text-secondary">
                                {{ $investigadores->get($solicitud->investigador_id)?->name ?? $solicitud->investigador_id }}
                            </td>
                            <td class="px-4 py-3">
                                <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @if($solicitud->estado === 'enviada')
                                        <flux:button size="sm" variant="primary" icon="check-circle"
                                            wire:navigate href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->id) }}">
                                            Decidir
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="ghost" icon="eye"
                                            wire:navigate href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->id) }}">
                                            Revisar
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
