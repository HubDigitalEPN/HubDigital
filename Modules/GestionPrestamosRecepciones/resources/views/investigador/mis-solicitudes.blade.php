<div class="space-y-6">

    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="font-display">Mis Solicitudes de Préstamo</flux:heading>
        <flux:button variant="primary" icon="plus" wire:navigate href="{{ route('prestamos.investigador.solicitud.crear') }}">
            Nueva Solicitud
        </flux:button>
    </div>

    @if($solicitudes->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="document-text" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Aún no tienes solicitudes</flux:heading>
            <flux:text class="text-text-secondary mt-1">Crea tu primera solicitud de préstamo para comenzar.</flux:text>
            <flux:button variant="primary" class="mt-4" wire:navigate href="{{ route('prestamos.investigador.solicitud.crear') }}">
                Crear solicitud
            </flux:button>
        </div>
    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>N.º Solicitud</flux:table.column>
                    <flux:table.column>Título del Estudio</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Acciones</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($solicitudes as $solicitud)
                        <flux:table.row>
                            <flux:table.cell class="font-mono text-xs text-text-secondary">
                                {{ $solicitud->numero_solicitud }}
                            </flux:table.cell>
                            <flux:table.cell class="font-medium text-text-primary">
                                {{ $solicitud->titulo_estudio }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-text-secondary">
                                {{ $solicitud->created_at->format('d/m/Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost" icon="eye"
                                        wire:navigate href="{{ route('prestamos.investigador.solicitud.detalle', $solicitud->id) }}">
                                        Ver
                                    </flux:button>
                                    @if(in_array($solicitud->estado, ['borrador', 'observada']))
                                        <flux:button size="sm" variant="ghost" icon="pencil"
                                            wire:navigate href="{{ route('prestamos.investigador.solicitud.editar', $solicitud->id) }}">
                                            Editar
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
