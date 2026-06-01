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
                    <flux:button variant="ghost" icon="document-magnifying-glass" size="sm" wire:navigate
                        href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->id) }}">
                        Ver solicitud
                    </flux:button>
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
                    <flux:button variant="ghost" icon="document-text" size="sm" wire:navigate
                        href="{{ route('prestamos.curador.acta.validar', $acta->id) }}">
                        Ver acta y documentos
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

                @if($verificacion)
                    <flux:separator />
                    <div class="space-y-2">
                        <p class="text-xs font-medium text-text-secondary uppercase tracking-wide">Verificación de entrega</p>
                        <div class="flex items-center gap-2">
                            @if($verificacion->estadoEnvio()->value === 'sin_novedades')
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#E8F5E9] text-[#2E7D32] border border-[#A5D6A7]">
                                    <span class="size-1.5 rounded-full bg-[#2E7D32]"></span>
                                    Sin novedades
                                </span>
                                <span class="text-xs text-text-secondary">Todos los especímenes llegaron en buen estado.</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#FFEBEE] text-[#C62828] border border-[#EF9A9A]">
                                    <span class="size-1.5 rounded-full bg-[#C62828]"></span>
                                    Con novedades
                                </span>
                            @endif
                        </div>
                        @foreach($verificacion->observaciones() as $obs)
                            @php
                                $codigoEspecimen = \Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ItemPrestamoModel::query()
                                    ->where('id', $obs->itemPrestamoId)
                                    ->value('especimen_codigo_externo') ?? $obs->itemPrestamoId;
                            @endphp
                            <div class="rounded-lg border border-border bg-bg-main px-3 py-2">
                                <p class="text-xs text-text-secondary font-mono">Espécimen: {{ $codigoEspecimen }}</p>
                                <p class="text-sm text-text-primary mt-0.5">{{ $obs->descripcion }}</p>
                            </div>
                        @endforeach
                        @if($prestamo->estado === 'pendiente_aprobacion_verificacion')
                            <div class="pt-2">
                                <flux:button variant="primary" wire:navigate href="{{ route('prestamos.curador.prestamo.aprobar-verificacion', $prestamoId) }}" size="sm">
                                    Revisar verificación
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Trámite de exportación — solo préstamos internacionales pendientes --}}
            @if($prestamo->estado === 'pendiente_documento_ministerio')
                <div class="rounded-lg border border-[#FFCC80] bg-[#FFF8E1] p-5 space-y-4">
                    <div class="flex items-center gap-2">
                        <flux:icon name="document-arrow-up" class="size-5 text-[#E65100] shrink-0" />
                        <p class="text-sm font-semibold text-[#E65100]">Trámite de exportación pendiente</p>
                    </div>
                    <flux:separator />
                    <p class="text-sm text-text-secondary">
                        Sube el documento de aprobación del Ministerio del Ambiente para habilitar el envío de especímenes al exterior.
                    </p>

                    @if($successMessage)
                        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
                    @endif

                    <flux:field>
                        <flux:label>Documento de aprobación del ministerio (PDF)</flux:label>
                        <div x-data="{ nombre: '' }">
                            <label for="upload-doc-exportacion-auditar"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-medium text-text-primary shadow-sm hover:bg-bg-main transition-colors">
                                <flux:icon name="paper-clip" class="size-4 shrink-0" />
                                Seleccionar archivo PDF
                            </label>
                            <input id="upload-doc-exportacion-auditar" type="file"
                                wire:model="documentoExportacion" accept=".pdf"
                                x-on:change="nombre = $event.target.files[0]?.name ?? ''"
                                class="sr-only" />
                            <p class="mt-1.5 text-xs text-text-secondary"
                                x-text="nombre || 'Ningún archivo seleccionado'"></p>
                            <div wire:loading wire:target="documentoExportacion"
                                class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
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
            @endif

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
                        $tieneHabilitacionEnvio = collect($timeline)
                            ->contains(fn($item) => $item['evento']->tipo === 'PrestamoHabilitadoParaEnvio');

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
                            'PrestamoIniciado'              => $tieneHabilitacionEnvio ? 'Préstamo iniciado' : 'Especímenes despachados',
                            'DocumentoExportacionSubido'    => 'Documento de exportación registrado',
                            'PrestamoHabilitadoParaEnvio'   => 'Especímenes despachados',
                            'VerificacionEntregaRegistrada' => 'Verificación de entrega registrada',
                            'VerificacionEntregaAprobada'   => 'Verificación de entrega aprobada',
                            'PrestamoActivado'              => 'Préstamo activo',
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
