<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-prestamos') }}">
            Mis préstamos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $detalle->numeroPrestamo ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Alerta: préstamo activo, listo para registrar devolución --}}
    @if($detalle->estadoPrestamo->value === 'activo')
        <div class="rounded-lg border border-science-blue/40 bg-science-blue/5 p-5 flex flex-col sm:flex-row items-center sm:items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-science-blue/15">
                <flux:icon name="arrow-uturn-left" class="size-6 text-science-blue" />
            </div>
            <div class="flex-1 text-center sm:text-left">
                <p class="text-sm font-semibold text-text-primary">Préstamo activo</p>
                <p class="text-xs text-text-secondary mt-1">
                    Cuando hayas enviado los especímenes de vuelta, registra la devolución para notificar al curador.
                </p>
            </div>
            <flux:button variant="primary" wire:navigate
                href="{{ route('prestamos.investigador.prestamo.registrar-devolucion', $detalle->prestamoId) }}"
                class="shrink-0">
                Registrar devolución
            </flux:button>
        </div>
    @endif

    {{-- Alerta: especímenes en camino --}}
    @if($detalle->estadoPrestamo->value === 'en_transito')
        <div class="rounded-lg border border-warning/40 bg-warning/5 p-5 flex flex-col sm:flex-row items-center sm:items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-warning/15">
                <flux:icon name="truck" class="size-6 text-warning" />
            </div>
            <div class="flex-1 text-center sm:text-left">
                <p class="text-sm font-semibold text-text-primary">Especímenes en camino</p>
                <p class="text-xs text-text-secondary mt-1">
                    Cuando recibas los especímenes debes reportar su estado para activar el préstamo.
                </p>
            </div>
            <flux:button variant="primary" wire:navigate
                href="{{ route('prestamos.investigador.prestamo.verificacion-entrega', $detalle->prestamoId) }}"
                class="shrink-0">
                Reportar recepción
            </flux:button>
        </div>
    @endif

    {{-- Préstamo cerrado con observación --}}
    @php
        $observacionCierreLegacy = null;
        foreach ($timeline as $tlItem) {
            if (in_array($tlItem['evento']->tipo, ['PrestamoCerrado', 'PrestamoCerradoConObservacion'], true)) {
                $observacionCierreLegacy = $tlItem['evento']->datos['observacion'] ?? null;
                if ($observacionCierreLegacy !== null) {
                    break;
                }
            }
        }
    @endphp
    <x-gestionprestamosrecepciones::cierre-observacion-banner
        :estado="$detalle->estadoPrestamo->value"
        :verificacion="$verificacionCierre"
        :observacion-legacy="$observacionCierreLegacy" />

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Columna principal --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Solicitud --}}
            @if($detalle->solicitudId)
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                            <flux:icon name="document-text" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display flex-1">Solicitud</flux:heading>
                        <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$detalle->solicitudEstado" />
                    </div>
                    <div class="p-5">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Institución</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $detalle->institucionAdscripcion }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Título del estudio</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $detalle->tituloEstudio }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Duración propuesta</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $detalle->duracionPropuestaMeses }} meses</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            @endif

            {{-- Acta --}}
            @if($detalle->actaId)
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                            <flux:icon name="clipboard-document" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display flex-1">Acta de préstamo</flux:heading>
                        <x-gestionprestamosrecepciones::acta-status-badge :estado="$detalle->actaEstado" />
                    </div>
                    <div class="p-5">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Tipo</dt>
                                <dd class="font-medium text-text-primary mt-1 capitalize">{{ str_replace('_', ' ', $detalle->tipoPrestamo) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha inicio</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $detalle->fechaInicioActa?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha fin</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $detalle->fechaFinActa?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                            @if($detalle->condicionesGenerales)
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Condiciones generales</dt>
                                    <dd class="text-text-primary mt-1 leading-relaxed">{{ $detalle->condicionesGenerales }}</dd>
                                </div>
                            @endif
                        </dl>
                        <div class="mt-4">
                            <flux:button variant="ghost" icon="document-text" size="sm" wire:navigate
                                href="{{ route('prestamos.investigador.acta.detalle', $detalle->actaId) }}">
                                Ver acta y documentos
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Préstamo --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="archive-box" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display flex-1">Préstamo</flux:heading>
                    <x-gestionprestamosrecepciones::prestamo-status-badge :estado="$detalle->estadoPrestamo->value" />
                </div>
                <div class="p-5">
                    @php
                        $fin = $detalle->fechaFin;
                        $vencido = $fin && $fin < now();
                        $diasRestantes = $fin ? (int) abs(now()->startOfDay()->diffInDays($fin)) : null;
                    @endphp

                    @if($diasRestantes !== null)
                        <x-gestionprestamosrecepciones::plazo-devolucion-banner
                            :dias-restantes="$diasRestantes" :vencido="$vencido" :fecha-fin="$fin" />
                    @endif

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Inicio</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $detalle->iniciadoEn?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Vencimiento</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $detalle->fechaFin?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if($verificacion->existe)
                        <flux:separator class="my-4" />
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Verificación de entrega</p>
                            <div class="flex items-center gap-2">
                                @if($verificacion->resultado->value === 'sin_novedades')
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold bg-bio-green/10 text-bio-green border border-bio-green/20">
                                        <span class="size-1.5 rounded-full bg-bio-green"></span>
                                        Sin novedades
                                    </span>
                                    <span class="text-xs text-text-secondary">Todos los especímenes llegaron en buen estado.</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold bg-error/10 text-error border border-error/20">
                                        <span class="size-1.5 rounded-full bg-error"></span>
                                        Con novedades
                                    </span>
                                @endif
                            </div>
                            @foreach($verificacion->observaciones as $obs)
                                <div class="rounded-lg border border-border bg-bg-main px-3 py-2 mt-2">
                                    <p class="text-xs text-text-secondary font-mono">{{ $obs->codigoEspecimen }}</p>
                                    <p class="text-sm text-text-primary mt-0.5">{{ $obs->descripcion }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Columna derecha --}}
        <div class="space-y-6">

            {{-- Historial --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="clock" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display">Historial</flux:heading>
                </div>
                <div class="p-5">
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mb-4">
                        <span class="flex items-center gap-1.5 text-xs text-text-secondary">
                            <span class="size-2 rounded-full bg-science-blue"></span> Solicitud
                        </span>
                        <span class="flex items-center gap-1.5 text-xs text-text-secondary">
                            <span class="size-2 rounded-full bg-warning"></span> Acta
                        </span>
                        <span class="flex items-center gap-1.5 text-xs text-text-secondary">
                            <span class="size-2 rounded-full bg-bio-green"></span> Préstamo
                        </span>
                    </div>

                    @php
                        $etiquetas = [
                            'SolicitudPrestamoRegistrada'   => 'Solicitud registrada',
                            'SolicitudPrestamoEnviada'      => 'Solicitud enviada',
                            'SolicitudPrestamoAprobada'     => 'Solicitud aprobada',
                            'SolicitudPrestamoRechazada'    => 'Solicitud rechazada',
                            'SolicitudPrestamoObservada'    => 'Solicitud observada',
                            'ActaEnviada'                   => 'Acta enviada al investigador',
                            'ActaFirmadaSubida'             => 'Acta firmada subida',
                            'ActaDevueltaPorFirmaInvalida'  => 'Acta devuelta por el curador',
                            'ActaValidada'                  => 'Acta validada',
                            'PrestamoIniciado'              => 'Especímenes despachados',
                            'DocumentoExportacionSubido'    => 'Documento de exportación registrado',
                            'PrestamoHabilitadoParaEnvio'   => 'Especímenes despachados',
                            'VerificacionEntregaRegistrada' => 'Verificación de entrega registrada',
                            'VerificacionEntregaAprobada'   => 'Verificación de entrega aprobada',
                            'PrestamoActivado'              => 'Préstamo activo',
                            'DevolucionRegistrada'          => 'Devolución registrada',
                            'PrestamoCerrado'               => 'Préstamo cerrado',
                        ];
                        $colores = [
                            'solicitud' => '#1976D2',
                            'acta'      => '#E65100',
                            'prestamo'  => '#2E7D32',
                        ];
                    @endphp
                    @forelse($timeline as $i => $item)
                        @php
                            $titulo = $etiquetas[$item['evento']->tipo] ?? $item['evento']->tipo;
                            $color = $colores[$item['origen']] ?? '#1976D2';
                            $esUltimo = $i === count($timeline) - 1;
                        @endphp
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$item['evento']->ocurridoEn->format('d/m/Y H:i')"
                            :titulo="$titulo"
                            :color="$color"
                            :ultimo="$esUltimo" />
                    @empty
                        <flux:text class="text-xs text-text-secondary">Sin eventos registrados.</flux:text>
                    @endforelse
                </div>
            </div>

            {{-- Recordatorios --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="bell" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display">Recordatorios</flux:heading>
                </div>
                <div class="p-5">
                    @if(count($recordatorios) > 0)
                        <div class="space-y-2">
                            @foreach(collect($recordatorios)->sortByDesc('diasAntes')->values() as $recordatorio)
                                <div class="flex items-center gap-3 rounded-lg border border-border bg-bg-main px-3 py-2">
                                    <flux:icon name="bell" class="size-4 text-warning shrink-0" />
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-text-primary">
                                            {{ $recordatorio['diasAntes'] }} {{ $recordatorio['diasAntes'] === 1 ? 'día' : 'días' }} antes del vencimiento
                                        </p>
                                        <p class="text-xs text-text-secondary">{{ $recordatorio['fecha'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center gap-2 text-sm text-text-secondary">
                            <flux:icon name="information-circle" class="size-4 shrink-0" />
                            <span>No hay recordatorios programados.</span>
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>

</div>
