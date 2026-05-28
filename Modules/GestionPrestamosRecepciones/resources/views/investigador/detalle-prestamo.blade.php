<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-prestamos') }}">
            Mis préstamos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $acta?->numero_prestamo ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-6 lg:grid-cols-3">

        <div class="lg:col-span-2 space-y-5">

        {{-- Solicitud --}}
        @if($solicitud)
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" level="2" class="font-display">Solicitud</flux:heading>
                    <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                </div>
                <flux:separator />
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-text-secondary">N.º solicitud</dt>
                        <dd class="font-mono font-medium text-text-primary">{{ $solicitud->numero_solicitud }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Institución</dt>
                        <dd class="text-text-primary">{{ $solicitud->institucion_adscripcion }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-text-secondary">Título del estudio</dt>
                        <dd class="font-medium text-text-primary">{{ $solicitud->titulo_estudio }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Duración propuesta</dt>
                        <dd class="text-text-primary">{{ $solicitud->duracion_propuesta_meses }} meses</dd>
                    </div>
                </dl>
            </div>
        @endif

        {{-- Acta --}}
        @if($acta)
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" level="2" class="font-display">Acta de préstamo</flux:heading>
                    <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                </div>
                <flux:separator />
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-text-secondary">N.º préstamo</dt>
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
                    @if($acta->condiciones_generales)
                        <div class="col-span-2">
                            <dt class="text-text-secondary">Condiciones generales</dt>
                            <dd class="text-text-primary">{{ $acta->condiciones_generales }}</dd>
                        </div>
                    @endif
                </dl>
                <flux:button variant="ghost" icon="document-text" size="sm"
                    href="{{ route('prestamos.acta.ver', $acta->id) }}" target="_blank">
                    Ver acta PDF
                </flux:button>
            </div>
        @endif

        {{-- Préstamo --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="lg" level="2" class="font-display">Préstamo</flux:heading>
                <x-gestionprestamosrecepciones::prestamo-status-badge :estado="$prestamo->estado" />
            </div>
            <flux:separator />
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-text-secondary">Inicio</dt>
                    <dd class="text-text-primary">{{ $prestamo->iniciado_en?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">Vencimiento</dt>
                    <dd class="text-text-primary">{{ $prestamo->fecha_fin?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                @php
                    $fin = $prestamo->fecha_fin;
                    $vencido = $fin && $fin->isPast();
                    $diasRestantes = $fin ? (int) abs(now()->startOfDay()->diffInDays($fin)) : null;
                @endphp
                @if($diasRestantes !== null)
                    <div class="col-span-2">
                        <dt class="text-text-secondary">Días restantes</dt>
                        <dd class="font-medium {{ $vencido ? 'text-error' : 'text-text-primary' }}">
                            {{ $vencido ? 'Vencido hace ' . $diasRestantes . ' días' : $diasRestantes . ' días' }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        </div>

        {{-- Columna derecha: Historial + Recordatorios --}}
        <div class="space-y-5">

        <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-2">
            <flux:heading size="lg" level="2" class="font-display">Historial</flux:heading>
            <flux:separator />

            <div class="flex flex-wrap gap-3 pt-1 pb-2">
                <span class="flex items-center gap-1.5 text-xs text-text-secondary">
                    <span class="size-2 rounded-full bg-[#1976D2]"></span> Solicitud
                </span>
                <span class="flex items-center gap-1.5 text-xs text-text-secondary">
                    <span class="size-2 rounded-full bg-[#E65100]"></span> Acta
                </span>
                <span class="flex items-center gap-1.5 text-xs text-text-secondary">
                    <span class="size-2 rounded-full bg-[#2E7D32]"></span> Préstamo
                </span>
            </div>

            <div class="mt-1">
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
                        'PrestamoIniciado'              => 'Préstamo iniciado',
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

        {{-- Recordatorios de devolución --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3">
            <flux:heading size="lg" level="2" class="font-display">Recordatorios de devolución</flux:heading>
            <flux:separator />

            @if(count($recordatorios) > 0)
                <div class="space-y-2">
                    @foreach(collect($recordatorios)->sortByDesc('diasAntes')->values() as $recordatorio)
                        <div class="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
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
                    <span>No hay recordatorios programados para este préstamo.</span>
                </div>
            @endif
        </div>

        </div>{{-- fin columna derecha --}}

    </div>

</div>
