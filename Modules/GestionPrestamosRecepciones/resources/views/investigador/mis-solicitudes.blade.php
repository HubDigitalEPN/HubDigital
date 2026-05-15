<div class="p-6 space-y-6">

    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="font-display">Mis solicitudes de préstamo</flux:heading>
        <flux:button variant="primary" icon="plus" wire:navigate href="{{ route('prestamos.investigador.solicitud.crear') }}">
            Nueva solicitud
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
                    <flux:table.column class="!ps-4">N.º solicitud</flux:table.column>
                    <flux:table.column>Título del estudio</flux:table.column>
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
                            <flux:table.cell class="px-4 py-3">
                                @php $actaSolicitud = $actasPorSolicitud[$solicitud->id] ?? null; @endphp
                                <div class="flex flex-col gap-1">
                                    <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                                    @if($actaSolicitud && in_array($actaSolicitud->estado, ['pendiente_firma', 'pendiente_validacion']))
                                        <x-gestionprestamosrecepciones::acta-status-badge :estado="$actaSolicitud->estado" />
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->created_at->format('d/m/Y') }}
                            </flux:table.cell>
                            <flux:table.cell class="px-4 py-3">
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
                                    @if($solicitud->estado === 'borrador')
                                        <flux:button size="sm" variant="primary" icon="paper-airplane"
                                            wire:click="enviarSolicitud('{{ $solicitud->id }}')"
                                            wire:confirm="¿Enviar esta solicitud para revisión del curador?"
                                            wire:loading.attr="disabled">
                                            Enviar
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
