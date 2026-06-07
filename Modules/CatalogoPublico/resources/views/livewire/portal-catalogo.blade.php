<div>
    {{-- =====================================================================
         NAV BAR TAXONÓMICO — siempre visible, permite explorar por nivel
         ===================================================================== --}}
    <div class="border-b border-border bg-surface">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <nav class="flex items-end gap-1 overflow-x-auto scrollbar-hide" aria-label="Catálogo por nivel taxonómico">
                <a
                    href="/portal"
                    wire:navigate
                    class="shrink-0 px-3 py-2 -mb-px text-xs font-medium transition-colors border-b-2 border-transparent text-text-secondary hover:text-text-primary hover:border-border"
                >
                    Catálogo
                </a>
                <span class="shrink-0 self-end h-4 w-px bg-border mb-2 mx-1"></span>
                @foreach(array_filter($nivelesNavegacion, fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY) as $nivelNav => $etiquetaNav)
                    <button
                        wire:click="explorarNivel('{{ $nivelNav }}')"
                        @class([
                            'shrink-0 px-3 py-2 -mb-px text-xs font-medium transition-colors border-b-2',
                            'border-science-blue text-science-blue' => $nivelExplorar === $nivelNav,
                            'border-transparent text-text-secondary hover:text-text-primary hover:border-border' => $nivelExplorar !== $nivelNav,
                        ])
                    >
                        {{ $etiquetaNav }}
                    </button>
                @endforeach
            </nav>
        </div>
    </div>

    {{-- =====================================================================
         MODO ÁRBOL — navegación jerárquica taxon a taxon
         ===================================================================== --}}
    @if($nivelExplorar === '')

    {{-- BREADCRUMB — aparece en todos los niveles excepto raíz --}}
    @if(count($ruta) > 0)
        <div class="bg-surface border-b border-border">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-3">
                <nav class="flex flex-wrap items-center gap-1.5 text-sm">
                    <button
                        wire:click="navegar('', '')"
                        class="text-science-blue hover:underline transition-colors"
                    >
                        Catálogo
                    </button>

                    @foreach($ruta as $segmento)
                        <flux:icon name="chevron-right" class="size-3.5 text-text-secondary shrink-0" />

                        @if($loop->last)
                            <span class="font-serif italic font-medium text-text-primary">
                                {{ $segmento['taxon'] }}
                            </span>
                            <span class="text-xs text-text-secondary hidden sm:inline">
                                ({{ $segmento['etiqueta'] }})
                            </span>
                        @else
                            <button
                                wire:click="navegar('{{ $segmento['nivel'] }}', '{{ $segmento['taxon'] }}')"
                                class="text-science-blue hover:underline transition-colors"
                            >
                                {{ $segmento['taxon'] }}
                            </button>
                        @endif
                    @endforeach

                    @if($nivelActual === 'species' && $taxonActual !== '')
                        <span class="ml-auto text-xs text-text-secondary tabular-nums hidden sm:block">
                            {{ $conteos['species:'.$taxonActual] ?? 0 }} especímenes
                        </span>
                    @elseif($nivelActual !== '' && $taxonActual !== '')
                        <span class="ml-auto text-xs text-text-secondary tabular-nums hidden sm:block">
                            {{ number_format($conteos[$nivelActual.':'.$taxonActual] ?? 0) }} especímenes
                        </span>
                    @endif
                </nav>
            </div>
        </div>
    @endif

    {{-- =====================================================================
         RAÍZ — presentación del catálogo + grid de filos
         ===================================================================== --}}
    @if($nivelActual === '')
        <div class="bg-blue-navy">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
                <h1 class="font-display text-2xl font-bold text-white">
                    Catálogo
                </h1>
                <p class="mt-1 text-sm text-white/70">
                    Centro de Conservación · Escuela Politécnica Nacional
                </p>
                @if($totalGlobal > 0)
                    <p class="mt-3 text-sm text-white/70">
                        <strong class="text-white tabular-nums">{{ number_format($totalGlobal) }}</strong>
                        especímenes en
                        <strong class="text-white tabular-nums">{{ count($hijos) }}</strong>
                        {{ count($hijos) === 1 ? 'filo' : 'filos' }}
                    </p>
                @endif
            </div>
        </div>

        <x-catalogopublico::filtro-catalogo
            :preparaciones="$preparacionesDisponibles"
            :biomas="$biomasDisponibles"
            :metodos-recoleccion="$metodosRecoleccionDisponibles"
            :colectores="$colectoresDisponibles"
            :filtros-activos="$filtrosActivos"
        />

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($hijos as $hijo)
                    @php
                        $clave = $hijo['nivel'].':'.$hijo['taxon'];
                        $stats = $descendientes[$clave] ?? [];
                        $numEspecimenes = $conteos[$clave] ?? 0;
                    @endphp
                    <button
                        wire:click="navegar('{{ $hijo['nivel'] }}', '{{ $hijo['taxon'] }}')"
                        class="group text-left rounded-lg border border-border bg-surface shadow-sm hover:border-science-blue/40 hover:shadow-md transition-all overflow-hidden"
                    >
                        {{-- Imagen (placeholder hasta implementar el feature de imágenes) --}}
                        <div class="h-28 bg-bg-main border-b border-border flex items-center justify-center">
                            <flux:icon name="photo" class="size-9 text-border" />
                        </div>

                        {{-- Cuerpo de la tarjeta --}}
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-serif italic text-lg text-text-primary group-hover:text-science-blue transition-colors truncate">
                                        {{ $hijo['taxon'] }}
                                    </div>
                                    <div class="text-xs text-text-secondary mt-0.5">
                                        {{ $etiquetas[$hijo['nivel']] ?? $hijo['nivel'] }}
                                    </div>
                                </div>
                                <flux:icon name="chevron-right" class="size-4 text-text-secondary shrink-0 mt-1 group-hover:text-science-blue transition-colors" />
                            </div>

                            {{-- Resumen de descendientes --}}
                            @if(!empty($stats) || $numEspecimenes > 0)
                                <div class="mt-3 pt-3 border-t border-border flex flex-wrap gap-x-3 gap-y-1 text-xs text-text-secondary">
                                    @foreach($etiquetasDescendientes as $nivelStat => $etiquetaStat)
                                        @if(isset($stats[$nivelStat]) && $stats[$nivelStat] > 0)
                                            <span>
                                                <strong class="text-text-primary tabular-nums">{{ number_format($stats[$nivelStat]) }}</strong>
                                                {{ $etiquetaStat }}
                                            </span>
                                        @endif
                                    @endforeach
                                    @if($numEspecimenes > 0)
                                        <span class="text-bio-green font-medium">
                                            <strong class="tabular-nums">{{ number_format($numEspecimenes) }}</strong>
                                            {{ $numEspecimenes === 1 ? 'espécimen' : 'especímenes' }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

    {{-- =====================================================================
         NIVELES INTERMEDIOS — phylum / class / order / family
         (dos columnas: rail de hermanos + grid de hijos)
         ===================================================================== --}}
    @elseif(in_array($nivelActual, ['phylum', 'class', 'order', 'family']))
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex gap-8">

                {{-- Rail de hermanos --}}
                @if(count($hermanos) > 0)
                    <aside class="hidden lg:block w-52 shrink-0">
                        <div class="rounded-lg border border-border bg-surface shadow-sm p-3 sticky top-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-text-secondary mb-2 px-1">
                                Otros {{ strtolower($etiquetas[$nivelActual] ?? $nivelActual) }}s
                            </h4>
                            <div class="space-y-0.5">
                                @foreach($hermanos as $hermano)
                                    <button
                                        wire:click="navegar('{{ $hermano['nivel'] }}', '{{ $hermano['taxon'] }}')"
                                        class="w-full text-left flex items-center justify-between gap-2 rounded px-2 py-1.5 text-sm text-text-secondary hover:text-science-blue hover:bg-science-blue/5 transition-colors"
                                    >
                                        <span class="font-serif italic truncate">{{ $hermano['taxon'] }}</span>
                                        <span class="tabular-nums text-xs shrink-0">
                                            {{ number_format($conteos[$hermano['nivel'].':'.$hermano['taxon']] ?? 0) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </aside>
                @endif

                {{-- Contenido principal --}}
                <div class="flex-1 min-w-0">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-blue-navy font-serif italic">
                            {{ $taxonActual }}
                        </h2>
                        <span class="text-xs text-text-secondary">
                            {{ count($hijos) }} {{ count($hijos) === 1 ? strtolower($etiquetas[$nivelHijo] ?? $nivelHijo) : strtolower($etiquetas[$nivelHijo] ?? $nivelHijo).'s' }}
                        </span>
                    </div>

                    @if(count($hijos) === 0)
                        <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-surface py-12 text-sm text-text-secondary">
                            Sin taxones visibles en este nivel.
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($hijos as $hijo)
                                @php
                                    $clave = $hijo['nivel'].':'.$hijo['taxon'];
                                    $stats = $descendientes[$clave] ?? [];
                                    $numEspecimenes = $conteos[$clave] ?? 0;
                                @endphp
                                <button
                                    wire:click="navegar('{{ $hijo['nivel'] }}', '{{ $hijo['taxon'] }}')"
                                    class="group text-left rounded-lg border border-border bg-surface shadow-sm hover:border-science-blue/40 hover:shadow-md transition-all overflow-hidden"
                                >
                                    {{-- Imagen (placeholder) --}}
                                    <div class="h-20 bg-bg-main border-b border-border flex items-center justify-center">
                                        <flux:icon name="photo" class="size-7 text-border" />
                                    </div>

                                    {{-- Cuerpo --}}
                                    <div class="p-3.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="font-serif italic text-base text-text-primary group-hover:text-science-blue transition-colors truncate">
                                                    {{ $hijo['taxon'] }}
                                                </div>
                                                <div class="text-xs text-text-secondary mt-0.5">
                                                    {{ $etiquetas[$hijo['nivel']] ?? $hijo['nivel'] }}
                                                </div>
                                            </div>
                                            <flux:icon name="chevron-right" class="size-4 text-text-secondary shrink-0 mt-0.5 group-hover:text-science-blue transition-colors" />
                                        </div>

                                        @if(!empty($stats) || $numEspecimenes > 0)
                                            <div class="mt-2.5 pt-2.5 border-t border-border flex flex-wrap gap-x-2.5 gap-y-1 text-xs text-text-secondary">
                                                @foreach($etiquetasDescendientes as $nivelStat => $etiquetaStat)
                                                    @if(isset($stats[$nivelStat]) && $stats[$nivelStat] > 0)
                                                        <span>
                                                            <strong class="text-text-primary tabular-nums">{{ number_format($stats[$nivelStat]) }}</strong>
                                                            {{ $etiquetaStat }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                                @if($numEspecimenes > 0)
                                                    <span class="text-bio-green font-medium">
                                                        <strong class="tabular-nums">{{ number_format($numEspecimenes) }}</strong>
                                                        {{ $numEspecimenes === 1 ? 'espécimen' : 'especímenes' }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

    {{-- =====================================================================
         GÉNERO — lista de especies bajo el género seleccionado
         ===================================================================== --}}
    @elseif($nivelActual === 'genus')
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex gap-8">

                {{-- Rail de géneros hermanos --}}
                @if(count($hermanos) > 0)
                    <aside class="hidden lg:block w-52 shrink-0">
                        <div class="rounded-lg border border-border bg-surface shadow-sm p-3 sticky top-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-text-secondary mb-2 px-1">
                                Otros géneros
                            </h4>
                            <div class="space-y-0.5">
                                @foreach($hermanos as $hermano)
                                    <button
                                        wire:click="navegar('{{ $hermano['nivel'] }}', '{{ $hermano['taxon'] }}')"
                                        class="w-full text-left flex items-center justify-between gap-2 rounded px-2 py-1.5 text-sm text-text-secondary hover:text-science-blue hover:bg-science-blue/5 transition-colors"
                                    >
                                        <span class="font-serif italic truncate">{{ $hermano['taxon'] }}</span>
                                        <span class="tabular-nums text-xs shrink-0">
                                            {{ number_format($conteos[$hermano['nivel'].':'.$hermano['taxon']] ?? 0) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </aside>
                @endif

                {{-- Lista de especies --}}
                <div class="flex-1 min-w-0">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-blue-navy">
                            <span class="font-serif italic">{{ $taxonActual }}</span>
                            <span class="text-base font-normal text-text-secondary">· Género</span>
                        </h2>
                        <span class="text-xs text-text-secondary">
                            {{ count($especiesActuales) }} {{ count($especiesActuales) === 1 ? 'especie' : 'especies' }}
                        </span>
                    </div>

                    @if(count($especiesActuales) === 0)
                        <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-surface py-12 text-sm text-text-secondary">
                            No hay especies divulgadas bajo este género.
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach($especiesActuales as $especie)
                                @php $numEspecimenes = $conteos['species:'.$especie['especie']] ?? 0; @endphp
                                <button
                                    wire:click="navegar('species', '{{ $especie['especie'] }}')"
                                    class="group w-full text-left rounded-lg border border-border bg-surface shadow-sm px-4 py-3.5 hover:border-science-blue/40 hover:shadow transition-all flex items-center justify-between gap-4"
                                >
                                    <div class="min-w-0">
                                        <div class="font-serif italic text-base text-text-primary group-hover:text-science-blue transition-colors">
                                            {{ $especie['especie'] }}
                                        </div>
                                        <div class="text-xs text-text-secondary mt-0.5">
                                            <span class="italic">{{ $especie['genus'] }}</span>
                                            {{ $especie['specificEpithet'] }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        @if($numEspecimenes > 0)
                                            <span class="text-xs text-bio-green font-medium tabular-nums">
                                                {{ $numEspecimenes }} {{ $numEspecimenes === 1 ? 'espécimen' : 'especímenes' }}
                                            </span>
                                        @endif
                                        <flux:icon name="chevron-right" class="size-4 text-text-secondary group-hover:text-science-blue transition-colors" />
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

    {{-- =====================================================================
         ESPECIE — tabla de especímenes divulgados
         ===================================================================== --}}
    @elseif($nivelActual === 'species')
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex gap-8">

                {{-- Rail de especies hermanas --}}
                @if(count($hermanos) > 0)
                    <aside class="hidden lg:block w-52 shrink-0">
                        <div class="rounded-lg border border-border bg-surface shadow-sm p-3 sticky top-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-text-secondary mb-2 px-1">
                                Otras especies
                            </h4>
                            <div class="space-y-0.5">
                                @foreach($hermanos as $hermano)
                                    <button
                                        wire:click="navegar('{{ $hermano['nivel'] }}', '{{ $hermano['taxon'] }}')"
                                        class="w-full text-left flex items-center justify-between gap-2 rounded px-2 py-1.5 text-xs text-text-secondary hover:text-science-blue hover:bg-science-blue/5 transition-colors"
                                    >
                                        <span class="font-serif italic truncate">{{ $hermano['taxon'] }}</span>
                                        <span class="tabular-nums shrink-0">
                                            {{ $conteos['species:'.$hermano['taxon']] ?? 0 }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </aside>
                @endif

                {{-- Registros de especímenes --}}
                <div class="flex-1 min-w-0">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-xl font-semibold text-blue-navy">
                            <span class="font-serif italic">{{ $taxonActual }}</span>
                            <span class="text-base font-normal text-text-secondary">· Especie</span>
                        </h2>
                        <span class="text-xs text-text-secondary tabular-nums">
                            {{ count($especimenes) }} {{ count($especimenes) === 1 ? 'espécimen' : 'especímenes' }}
                        </span>
                    </div>

                    @if(count($especimenes) === 0)
                        <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-surface py-12 text-sm text-text-secondary">
                            No hay especímenes registrados para esta especie.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($especimenes as $especimen)
                                @php
                                    $typeBadgeColor = match(strtolower($especimen->type_status ?? '')) {
                                        'holotype'                  => 'warning',
                                        'paratype', 'allotype'      => 'blue',
                                        'syntype', 'lectotype',
                                        'paralectotype', 'neotype'  => 'zinc',
                                        default                     => 'zinc',
                                    };
                                    $lat = $especimen->decimal_latitude;
                                    $lon = $especimen->decimal_longitude;
                                    $coordStr = ($lat !== null && $lon !== null)
                                        ? number_format(abs((float) $lat), 5).'°'.($lat >= 0 ? 'N' : 'S')
                                          .' · '.number_format(abs((float) $lon), 5).'°'.($lon >= 0 ? 'E' : 'O')
                                        : null;
                                    $localidad = collect([$especimen->locality_name, $especimen->country])
                                        ->filter()
                                        ->implode(' · ');
                                @endphp
                                <article class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                                    <div class="flex">

                                        {{-- Miniatura de imagen (placeholder hasta implementar el feature de imágenes) --}}
                                        <div class="hidden sm:flex w-28 shrink-0 flex-col items-center justify-center gap-1.5 bg-bg-main border-r border-border py-4">
                                            <flux:icon name="photo" class="size-7 text-border" />
                                            <span class="text-xs text-border leading-none">Sin imagen</span>
                                        </div>

                                        {{-- Contenido del registro --}}
                                        <div class="flex-1 min-w-0 p-4">

                                            {{-- Cabecera: código + badges + conteo --}}
                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                                <code class="rounded border border-border bg-bg-main px-2 py-0.5 text-xs font-medium text-text-primary">
                                                    {{ $especimen->occurrence_id }}
                                                </code>
                                                @if($especimen->type_status)
                                                    <flux:badge color="{{ $typeBadgeColor }}" size="sm">
                                                        {{ $especimen->type_status }}
                                                    </flux:badge>
                                                @endif
                                                @if($especimen->occurrence_status)
                                                    <x-catalogopublico::occurrence-status-badge
                                                        :status="$especimen->occurrence_status"
                                                    />
                                                @endif
                                                @if(($especimen->individual_count ?? 0) > 1)
                                                    <span class="text-xs text-text-secondary">
                                                        {{ $especimen->individual_count }} individuos
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- Nombre científico --}}
                                            <p class="mb-3 font-serif italic text-base text-text-primary">
                                                {{ $especimen->scientific_name }}
                                            </p>

                                            {{-- Metadatos como lista de definición --}}
                                            <dl class="grid grid-cols-1 gap-x-8 gap-y-1.5 text-xs sm:grid-cols-2">
                                                @if($localidad !== '')
                                                    <div class="flex gap-2 sm:col-span-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Localidad</dt>
                                                        <dd class="text-text-primary">{{ $localidad }}</dd>
                                                    </div>
                                                @endif
                                                @if($coordStr)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Coordenadas</dt>
                                                        <dd class="font-mono text-text-primary">{{ $coordStr }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->recorded_by)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Recolector</dt>
                                                        <dd class="text-text-primary">{{ $especimen->recorded_by }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->sampling_protocol)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Método</dt>
                                                        <dd class="text-text-primary">{{ $especimen->sampling_protocol }}</dd>
                                                    </div>
                                                @endif
                                            </dl>

                                            {{-- Notas (opcionales) --}}
                                            @if($especimen->type_notes || $especimen->specimen_notes)
                                                <div class="mt-3 space-y-1 border-t border-border pt-3">
                                                    @if($especimen->type_notes)
                                                        <p class="text-xs text-text-secondary">
                                                            <span class="font-medium text-text-primary not-italic">Nota de tipo:</span>
                                                            {{ $especimen->type_notes }}
                                                        </p>
                                                    @endif
                                                    @if($especimen->specimen_notes)
                                                        <p class="text-xs italic text-text-secondary">
                                                            {{ $especimen->specimen_notes }}
                                                        </p>
                                                    @endif
                                                </div>
                                            @endif

                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @else
    {{-- =====================================================================
         MODO EXPLORAR — todos los taxones de un nivel dado
         ===================================================================== --}}
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Encabezado --}}
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-xl font-semibold text-blue-navy capitalize">
                    {{ $etiquetas[$nivelExplorar] ?? $nivelExplorar }}s divulgados
                </h2>
                <p class="mt-0.5 text-sm text-text-secondary">
                    @if(count($taxonesExplorados) === 0)
                        Ningún taxón divulgado en este nivel.
                    @else
                        <strong class="text-text-primary tabular-nums">{{ number_format(count($taxonesExplorados)) }}</strong>
                        {{ $nivelesPluralNavegacion[$nivelExplorar] ?? $nivelExplorar }} en la colección
                    @endif
                </p>
            </div>
            <button
                wire:click="volverAlArbol"
                class="shrink-0 flex items-center gap-1.5 text-xs text-text-secondary hover:text-science-blue transition-colors"
            >
                <flux:icon name="x-mark" class="size-3.5" />
                Volver al árbol
            </button>
        </div>

        @if(count($taxonesExplorados) === 0)
            <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-surface py-16 text-sm text-text-secondary">
                No hay taxones divulgados en este nivel.
            </div>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($taxonesExplorados as $nodo)
                    @php
                        $clave = $nodo['nivel'].':'.$nodo['taxon'];
                        $stats = $descendientes[$clave] ?? [];
                        $numEspecimenes = $conteos[$clave] ?? 0;
                    @endphp
                    <button
                        wire:click="navegar('{{ $nodo['nivel'] }}', '{{ $nodo['taxon'] }}')"
                        class="group text-left rounded-lg border border-border bg-surface shadow-sm hover:border-science-blue/40 hover:shadow-md transition-all overflow-hidden"
                    >
                        {{-- Imagen (placeholder) --}}
                        <div class="h-20 bg-bg-main border-b border-border flex items-center justify-center">
                            <flux:icon name="photo" class="size-7 text-border" />
                        </div>

                        {{-- Cuerpo --}}
                        <div class="p-3.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-serif italic text-base text-text-primary group-hover:text-science-blue transition-colors truncate">
                                        {{ $nodo['taxon'] }}
                                    </div>
                                    <div class="text-xs text-text-secondary mt-0.5">
                                        {{ $etiquetas[$nodo['nivel']] ?? $nodo['nivel'] }}
                                        @if($nodo['nivel'] !== 'phylum')
                                            <span class="text-text-secondary/60">· {{ $nodo['padre'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <flux:icon name="chevron-right" class="size-4 text-text-secondary shrink-0 mt-0.5 group-hover:text-science-blue transition-colors" />
                            </div>

                            @if(!empty($stats) || $numEspecimenes > 0)
                                <div class="mt-2.5 pt-2.5 border-t border-border flex flex-wrap gap-x-2.5 gap-y-1 text-xs text-text-secondary">
                                    @foreach($etiquetasDescendientes as $nivelStat => $etiquetaStat)
                                        @if(isset($stats[$nivelStat]) && $stats[$nivelStat] > 0)
                                            <span>
                                                <strong class="text-text-primary tabular-nums">{{ number_format($stats[$nivelStat]) }}</strong>
                                                {{ $etiquetaStat }}
                                            </span>
                                        @endif
                                    @endforeach
                                    @if($numEspecimenes > 0)
                                        <span class="text-bio-green font-medium">
                                            <strong class="tabular-nums">{{ number_format($numEspecimenes) }}</strong>
                                            {{ $numEspecimenes === 1 ? 'espécimen' : 'especímenes' }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @endif {{-- fin modo árbol / explorar --}}

    {{-- Indicador de carga --}}
    <div
        wire:loading.delay
        class="fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 shadow-sm text-xs text-text-secondary"
    >
        <span class="inline-block size-3 rounded-full border-2 border-science-blue border-t-transparent animate-spin"></span>
        Cargando…
    </div>
</div>
