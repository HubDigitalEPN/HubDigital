<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-solicitudes') }}">
            Mis solicitudes
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $solicitud?->numeroSolicitud ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if(!$solicitud)
        <flux:callout variant="danger" icon="exclamation-triangle">Solicitud no encontrada.</flux:callout>
    @else

        {{-- Encabezado --}}
        <div>
            <flux:heading size="xl" level="1" class="font-display">
                {{ $solicitud->tituloEstudio }}
            </flux:heading>
            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                <p class="font-mono text-xs text-text-secondary">{{ $solicitud->numeroSolicitud }}</p>
                <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                @if(in_array($solicitud->estado, ['borrador', 'observada']))
                    <flux:button size="sm" variant="ghost" icon="pencil" wire:navigate
                        href="{{ route('prestamos.investigador.solicitud.editar', $solicitud->solicitudId) }}">
                        Editar
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Observación del curador --}}
        @if($solicitud->estado === 'observada' && $solicitud->comentarioCurador)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:heading size="sm">Observación del curador</flux:heading>
                <flux:text class="mt-1 text-sm">{{ $solicitud->comentarioCurador }}</flux:text>
            </flux:callout>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Columna principal --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Datos generales --}}
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
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Institución</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $solicitud->institucionAdscripcion }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Línea de investigación</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $solicitud->lineaInvestigacion }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Duración</dt>
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

            {{-- Timeline lateral --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden h-fit">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="clock" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display">Historial</flux:heading>
                </div>
                <div class="p-5">
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
