<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.solicitudes') }}">
            Bandeja de Solicitudes
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $solicitud?->numero_solicitud ?? 'Revisar' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if(!$solicitud)
        <flux:callout variant="danger" icon="exclamation-triangle">Solicitud no encontrada.</flux:callout>
    @else
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Detalle --}}
            <div class="lg:col-span-2 space-y-6">

                <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="xl" level="1" class="font-display">{{ $solicitud->titulo_estudio }}</flux:heading>
                        <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                    </div>
                    <flux:separator />

                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-text-secondary">N.º Solicitud</dt>
                            <dd class="font-mono font-medium text-text-primary">{{ $solicitud->numero_solicitud }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Investigador</dt>
                            <dd class="text-text-primary">{{ $nombreInvestigador }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Institución</dt>
                            <dd class="text-text-primary">{{ $solicitud->institucion_adscripcion }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Línea de Investigación</dt>
                            <dd class="text-text-primary">{{ $solicitud->linea_investigacion }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Duración Propuesta</dt>
                            <dd class="text-text-primary">{{ $solicitud->duracion_propuesta_meses }} meses</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-text-secondary">Propósito del Préstamo</dt>
                            <dd class="text-text-primary mt-1">{{ $solicitud->proposito_prestamo }}</dd>
                        </div>
                        @if($solicitud->justificacion_extendida)
                            <div class="col-span-2">
                                <dt class="text-text-secondary">Justificación para Duración Extendida</dt>
                                <dd class="text-text-primary mt-1">{{ $solicitud->justificacion_extendida }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Items --}}
                @if($solicitud->items && $solicitud->items->count())
                    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                        <div class="p-4">
                            <flux:heading size="lg" level="2" class="font-display">Especimenes Solicitados</flux:heading>
                        </div>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="!ps-4">Código de Espécimen</flux:table.column>
                                <flux:table.column class="px-4">Cantidad</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($solicitud->items as $item)
                                    <flux:table.row>
                                        <flux:table.cell class="!ps-4 px-4 py-3 font-mono text-sm whitespace-nowrap">{{ $item->especimen_codigo_externo }}</flux:table.cell>
                                        <flux:table.cell class="px-4 py-3">{{ $item->cantidad_solicitada }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif

            </div>

            {{-- Acciones del curador --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4 h-fit">
                <flux:heading size="lg" level="2" class="font-display">Resolución</flux:heading>
                <flux:separator />

                @if($solicitud->estado === 'enviada')
                    <flux:text class="text-text-secondary text-sm">
                        Revisa la información y decide si apruebas la solicitud o la devuelves con observaciones.
                    </flux:text>
                    <div class="flex flex-col gap-2">
                        <flux:button variant="primary" icon="check-circle" wire:click="aprobar"
                            wire:loading.attr="disabled" wire:target="aprobar"
                            wire:confirm="¿Confirmas la aprobación? Se generará el acta de préstamo.">
                            <flux:icon wire:loading wire:target="aprobar" name="arrow-path" class="animate-spin" />
                            Aprobar y Generar Acta
                        </flux:button>
                        <flux:button variant="ghost" icon="arrow-uturn-left"
                            wire:click="$set('showMotivoModal', true)">
                            Devolver con Observaciones
                        </flux:button>
                    </div>
                @else
                    <flux:callout variant="info" icon="information-circle">
                        Esta solicitud está en estado
                        <strong>{{ $solicitud->estado }}</strong> y no puede modificarse.
                    </flux:callout>
                @endif
            </div>

        </div>
    @endif

    {{-- Modal: motivo de observación --}}
    <flux:modal wire:model="showMotivoModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Devolver con Observaciones</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Indica el motivo por el que la solicitud debe ser corregida por el investigador.
            </flux:text>

            <flux:field>
                <flux:label>Motivo de la Observación</flux:label>
                <flux:textarea wire:model="motivoObservacion" rows="4"
                    placeholder="Describe las correcciones necesarias..." />
                <flux:error name="motivoObservacion" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showMotivoModal', false)">Cancelar</flux:button>
                <flux:button variant="danger" wire:click="devolver"
                    wire:loading.attr="disabled" wire:target="devolver">
                    <flux:icon wire:loading wire:target="devolver" name="arrow-path" class="animate-spin" />
                    Devolver Solicitud
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
