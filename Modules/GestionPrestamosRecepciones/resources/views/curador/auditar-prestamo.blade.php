<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.panel') }}">Panel</flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.prestamos') }}">Préstamos</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Auditoría {{ $detalle->numeroPrestamo ?? $prestamoId }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Columna principal --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Cierre con observación --}}
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
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Código</dt>
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
                            <div>
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Duración propuesta</dt>
                                <dd class="font-medium text-text-primary mt-1">{{ $detalle->duracionPropuestaMeses }} meses</dd>
                            </div>
                            @if($detalle->comentarioCurador)
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Comentario curador</dt>
                                    <dd class="text-text-primary mt-1">{{ $detalle->comentarioCurador }}</dd>
                                </div>
                            @endif
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
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Código</dt>
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
                            @if($detalle->motivoDevolucion)
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Motivo de devolución</dt>
                                    <dd class="text-text-primary mt-1">{{ $detalle->motivoDevolucion }}</dd>
                                </div>
                            @endif
                            @if($detalle->nombreValidador)
                                <div>
                                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Validada por</dt>
                                    <dd class="font-medium text-text-primary mt-1">{{ $detalle->nombreValidador }}</dd>
                                </div>
                            @endif
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
                            @if($detalle->estadoPrestamo->value === 'pendiente_aprobacion_verificacion')
                                <div class="pt-2">
                                    <flux:button variant="primary" wire:navigate
                                        href="{{ route('prestamos.curador.prestamo.aprobar-verificacion', $prestamoId) }}" size="sm">
                                        Revisar verificación
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endif
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
                            <flux:button variant="primary" wire:navigate href="{{ route('prestamos.curador.prestamo.cerrar', $prestamoId) }}">
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

        </div>

        {{-- Columna derecha --}}
        <div class="space-y-6">

            {{-- Trazabilidad completa --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="clock" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display">Trazabilidad completa</flux:heading>
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
                            'DevolucionRegistrada'          => 'Devolución registrada por el investigador',
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
                    <flux:heading size="base" level="2" class="font-display flex-1">Recordatorios</flux:heading>
                    <flux:button wire:click="abrirModalRecordatorios" variant="ghost" icon="pencil-square" size="sm">
                        Personalizar
                    </flux:button>
                </div>
                <div class="p-5">
                    @if(count($recordatoriosPersonalizados) > 0)
                        <div class="space-y-2">
                            @foreach(collect($recordatoriosPersonalizados)->sortByDesc('diasAntes')->values() as $recordatorio)
                                <div class="flex items-center gap-3 rounded-lg border border-border bg-bg-main px-3 py-2">
                                    <flux:icon name="bell" class="size-4 text-warning shrink-0" />
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-text-primary">
                                            {{ $recordatorio['diasAntes'] }} {{ $recordatorio['diasAntes'] === 1 ? 'día' : 'días' }} antes
                                        </p>
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

    </div>

    {{-- Modal: personalizar recordatorios --}}
    <flux:modal wire:model="mostrarModalRecordatorios" class="max-w-lg w-full">
        <div class="space-y-5 p-1">
            <div>
                <flux:heading size="lg" level="2">Personalizar recordatorios de este préstamo</flux:heading>
                <flux:text class="text-text-secondary text-sm mt-1">Estos recordatorios sobreescriben la configuración global para este préstamo.</flux:text>
            </div>

            <div class="flex items-center gap-6 rounded-lg bg-bg-main border border-border px-4 py-3">
                <div>
                    <p class="text-xs text-text-secondary">Fecha de inicio</p>
                    <p class="text-sm font-medium text-text-primary">{{ $detalle->iniciadoEn?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <flux:separator vertical class="h-8" />
                <div>
                    <p class="text-xs text-text-secondary">Fecha de vencimiento</p>
                    <p class="text-sm font-medium {{ ($detalle->fechaFin && $detalle->fechaFin < now()) ? 'text-error' : 'text-text-primary' }}">
                        {{ $detalle->fechaFin?->format('d/m/Y') ?? '—' }}
                    </p>
                </div>
            </div>

            <flux:separator />

            <div>
                <flux:text class="text-xs text-text-secondary mb-2">Cadencia sugerida — selecciona para activar o desactivar</flux:text>
                <div class="flex flex-wrap gap-2">
                    @foreach([30, 15, 7, 3, 1] as $preset)
                        @php $activo = in_array($preset, array_map('intval', $diasAntesModal), true); @endphp
                        <button type="button" wire:click="toggleDiaModal({{ $preset }})"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium border transition-colors',
                                'bg-blue-navy text-white border-blue-navy' => $activo,
                                'bg-surface text-text-secondary border-border hover:border-blue-navy hover:text-blue-navy' => !$activo,
                            ])>
                            @if($activo)<flux:icon name="check" class="size-3.5" />@else<flux:icon name="bell" class="size-3.5" />@endif
                            {{ $preset }} {{ $preset === 1 ? 'día' : 'días' }}
                        </button>
                    @endforeach
                </div>
            </div>

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

            <div>
                <flux:text class="text-xs text-text-secondary mb-2">Agregar día personalizado</flux:text>
                <div class="flex items-center gap-2 max-w-sm">
                    <flux:input wire:model="nuevoDiaModal" wire:keydown.enter.prevent="agregarDiaModal"
                        type="number" min="1" placeholder="Ej: 45" class="flex-1" />
                    <flux:button wire:click="agregarDiaModal" variant="ghost" icon="plus" size="sm">Agregar</flux:button>
                </div>
            </div>

            <flux:error name="diasAntesModal" />

            <div class="flex flex-col gap-2 sm:flex-row pt-1">
                <flux:button wire:click="actualizarRecordatorios" wire:loading.attr="disabled" variant="primary">
                    <span wire:loading.remove wire:target="actualizarRecordatorios">Guardar recordatorios</span>
                    <span wire:loading wire:target="actualizarRecordatorios">Guardando...</span>
                </flux:button>
                <flux:button wire:click="$set('mostrarModalRecordatorios', false)" variant="ghost">Cancelar</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
