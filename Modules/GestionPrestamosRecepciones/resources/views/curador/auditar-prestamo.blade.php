<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.panel') }}">Panel</flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.prestamos') }}">Préstamos</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Auditoría {{ $acta?->numero_prestamo ?? $prestamoId }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Columna izquierda: Solicitud, Acta, Préstamo --}}
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
                        @if($solicitud->comentario_curador)
                            <div class="col-span-2">
                                <dt class="text-text-secondary">Comentario curador</dt>
                                <dd class="text-text-primary">{{ $solicitud->comentario_curador }}</dd>
                            </div>
                        @endif
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
                            <dd class="text-text-primary">{{ $acta->tipo_prestamo }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Fecha inicio</dt>
                            <dd class="text-text-primary">{{ $acta->fecha_inicio?->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Fecha fin</dt>
                            <dd class="text-text-primary">{{ $acta->fecha_fin?->format('d/m/Y') }}</dd>
                        </div>
                        @if($acta->motivo_devolucion)
                            <div class="col-span-2">
                                <dt class="text-text-secondary">Motivo de devolución</dt>
                                <dd class="text-text-primary">{{ $acta->motivo_devolucion }}</dd>
                            </div>
                        @endif
                        @if($acta->validada_por)
                            <div>
                                <dt class="text-text-secondary">Validada por</dt>
                                <dd class="text-text-primary">{{ $acta->validada_por }}</dd>
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
                </dl>
            </div>

        </div>

        {{-- Columna derecha: Trazabilidad + Recordatorios --}}
        <div class="space-y-5">

            {{-- Trazabilidad completa --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-2">
                <flux:heading size="lg" level="2" class="font-display">Trazabilidad completa</flux:heading>
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
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" level="2" class="font-display">Recordatorios de devolución</flux:heading>
                    <flux:button wire:click="abrirModalRecordatorios" variant="ghost" icon="pencil-square" size="sm">
                        Personalizar
                    </flux:button>
                </div>
                <flux:separator />

                @if(count($recordatoriosPersonalizados) > 0)
                    <div class="space-y-2">
                        @foreach(collect($recordatoriosPersonalizados)->sortByDesc('diasAntes')->values() as $recordatorio)
                            <div class="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                                <flux:icon name="bell" class="size-4 text-warning shrink-0" />
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-text-primary">{{ $recordatorio['diasAntes'] }} {{ $recordatorio['diasAntes'] === 1 ? 'día' : 'días' }} antes</p>
                                    <p class="text-xs text-text-secondary">{{ $recordatorio['fecha'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center gap-2 text-sm text-text-secondary">
                        <flux:icon name="information-circle" class="size-4 shrink-0" />
                        <span>Usa la configuración global de recordatorios.</span>
                    </div>
                @endif
            </div>

        </div>

    </div>

    {{-- Modal: personalizar recordatorios del préstamo --}}
    <flux:modal wire:model="mostrarModalRecordatorios" class="max-w-lg w-full">
        <div class="space-y-5 p-1">

            <div>
                <flux:heading size="lg" level="2">Personalizar recordatorios de este préstamo</flux:heading>
                <flux:text class="text-text-secondary text-sm mt-1">Estos recordatorios sobreescriben la configuración global para este préstamo.</flux:text>
            </div>

            {{-- Fechas del préstamo para referencia --}}
            <div class="flex items-center gap-6 rounded-lg bg-bg-main border border-border px-4 py-3">
                <div>
                    <p class="text-xs text-text-secondary">Fecha de inicio</p>
                    <p class="text-sm font-medium text-text-primary">{{ $prestamo->iniciado_en?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <flux:separator vertical class="h-8" />
                <div>
                    <p class="text-xs text-text-secondary">Fecha de vencimiento</p>
                    <p class="text-sm font-medium {{ $prestamo->fecha_fin?->isPast() ? 'text-error' : 'text-text-primary' }}">
                        {{ $prestamo->fecha_fin?->format('d/m/Y') ?? '—' }}
                    </p>
                </div>
            </div>

            <flux:separator />

            {{-- Presets rápidos --}}
            <div>
                <flux:text class="text-xs text-text-secondary mb-2">Cadencia sugerida — selecciona para activar o desactivar</flux:text>
                <div class="flex flex-wrap gap-2">
                    @foreach([30, 15, 7, 3, 1] as $preset)
                        @php $activo = in_array($preset, array_map('intval', $diasAntesModal), true); @endphp
                        <button
                            type="button"
                            wire:click="toggleDiaModal({{ $preset }})"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium border transition-colors',
                                'bg-blue-navy text-white border-blue-navy' => $activo,
                                'bg-surface text-text-secondary border-border hover:border-blue-navy hover:text-blue-navy' => !$activo,
                            ])
                        >
                            @if($activo)
                                <flux:icon name="check" class="size-3.5" />
                            @else
                                <flux:icon name="bell" class="size-3.5" />
                            @endif
                            {{ $preset }} {{ $preset === 1 ? 'día' : 'días' }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Días seleccionados --}}
            @if(count($diasAntesModal) > 0)
                <div>
                    <flux:text class="text-xs text-text-secondary mb-2">Recordatorios seleccionados</flux:text>
                    <div class="flex flex-wrap gap-2">
                        @foreach(collect($diasAntesModal)->map(fn($d) => (int)$d)->sortDesc()->values() as $dia)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-navy/10 text-blue-navy px-3 py-1 text-sm font-medium">
                                <flux:icon name="bell" class="size-3.5" />
                                {{ $dia }} {{ $dia === 1 ? 'día' : 'días' }} antes
                                <button type="button" wire:click="quitarDiaModal({{ $dia }})" class="hover:text-error transition-colors ml-0.5">
                                    <flux:icon name="x-mark" class="size-3" />
                                </button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Agregar día personalizado --}}
            <div>
                <flux:text class="text-xs text-text-secondary mb-2">Agregar día personalizado</flux:text>
                <div class="flex items-center gap-2 max-w-sm">
                    <flux:input
                        wire:model="nuevoDiaModal"
                        wire:keydown.enter.prevent="agregarDiaModal"
                        type="number"
                        min="1"
                        placeholder="Ej: 45"
                        class="flex-1" />
                    <flux:button wire:click="agregarDiaModal" variant="ghost" icon="plus" size="sm">
                        Agregar
                    </flux:button>
                </div>
            </div>

            <flux:error name="diasAntesModal" />

            <div class="flex flex-col gap-2 sm:flex-row pt-1">
                <flux:button
                    wire:click="actualizarRecordatorios"
                    wire:loading.attr="disabled"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="actualizarRecordatorios">Guardar recordatorios</span>
                    <span wire:loading wire:target="actualizarRecordatorios">Guardando...</span>
                </flux:button>
                <flux:button wire:click="$set('mostrarModalRecordatorios', false)" variant="ghost">
                    Cancelar
                </flux:button>
            </div>

        </div>
    </flux:modal>

</div>
