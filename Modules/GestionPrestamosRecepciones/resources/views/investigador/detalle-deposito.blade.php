<div class="space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-depositos') }}">
            Mis depósitos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $deposito?->numero ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if(!$deposito)
        <flux:callout variant="danger" icon="exclamation-triangle">Solicitud no encontrada.</flux:callout>
    @else
        @php
            $badgeVariant = match($deposito->estado) {
                'Pendiente de Revisión por Curaduría' => 'info',
                'Pausada para Asesoría'   => 'warning',
                'Aprobada Documentalmente'            => 'success',
                'Requiere Corrección'                 => 'warning',
                'Rechazada', 'Rechazo Permanente'     => 'danger',
                default                               => 'ghost',
            };
        @endphp

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Columna principal --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Devolución para corrección (rechazo subsanable) --}}
                @if($deposito->estado === 'Requiere Corrección')
                    <div class="rounded-lg border border-warning/40 bg-warning/5 p-5 space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-warning text-white">
                                <flux:icon name="exclamation-triangle" class="size-4" />
                            </div>
                            <div class="min-w-0 space-y-1">
                                <flux:heading size="base" level="2" class="font-display text-warning">La curaduría devolvió tu solicitud para corrección</flux:heading>
                                @if($deposito->comentario_curador)
                                    <p class="text-sm text-text-primary leading-relaxed">
                                        <span class="font-medium">Observaciones del curador:</span> {{ $deposito->comentario_curador }}
                                    </p>
                                @else
                                    <p class="text-sm text-text-secondary">Corrige lo indicado y reenvía tu solicitud.</p>
                                @endif
                            </div>
                        </div>
                        <flux:button wire:navigate href="{{ route('prestamos.investigador.deposito.corregir', $deposito->id) }}"
                            variant="primary" icon="pencil-square" class="w-full sm:w-auto">
                            Corregir y reenviar
                        </flux:button>
                    </div>
                @elseif($deposito->estado === 'Rechazo Permanente')
                    <div class="rounded-lg border border-error/40 bg-error/5 p-5">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-error text-white">
                                <flux:icon name="x-circle" class="size-4" />
                            </div>
                            <div class="min-w-0 space-y-1">
                                <flux:heading size="base" level="2" class="font-display text-error">Solicitud rechazada de forma definitiva</flux:heading>
                                @if($deposito->comentario_curador)
                                    <p class="text-sm text-text-primary leading-relaxed">
                                        <span class="font-medium">Motivo:</span> {{ $deposito->comentario_curador }}
                                    </p>
                                @endif
                                <p class="text-xs text-text-secondary">Esta decisión es final y la solicitud no admite corrección.</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Código QR del lote (solicitud aprobada) --}}
                @if($deposito->estado === 'Aprobada Documentalmente' && $deposito->codigo_qr)
                    <div class="rounded-lg border border-success/40 bg-success/5 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-success/30 flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-success text-white shrink-0">
                                <flux:icon name="qr-code" class="size-4" />
                            </div>
                            <div>
                                <flux:heading size="base" level="2" class="font-display">¡Solicitud aprobada! Tu Código QR está listo</flux:heading>
                                <flux:text class="text-text-secondary text-xs">Imprímelo y adjúntalo a la entrega física de las muestras.</flux:text>
                            </div>
                        </div>
                        <div class="p-5 flex flex-col items-center gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <x-gestionprestamosrecepciones::codigo-qr :codigo="$deposito->codigo_qr" :tamanio="150" />
                            <div class="flex w-full flex-col gap-2 sm:w-auto">
                                <a href="{{ route('prestamos.deposito.qr-pdf', $deposito->id) }}" target="_blank" rel="noopener"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-navy px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition-opacity">
                                    <flux:icon name="printer" class="size-4" />
                                    Imprimir / Guardar QR (PDF)
                                </a>
                                @if($deposito->tipo_tramite === 'Donación' && $deposito->acta_transferencia_dominio
                                    && \Illuminate\Support\Facades\Storage::disk('public')->exists($deposito->acta_transferencia_dominio['ruta'] ?? ''))
                                    <a href="{{ route('prestamos.deposito.acta', $deposito->id) }}"
                                        target="_blank" rel="noopener"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-border px-5 py-2.5 text-sm font-medium text-text-secondary hover:border-science-blue hover:text-science-blue transition-colors">
                                        <flux:icon name="document-check" class="size-4" />
                                        Acta de transferencia
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Encabezado --}}
                <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <flux:heading size="xl" level="1" class="font-display">
                                Solicitud de {{ mb_strtolower($deposito->tipo_tramite) }}
                            </flux:heading>
                            <p class="font-mono text-xs text-text-secondary mt-1">{{ $deposito->numero }}</p>
                        </div>
                        <flux:badge :variant="$badgeVariant">{{ $deposito->estado }}</flux:badge>
                    </div>
                    <flux:separator />

                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-4 text-sm">
                        <div>
                            <dt class="text-text-secondary">Tipo de trámite</dt>
                            <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->tipo_tramite }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Fecha de registro</dt>
                            <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                        @if($deposito->origen_recoleccion)
                            <div>
                                <dt class="text-text-secondary">Origen de recolección</dt>
                                <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->origen_recoleccion }}</dd>
                            </div>
                        @endif
                        @if($deposito->situacion_regulatoria)
                            <div>
                                <dt class="text-text-secondary">Situación regulatoria</dt>
                                <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->situacion_regulatoria }}</dd>
                            </div>
                        @endif
                        @if($deposito->provincia_origen)
                            <div>
                                <dt class="text-text-secondary">Provincia de origen</dt>
                                <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->provincia_origen }}</dd>
                            </div>
                        @endif
                        @if($deposito->localidad)
                            <div>
                                <dt class="text-text-secondary">Localidad</dt>
                                <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->localidad }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Datos integrados de documentación --}}
                @php
                    $tieneDatos = $deposito->nro_permiso_recoleccion
                        || $deposito->nro_permiso_movilizacion
                        || $deposito->grupo_animal
                        || $deposito->origen_donacion;
                @endphp

                @if($tieneDatos)
                    <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                        <flux:heading size="lg" level="2" class="font-display">Datos de documentación oficial</flux:heading>
                        <flux:separator />
                        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-4 text-sm">
                            @if($deposito->nro_permiso_recoleccion)
                                <div>
                                    <dt class="text-text-secondary">N.º permiso recolección</dt>
                                    <dd class="font-mono font-medium text-text-primary mt-0.5">{{ $deposito->nro_permiso_recoleccion }}</dd>
                                </div>
                            @endif
                            @if($deposito->nro_permiso_movilizacion)
                                <div>
                                    <dt class="text-text-secondary">N.º permiso movilización</dt>
                                    <dd class="font-mono font-medium text-text-primary mt-0.5">{{ $deposito->nro_permiso_movilizacion }}</dd>
                                </div>
                            @endif
                            @if($deposito->grupo_animal)
                                <div>
                                    <dt class="text-text-secondary">Grupo animal</dt>
                                    <dd class="font-medium text-text-primary mt-0.5 italic font-serif">{{ $deposito->grupo_animal }}</dd>
                                </div>
                            @endif
                            @if($deposito->origen_donacion)
                                <div class="col-span-2">
                                    <dt class="text-text-secondary">Origen de donación</dt>
                                    <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->origen_donacion }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                {{-- Matriz de especímenes --}}
                @if($matriz)
                    @php
                        $registros        = $matriz->registros();
                        $totalRegistros   = count($registros);
                        $validados        = 0;
                        $listaCorregidos  = [];
                        $listaRevision    = [];

                        foreach ($registros as $reg) {
                            $estadoReg = $reg->estado()->value;
                            if (in_array($estadoReg, ['Validado Técnicamente', 'Corregido por Sugerencia'], true)) {
                                $validados++;
                            }
                            if ($estadoReg === 'Corregido por Sugerencia') {
                                $listaCorregidos[] = $reg;
                            }
                            if ($estadoReg === 'Validación Manual por Curaduría') {
                                $listaRevision[] = $reg;
                            }
                        }

                        $nCorregidos     = count($listaCorregidos);
                        $nRevision       = count($listaRevision);
                        $sinExcepciones  = $nCorregidos === 0 && $nRevision === 0;
                        $limite          = 8;

                        $estadoMatrizValor = $matriz->estado()->value;
                        [$estadoBadgeBg, $estadoBadgeText, $estadoBadgeBorder, $estadoBadgeIcon] = match($estadoMatrizValor) {
                            'Validada Técnicamente' => ['bg-success/10', 'text-success', 'border-success/30', 'check-circle'],
                            'Cargada con Alertas'   => ['bg-warning/10', 'text-warning', 'border-warning/30', 'exclamation-triangle'],
                            default                 => ['bg-info/10', 'text-info', 'border-info/30', 'clock'],
                        };
                    @endphp

                    <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                        {{-- Encabezado --}}
                        <div class="flex items-center justify-between gap-3">
                            <flux:heading size="lg" level="2" class="font-display">Matriz de especímenes</flux:heading>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $estadoBadgeBg }} {{ $estadoBadgeBorder }} {{ $estadoBadgeText }}">
                                <flux:icon name="{{ $estadoBadgeIcon }}" class="size-3.5" />
                                {{ $estadoMatrizValor }}
                            </span>
                        </div>
                        <flux:separator />

                        {{-- Contadores --}}
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-bg-main border border-border text-text-secondary">
                                <flux:icon name="table-cells" class="size-3.5" />
                                {{ $totalRegistros }} {{ $totalRegistros === 1 ? 'espécimen' : 'especímenes' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-success/10 border border-success/20 text-success">
                                <flux:icon name="check" class="size-3.5" />
                                {{ $validados }} validados
                            </span>
                            @if($nCorregidos > 0)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-bio-green/10 border border-bio-green/20 text-bio-green">
                                    <flux:icon name="sparkles" class="size-3.5" />
                                    {{ $nCorregidos }} {{ $nCorregidos === 1 ? 'corrección' : 'correcciones' }}
                                </span>
                            @endif
                            @if($nRevision > 0)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-warning/10 border border-warning/20 text-warning">
                                    <flux:icon name="exclamation-triangle" class="size-3.5" />
                                    {{ $nRevision }} revisión curatorial
                                </span>
                            @endif
                        </div>

                        {{-- Sin excepciones: mensaje de éxito limpio --}}
                        @if($sinExcepciones)
                            <div class="flex items-center gap-3 rounded-lg bg-success/5 border border-success/20 px-4 py-3">
                                <flux:icon name="check-circle" class="size-5 text-success shrink-0" />
                                <p class="text-sm text-text-primary">
                                    Todos los especímenes pasaron la validación taxonómica sin observaciones.
                                </p>
                            </div>

                        {{-- Hay excepciones: mostrar solo las que le afectan --}}
                        @else
                            {{-- Correcciones tipográficas --}}
                            @if($nCorregidos > 0)
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">
                                        Nombres corregidos automáticamente
                                    </p>
                                    <div class="rounded-lg border border-border overflow-hidden">
                                        @foreach(array_slice($listaCorregidos, 0, $limite) as $reg)
                                            <div class="flex items-center gap-2 px-4 py-3 border-b border-border last:border-b-0 text-sm">
                                                <span class="font-serif italic text-text-secondary line-through text-xs shrink-0">{{ $reg->nombreCientifico() }}</span>
                                                <flux:icon name="arrow-right" class="size-3 text-text-secondary shrink-0" />
                                                <span class="font-serif italic text-bio-green font-medium">{{ $reg->nombreCorregido() }}</span>
                                            </div>
                                        @endforeach
                                        @if($nCorregidos > $limite)
                                            <div class="px-4 py-2.5 bg-bg-main border-t border-border">
                                                <span class="text-xs text-text-secondary">y {{ $nCorregidos - $limite }} más...</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Especies para revisión curatorial --}}
                            @if($nRevision > 0)
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">
                                        Hallazgos para revisión curatorial
                                    </p>
                                    <div class="rounded-lg border border-warning/30 overflow-hidden">
                                        @foreach(array_slice($listaRevision, 0, $limite) as $reg)
                                            <div class="flex items-center justify-between gap-4 px-4 py-3 border-b border-warning/20 last:border-b-0 text-sm bg-warning/5">
                                                <span class="font-serif italic text-text-primary">{{ $reg->nombreCientifico() }}</span>
                                                @if($reg->motivoJustificacion())
                                                    <span class="shrink-0 text-xs text-text-secondary">{{ $reg->motivoJustificacion() }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                        @if($nRevision > $limite)
                                            <div class="px-4 py-2.5 bg-warning/5 border-t border-warning/20">
                                                <span class="text-xs text-text-secondary">y {{ $nRevision - $limite }} más...</span>
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-xs text-text-secondary leading-relaxed">
                                        {{ $nRevision === 1 ? 'Esta especie no fue encontrada' : 'Estas especies no fueron encontradas' }} en el catálogo de referencia. El funcionario responsable {{ $nRevision === 1 ? 'la revisará' : 'las revisará' }} antes de proceder con el depósito.
                                    </p>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                {{-- Alerta si retenida --}}
                @if($deposito->estado === 'Pausada para Asesoría')
                    <div class="rounded-xl border border-warning/40 bg-warning/5 p-5 space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-warning/15">
                                <flux:icon name="pause-circle" class="size-5 text-warning" />
                            </div>
                            <p class="text-sm font-semibold text-text-primary">En espera de asesoría curatorial</p>
                        </div>
                        <p class="text-sm text-text-secondary">
                            El funcionario responsable revisará tu caso y se pondrá en contacto contigo directamente. No necesitas realizar ninguna acción por ahora.
                        </p>
                    </div>
                @endif

            </div>

            {{-- Timeline lateral --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3 h-fit">
                <flux:heading size="lg" level="2" class="font-display">Historial</flux:heading>
                <flux:separator />

                <div class="mt-3 space-y-0">
                    <x-gestionprestamosrecepciones::timeline-event
                        :fecha="$deposito->created_at->format('d/m/Y H:i')"
                        titulo="Solicitud registrada"
                        :ultimo="$deposito->estado === 'En Borrador'" />

                    @if($matriz && $matriz->estado()->value !== 'Pendiente')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$deposito->updated_at->format('d/m/Y H:i')"
                            titulo="Matriz de especímenes procesada"
                            :ultimo="$deposito->estado === 'En Borrador'" />
                    @endif

                    @if($deposito->estado === 'Pausada para Asesoría')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$deposito->updated_at->format('d/m/Y H:i')"
                            titulo="Pausada para asesoría"
                            descripcion="Sin documentación disponible. Funcionario responsable notificado."
                            :ultimo="true" />
                    @endif

                    @if($deposito->estado === 'Pendiente de Revisión por Curaduría')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$deposito->updated_at->format('d/m/Y H:i')"
                            titulo="Documentación cargada"
                            :ultimo="true" />
                    @endif

                    @if($deposito->estado === 'Rechazada')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$deposito->updated_at->format('d/m/Y H:i')"
                            titulo="Solicitud rechazada"
                            :ultimo="true" />
                    @endif
                </div>
            </div>

        </div>
    @endif

</div>
