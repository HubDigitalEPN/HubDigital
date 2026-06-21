<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.solicitudes') }}">
            Bandeja de solicitudes
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $solicitud?->numeroSolicitud ?? 'Revisar' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if(!$solicitud)
        <flux:callout variant="danger" icon="exclamation-triangle">Solicitud no encontrada.</flux:callout>
    @else

        {{-- Encabezado --}}
        <div>
            <flux:heading size="xl" level="1" class="font-display">{{ $solicitud->tituloEstudio }}</flux:heading>
            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                <p class="font-mono text-xs text-text-secondary">{{ $solicitud->numeroSolicitud }}</p>
                <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Columna principal --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Datos del estudio --}}
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                            <flux:icon name="document-text" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display">Datos del estudio</flux:heading>
                    </div>
                    <div class="p-5">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">N.º solicitud</dt>
                                <dd class="font-mono font-medium text-text-primary mt-1">{{ $solicitud->numeroSolicitud }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Investigador</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $nombreInvestigador }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Institución</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $solicitud->institucionAdscripcion }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Línea de investigación</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $solicitud->lineaInvestigacion }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Duración propuesta</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $solicitud->duracionPropuestaMeses }} meses</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Tipo de préstamo</dt>
                                <dd class="font-medium text-text-primary mt-1 capitalize">{{ $solicitud->alcancePrestamo ?? '—' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Propósito del préstamo</dt>
                                <dd class="text-text-primary mt-1 leading-relaxed">{{ $solicitud->propositoPrestamo }}</dd>
                            </div>
                            @if($solicitud->justificacionExtendida)
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Justificación para duración extendida</dt>
                                    <dd class="text-text-primary mt-1 leading-relaxed">{{ $solicitud->justificacionExtendida }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Especímenes --}}
                @if(count($solicitud->items))
                    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                                <flux:icon name="beaker" class="size-3.5" />
                            </div>
                            <flux:heading size="base" level="2" class="font-display flex-1">Especímenes solicitados</flux:heading>
                            <span class="text-xs bg-science-blue/10 text-science-blue px-2.5 py-1 rounded-full font-medium tabular-nums">
                                {{ count($solicitud->items) }} {{ count($solicitud->items) === 1 ? 'espécimen' : 'especímenes' }}
                            </span>
                        </div>
                        <div class="p-5 space-y-2">
                            @foreach($solicitud->items as $item)
                                <div class="flex items-center gap-3 rounded-lg border border-border bg-bg-main px-4 py-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-science-blue/10">
                                        <flux:icon name="beaker" class="size-4 text-science-blue" />
                                    </div>
                                    <p class="flex-1 text-sm font-mono font-medium text-text-primary">{{ $item->codigoExterno }}</p>
                                    <span class="text-xs text-text-secondary tabular-nums">
                                        Cant. <strong class="text-text-primary">{{ $item->cantidadSolicitada }}</strong>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

            {{-- Columna derecha --}}
            <div class="space-y-5">

                {{-- Panel de resolución (solo cuando enviada) --}}
                @if($solicitud->estado === 'enviada')
                    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                                <flux:icon name="check-badge" class="size-3.5" />
                            </div>
                            <flux:heading size="base" level="2" class="font-display">Resolución</flux:heading>
                        </div>
                        <div class="p-5 space-y-4">
                            <flux:text class="text-text-secondary text-sm">
                                Revisa la información y decide si apruebas la solicitud o la devuelves con observaciones.
                            </flux:text>
                            <div class="flex flex-col gap-2">
                                <flux:button variant="primary" icon="check-circle"
                                    wire:click="$set('showAprobacionModal', true)">
                                    Aprobar y generar acta
                                </flux:button>
                                <flux:button variant="ghost" icon="arrow-uturn-left"
                                    wire:click="$set('showMotivoModal', true)">
                                    Devolver con observaciones
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Historial --}}
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                            <flux:icon name="clock" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display">Historial</flux:heading>
                    </div>
                    <div class="p-5">
                        @php
                            $etiquetasSolicitud = [
                                'SolicitudPrestamoRegistrada' => 'Solicitud registrada',
                                'SolicitudPrestamoEnviada'    => 'Solicitud enviada',
                                'SolicitudPrestamoAprobada'   => 'Solicitud aprobada',
                                'SolicitudPrestamoRechazada'  => 'Solicitud rechazada',
                                'SolicitudPrestamoObservada'  => 'Solicitud observada',
                            ];
                            $eventosSolicitud = array_values(array_filter(
                                $historial->eventos,
                                fn ($e) => array_key_exists($e->tipo, $etiquetasSolicitud)
                            ));
                        @endphp
                        @forelse($eventosSolicitud as $i => $evento)
                            <x-gestionprestamosrecepciones::timeline-event
                                :fecha="$evento->ocurridoEn->format('d/m/Y H:i')"
                                :titulo="$etiquetasSolicitud[$evento->tipo]"
                                :ultimo="$i === count($eventosSolicitud) - 1" />
                        @empty
                            <flux:text class="text-xs text-text-secondary">Sin eventos registrados.</flux:text>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>
    @endif

    {{-- Modal: formulario de aprobación --}}
    <flux:modal wire:model="showAprobacionModal" class="max-w-2xl">
        <div class="space-y-5 p-2">
            <flux:heading size="lg">Aprobar solicitud de préstamo</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Configura las condiciones del préstamo antes de generar el acta.
            </flux:text>
            <flux:separator />

            <flux:field>
                <flux:label>Tipo de préstamo <span class="text-error">*</span></flux:label>
                <flux:select wire:model="tipoPrestamo">
                    <flux:select.option value="temporal">Temporal</flux:select.option>
                    <flux:select.option value="permanente">Permanente</flux:select.option>
                </flux:select>
                <flux:error name="tipoPrestamo" />
            </flux:field>

            <flux:field>
                <flux:label>Duración del préstamo</flux:label>
                <div class="flex items-center gap-3 mt-1">
                    <input type="checkbox" wire:model.live="usarDuracionPropuesta" id="usar-propuesta"
                        class="rounded border-border text-science-blue focus:ring-science-blue" />
                    <label for="usar-propuesta" class="text-sm text-text-primary">
                        Usar duración propuesta por el investigador
                        <span class="font-medium">({{ $solicitud?->duracionPropuestaMeses }} meses)</span>
                    </label>
                </div>
                @if(!$usarDuracionPropuesta)
                    <div
                        x-data="{ val: $wire.duracionPersonalizadaMeses }"
                        x-init="$watch('val', v => { $wire.duracionPersonalizadaMeses = parseInt(v) })"
                        class="mt-3 space-y-3"
                    >
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-3xl font-bold tabular-nums text-science-blue" x-text="val"></span>
                            <span class="text-sm text-text-secondary" x-text="val == 1 ? 'mes' : 'meses'"></span>
                        </div>
                        <input
                            type="range" x-model="val"
                            @change="$wire.duracionPersonalizadaMeses = parseInt(val)"
                            min="1" max="60" step="1"
                            class="w-full h-2 rounded-full appearance-none cursor-pointer bg-border focus:outline-none
                                   [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5
                                   [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:shadow
                                   [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:border-2
                                   [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-science-blue"
                            :style="`background: linear-gradient(to right, #1976D2 ${(val - 1) / 59 * 100}%, #E0E0E0 ${(val - 1) / 59 * 100}%)`"
                        />
                        <div class="flex justify-between text-[10px] text-text-secondary select-none">
                            <span>1 mes</span><span>12</span><span>24</span><span>36</span><span>48</span><span>60</span>
                        </div>
                        <flux:error name="duracionPersonalizadaMeses" />
                    </div>
                @endif
            </flux:field>

            <flux:field>
                <flux:label>Condiciones generales <span class="text-text-secondary text-xs">(opcional)</span></flux:label>
                <flux:textarea wire:model="condicionesGenerales" rows="3"
                    placeholder="Ej. Los especímenes deben ser manipulados con guantes y en ambiente controlado..." />
            </flux:field>

            @if($solicitud && count($solicitud->items))
                <div class="space-y-3">
                    <flux:label>Condiciones por espécimen <span class="text-text-secondary text-xs">(opcional)</span></flux:label>
                    @foreach($solicitud->items as $item)
                        <flux:field>
                            <flux:label class="font-mono text-xs text-text-secondary">{{ $item->codigoExterno }}</flux:label>
                            <flux:input wire:model="condicionesPorItem.{{ $item->itemPrestamoId }}"
                                placeholder="Condición específica para este espécimen..." />
                        </flux:field>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showAprobacionModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" icon="check-circle" wire:click="aprobar"
                    wire:loading.attr="disabled" wire:target="aprobar">
                    <flux:icon wire:loading wire:target="aprobar" name="arrow-path" class="animate-spin" />
                    Confirmar aprobación
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: motivo de observación --}}
    <flux:modal wire:model="showMotivoModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Devolver con observaciones</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Indica el motivo por el que la solicitud debe ser corregida por el investigador.
            </flux:text>
            <flux:field>
                <flux:label>Motivo de la observación</flux:label>
                <flux:textarea wire:model="motivoObservacion" rows="4"
                    placeholder="Describe las correcciones necesarias..." />
                <flux:error name="motivoObservacion" />
            </flux:field>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showMotivoModal', false)">Cancelar</flux:button>
                <flux:button variant="danger" wire:click="devolver"
                    wire:loading.attr="disabled" wire:target="devolver">
                    <flux:icon wire:loading wire:target="devolver" name="arrow-path" class="animate-spin" />
                    Devolver solicitud
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
