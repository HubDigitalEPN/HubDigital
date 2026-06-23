<div class="p-4 sm:p-6 space-y-5"
    x-data
    @toast.window="$flux.toast($event.detail.message)"
    @domain-error.window="$flux.toast({ text: $event.detail.message, variant: 'danger' })">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.depositos') }}">
            Bandeja de recepciones
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $deposito->numero ?? 'Revisar' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Encabezado --}}
    <div>
        <flux:heading size="xl" level="1" class="font-display">
            Solicitud de {{ mb_strtolower($deposito->tipo_tramite) }}
        </flux:heading>
        <div class="flex items-center gap-3 mt-1.5 flex-wrap">
            <p class="font-mono text-xs text-text-secondary">{{ $deposito->numero }}</p>
            <x-gestionprestamosrecepciones::deposito-status-badge :estado="$deposito->estado" />
            @if($deposito->prioridad === 'Prioritaria')
                <flux:badge size="sm" color="indigo" icon="flag">Prioritaria</flux:badge>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Columna principal --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Datos de la solicitud --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="document-text" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display">Datos de la solicitud</flux:heading>
                </div>
                <div class="p-5">
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Tipo de trámite</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $deposito->tipo_tramite }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Investigador</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $nombreInvestigador }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de registro</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $deposito->created_at?->format('d/m/Y H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Matriz de especímenes + alertas del sistema --}}
            @if($matriz)
                @php
                    $registros = array_values($matriz->registros());
                    $totalReg = count($registros);
                    $estadoMatriz = $matriz->estado()->value;

                    // Columnas Darwin Core (todos los registros comparten las mismas claves).
                    $columnasDwC = $totalReg > 0 ? array_keys($registros[0]->datosDwC()) : [];

                    // Etiquetas legibles en español para los términos Darwin Core.
                    $etiquetasDwC = [
                        'scientificName' => 'Nombre científico', 'specificEpithet' => 'Epíteto específico',
                        'scientificNameAuthorship' => 'Autoría del nombre', 'vernacularName' => 'Nombre común',
                        'taxonRank' => 'Rango taxonómico', 'subfamily' => 'Subfamilia',
                        'recordNumber' => 'N.º de registro', 'otherCatalogNumbers' => 'Otros n.º de catálogo',
                        'individualCount' => 'N.º de individuos', 'lifeStage' => 'Estadio de vida',
                        'recordedBy' => 'Recolectado por', 'identifiedBy' => 'Identificado por',
                        'dateDetermined' => 'Fecha de determinación', 'identificationRemarks' => 'Notas de identificación',
                        'identificationQualifier' => 'Calificador de identificación', 'eventDate' => 'Fecha de evento',
                        'eventTime' => 'Hora de evento', 'dateCollectedEnd' => 'Fecha fin de recolección',
                        'samplingProtocol' => 'Protocolo de muestreo', 'microhabitat' => 'Microhábitat',
                        'localityName' => 'Localidad', 'verbatimLocality' => 'Localidad textual',
                        'localityNotes' => 'Notas de localidad', 'municipality' => 'Cantón / Municipio',
                        'stateProvince' => 'Provincia', 'decimalLatitude' => 'Latitud', 'decimalLongitude' => 'Longitud',
                        'geodeticDatum' => 'Datum geodésico', 'verbatimElevation' => 'Elevación textual',
                        'verbatimCoordinateSystem' => 'Sistema de coordenadas', 'collectionNotes' => 'Notas de recolección',
                        'projectName' => 'Proyecto', 'researchPermit' => 'Permiso de investigación',
                        'movilizationPermit' => 'Permiso de movilización', 'preparations' => 'Preparaciones',
                        'language' => 'Idioma',
                    ];
                    $dwcLabel = fn (string $col): string => $etiquetasDwC[$col] ?? \Illuminate\Support\Str::headline($col);

                    // Columnas clave visibles en la tabla; el resto va al detalle por fila.
                    $columnasClave = array_values(array_filter(
                        ['scientificName', 'recordNumber', 'individualCount', 'eventDate', 'localityName', 'recordedBy'],
                        fn ($c) => in_array($c, $columnasDwC, true),
                    ));
                    $colspanDetalle = count($columnasClave) + 2;

                    $conteo = ['Validado Técnicamente' => 0, 'Corregido por Sugerencia' => 0, 'Validación Manual por Curaduría' => 0, 'Pendiente' => 0];
                    foreach ($registros as $r) {
                        $e = $r->estado()->value;
                        $conteo[$e] = ($conteo[$e] ?? 0) + 1;
                    }
                    $nRevision = $conteo['Validación Manual por Curaduría'] ?? 0;
                    $nCorregidos = $conteo['Corregido por Sugerencia'] ?? 0;

                    [$mzBg, $mzText, $mzIcon] = match($estadoMatriz) {
                        'Validada Técnicamente' => ['bg-success/10', 'text-success', 'check-circle'],
                        'Cargada con Alertas'   => ['bg-warning/10', 'text-warning', 'exclamation-triangle'],
                        default                 => ['bg-info/10', 'text-info', 'clock'],
                    };

                    $regBadge = fn (string $estado): array => match($estado) {
                        'Validado Técnicamente'         => ['green', 'Validado'],
                        'Corregido por Sugerencia'      => ['teal', 'Corregido'],
                        'Validación Manual por Curaduría' => ['amber', 'Revisión curatorial'],
                        default                         => ['zinc', 'Pendiente'],
                    };
                @endphp
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                            <flux:icon name="beaker" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display flex-1">Matriz de especímenes</flux:heading>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $mzBg }} {{ $mzText }}">
                            <flux:icon name="{{ $mzIcon }}" class="size-3.5" />
                            {{ $estadoMatriz }}
                        </span>
                    </div>

                    {{-- Contadores --}}
                    <div class="flex flex-wrap gap-2 px-5 pt-4">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-bg-main border border-border text-text-secondary">
                            <flux:icon name="table-cells" class="size-3.5" />
                            {{ $totalReg }} {{ $totalReg === 1 ? 'espécimen' : 'especímenes' }}
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
                                {{ $nRevision }} para revisión curatorial
                            </span>
                        @endif
                    </div>

                    {{-- Recurso ante anomalías: devolver para corrección --}}
                    @if($nRevision > 0 && $esPendiente)
                        <div class="mx-5 mt-3 flex items-start gap-2.5 rounded-lg border border-warning/30 bg-warning/5 px-4 py-3">
                            <flux:icon name="information-circle" class="size-4 text-warning shrink-0 mt-0.5" />
                            <p class="text-xs text-text-secondary leading-relaxed">
                                Las celdas resaltadas requieren atención. Si los datos deben corregirse, devuelve la solicitud
                                al investigador con <span class="font-medium text-text-primary">Rechazar solicitud → Subsanable</span>;
                                el investigador es quien edita y reenvía la matriz.
                            </p>
                        </div>
                    @endif

                    {{-- Tabla Darwin Core (columnas clave + detalle por fila) — escritorio --}}
                    <div class="hidden md:block p-5" x-data="{ abiertos: {} }">
                        <div class="overflow-x-auto rounded-lg border border-border">
                            <table class="w-full text-sm">
                                <thead class="bg-blue-navy">
                                    <tr>
                                        <th class="sticky left-0 top-0 z-30 bg-blue-navy px-4 py-3 text-left font-medium text-white whitespace-nowrap">Estado</th>
                                        @foreach($columnasClave as $col)
                                            <th class="sticky top-0 z-20 bg-blue-navy px-4 py-3 text-left font-medium text-white whitespace-nowrap">{{ $dwcLabel($col) }}</th>
                                        @endforeach
                                        <th class="sticky top-0 z-20 bg-blue-navy px-4 py-3 text-right font-medium text-white whitespace-nowrap">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($registros as $i => $reg)
                                        @php
                                            [$bColor, $bLabel] = $regBadge($reg->estado()->value);
                                            $dwc = $reg->datosDwC();
                                            $noCat = $reg->esNoCatalogado();
                                            $corr = $reg->nombreCorregido();
                                        @endphp
                                        <tr class="hover:bg-bg-main transition-colors">
                                            <td class="sticky left-0 z-10 bg-surface px-4 py-3 whitespace-nowrap">
                                                <flux:badge size="sm" :color="$bColor">{{ $bLabel }}</flux:badge>
                                            </td>
                                            @foreach($columnasClave as $col)
                                                @if($col === 'scientificName')
                                                    <td @class([
                                                        'px-4 py-3 whitespace-nowrap',
                                                        'bg-warning/10' => $noCat,
                                                        'bg-bio-green/10' => $corr && ! $noCat,
                                                    ])>
                                                        <div class="flex items-center gap-1.5">
                                                            @if($noCat)
                                                                <flux:icon name="exclamation-triangle" class="size-3.5 text-warning shrink-0" />
                                                            @endif
                                                            <span class="font-serif italic text-text-primary">{{ ($dwc[$col] ?? '') !== '' ? $dwc[$col] : '—' }}</span>
                                                            @if($corr)
                                                                <flux:icon name="arrow-right" class="size-3 text-text-secondary" />
                                                                <span class="font-serif italic text-bio-green font-medium">{{ $corr }}</span>
                                                            @endif
                                                        </div>
                                                        @if($noCat && $reg->motivoJustificacion())
                                                            <p class="text-[11px] text-warning mt-0.5">{{ $reg->motivoJustificacion() }}</p>
                                                        @endif
                                                    </td>
                                                @else
                                                    <td class="px-4 py-3 whitespace-nowrap text-text-secondary">
                                                        {{ ($dwc[$col] ?? '') !== '' ? $dwc[$col] : '—' }}
                                                    </td>
                                                @endif
                                            @endforeach
                                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                                <button type="button" x-on:click="abiertos[{{ $i }}] = ! abiertos[{{ $i }}]"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-science-blue hover:underline">
                                                    <span x-show="! abiertos[{{ $i }}]">Ver campos</span>
                                                    <span x-show="abiertos[{{ $i }}]" x-cloak>Ocultar</span>
                                                    <flux:icon name="chevron-down" class="size-3 transition-transform" x-bind:class="abiertos[{{ $i }}] && 'rotate-180'" />
                                                </button>
                                            </td>
                                        </tr>
                                        <tr x-show="abiertos[{{ $i }}]" x-cloak>
                                            <td colspan="{{ $colspanDetalle }}" class="bg-bg-main px-4 py-4">
                                                <dl class="grid grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3">
                                                    @foreach($columnasDwC as $col)
                                                        <div>
                                                            <dt class="text-[11px] text-text-secondary uppercase tracking-wide">{{ $dwcLabel($col) }}</dt>
                                                            <dd class="text-sm text-text-primary {{ $col === 'scientificName' ? 'font-serif italic' : '' }}">{{ ($dwc[$col] ?? '') !== '' ? $dwc[$col] : '—' }}</dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-text-secondary mt-2">
                            Se muestran los campos clave. Usa <span class="font-medium text-text-primary">Ver campos</span> para el detalle Darwin Core completo de cada espécimen.
                        </p>
                    </div>

                    {{-- Tarjetas por espécimen — móvil --}}
                    <div class="md:hidden p-4 space-y-3">
                        @foreach($registros as $reg)
                            @php
                                [$bColor, $bLabel] = $regBadge($reg->estado()->value);
                                $dwc = $reg->datosDwC();
                                $noCat = $reg->esNoCatalogado();
                                $corr = $reg->nombreCorregido();
                            @endphp
                            <div @class([
                                    'rounded-lg border bg-bg-main p-4 space-y-3',
                                    'border-warning/40' => $noCat,
                                    'border-border' => ! $noCat,
                                ])
                                x-data="{ abierto: false }">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            @if($noCat)
                                                <flux:icon name="exclamation-triangle" class="size-3.5 text-warning shrink-0" />
                                            @endif
                                            <span class="font-serif italic text-sm text-text-primary">{{ $reg->nombreCientifico() }}</span>
                                            @if($corr)
                                                <flux:icon name="arrow-right" class="size-3 text-text-secondary" />
                                                <span class="font-serif italic text-sm text-bio-green font-medium">{{ $corr }}</span>
                                            @endif
                                        </div>
                                        @if($noCat && $reg->motivoJustificacion())
                                            <p class="text-[11px] text-warning mt-1">{{ $reg->motivoJustificacion() }}</p>
                                        @endif
                                    </div>
                                    <flux:badge size="sm" :color="$bColor" class="shrink-0">{{ $bLabel }}</flux:badge>
                                </div>
                                <dl class="space-y-2 text-sm" x-show="abierto" x-collapse>
                                    @foreach($columnasDwC as $col)
                                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil :etiqueta="$dwcLabel($col)">
                                            {{ ($dwc[$col] ?? '') !== '' ? $dwc[$col] : '—' }}
                                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                                    @endforeach
                                </dl>
                                <button type="button" x-on:click="abierto = !abierto"
                                    class="text-xs font-medium text-science-blue hover:underline">
                                    <span x-show="!abierto">Ver todos los campos</span>
                                    <span x-show="abierto">Ocultar campos</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Documentos + datos extraídos (comparación lado a lado) --}}
            @php
                $documentos = $deposito->documentos_cargados ?? [];
                $nombresDoc = $deposito->nombres_archivos_originales ?? [];

                // Datos para contrastar con el PDF. Cada fila marca su origen: extraído
                // por el sistema (verde) o ingresado manualmente (amarillo).
                $manualSet = array_map(
                    fn ($s) => mb_strtolower(trim((string) $s)),
                    $deposito->datos_ingresados_manualmente ?? [],
                );
                $esManual = fn (string $canon): bool => in_array(mb_strtolower($canon), $manualSet, true);

                // [etiqueta, valor, mono, manual]
                $datosExtraidos = array_values(array_filter([
                    ['Nombre en el documento', $deposito->nombre_investigador_documento, false, false],
                    ['Situación regulatoria', $deposito->situacion_regulatoria, false, $esManual('Situación Regulatoria')],
                    ['Origen de recolección', $deposito->origen_recoleccion, false, $esManual('Origen')],
                    ['Provincia', $deposito->provincia_origen, false, $esManual('Provincia')],
                    ['Localidad', $deposito->localidad, false, $esManual('Localidad')],
                    ['N.º permiso recolección', $deposito->nro_permiso_recoleccion, true, $esManual('N.º Permiso Recolección')],
                    ['N.º permiso movilización', $deposito->nro_permiso_movilizacion, true, $esManual('N.º Permiso Movilización')],
                    ['Grupo animal', $deposito->grupo_animal, false, $esManual('Grupo Animal')],
                    ['Origen de donación', $deposito->origen_donacion, false, false],
                    ['N.º individuos', $deposito->nro_individuos, false, true],
                    ['N.º morfoespecies', $deposito->nro_morfoespecies, false, true],
                    ['N.º lotes', $deposito->nro_lotes, false, true],
                ], fn ($c) => ($c[1] ?? '') !== '' && $c[1] !== null));
            @endphp
            @if(count($documentos))
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden"
                    x-data="{
                        idx: 0,
                        urls: [
                            @foreach(array_keys($documentos) as $i => $clave)
                                '{{ route('prestamos.deposito.documento', [$deposito->id, $i]) }}',
                            @endforeach
                        ]
                    }">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                            <flux:icon name="paper-clip" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display flex-1">Documentos y datos ingresados</flux:heading>
                        <div class="flex items-center gap-2">
                            <a x-bind:href="urls[idx]" target="_blank" rel="noopener"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-text-secondary hover:border-science-blue hover:text-science-blue transition-colors">
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                Abrir
                            </a>
                            <a x-bind:href="urls[idx] + '?descargar=1'"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-text-secondary hover:border-science-blue hover:text-science-blue transition-colors">
                                <flux:icon name="arrow-down-tray" class="size-3.5" />
                                Descargar
                            </a>
                        </div>
                    </div>

                    <div class="grid lg:grid-cols-5">
                        {{-- Izquierda: visor del documento --}}
                        <div class="lg:col-span-3 lg:border-r border-border">
                            <div class="flex flex-wrap gap-1 border-b border-border bg-bg-main px-3 py-2">
                                @foreach(array_keys($documentos) as $i => $clave)
                                    <button type="button"
                                        x-on:click="idx = {{ $i }}"
                                        x-bind:class="idx === {{ $i }}
                                            ? 'bg-surface text-science-blue border-science-blue'
                                            : 'text-text-secondary border-transparent hover:text-text-primary'"
                                        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors">
                                        <flux:icon name="document" class="size-3.5" />
                                        {{ $nombresDoc[$clave] ?? $clave }}
                                    </button>
                                @endforeach
                            </div>
                            <div class="bg-bg-main p-3">
                                <iframe x-bind:src="urls[idx]"
                                    class="w-full rounded-lg border border-border bg-white h-[55vh] min-h-[420px]"
                                    title="Documento de la solicitud"></iframe>
                            </div>
                        </div>

                        {{-- Derecha: datos extraídos por el sistema --}}
                        <div class="lg:col-span-2 border-t lg:border-t-0 border-border">
                            <div class="border-b border-border bg-bg-main px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="document-magnifying-glass" class="size-3.5 text-science-blue" />
                                    <span class="text-xs font-semibold uppercase tracking-wide text-text-secondary flex-1">Datos ingresados</span>
                                </div>
                                <div class="flex items-center gap-3 mt-1.5">
                                    <span class="inline-flex items-center gap-1 text-[10px] text-text-secondary">
                                        <span class="size-2 rounded-full bg-success"></span> Extraído por el sistema
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-[10px] text-text-secondary">
                                        <span class="size-2 rounded-full bg-warning"></span> Manual
                                    </span>
                                </div>
                            </div>
                            <div class="p-4">
                                @if(count($datosExtraidos))
                                    <dl class="space-y-1.5">
                                        @foreach($datosExtraidos as [$etiqueta, $valor, $mono, $manual])
                                            <div @class([
                                                'rounded-md border-l-2 px-3 py-2',
                                                'border-warning bg-warning/5' => $manual,
                                                'border-success bg-success/5' => ! $manual,
                                            ])>
                                                <dt class="text-[11px] uppercase tracking-wide {{ $manual ? 'text-warning' : 'text-success' }}">{{ $etiqueta }}</dt>
                                                <dd class="mt-0.5 text-sm text-text-primary {{ $mono ? 'font-mono' : 'font-medium' }}">{{ $valor }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                    <p class="mt-3 text-[11px] text-text-secondary leading-relaxed">
                                        Contrasta los valores <span class="text-success font-medium">extraídos</span> con el documento abierto a la izquierda.
                                        Si algo no coincide, devuelve la solicitud con <span class="font-medium text-text-primary">Rechazar → Subsanable</span>.
                                    </p>
                                @else
                                    <flux:text class="text-sm text-text-secondary">No hay datos registrados para esta solicitud.</flux:text>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Alertas --}}
            @if(count($alertas))
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-warning text-white shrink-0">
                            <flux:icon name="exclamation-triangle" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display flex-1">Alertas justificadas</flux:heading>
                        <span class="text-xs bg-warning/10 text-warning px-2.5 py-1 rounded-full font-medium tabular-nums">
                            {{ count($alertas) }}
                        </span>
                    </div>
                    <div class="p-5 space-y-3">
                        @foreach($alertas as $alerta)
                            @php
                                [$revColor, $revLabel] = match($alerta->estado_revision) {
                                    'Aceptada'  => ['green', 'Aceptada'],
                                    'Rechazada' => ['red', 'Rechazada'],
                                    default     => ['blue', 'Pendiente'],
                                };
                            @endphp
                            <div class="rounded-lg border border-border bg-bg-main p-4 space-y-2">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-semibold text-text-primary">{{ $alerta->tipo }}</p>
                                    <flux:badge size="sm" :color="$revColor">{{ $revLabel }}</flux:badge>
                                </div>
                                <div>
                                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Justificación del investigador</dt>
                                    <dd class="text-sm text-text-primary mt-1 leading-relaxed">{{ $alerta->justificacion_investigador }}</dd>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Comentario del curador (rechazo previo) --}}
            @if($deposito->comentario_curador)
                <flux:callout variant="warning" icon="chat-bubble-left-ellipsis">
                    <flux:callout.heading>Comentario para el investigador</flux:callout.heading>
                    <flux:callout.text>{{ $deposito->comentario_curador }}</flux:callout.text>
                </flux:callout>
            @endif

        </div>

        {{-- Columna derecha --}}
        <div class="space-y-5">

            {{-- Panel de resolución --}}
            @if($esPendiente)
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                            <flux:icon name="check-badge" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display">Resolución</flux:heading>
                    </div>
                    <div class="p-5 space-y-4">
                        @if($hayAlertasPendientes)
                            <flux:text class="text-text-secondary text-sm">
                                Esta solicitud tiene justificaciones de alertas por revisar. Acepta todas para aprobarla,
                                o rechaza las que no consideres válidas.
                            </flux:text>
                            <div class="flex flex-col gap-2">
                                <flux:button variant="primary" icon="check-circle"
                                    wire:click="pedirConfirmacion('justificaciones')">
                                    Aceptar todas las justificaciones
                                </flux:button>
                                <flux:button variant="ghost" icon="x-circle"
                                    wire:click="$set('showRechazoJustificacionesModal', true)">
                                    Rechazar justificaciones
                                </flux:button>
                            </div>
                        @else
                            <flux:text class="text-text-secondary text-sm">
                                Revisa la documentación y decide si apruebas la solicitud o la rechazas con un motivo.
                            </flux:text>
                            <div class="flex flex-col gap-2">
                                @if($esDonacion)
                                    <flux:button variant="primary" icon="check-circle"
                                        wire:click="pedirConfirmacion('donacion')">
                                        Aprobar donación (con Acta)
                                    </flux:button>
                                @else
                                    <flux:button variant="primary" icon="check-circle"
                                        wire:click="pedirConfirmacion('deposito')">
                                        Aprobar documentalmente
                                    </flux:button>
                                @endif
                                <flux:button variant="ghost" icon="x-circle"
                                    wire:click="$set('showRechazoModal', true)">
                                    Rechazar solicitud
                                </flux:button>
                            </div>
                        @endif

                        <flux:separator />

                        @if($deposito->prioridad !== 'Prioritaria')
                            <flux:button variant="subtle" icon="flag" class="w-full"
                                wire:click="priorizar"
                                wire:loading.attr="disabled" wire:target="priorizar">
                                <flux:icon wire:loading wire:target="priorizar" name="arrow-path" class="animate-spin" />
                                Marcar como prioritaria
                            </flux:button>
                        @else
                            <div class="flex items-center justify-center gap-2 text-xs text-text-secondary">
                                <flux:icon name="flag" class="size-3.5 text-indigo-500" />
                                Posicionada al inicio de la cola
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Panel de confirmación de aprobación (sin QR: el QR es del investigador) --}}
            @if($deposito->estado === 'Aprobada Documentalmente' && $deposito->codigo_qr)
                <div class="rounded-lg border border-success/40 bg-success/5 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-success/30 flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-success text-white shrink-0">
                            <flux:icon name="check-badge" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display">Solicitud aprobada</flux:heading>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-text-secondary uppercase tracking-wide">Lote asignado</span>
                            <span class="font-mono font-semibold text-text-primary">{{ $deposito->codigo_qr }}</span>
                        </div>
                        <flux:text class="text-text-secondary text-xs">Código QR disponible para el investigador.</flux:text>

                        @if($esDonacion && $deposito->acta_transferencia_dominio)
                            @php $actaDisponible = \Illuminate\Support\Facades\Storage::disk('public')->exists($deposito->acta_transferencia_dominio['ruta'] ?? ''); @endphp
                            @if($actaDisponible)
                                <a href="{{ route('prestamos.deposito.acta', $deposito->id) }}"
                                    target="_blank" rel="noopener"
                                    class="flex items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3 hover:border-science-blue transition-colors">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-science-blue/10">
                                        <flux:icon name="document-check" class="size-4 text-science-blue" />
                                    </div>
                                    <p class="flex-1 text-sm font-medium text-text-primary">Acta de transferencia de dominio</p>
                                    <flux:icon name="arrow-top-right-on-square" class="size-4 text-text-secondary shrink-0" />
                                </a>
                            @else
                                <div class="flex items-center gap-3 rounded-lg border border-border bg-bg-main px-4 py-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-success/10">
                                        <flux:icon name="document-check" class="size-4 text-success" />
                                    </div>
                                    <p class="flex-1 text-sm text-text-secondary">Acta de transferencia de dominio generada.</p>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            {{-- Estado resuelto distinto a aprobado --}}
            @if(in_array($deposito->estado, ['Requiere Corrección', 'Rechazo Permanente'], true))
                <flux:callout :variant="$deposito->estado === 'Rechazo Permanente' ? 'danger' : 'warning'" icon="information-circle">
                    <flux:callout.heading>{{ $deposito->estado }}</flux:callout.heading>
                    <flux:callout.text>La solicitud ya fue resuelta y notificada al investigador.</flux:callout.text>
                </flux:callout>
            @endif

            {{-- Estados sin decisión documental disponible (no enviada / en asesoría) --}}
            @if($deposito->estado === 'Pausada para Asesoría')
                <flux:callout variant="warning" icon="pause-circle">
                    <flux:callout.heading>En espera de asesoría curatorial</flux:callout.heading>
                    <flux:callout.text>
                        Esta solicitud se pausó para asesoría y aún no está en la cola de revisión documental,
                        por lo que no hay decisión de aprobación disponible.
                    </flux:callout.text>
                </flux:callout>
            @elseif($deposito->estado === 'En Borrador')
                <flux:callout variant="secondary" icon="pencil-square">
                    <flux:callout.heading>Solicitud aún no enviada</flux:callout.heading>
                    <flux:callout.text>
                        El investigador todavía no ha enviado esta solicitud para revisión documental.
                    </flux:callout.text>
                </flux:callout>
            @endif

        </div>
    </div>

    {{-- Modal: confirmación de aprobación --}}
    <flux:modal wire:model="showConfirmacionModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-success/10 shrink-0">
                    <flux:icon name="check-badge" class="size-5 text-success" />
                </div>
                <flux:heading size="lg">
                    {{ $accionAprobar === 'justificaciones' ? 'Aceptar justificaciones y aprobar' : ($accionAprobar === 'donacion' ? 'Aprobar donación' : 'Aprobar solicitud') }}
                </flux:heading>
            </div>
            <flux:text class="text-text-secondary text-sm">
                ¿Confirmas la aprobación documental de esta solicitud? Esta acción no se puede deshacer e implica:
            </flux:text>
            @if($hallazgosMatriz > 0)
                <div class="flex items-start gap-2.5 rounded-lg border border-warning/40 bg-warning/5 px-4 py-3">
                    <flux:icon name="exclamation-triangle" class="size-4 text-warning shrink-0 mt-0.5" />
                    <p class="text-sm text-text-primary leading-relaxed">
                        La matriz tiene <span class="font-semibold">{{ $hallazgosMatriz }}</span>
                        {{ $hallazgosMatriz === 1 ? 'especie sin resolver' : 'especies sin resolver' }}
                        (para revisión curatorial). Si apruebas, {{ $hallazgosMatriz === 1 ? 'quedará pendiente' : 'quedarán pendientes' }}
                        de validación manual. Si requieren corrección, considera devolver la solicitud como
                        <span class="font-medium">Subsanable</span>.
                    </p>
                </div>
            @endif
            <ul class="space-y-2 text-sm text-text-primary">
                @if($accionAprobar === 'justificaciones')
                    <li class="flex items-start gap-2"><flux:icon name="check" class="size-4 text-success shrink-0 mt-0.5" />Se aceptan todas las justificaciones de alertas pendientes.</li>
                @endif
                <li class="flex items-start gap-2"><flux:icon name="check" class="size-4 text-success shrink-0 mt-0.5" />La solicitud pasa a <span class="font-medium">Aprobada documentalmente</span> y queda registrada en la auditoría a tu nombre.</li>
                <li class="flex items-start gap-2"><flux:icon name="check" class="size-4 text-success shrink-0 mt-0.5" />Se asigna un Código QR único de lote.</li>
                @if($accionAprobar === 'donacion')
                    <li class="flex items-start gap-2"><flux:icon name="check" class="size-4 text-success shrink-0 mt-0.5" />Se genera el Acta de Transferencia de Dominio.</li>
                @endif
                <li class="flex items-start gap-2"><flux:icon name="check" class="size-4 text-success shrink-0 mt-0.5" />Se notifica al investigador para la entrega física.</li>
            </ul>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showConfirmacionModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" icon="check-circle" wire:click="confirmarAprobacion"
                    wire:loading.attr="disabled" wire:target="confirmarAprobacion">
                    <flux:icon wire:loading wire:target="confirmarAprobacion" name="arrow-path" class="animate-spin" />
                    Sí, aprobar
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: rechazo documental --}}
    <flux:modal wire:model="showRechazoModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Rechazar solicitud</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Indica el tipo de rechazo y el motivo. Quedará registrado como comentario para el investigador.
            </flux:text>
            @if($hallazgosMatriz > 0)
                <div class="flex items-start gap-2 rounded-lg bg-warning/5 border border-warning/30 px-3 py-2">
                    <flux:icon name="exclamation-triangle" class="size-3.5 text-warning shrink-0 mt-0.5" />
                    <p class="text-xs text-text-secondary">Recordatorio: la matriz tiene {{ $hallazgosMatriz }} {{ $hallazgosMatriz === 1 ? 'especie' : 'especies' }} para revisión curatorial que puedes citar en el motivo.</p>
                </div>
            @endif
            <flux:field>
                <flux:label>Tipo de rechazo</flux:label>
                <flux:select wire:model="tipoRechazo">
                    <flux:select.option value="Subsanable">Subsanable (requiere corrección)</flux:select.option>
                    <flux:select.option value="Definitivo">Definitivo (rechazo permanente)</flux:select.option>
                </flux:select>
                <flux:description>
                    <span class="font-medium">Subsanable:</span> el investigador corrige y reenvía.
                    <span class="font-medium">Definitivo:</span> cierra la solicitud sin posibilidad de corregir.
                </flux:description>
                <flux:error name="tipoRechazo" />
            </flux:field>
            <flux:field>
                <flux:label>Motivo del rechazo</flux:label>
                <flux:textarea wire:model="motivoRechazo" rows="4"
                    placeholder="Describe el motivo del rechazo para el investigador..." />
                <flux:error name="motivoRechazo" />
            </flux:field>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showRechazoModal', false)">Cancelar</flux:button>
                <flux:button variant="danger" wire:click="rechazar"
                    wire:loading.attr="disabled" wire:target="rechazar">
                    <flux:icon wire:loading wire:target="rechazar" name="arrow-path" class="animate-spin" />
                    Rechazar solicitud
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: rechazar justificaciones --}}
    <flux:modal wire:model="showRechazoJustificacionesModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Rechazar justificaciones</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Selecciona las justificaciones que no aceptas. La solicitud pasará a "Requiere corrección".
            </flux:text>
            <div class="space-y-2">
                @foreach($alertas as $alerta)
                    @if($alerta->estado_revision === 'Pendiente de Revisión')
                        <label class="flex items-start gap-3 rounded-lg border border-border bg-bg-main px-4 py-3 cursor-pointer">
                            <input type="checkbox" wire:model="justificacionesRechazadas" value="{{ $alerta->tipo }}"
                                class="mt-0.5 rounded border-border text-science-blue focus:ring-science-blue" />
                            <span class="text-sm text-text-primary">{{ $alerta->tipo }}</span>
                        </label>
                    @endif
                @endforeach
            </div>
            <flux:error name="justificacionesRechazadas" />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showRechazoJustificacionesModal', false)">Cancelar</flux:button>
                <flux:button variant="danger" wire:click="rechazarJustificaciones"
                    wire:loading.attr="disabled" wire:target="rechazarJustificaciones">
                    <flux:icon wire:loading wire:target="rechazarJustificaciones" name="arrow-path" class="animate-spin" />
                    Rechazar seleccionadas
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
