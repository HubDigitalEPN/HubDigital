<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-solicitudes') }}">
            Mis solicitudes
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $solicitud?->numero_solicitud ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if(!$solicitud)
        <flux:callout variant="danger" icon="exclamation-triangle">Solicitud no encontrada.</flux:callout>
    @else
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Info principal --}}
            <div class="lg:col-span-2 space-y-6">

                <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <flux:heading size="xl" level="1" class="font-display">{{ $solicitud->titulo_estudio }}</flux:heading>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                            @if(in_array($solicitud->estado, ['borrador', 'observada']))
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:navigate
                                    href="{{ route('prestamos.investigador.solicitud.editar', $solicitud->id) }}"
                                >
                                    Editar
                                </flux:button>
                            @endif
                        </div>
                    </div>
                    <flux:separator />

                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-text-secondary">N.º solicitud</dt>
                            <dd class="font-mono font-medium text-text-primary">{{ $solicitud->numero_solicitud }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Institución</dt>
                            <dd class="text-text-primary">{{ $solicitud->institucion_adscripcion }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Línea de investigación</dt>
                            <dd class="text-text-primary">{{ $solicitud->linea_investigacion }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Duración</dt>
                            <dd class="text-text-primary">{{ $solicitud->duracion_propuesta_meses }} meses</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-text-secondary">Propósito del préstamo</dt>
                            <dd class="text-text-primary mt-1">{{ $solicitud->proposito_prestamo }}</dd>
                        </div>
                        @if($solicitud->justificacion_extendida)
                            <div class="col-span-2">
                                <dt class="text-text-secondary">Justificación para duración extendida</dt>
                                <dd class="text-text-primary mt-1">{{ $solicitud->justificacion_extendida }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($solicitud->estado === 'observada' && $solicitud->comentario_curador)
                        <flux:separator />
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:heading size="sm">Observación del funcionario responsable</flux:heading>
                            <flux:text class="mt-1 text-sm">{{ $solicitud->comentario_curador }}</flux:text>
                        </flux:callout>
                    @endif
                </div>

                {{-- Especimenes --}}
                @if($solicitud->items && $solicitud->items->count())
                    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                        <div class="p-4">
                            <flux:heading size="lg" level="2" class="font-display">Especímenes solicitados</flux:heading>
                        </div>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="!ps-4">Código de espécimen</flux:table.column>
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

            {{-- Timeline lateral --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-2 h-fit">
                <flux:heading size="lg" level="2" class="font-display">Historial</flux:heading>
                <flux:separator />
                <div class="mt-3">
                    @php
                        $etiquetas = [
                            'SolicitudPrestamoRegistrada' => 'Solicitud registrada',
                            'SolicitudPrestamoEnviada'    => 'Solicitud enviada',
                            'SolicitudPrestamoAprobada'   => 'Solicitud aprobada',
                            'SolicitudPrestamoRechazada'  => 'Solicitud rechazada',
                            'SolicitudPrestamoObservada'  => 'Solicitud observada',
                        ];
                        $eventosSolicitud = array_values(array_filter(
                            $historial->eventos,
                            fn ($e) => array_key_exists($e->tipo, $etiquetas)
                        ));
                    @endphp
                    @forelse($eventosSolicitud as $i => $evento)
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$evento->ocurridoEn->format('d/m/Y H:i')"
                            :titulo="$etiquetas[$evento->tipo]"
                            :ultimo="$i === count($eventosSolicitud) - 1" />
                    @empty
                        <flux:text class="text-xs text-text-secondary">Sin eventos registrados.</flux:text>
                    @endforelse
                </div>
            </div>

        </div>
    @endif


</div>
