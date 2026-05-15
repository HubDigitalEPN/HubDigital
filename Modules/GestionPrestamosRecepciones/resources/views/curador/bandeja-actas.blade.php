<div class="p-6 space-y-8">

    <flux:heading size="xl" level="1" class="font-display">Bandeja de actas</flux:heading>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    {{-- Sección 1: Por Enviar --}}
    <div class="space-y-3">
        <div class="flex items-center gap-3">
            <flux:heading size="lg" level="2" class="font-display">Pendientes de envío</flux:heading>
            @if($actasPorEnviar->isNotEmpty())
                <flux:badge color="yellow" size="sm">{{ $actasPorEnviar->count() }}</flux:badge>
            @endif
        </div>

        @if($actasPorEnviar->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-8 text-center">
                <flux:icon name="paper-airplane" class="size-10 text-text-secondary mb-2" />
                <flux:text class="text-text-secondary text-sm">No hay actas pendientes de envío.</flux:text>
            </div>
        @else
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="!ps-4">N.º Préstamo</flux:table.column>
                        <flux:table.column class="px-4">Solicitud</flux:table.column>
                        <flux:table.column class="px-4">Estado</flux:table.column>
                        <flux:table.column class="px-4">Generada</flux:table.column>
                        <flux:table.column class="px-4">Acciones</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($actasPorEnviar as $acta)
                            <flux:table.row>
                                <flux:table.cell class="!ps-4 px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                    {{ $acta->numero_prestamo }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3 text-sm text-text-primary">
                                    {{ $acta->solicitud?->titulo_estudio ?? $acta->solicitud_prestamo_id }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3">
                                    <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                    {{ $acta->created_at->format('d/m/Y') }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            href="{{ route('prestamos.acta.ver', $acta->id) }}"
                                            target="_blank"
                                        >
                                            Vista previa
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            icon="paper-airplane"
                                            wire:click="enviarActa('{{ $acta->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="enviarActa('{{ $acta->id }}')"
                                            wire:confirm="¿Confirmas que deseas enviar el acta al investigador para su firma?"
                                        >
                                            <flux:icon wire:loading wire:target="enviarActa('{{ $acta->id }}')" name="arrow-path" class="animate-spin" />
                                            Enviar
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>

    {{-- Sección 2: Pendientes de Firma --}}
    <div class="space-y-3">
        <div class="flex items-center gap-3">
            <flux:heading size="lg" level="2" class="font-display">Esperando firma del investigador</flux:heading>
            @if($actasPendienteFirma->isNotEmpty())
                <flux:badge color="orange" size="sm">{{ $actasPendienteFirma->count() }}</flux:badge>
            @endif
        </div>

        @if($actasPendienteFirma->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-8 text-center">
                <flux:icon name="clock" class="size-10 text-text-secondary mb-2" />
                <flux:text class="text-text-secondary text-sm">No hay actas esperando firma del investigador.</flux:text>
            </div>
        @else
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="!ps-4">N.º Préstamo</flux:table.column>
                        <flux:table.column class="px-4">Solicitud</flux:table.column>
                        <flux:table.column class="px-4">Estado</flux:table.column>
                        <flux:table.column class="px-4">Enviada</flux:table.column>
                        <flux:table.column class="px-4">Vista previa</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($actasPendienteFirma as $acta)
                            <flux:table.row>
                                <flux:table.cell class="!ps-4 px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                    {{ $acta->numero_prestamo }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3 text-sm text-text-primary">
                                    {{ $acta->solicitud?->titulo_estudio ?? $acta->solicitud_prestamo_id }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3">
                                    <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                    {{ $acta->updated_at->format('d/m/Y') }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="document-text"
                                        href="{{ route('prestamos.acta.ver', $acta->id) }}"
                                        target="_blank"
                                    >
                                        Ver acta
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>

    {{-- Sección 3: Por Validar --}}
    <div class="space-y-3">
        <div class="flex items-center gap-3">
            <flux:heading size="lg" level="2" class="font-display">Pendientes de validación</flux:heading>
            @if($actasPorValidar->isNotEmpty())
                <flux:badge color="blue" size="sm">{{ $actasPorValidar->count() }}</flux:badge>
            @endif
        </div>

        @if($actasPorValidar->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-8 text-center">
                <flux:icon name="document-check" class="size-10 text-text-secondary mb-2" />
                <flux:text class="text-text-secondary text-sm">No hay actas firmadas esperando validación.</flux:text>
            </div>
        @else
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="!ps-4">N.º Préstamo</flux:table.column>
                        <flux:table.column class="px-4">Solicitud</flux:table.column>
                        <flux:table.column class="px-4">Estado</flux:table.column>
                        <flux:table.column class="px-4">Acta subida</flux:table.column>
                        <flux:table.column class="px-4">Acciones</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($actasPorValidar as $acta)
                            <flux:table.row>
                                <flux:table.cell class="!ps-4 px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                    {{ $acta->numero_prestamo }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3 text-sm text-text-primary">
                                    {{ $acta->solicitud?->titulo_estudio ?? $acta->solicitud_prestamo_id }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3">
                                    <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                    {{ $acta->updated_at->format('d/m/Y') }}
                                </flux:table.cell>
                                <flux:table.cell class="px-4 py-3">
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

</div>
