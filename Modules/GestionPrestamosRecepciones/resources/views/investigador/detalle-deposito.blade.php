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
                            <x-gestionprestamosrecepciones::codigo-qr :codigo="$deposito->codigo_qr"
                                :contenido="route('prestamos.lote.resolver', $deposito->codigo_qr)" :tamanio="150" />
                            <div class="flex w-full flex-col gap-2 sm:w-auto">
                                <a href="{{ route('prestamos.deposito.qr-pdf', $deposito->id) }}" target="_blank" rel="noopener"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-navy px-5 py-2.5 text-sm font-medium text-white! hover:opacity-90 transition-opacity">
                                    <flux:icon name="printer" class="size-4 text-white!" />
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

                {{-- Consideraciones para la entrega física (desde la aprobación hasta que la recepción se finalice) --}}
                @php
                    $recepcionFinalizada = isset($recepcion) && in_array(
                        $recepcion?->estadoRecepcion,
                        ['Verificado Físicamente', 'Verificado con Observaciones'],
                        true,
                    );
                @endphp
                @if($deposito->estado === 'Aprobada Documentalmente' && !$recepcionFinalizada)
                    <div class="rounded-lg border-2 border-warning/40 bg-surface shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-warning/20 bg-warning/5 flex items-start gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning">
                                <flux:icon name="exclamation-triangle" variant="outline" class="size-5" />
                            </div>
                            <div>
                                <flux:heading size="base" level="2" class="font-display text-warning">Importante: consideraciones para la entrega física</flux:heading>
                                <flux:text class="text-text-secondary text-xs">Prepara tus muestras según estos requisitos antes de entregarlas en el laboratorio. No cumplirlos puede retrasar la recepción.</flux:text>
                            </div>
                        </div>

                        <div class="p-5 space-y-5">
                            {{-- Certificado por proyecto --}}
                            <div class="flex items-center gap-3 rounded-lg bg-bio-green/5 border border-bio-green/20 px-4 py-3">
                                <flux:icon name="document-check" variant="outline" class="size-5 text-bio-green shrink-0" />
                                <p class="text-sm font-medium text-text-primary">Se emitirá un certificado por cada proyecto.</p>
                            </div>

                            {{-- Cajas entomológicas --}}
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="archive-box" variant="outline" class="size-4 text-text-secondary shrink-0" />
                                    <flux:heading size="sm" class="font-display">Cajas entomológicas</flux:heading>
                                </div>
                                <p class="text-sm text-text-secondary leading-relaxed">
                                    Por cada <span class="font-medium text-text-primary">cinco lotes en alcohol</span> a depositar de una localidad o
                                    proyecto, se receptará <span class="font-medium text-text-primary">una caja entomológica</span>. El número de cajas
                                    aumenta proporcionalmente al número de lotes:
                                </p>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border border-border rounded-lg overflow-hidden">
                                        <thead class="bg-bg-main">
                                            <tr>
                                                <th class="text-left font-medium text-text-secondary px-4 py-2">Lotes en alcohol</th>
                                                <th class="text-left font-medium text-text-secondary px-4 py-2">Cajas requeridas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-t border-border"><td class="px-4 py-2 text-text-primary">5 lotes</td><td class="px-4 py-2 text-text-primary">1 caja</td></tr>
                                            <tr class="border-t border-border"><td class="px-4 py-2 text-text-primary">10 lotes</td><td class="px-4 py-2 text-text-primary">2 cajas</td></tr>
                                            <tr class="border-t border-border"><td class="px-4 py-2 text-text-primary">15 lotes</td><td class="px-4 py-2 text-text-primary">3 cajas</td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <p class="text-sm text-text-secondary leading-relaxed">
                                    Las cajas alojan especímenes extraídos de los tubos con alcohol, que el staff del Laboratorio montará en alfileres
                                    después de la entrega. Pueden ser de cualquier madera (se recomienda <span class="font-medium text-text-primary">balsa</span>),
                                    deben tener <span class="font-medium text-text-primary">tapa de vidrio</span> y medir
                                    <span class="font-medium text-text-primary">52 × 39 × 6.5 cm</span>.
                                </p>
                                <p class="text-sm text-text-secondary leading-relaxed">
                                    Dudas sobre las cajas o dónde adquirirlas:
                                    <a href="mailto:adrian.troya@epn.edu.ec" class="text-science-blue hover:underline">adrian.troya@epn.edu.ec</a>
                                    o
                                    <a href="mailto:vladimir.carvajal@epn.edu.ec" class="text-science-blue hover:underline">vladimir.carvajal@epn.edu.ec</a>.
                                </p>
                            </div>

                            <flux:separator />

                            {{-- Material en alcohol --}}
                            <details class="group">
                                <summary class="flex cursor-pointer items-center justify-between gap-3 list-none">
                                    <span class="flex items-center gap-2">
                                        <flux:icon name="beaker" variant="outline" class="size-4 text-text-secondary shrink-0" />
                                        <flux:heading size="sm" class="font-display">Material en alcohol</flux:heading>
                                    </span>
                                    <flux:icon name="chevron-down" class="size-4 text-text-secondary transition-transform group-open:rotate-180" />
                                </summary>
                                <div class="mt-3 space-y-2 text-sm text-text-secondary leading-relaxed">
                                    <p>Corresponde a especímenes de cuerpo blando (larvas o ninfas de macroinvertebrados acuáticos, o especímenes que no pudieron montarse en alfileres).</p>
                                    <ul class="space-y-2 pl-1">
                                        <li class="flex gap-2"><flux:icon name="check" class="size-4 text-bio-green shrink-0 mt-0.5" /><span>Individualiza cada espécimen en frascos plásticos pequeños (p. ej. tubos de 1.5 ml con tapa rosca hermética), agrúpalos por lote o sitio dentro de un frasco contenedor de vidrio de boca ancha (100–200 ml) con tapa hermética, y preserva en <span class="font-medium text-text-primary">alcohol al 75%</span>. Alternativa: entregar los tubos en cajas portaviales.</span></li>
                                        <li class="flex gap-2"><flux:icon name="check" class="size-4 text-bio-green shrink-0 mt-0.5" /><span>Cada tubo de 1.5 ml lleva dos etiquetas. <span class="font-medium text-text-primary">Colección</span>: país, administración política 1, localidad específica, coordenadas en grados decimales, método y fecha de colección, colector. <span class="font-medium text-text-primary">Identificación</span>: familia, género, especie (si es posible) y nombre abreviado de quien identificó.</span></li>
                                        <li class="flex gap-2"><flux:icon name="check" class="size-4 text-bio-green shrink-0 mt-0.5" /><span>En la pared exterior de cada frasco contenedor van dos etiquetas: la de colección (misma información) y la de identificación indicando solo el/los órdenes de insectos representados.</span></li>
                                        <li class="flex gap-2"><flux:icon name="check" class="size-4 text-bio-green shrink-0 mt-0.5" /><span>Etiquetas impresas en láser sobre papel bond blanco, preferentemente de 75–105 gramos.</span></li>
                                    </ul>
                                </div>
                            </details>

                            <flux:separator />

                            {{-- Material en seco --}}
                            <details class="group">
                                <summary class="flex cursor-pointer items-center justify-between gap-3 list-none">
                                    <span class="flex items-center gap-2">
                                        <flux:icon name="bug-ant" variant="outline" class="size-4 text-text-secondary shrink-0" />
                                        <flux:heading size="sm" class="font-display">Material en seco</flux:heading>
                                    </span>
                                    <flux:icon name="chevron-down" class="size-4 text-text-secondary transition-transform group-open:rotate-180" />
                                </summary>
                                <div class="mt-3 space-y-2 text-sm text-text-secondary leading-relaxed">
                                    <p>Corresponde a especímenes de cuerpo duro (insectos adultos como escarabajos u hormigas, o caparazones de moluscos).</p>
                                    <ul class="space-y-2 pl-1">
                                        <li class="flex gap-2"><flux:icon name="check" class="size-4 text-bio-green shrink-0 mt-0.5" /><span>Los especímenes montados en alfileres deben llevar <span class="font-medium text-text-primary">tres etiquetas</span>.</span></li>
                                        <li class="flex gap-2"><flux:icon name="check" class="size-4 text-bio-green shrink-0 mt-0.5" /><span>Sigue las normas de etiquetado del Laboratorio (tipo de papel, modelo de etiquetas, alfileres y montaje) requeridas para la recepción.</span></li>
                                    </ul>
                                </div>
                            </details>

                            <flux:separator />

                            {{-- Definición de lote --}}
                            <details class="group">
                                <summary class="flex cursor-pointer items-center justify-between gap-3 list-none">
                                    <span class="flex items-center gap-2">
                                        <flux:icon name="information-circle" variant="outline" class="size-4 text-text-secondary shrink-0" />
                                        <flux:heading size="sm" class="font-display">¿Qué es un lote?</flux:heading>
                                    </span>
                                    <flux:icon name="chevron-down" class="size-4 text-text-secondary transition-transform group-open:rotate-180" />
                                </summary>
                                <div class="mt-3 space-y-3 text-sm text-text-secondary leading-relaxed">
                                    <p>Conjunto de especímenes recolectados en una localidad y en días específicos.</p>
                                    <div class="rounded-lg bg-bg-main border border-border px-4 py-3 space-y-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Ejemplos</p>
                                        <p>En Lumbaquí (Sucumbíos) se recolectaron 250 especímenes los días 12 y 13 de junio de 2022. <span class="font-medium text-text-primary">Total de lotes = 2.</span></p>
                                        <p>En San Isidro (Morona Santiago), Puyo (Pastaza) y El Pangui (Zamora Chinchipe) se recolectaron 1200 especímenes: San Isidro el 15 y 16 de marzo; Puyo el 2 de abril; El Pangui el 10 y 11 de abril. <span class="font-medium text-text-primary">Total de lotes = 5.</span></p>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                @endif

                {{-- Estado de la recepción física del lote --}}
                @if(isset($recepcion) && $recepcion?->recepcionIniciada)
                    @php
                        $estiloRecepcion = match($recepcion->estadoRecepcion) {
                            'Verificado Físicamente' => ['green', 'check-badge', 'text-success', 'border-success/30', 'bg-success/5'],
                            'Verificado con Observaciones' => ['amber', 'exclamation-triangle', 'text-warning', 'border-warning/30', 'bg-warning/5'],
                            'Recepción Suspendida' => ['red', 'arrow-path', 'text-error', 'border-error/30', 'bg-error/5'],
                            default => ['sky', 'clock', 'text-info', 'border-border', 'bg-surface'],
                        };
                    @endphp
                    <div class="rounded-lg border {{ $estiloRecepcion[3] }} {{ $estiloRecepcion[4] }} shadow-sm p-5 space-y-3">
                        <div class="flex items-start gap-3">
                            <flux:icon name="{{ $estiloRecepcion[1] }}" variant="outline" class="size-6 shrink-0 {{ $estiloRecepcion[2] }}" />
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="sm">Recepción física</flux:heading>
                                    <flux:badge size="sm" color="{{ $estiloRecepcion[0] }}">{{ $recepcion->estadoRecepcion }}</flux:badge>
                                </div>

                                @if($recepcion->estadoRecepcion === 'En Verificación')
                                    <flux:text class="text-text-secondary text-sm">El curador está verificando físicamente tus muestras.</flux:text>
                                @elseif($recepcion->estadoRecepcion === 'Recepción Suspendida')
                                    <flux:text class="text-text-secondary text-sm">
                                        Anomalía: <span class="font-medium text-text-primary">{{ $recepcion->motivoFallo }}</span>.
                                        Acción correctiva: <span class="font-medium text-text-primary">{{ $recepcion->accionCorrectiva }}</span>.
                                    </flux:text>
                                @else
                                    <flux:text class="text-text-secondary text-sm">
                                        Tus muestras fueron recibidas e ingresan a la colección en estado
                                        <span class="font-medium text-text-primary">{{ $recepcion->estadoColeccion ?? '—' }}</span>.
                                    </flux:text>
                                @endif
                            </div>
                        </div>

                        @if($recepcion->observaciones !== [])
                            <ul class="ml-9 space-y-1 text-sm text-text-primary">
                                @foreach($recepcion->observaciones as $observacion)
                                    <li class="flex items-start gap-2">
                                        <flux:icon name="exclamation-triangle" variant="outline" class="size-4 text-warning shrink-0 mt-0.5" />
                                        {{ $observacion }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                {{-- Acta de Recepción firmada electrónicamente (disponible tras la firma del curador) --}}
                @if(isset($recepcion) && $recepcion?->actaFirmada)
                    <div class="rounded-lg border border-success/30 bg-success/5 shadow-sm overflow-hidden">
                        <div class="p-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <flux:icon name="shield-check" variant="outline" class="size-6 shrink-0 text-success" />
                                <div>
                                    <flux:heading size="sm">Acta de recepción-depósito</flux:heading>
                                    <flux:text class="text-text-secondary text-xs">
                                        Firmada electrónicamente por el curador. Constancia oficial de la recepción de tus muestras.
                                    </flux:text>
                                </div>
                            </div>
                            <a href="{{ route('prestamos.deposito.acta-recepcion', $deposito->id) }}"
                                target="_blank" rel="noopener"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-bio-green! px-5 py-2.5 text-sm font-medium text-white! hover:opacity-90 transition-opacity sm:w-auto">
                                <flux:icon name="document-arrow-down" class="size-4 text-white!" />
                                Descargar acta firmada
                            </a>
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
                            <dd class="font-medium text-text-primary mt-0.5">@fechaEc($deposito->created_at)</dd>
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
                        :fecha="\Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador::formatear($deposito->created_at)"
                        titulo="Solicitud registrada"
                        :ultimo="$deposito->estado === 'En Borrador'" />

                    @if($matriz && $matriz->estado()->value !== 'Pendiente')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="\Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador::formatear($deposito->updated_at)"
                            titulo="Matriz de especímenes procesada"
                            :ultimo="$deposito->estado === 'En Borrador'" />
                    @endif

                    @if($deposito->estado === 'Pausada para Asesoría')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="\Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador::formatear($deposito->updated_at)"
                            titulo="Pausada para asesoría"
                            descripcion="Sin documentación disponible. Funcionario responsable notificado."
                            :ultimo="true" />
                    @endif

                    @if($deposito->estado === 'Pendiente de Revisión por Curaduría')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="\Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador::formatear($deposito->updated_at)"
                            titulo="Documentación cargada"
                            :ultimo="true" />
                    @endif

                    @if($deposito->estado === 'Rechazada')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="\Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador::formatear($deposito->updated_at)"
                            titulo="Solicitud rechazada"
                            :ultimo="true" />
                    @endif
                </div>
            </div>

        </div>
    @endif

</div>
