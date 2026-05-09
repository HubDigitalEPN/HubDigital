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
                        <flux:button variant="primary" icon="check-circle"
                            wire:click="$set('showAprobacionModal', true)">
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

    {{-- Modal: formulario de aprobación --}}
    <flux:modal wire:model="showAprobacionModal" class="max-w-2xl">
        <div class="space-y-5 p-2">
            <flux:heading size="lg">Aprobar Solicitud de Préstamo</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Configura las condiciones del préstamo antes de generar el acta.
            </flux:text>
            <flux:separator />

            {{-- Tipo de préstamo --}}
            <flux:field>
                <flux:label>Tipo de Préstamo <span class="text-error">*</span></flux:label>
                <flux:select wire:model="tipoPrestamo">
                    <flux:select.option value="temporal">Temporal</flux:select.option>
                    <flux:select.option value="permanente">Permanente</flux:select.option>
                </flux:select>
                <flux:error name="tipoPrestamo" />
            </flux:field>

            {{-- Duración --}}
            <flux:field>
                <flux:label>Duración del Préstamo</flux:label>
                <div class="flex items-center gap-3 mt-1">
                    <input type="checkbox" wire:model.live="usarDuracionPropuesta" id="usar-propuesta"
                        class="rounded border-border text-science-blue focus:ring-science-blue" />
                    <label for="usar-propuesta" class="text-sm text-text-primary">
                        Usar duración propuesta por el investigador
                        <span class="font-medium">({{ $solicitud?->duracion_propuesta_meses }} meses)</span>
                    </label>
                </div>
                @if(!$usarDuracionPropuesta)
                    <div class="mt-2">
                        <flux:input type="number" wire:model="duracionPersonalizadaMeses"
                            min="1" max="60" placeholder="Duración en meses" />
                        <flux:error name="duracionPersonalizadaMeses" />
                    </div>
                @endif
            </flux:field>

            {{-- Condiciones generales --}}
            <flux:field>
                <flux:label>Condiciones Generales del Préstamo <span class="text-text-secondary text-xs">(opcional)</span></flux:label>
                <flux:textarea wire:model="condicionesGenerales" rows="3"
                    placeholder="Ej. Los especímenes deben ser manipulados con guantes y en ambiente controlado..." />
            </flux:field>

            {{-- Condiciones por espécimen --}}
            @if($solicitud?->items && $solicitud->items->count())
                <div class="space-y-3">
                    <flux:label>Condiciones por Espécimen <span class="text-text-secondary text-xs">(opcional)</span></flux:label>
                    @foreach($solicitud->items as $item)
                        <flux:field>
                            <flux:label class="font-mono text-xs text-text-secondary">{{ $item->especimen_codigo_externo }}</flux:label>
                            <flux:input wire:model="condicionesPorItem.{{ $item->id }}"
                                placeholder="Condición específica para este espécimen..." />
                        </flux:field>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showAprobacionModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" icon="check-circle" wire:click="aprobar"
                    wire:loading.attr="disabled" wire:target="aprobar"
                    wire:confirm="¿Confirmas la aprobación? Se generará el acta de préstamo.">
                    <flux:icon wire:loading wire:target="aprobar" name="arrow-path" class="animate-spin" />
                    Confirmar Aprobación
                </flux:button>
            </div>
        </div>
    </flux:modal>

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
