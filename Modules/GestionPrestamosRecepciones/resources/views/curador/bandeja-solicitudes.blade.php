<div class="p-6 space-y-6">

    <flux:heading size="xl" level="1" class="font-display">Bandeja de Solicitudes</flux:heading>

    {{-- Filtro de estado --}}
    <div class="flex items-center gap-3">
        <flux:text class="text-sm text-text-secondary">Filtrar por estado:</flux:text>
        <flux:select wire:model.live="filtroEstado" class="w-48">
            <flux:select.option value="todos">Todos</flux:select.option>
            <flux:select.option value="enviada">Enviadas</flux:select.option>
            <flux:select.option value="aprobada">Aprobadas</flux:select.option>
            <flux:select.option value="observada">Observadas</flux:select.option>
            <flux:select.option value="rechazada">Rechazadas</flux:select.option>
        </flux:select>
    </div>

    @if($solicitudes->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="inbox" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin solicitudes</flux:heading>
            <flux:text class="text-text-secondary mt-1">No hay solicitudes con el filtro seleccionado.</flux:text>
        </div>
    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="!ps-4">N.º Solicitud</flux:table.column>
                    <flux:table.column>Título</flux:table.column>
                    <flux:table.column>Investigador</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Acciones</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($solicitudes as $solicitud)
                        <flux:table.row>
                            <flux:table.cell class="!ps-4 px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->numero_solicitud }}
                            </flux:table.cell>
                            <flux:table.cell class="px-4 py-3 font-medium text-text-primary">
                                {{ $solicitud->titulo_estudio }}
                            </flux:table.cell>
                            <flux:table.cell class="px-4 py-3 text-sm text-text-secondary">
                                {{ $investigadores->get($solicitud->investigador_id)?->name ?? $solicitud->investigador_id }}
                            </flux:table.cell>
                            <flux:table.cell class="px-4 py-3">
                                <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                            </flux:table.cell>
                            <flux:table.cell class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->created_at->format('d/m/Y') }}
                            </flux:table.cell>
                            <flux:table.cell class="px-4 py-3 pr-6 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <flux:button size="sm" variant="ghost" icon="eye"
                                        wire:navigate href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->id) }}">
                                        Revisar
                                    </flux:button>
                                    @if($solicitud->estado === 'enviada')
                                        <flux:button size="sm" variant="primary" icon="check-circle"
                                            wire:navigate href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->id) }}">
                                            Decidir
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

</div>
