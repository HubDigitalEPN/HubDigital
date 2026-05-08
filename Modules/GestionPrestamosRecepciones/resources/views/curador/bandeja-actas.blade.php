<div class="space-y-6">

    <flux:heading size="xl" level="1">Actas Pendientes de Validación</flux:heading>

    @if($actas->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center">
            <flux:icon name="document-check" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin actas pendientes</flux:heading>
            <flux:text class="text-text-secondary mt-1">
                No hay actas firmadas esperando validación en este momento.
            </flux:text>
        </div>
    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>N.º Préstamo</flux:table.column>
                    <flux:table.column>Solicitud</flux:table.column>
                    <flux:table.column>Estado Acta</flux:table.column>
                    <flux:table.column>Subida</flux:table.column>
                    <flux:table.column>Acciones</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($actas as $acta)
                        <flux:table.row>
                            <flux:table.cell class="font-mono text-xs text-text-secondary">
                                {{ $acta->numero_prestamo }}
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-text-primary">
                                {{ $acta->solicitud?->titulo_estudio ?? $acta->solicitud_prestamo_id }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-text-secondary">
                                {{ $acta->created_at->format('d/m/Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="sm" variant="ghost" icon="magnifying-glass"
                                    wire:navigate href="{{ route('prestamos.curador.acta.validar', $acta->id) }}">
                                    Validar
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

</div>
