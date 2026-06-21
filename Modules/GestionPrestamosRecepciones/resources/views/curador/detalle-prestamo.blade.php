<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.panel') }}">
            Panel
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Préstamo {{ $detalle->numeroPrestamo }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Columna principal --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Préstamo cerrado con observación --}}
            @php
                $observacionCierreLegacy = null;
                foreach ($historial->eventos as $evtCierre) {
                    if (in_array($evtCierre->tipo, ['PrestamoCerrado', 'PrestamoCerradoConObservacion'], true)) {
                        $observacionCierreLegacy = $evtCierre->datos['observacion'] ?? null;
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

            {{-- Préstamo --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="archive-box" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display flex-1">
                        Préstamo {{ $detalle->numeroPrestamo }}
                    </flux:heading>
                    <x-gestionprestamosrecepciones::prestamo-status-badge :estado="$detalle->estadoPrestamo" />
                </div>
                <div class="p-5">
                    @php
                        $hoy = new \DateTimeImmutable('today');
                        $diasRestantes = (int) $hoy->diff($detalle->fechaFin)->days;
                        $vencido = $detalle->fechaFin < $hoy;
                    @endphp
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de inicio</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $detalle->iniciadoEn->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de vencimiento</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $detalle->fechaFin->format('d/m/Y') }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Días restantes</dt>
                            <dd class="font-semibold mt-1 {{ $vencido ? 'text-error' : ($diasRestantes <= 30 ? 'text-warning' : 'text-bio-green') }}">
                                {{ $vencido ? 'Vencido hace ' . $diasRestantes . ' días' : $diasRestantes . ' días restantes' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Devolución registrada — pendiente de cierre --}}
            @if($detalle->estadoPrestamo->value === 'en_revision')
                <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 overflow-hidden">
                    <div class="px-5 py-4 border-b border-science-blue/20 flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-science-blue/15 shrink-0">
                            <flux:icon name="arrow-uturn-left" class="size-3.5 text-science-blue" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display text-science-blue">Devolución registrada por el investigador</flux:heading>
                    </div>
                    <div class="p-5 space-y-3">
                        <p class="text-sm text-text-secondary">
                            El investigador ha declarado que los especímenes fueron enviados de vuelta. Registra el resultado de la inspección para cerrar el préstamo.
                        </p>
                        <div class="pt-1">
                            <flux:button variant="primary" wire:navigate href="{{ route('prestamos.curador.prestamo.cerrar', $detalle->prestamoId) }}">
                                Registrar resultado y cerrar
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Trámite de exportación --}}
            @if($detalle->estadoPrestamo->value === 'pendiente_documento_ministerio')
                <div class="rounded-lg border border-warning/40 bg-warning/5 overflow-hidden">
                    <div class="px-5 py-4 border-b border-warning/20 flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-warning/20 shrink-0">
                            <flux:icon name="document-arrow-up" class="size-3.5 text-warning" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display text-warning">Trámite de exportación pendiente</flux:heading>
                    </div>
                    <div class="p-5 space-y-4">
                        <p class="text-sm text-text-secondary">
                            Sube el documento de aprobación del Ministerio del Ambiente para habilitar el envío de especímenes al exterior.
                        </p>
                        <flux:field>
                            <flux:label>Documento de aprobación del ministerio (PDF)</flux:label>
                            <div x-data="{ nombre: '' }">
                                <label for="upload-doc-exportacion-detalle"
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-medium text-text-primary shadow-sm hover:bg-bg-main transition-colors">
                                    <flux:icon name="paper-clip" class="size-4 shrink-0" />
                                    Seleccionar archivo PDF
                                </label>
                                <input id="upload-doc-exportacion-detalle" type="file"
                                    wire:model="documentoExportacion" accept=".pdf"
                                    x-on:change="nombre = $event.target.files[0]?.name ?? ''"
                                    class="sr-only" />
                                <p class="mt-1.5 text-xs text-text-secondary" x-text="nombre || 'Ningún archivo seleccionado'"></p>
                                <div wire:loading wire:target="documentoExportacion" class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
                                    <flux:icon name="arrow-path" class="animate-spin size-3" />
                                    Subiendo archivo...
                                </div>
                            </div>
                            <flux:error name="documentoExportacion" />
                        </flux:field>
                        <flux:button wire:click="habilitarEnvio" variant="primary"
                            wire:loading.attr="disabled" wire:target="habilitarEnvio">
                            <flux:icon wire:loading wire:target="habilitarEnvio" name="arrow-path" class="animate-spin" />
                            Habilitar envío
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- Verificación pendiente de aprobación --}}
            @if($detalle->estadoPrestamo->value === 'pendiente_aprobacion_verificacion' && $verificacion?->existe)
                <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 overflow-hidden">
                    <div class="px-5 py-4 border-b border-science-blue/20 flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-science-blue/15 shrink-0">
                            <flux:icon name="clipboard-document-check" class="size-3.5 text-science-blue" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display text-science-blue">Verificación de entrega — pendiente de aprobación</flux:heading>
                    </div>
                    <div class="p-5 space-y-3">
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
                        @if(count($verificacion->observaciones) > 0)
                            <div class="space-y-2">
                                <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Observaciones reportadas</p>
                                @foreach($verificacion->observaciones as $obs)
                                    <div class="rounded-lg border border-border bg-surface px-3 py-2">
                                        <p class="text-xs text-text-secondary font-mono">{{ $obs->codigoEspecimen }}</p>
                                        <p class="text-sm text-text-primary mt-0.5">{{ $obs->descripcion }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="pt-1">
                            <flux:button variant="primary" wire:navigate href="{{ route('prestamos.curador.prestamo.aprobar-verificacion', $detalle->prestamoId) }}">
                                Revisar verificación
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

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
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">N.º solicitud</dt>
                                <dd class="font-mono font-medium text-text-primary mt-1">{{ $detalle->numeroSolicitud }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Institución</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $detalle->institucionAdscripcion }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Título del estudio</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $detalle->tituloEstudio }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4">
                            <flux:button variant="ghost" icon="document-magnifying-glass" size="sm" wire:navigate
                                href="{{ route('prestamos.curador.solicitud.revisar', $detalle->solicitudId) }}">
                                Ver solicitud
                            </flux:button>
                        </div>
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
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">N.º acta</dt>
                                <dd class="font-mono font-medium text-text-primary mt-1">{{ $detalle->numeroPrestamo }}</dd>
                            </div>
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
                        </dl>
                        <div class="mt-4">
                            <flux:button variant="ghost" icon="document-text" size="sm" wire:navigate
                                href="{{ route('prestamos.curador.acta.validar', $detalle->actaId) }}">
                                Ver acta y documentos
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

        </div>

        {{-- Historial --}}
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
                        'PrestamoIniciado'              => 'Especímenes despachados',
                        'DocumentoExportacionSubido'    => 'Documento de exportación registrado',
                        'PrestamoHabilitadoParaEnvio'   => 'Especímenes despachados',
                        'ActaEnviada'                   => 'Acta enviada',
                        'ActaFirmadaSubida'             => 'Acta firmada subida',
                        'ActaValidada'                  => 'Acta validada',
                        'ActaDevueltaPorFirmaInvalida'  => 'Acta devuelta por el curador',
                        'VerificacionEntregaRegistrada' => 'Verificación de entrega registrada',
                        'VerificacionEntregaAprobada'   => 'Verificación de entrega aprobada',
                        'PrestamoActivado'              => 'Préstamo activo',
                        'DevolucionRegistrada'          => 'Devolución registrada por el investigador',
                        'PrestamoCerrado'               => 'Préstamo cerrado',
                    ];
                @endphp
                @forelse($historial->eventos as $i => $evento)
                    @php
                        $titulo = $etiquetas[$evento->tipo] ?? $evento->tipo;
                        $esUltimo = $i === count($historial->eventos) - 1;
                    @endphp
                    <x-gestionprestamosrecepciones::timeline-event
                        :fecha="$evento->ocurridoEn->format('d/m/Y H:i')"
                        :titulo="$titulo"
                        :ultimo="$esUltimo" />
                @empty
                    <flux:text class="text-xs text-text-secondary">Sin eventos registrados.</flux:text>
                @endforelse
            </div>
        </div>

    </div>

</div>
