<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.panel') }}">
            Panel
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Préstamo {{ $prestamo->numeroPrestamo }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Info principal --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Préstamo --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <flux:heading size="xl" level="1" class="font-display">
                        Préstamo {{ $prestamo->numeroPrestamo }}
                    </flux:heading>
                    <x-gestionprestamosrecepciones::prestamo-status-badge :estado="$prestamo->estado" />
                </div>
                <flux:separator />
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-secondary">Fecha de inicio</dt>
                        <dd class="text-text-primary">{{ $prestamo->iniciadoEn->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Fecha de vencimiento</dt>
                        <dd class="text-text-primary">{{ $prestamo->fechaFin->format('d/m/Y') }}</dd>
                    </div>
                    @php
                        $hoy = new \DateTimeImmutable('today');
                        $diasRestantes = (int) $hoy->diff($prestamo->fechaFin)->days;
                        $vencido = $prestamo->fechaFin < $hoy;
                    @endphp
                    <div class="col-span-2">
                        <dt class="text-text-secondary">Días restantes</dt>
                        <dd class="font-medium {{ $vencido ? 'text-error' : 'text-text-primary' }}">
                            {{ $vencido ? 'Vencido hace ' . $diasRestantes . ' días' : $diasRestantes . ' días' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Verificación de entrega pendiente de aprobación --}}
            @if($prestamo->estado === 'pendiente_aprobacion_verificacion' && $verificacion)
                <div class="rounded-lg border border-[#90CAF9] bg-[#E3F2FD] p-5 space-y-4">
                    <div class="flex items-center gap-2">
                        <flux:icon name="clipboard-document-check" class="size-5 text-[#1565C0] shrink-0" />
                        <p class="text-sm font-semibold text-[#1565C0]">Verificación de entrega — pendiente de aprobación</p>
                    </div>
                    <flux:separator />

                    {{-- Estado del envío --}}
                    <div class="flex items-center gap-2">
                        @if($verificacion->estadoEnvio()->value === 'sin_novedades')
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#E8F5E9] text-[#2E7D32] border border-[#A5D6A7]">
                                <span class="size-1.5 rounded-full bg-[#2E7D32]"></span>
                                Sin novedades
                            </span>
                            <span class="text-xs text-[#1565C0]">Todos los especímenes llegaron en buen estado.</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#FFEBEE] text-[#C62828] border border-[#EF9A9A]">
                                <span class="size-1.5 rounded-full bg-[#C62828]"></span>
                                Con novedades
                            </span>
                        @endif
                    </div>

                    {{-- Observaciones por especímen --}}
                    @if(count($verificacion->observaciones()) > 0)
                        <div class="space-y-2">
                            <p class="text-xs font-medium text-[#1565C0] uppercase tracking-wide">Observaciones reportadas</p>
                            @foreach($verificacion->observaciones() as $obs)
                                <div class="rounded-lg border border-[#90CAF9] bg-white px-3 py-2">
                                    <p class="text-xs text-text-secondary font-mono">{{ $obs->itemPrestamoId }}</p>
                                    <p class="text-sm text-text-primary mt-0.5">{{ $obs->descripcion }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Acción --}}
                    <div class="flex items-center gap-3 pt-1">
                        <flux:button variant="primary" wire:click="aprobar" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="aprobar">Aprobar verificación</span>
                            <span wire:loading wire:target="aprobar">Aprobando…</span>
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- Solicitud --}}
            @if($acta?->solicitud)
                <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3">
                    <div class="flex items-center justify-between gap-4">
                        <flux:heading size="lg" level="2" class="font-display">Solicitud</flux:heading>
                        <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$acta->solicitud->estado" />
                    </div>
                    <flux:separator />
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-text-secondary">N.º solicitud</dt>
                            <dd class="font-mono font-medium text-text-primary">{{ $acta->solicitud->numero_solicitud }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Institución</dt>
                            <dd class="text-text-primary">{{ $acta->solicitud->institucion_adscripcion }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-text-secondary">Título del estudio</dt>
                            <dd class="font-medium text-text-primary">{{ $acta->solicitud->titulo_estudio }}</dd>
                        </div>
                    </dl>
                    <flux:button variant="ghost" icon="document-magnifying-glass" size="sm" wire:navigate
                        href="{{ route('prestamos.curador.solicitud.revisar', $acta->solicitud_prestamo_id) }}">
                        Ver solicitud
                    </flux:button>
                </div>
            @endif

            {{-- Acta --}}
            @if($acta)
                <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3">
                    <div class="flex items-center justify-between gap-4">
                        <flux:heading size="lg" level="2" class="font-display">Acta de préstamo</flux:heading>
                        <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                    </div>
                    <flux:separator />
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-text-secondary">N.º acta</dt>
                            <dd class="font-mono font-medium text-text-primary">{{ $acta->numero_prestamo }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Tipo</dt>
                            <dd class="text-text-primary capitalize">{{ str_replace('_', ' ', $acta->tipo_prestamo) }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Fecha inicio</dt>
                            <dd class="text-text-primary">{{ $acta->fecha_inicio?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Fecha fin</dt>
                            <dd class="text-text-primary">{{ $acta->fecha_fin?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                    <flux:button variant="ghost" icon="document-text" size="sm" wire:navigate
                        href="{{ route('prestamos.curador.acta.validar', $acta->id) }}">
                        Ver acta y documentos
                    </flux:button>
                </div>
            @endif

        </div>

        {{-- Timeline historial --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-2 h-fit">
            <flux:heading size="lg" level="2" class="font-display">Historial</flux:heading>
            <flux:separator />
            <div class="mt-3">
                @forelse($historial->eventos as $i => $evento)
                    @php
                        $etiquetas = [
                            'PrestamoIniciado'              => 'Especímenes despachados',
                            'ActaEnviada'                   => 'Acta enviada',
                            'ActaFirmadaSubida'             => 'Acta firmada subida',
                            'ActaValidada'                  => 'Acta validada',
                            'ActaDevueltaPorFirmaInvalida'  => 'Acta devuelta por el curador',
                            'VerificacionEntregaRegistrada' => 'Verificación de entrega registrada',
                            'VerificacionEntregaAprobada'   => 'Verificación de entrega aprobada',
                            'PrestamoActivado'              => 'Préstamo activo',
                        ];
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
