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
                            {{ $conteos['species:'.$taxonActual] ?? 0 }} registros
                        </span>
                    @elseif($nivelActual !== '' && $taxonActual !== '')
                        <span class="ml-auto text-xs text-text-secondary tabular-nums hidden sm:block">
                            {{ number_format($conteos[$nivelActual.':'.$taxonActual] ?? 0) }} registros
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
                        registros en
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
                                            {{ $numEspecimenes === 1 ? 'registro' : 'registros' }}
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
                                    {{-- Imagen (solo para género; filo/clase/orden/familia son tarjetas simples) --}}
                                    @if($hijo['nivel'] === 'genus')
                                        @php $portadaUrl = $portadas['genus:'.$hijo['taxon']] ?? null; @endphp
                                        <div class="h-20 bg-bg-main border-b border-border flex items-center justify-center overflow-hidden">
                                            @if($portadaUrl)
                                                <img src="{{ $portadaUrl }}" alt="{{ $hijo['taxon'] }}" class="h-full w-full object-cover" />
                                            @else
                                                <flux:icon name="photo" class="size-7 text-border" />
                                            @endif
                                        </div>
                                    @endif

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
                                                        {{ $numEspecimenes === 1 ? 'registro' : 'registros' }}
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
                                @php
                                    $numEspecimenes = $conteos['species:'.$especie['especie']] ?? 0;
                                    $portadaEspecie = $portadas['species:'.$especie['especie']] ?? null;
                                @endphp
                                <button
                                    wire:click="navegar('species', '{{ $especie['especie'] }}')"
                                    class="group w-full text-left rounded-lg border border-border bg-surface shadow-sm px-4 py-3.5 hover:border-science-blue/40 hover:shadow transition-all flex items-center gap-4"
                                >
                                    {{-- Imagen por defecto de la especie (si tiene) --}}
                                    @if($portadaEspecie)
                                        <div class="size-14 shrink-0 overflow-hidden rounded-lg border border-border bg-bg-main">
                                            <img src="{{ $portadaEspecie }}" alt="{{ $especie['especie'] }}" class="h-full w-full object-cover" loading="lazy" />
                                        </div>
                                    @else
                                        <div class="size-14 shrink-0 flex items-center justify-center rounded-lg border border-dashed border-border bg-bg-main">
                                            <flux:icon name="photo" class="size-5 text-border" />
                                        </div>
                                    @endif

                                    <div class="min-w-0 flex-1">
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
                                                {{ $numEspecimenes }} {{ $numEspecimenes === 1 ? 'registro' : 'registros' }}
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
         ESPECIE — tabla de registros divulgados
         ===================================================================== --}}
    @elseif($nivelActual === 'species')
        @assets
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        @endassets
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

                {{-- Registros de registros --}}
                <div class="flex-1 min-w-0">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="font-display text-xl font-semibold text-blue-navy">
                            <span class="font-serif italic">{{ $taxonActual }}</span>
                            <span class="text-base font-normal text-text-secondary">· Especie</span>
                        </h2>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-secondary tabular-nums">
                                {{ count($especimenes) }} {{ count($especimenes) === 1 ? 'registro' : 'registros' }}
                            </span>
                            @if(count($especimenes) > 0)
                                <button
                                    wire:click="descargarDatos"
                                    wire:loading.attr="disabled"
                                    wire:target="descargarDatos"
                                    class="flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-2 text-xs font-medium text-text-secondary shadow-sm transition-colors hover:border-science-blue/50 hover:text-science-blue disabled:opacity-50 w-full sm:w-auto"
                                >
                                    <span wire:loading.remove wire:target="descargarDatos" class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        Descargar datos
                                    </span>
                                    <span wire:loading wire:target="descargarDatos" class="flex items-center gap-1.5">
                                        <span class="inline-block size-3 rounded-full border-2 border-current border-t-transparent animate-spin"></span>
                                        Generando…
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- ══════════════════════════════════
                         IMAGEN DESTACADA DE LA ESPECIE (portada por defecto)
                         ══════════════════════════════════ --}}
                    @php $portadaEspecie = collect($galeriaEspecie)->firstWhere('esPortada', true); @endphp
                    <section class="mb-6">
                        <div class="mb-3 flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-text-primary flex items-center gap-2">
                                <flux:icon name="photo" class="size-4 text-text-secondary" />
                                Imagen de la especie
                            </h3>
                        </div>

                        @if(! $portadaEspecie)
                            <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-bg-main py-10 text-sm text-text-secondary gap-2">
                                <flux:icon name="photo" class="size-5 text-border" />
                                Especie sin imagen destacada
                            </div>
                        @else
                            <div class="max-w-xs overflow-hidden rounded-lg border border-bio-green ring-2 ring-bio-green/30 bg-surface shadow-sm">
                                <div class="aspect-square bg-bg-main">
                                    <img src="{{ $portadaEspecie['url'] }}" alt="{{ $taxonActual }}" class="h-full w-full object-cover" loading="lazy" />
                                </div>
                            </div>
                        @endif
                    </section>

                    {{-- ══════════════════════════════════
                         MAPA DE DISTRIBUCIÓN
                         ══════════════════════════════════ --}}
                    @php
                        $puntosGeo = collect($especimenes)
                            ->filter(fn($e) => $e->decimal_latitude !== null && $e->decimal_longitude !== null)
                            ->map(fn($e) => [
                                'lat'             => (float) $e->decimal_latitude,
                                'lon'             => (float) $e->decimal_longitude,
                                'occurrence_id'   => $e->occurrence_id,
                                'scientific_name' => $e->scientific_name,
                                'locality'        => collect([$e->locality_name, $e->country])->filter()->implode(' · '),
                            ])
                            ->values();
                    @endphp

                    <section class="mb-6">
                        <div class="mb-3 flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-text-primary flex items-center gap-2">
                                <flux:icon name="map-pin" class="size-4 text-text-secondary" />
                                Mapa de distribución
                            </h3>
                            @if($puntosGeo->count() > 0)
                                <span class="text-xs text-text-secondary tabular-nums">
                                    {{ $puntosGeo->count() }} {{ $puntosGeo->count() === 1 ? 'localidad' : 'localidades' }}
                                </span>
                            @endif
                        </div>

                        @if($puntosGeo->isEmpty())
                            <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-bg-main py-10 text-sm text-text-secondary gap-2">
                                <flux:icon name="map-pin" class="size-5 text-border" />
                                Sin datos geográficos disponibles
                            </div>
                        @else
                            <div
                                x-data="{
                                    puntos: {{ Js::from($puntosGeo) }},
                                    mapa: null,
                                    init() {
                                        this.mapa = L.map(this.$refs.mapaContainer, {
                                            scrollWheelZoom: false,
                                        });
                                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                            attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a>',
                                            maxZoom: 18,
                                        }).addTo(this.mapa);

                                        const marcadores = this.puntos.map(p => {
                                            const m = L.marker([p.lat, p.lon]).addTo(this.mapa);
                                            m.bindPopup(
                                                '<div style=\'font-family:sans-serif;font-size:13px;min-width:160px\'>' +
                                                '<p style=\'font-style:italic;font-weight:600;margin:0 0 4px\'>' + p.scientific_name + '</p>' +
                                                '<p style=\'font-family:monospace;font-size:11px;margin:0 0 2px\'>' + p.occurrence_id + '</p>' +
                                                (p.locality ? '<p style=\'color:#666;font-size:11px;margin:0\'>' + p.locality + '</p>' : '') +
                                                '</div>'
                                            );
                                            return m;
                                        });

                                        if (marcadores.length === 1) {
                                            this.mapa.setView([this.puntos[0].lat, this.puntos[0].lon], 10);
                                        } else {
                                            const grupo = L.featureGroup(marcadores);
                                            this.mapa.fitBounds(grupo.getBounds().pad(0.2));
                                        }

                                        this.$cleanup(() => {
                                            if (this.mapa) {
                                                this.mapa.remove();
                                                this.mapa = null;
                                            }
                                        });
                                    }
                                }"
                                class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden"
                            >
                                <div x-ref="mapaContainer" class="h-72 sm:h-96 w-full"></div>
                            </div>
                        @endif
                    </section>

                    @if(count($especimenes) === 0)
                        <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-surface py-12 text-sm text-text-secondary">
                            No hay registros registrados para esta especie.
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
                                    $elevMin = $especimen->elevation_min_m ?? null;
                                    $elevMax = $especimen->elevation_max_m ?? null;
                                    $elevStr = match (true) {
                                        $elevMin !== null && $elevMax !== null && (float) $elevMin !== (float) $elevMax
                                            => number_format((float) $elevMin).'–'.number_format((float) $elevMax).' m',
                                        $elevMin !== null => number_format((float) $elevMin).' m',
                                        $elevMax !== null => number_format((float) $elevMax).' m',
                                        default           => null,
                                    };
                                    $imagenesEspecimen = $imagenesPorEspecimen[$especimen->occurrence_id] ?? [];
                                    $numImagenes = count($imagenesEspecimen);
                                @endphp
                                <article
                                    x-data="{ abierto: false }"
                                    class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden"
                                >
                                    <div class="flex">

                                        {{-- Miniatura: si hay imágenes, abre la galería del registro; si no, placeholder --}}
                                        @if($numImagenes > 0)
                                            <button
                                                type="button"
                                                @click="abierto = !abierto"
                                                class="group/thumb relative hidden sm:block w-28 shrink-0 bg-bg-main border-r border-border overflow-hidden"
                                                title="Ver {{ $numImagenes }} {{ $numImagenes === 1 ? 'imagen' : 'imágenes' }}"
                                            >
                                                <img src="{{ $imagenesEspecimen[0]['url'] }}" alt="{{ $especimen->occurrence_id }}" class="h-full w-full object-cover" loading="lazy" />
                                                <span class="absolute inset-0 flex items-center justify-center bg-blue-navy/0 transition-colors group-hover/thumb:bg-blue-navy/30">
                                                    <span class="flex items-center gap-1 rounded-full bg-blue-navy/80 px-2 py-0.5 text-xs font-medium text-white">
                                                        <flux:icon name="photo" class="size-3.5" />
                                                        {{ $numImagenes }}
                                                    </span>
                                                </span>
                                            </button>
                                        @else
                                            <div class="hidden sm:flex w-28 shrink-0 flex-col items-center justify-center gap-1.5 bg-bg-main border-r border-border py-4">
                                                <flux:icon name="photo" class="size-7 text-border" />
                                                <span class="text-xs text-border leading-none">Sin imagen</span>
                                            </div>
                                        @endif

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

                                                {{-- Conteo de imágenes del registro: despliega la galería --}}
                                                @if($numImagenes > 0)
                                                    <button
                                                        type="button"
                                                        @click="abierto = !abierto"
                                                        class="inline-flex items-center gap-1 rounded-full border border-science-blue/30 bg-science-blue/5 px-2 py-0.5 text-xs font-medium text-science-blue transition-colors hover:bg-science-blue/10"
                                                    >
                                                        <flux:icon name="photo" class="size-3.5" />
                                                        {{ $numImagenes }} {{ $numImagenes === 1 ? 'imagen' : 'imágenes' }}
                                                        <flux:icon name="chevron-down" class="size-3.5 transition-transform" x-bind:class="abierto ? 'rotate-180' : ''" />
                                                    </button>
                                                @endif
                                            </div>

                                            {{-- Nombre científico --}}
                                            <p class="mb-3 font-serif italic text-base text-text-primary">
                                                {{ $especimen->scientific_name }}
                                            </p>

                                            {{-- Metadatos como lista de definición --}}
                                            <dl class="grid grid-cols-1 gap-x-8 gap-y-1.5 text-xs sm:grid-cols-2">
                                                @if($especimen->locality_name)
                                                    <div class="flex gap-2 sm:col-span-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Localidad</dt>
                                                        <dd class="text-text-primary">{{ $especimen->locality_name }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->country)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">País</dt>
                                                        <dd class="text-text-primary">{{ $especimen->country }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->state_province)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Provincia</dt>
                                                        <dd class="text-text-primary">{{ $especimen->state_province }}</dd>
                                                    </div>
                                                @endif
                                                @if($elevStr)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Elevación</dt>
                                                        <dd class="text-text-primary tabular-nums">{{ $elevStr }}</dd>
                                                    </div>
                                                @endif
                                                @if($coordStr)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Coordenadas</dt>
                                                        <dd class="font-mono text-text-primary">{{ $coordStr }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->event_date)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Recolección</dt>
                                                        <dd class="text-text-primary tabular-nums">{{ $especimen->event_date }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->sampling_protocol)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Método</dt>
                                                        <dd class="text-text-primary">{{ $especimen->sampling_protocol }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->recorded_by)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Recolector</dt>
                                                        <dd class="text-text-primary">{{ $especimen->recorded_by }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->caste)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Casta</dt>
                                                        <dd class="text-text-primary">{{ $especimen->caste }}</dd>
                                                    </div>
                                                @endif
                                                @if($especimen->life_stage)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Estadio</dt>
                                                        <dd class="text-text-primary">{{ $especimen->life_stage }}</dd>
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

                                            {{-- Galería del registro: se despliega al pulsar la miniatura o el conteo --}}
                                            @if($numImagenes > 0)
                                                <div x-show="abierto" x-collapse x-cloak class="mt-3 border-t border-border pt-3">
                                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                                                        @foreach($imagenesEspecimen as $img)
                                                            <div class="overflow-hidden rounded-lg border border-border bg-bg-main">
                                                                <div class="aspect-square">
                                                                    <img src="{{ $img['url'] }}" alt="{{ $especimen->occurrence_id }}" class="h-full w-full object-cover" loading="lazy" />
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
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
                        {{-- Imagen (solo para género; filo/clase/orden/familia son tarjetas simples) --}}
                        @if($nodo['nivel'] === 'genus')
                            @php $portadaUrl = $portadas['genus:'.$nodo['taxon']] ?? null; @endphp
                            <div class="h-20 bg-bg-main border-b border-border flex items-center justify-center overflow-hidden">
                                @if($portadaUrl)
                                    <img src="{{ $portadaUrl }}" alt="{{ $nodo['taxon'] }}" class="h-full w-full object-cover" />
                                @else
                                    <flux:icon name="photo" class="size-7 text-border" />
                                @endif
                            </div>
                        @endif

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
                                            {{ $numEspecimenes === 1 ? 'registro' : 'registros' }}
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
