<div x-data="portalCatalogo" data-catalogo-seleccion="{{ json_encode($this->seleccionPublicaChat) }}" data-catalogo-historial="{{ json_encode($this->historialCatalogo) }}" x-on:catalogo-estado-url.window="actualizarHistorial($event.detail)" x-on:criterio-retirado.window="$nextTick(() => ($el.querySelector('.collection-active-filters button') || $el.querySelector('.collection-view-switch button'))?.focus({preventScroll: true}))">
    @php
        $etiquetaConteo = static fn (string $nivel, int $cantidad, string $plural): string => $cantidad === 1 ? (['kingdom' => 'reino', 'phylum' => 'filo', 'class' => 'clase', 'order' => 'orden', 'family' => 'familia', 'genus' => 'género', 'species' => 'especie'][$nivel] ?? $plural) : $plural;
    @endphp
    @if($avisoSeleccionUrl !== '')<p role="status" class="mx-auto max-w-7xl px-4 py-3 text-sm text-text-secondary">{{ $avisoSeleccionUrl }}</p>@endif
    <div x-cloak x-show="errorHistorial" class="mx-auto max-w-7xl px-4 py-3 text-sm text-text-secondary">
        <p x-text="errorHistorial" role="alert"></p>
        <button type="button" class="mt-2 underline text-science-blue" x-on:click="window.location.reload()">Recargar la selección del enlace actual</button>
    </div>
    <div x-cloak x-show="errorConsulta" class="mx-auto max-w-7xl px-4 py-3 text-sm text-text-secondary">
        <p x-text="errorConsulta" role="alert"></p>
        <a :href="enlaceReintento" class="mt-2 inline-block underline text-science-blue">Reintentar la selección</a>
    </div>
    @if($vista === 'tarjetas')
    {{-- =====================================================================
         NAV BAR TAXONÓMICO — siempre visible, permite explorar por nivel
         ===================================================================== --}}
    <div class="border-b border-border bg-surface">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <nav class="flex items-end gap-1 overflow-x-auto scrollbar-hide" aria-label="Catálogo por nivel taxonómico">
                <button
                    type="button"
                    wire:click="navegar('', '')"
                    class="shrink-0 px-3 py-2 -mb-px text-xs font-medium transition-colors border-b-2 border-transparent text-text-secondary hover:text-text-primary hover:border-border"
                >
                    Catálogo
                </button>
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
    <x-catalogopublico::avisos-curatoriales :total="$curatoriales_total ?? 0" :notas="$curatoriales ?? []" />
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
                            {{ number_format($conteos['species:'.$taxonActual] ?? 0, 0, ',', '.') }} {{ (int) ($conteos['species:'.$taxonActual] ?? 0) === 1 ? 'registro' : 'registros' }}
                        </span>
                    @elseif($nivelActual !== '' && $taxonActual !== '')
                        <span class="ml-auto text-xs text-text-secondary tabular-nums hidden sm:block">
                            {{ number_format($conteos[$nivelActual.':'.$taxonActual] ?? 0, 0, ',', '.') }} {{ (int) ($conteos[$nivelActual.':'.$taxonActual] ?? 0) === 1 ? 'registro' : 'registros' }}
                        </span>
                    @endif
                </nav>
            </div>
        </div>
    @endif

    @endif {{-- fin breadcrumb del modo árbol --}}
    @endif {{-- fin navegación de tarjetas --}}


    <div class="collection-loading" wire:loading.delay wire:target="borradorFiltros,cambiarVista,aplicarBorrador,limpiarFiltros,retirarCriterio,seleccionarFilo,seleccionarProvincia,seleccionarDecada,seleccionarMes,seleccionarAltitud,seleccionarMetodo,seleccionarArea,explorarNivel,navegar,cambiarPagina,cambiarPaginaHermanos,abrirFichaRegistro">
        <span class="collection-loading-indicator" role="status" aria-live="polite"><span class="atlas-spinner" aria-hidden="true"></span><span>Actualizando filtros y registros…</span></span>
    </div>
    <div class="collection-workspace" wire:loading.attr="inert" wire:loading.class="is-updating">
        <x-catalogopublico::filtro-investigacion
            :provincias="$provinciasDisponibles" :localidades="$this->localidadesDisponibles" :filos="$filosDisponibles"
            :preparaciones="$preparacionesDisponibles" :metodos="$metodosRecoleccionDisponibles"
            :biomas="$biomasDisponibles" :hay-filtros-activos="$hayFiltrosActivos"
            :aplicados="$this->filtrosAplicados"
        />
        <div class="collection-main">
    @if($taxon !== '')
        <div class="collection-selection" role="status"><span>Selección: <strong>{{ $taxon }}</strong></span><button type="button" wire:click="quitarTaxon">Quitar taxón ×</button></div>
    @endif
    @if($this->criteriosActivos !== [])
        <section class="collection-active-filters" aria-label="Filtros activos">
            <h2 class="sr-only">Filtros activos de la selección aplicada</h2>
            @foreach($this->criteriosActivos as $criterio)
                <button type="button" wire:key="criterio-{{ $criterio['clave'] }}-{{ $criterio['indice'] }}" wire:click="retirarCriterio(@js($criterio['clave']), {{ $criterio['indice'] }})" wire:loading.attr="disabled"
                    aria-label="Quitar {{ $criterio['etiqueta'] }}: {{ $criterio['valor'] }}">
                    <strong>{{ $criterio['etiqueta'] }}:</strong> {{ $criterio['valor'] }} <span aria-hidden="true">×</span>
                </button>
            @endforeach
        </section>
    @endif
    <nav class="collection-view-bar" aria-label="Vista de la Colección Biológica">
        <div class="collection-view-switch">
            <button type="button" wire:loading.attr="disabled" wire:click="cambiarVista('tarjetas')" aria-label="Vista de tarjetas" title="Tarjetas" aria-pressed="{{ $vista === 'tarjetas' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span class="sr-only">Tarjetas</span></button>
            <button type="button" wire:loading.attr="disabled" wire:click="cambiarVista('registros')" aria-label="Vista de registros" title="Registros" aria-pressed="{{ $vista === 'registros' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h3m4 0h9M4 12h3m4 0h9M4 18h3m4 0h9"/></svg><span class="sr-only">Registros</span></button>
            <button type="button" wire:loading.attr="disabled" wire:click="cambiarVista('mapa')" aria-label="Vista de mapa y análisis" title="Mapa y análisis" aria-pressed="{{ $vista === 'mapa' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2zM9 3v16m6-14v16"/></svg><span class="sr-only">Mapa y análisis</span></button>
        </div>
    </nav>

    @include('catalogopublico::components.ficha-registro-publico')
    @if($vista === 'mapa')
        @if($errorMapa ?? false)
            <section class="mx-auto max-w-7xl px-4 py-8" aria-labelledby="mapa-error-titulo">
                <h1 id="mapa-error-titulo" class="font-display text-2xl font-bold text-blue-navy">No se pudo cargar el mapa</h1>
                <p class="mt-3" role="alert">La consulta no pudo completarse en el tiempo disponible. Tus filtros se conservan; puedes reintentar o consultar los registros.</p>
                <button type="button" wire:click="$refresh" class="mt-4 underline text-science-blue">Reintentar mapa</button>
                <button type="button" wire:click="cambiarVista('registros')" class="mt-4 ml-4 underline text-science-blue">Ver registros de la selección</button>
            </section>
        @else
        @include('catalogopublico::dashboard-coleccion')
        @include('catalogopublico::components.detalle-celda-mapa')
        @endif
    @elseif($vista === 'registros')
        <div class="mx-auto max-w-7xl px-4 pb-10 pt-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl font-bold text-blue-navy">
                        {{ $taxonActual !== '' ? 'Registros de '.$taxonActual : 'Registros del catálogo' }}
                    </h1>
                    <p class="mt-1 text-sm text-text-secondary">{{ number_format($totalRegistrosVista, 0, ',', '.') }} {{ $totalRegistrosVista === 1 ? 'registro' : 'registros' }} · página {{ $paginaActual }} de {{ $ultimaPagina }}</p>
                </div>
                @if($totalRegistrosVista > 0)
                    <button type="button" wire:click="descargarResultados" wire:loading.attr="disabled" wire:target="descargarResultados" class="rounded-md border border-science-blue px-4 py-2 text-sm font-semibold text-science-blue hover:bg-sky-50 disabled:opacity-50">Descargar resultados CSV</button>
                @endif
            </div>
            @if($totalRegistrosVista === 0)
                <p class="rounded-lg border border-border bg-surface p-8 text-center text-text-secondary">No hay registros públicos para esta selección.</p>
            @else
                <x-catalogopublico::tabla-registros-mapa :registros="$registrosVista" :imagenes="$imagenesRegistrosVista" :total="$totalRegistrosVista" contexto="catalogo" :mostrar-titulo="false" />
                <nav class="mt-5 flex items-center justify-between gap-3" aria-label="Páginas de registros">
                    <button type="button" wire:click="cambiarPagina({{ $paginaActual - 1 }})" @disabled($paginaActual <= 1)
                        class="rounded-lg border border-border px-4 py-2 text-sm text-science-blue disabled:cursor-not-allowed disabled:opacity-40">Anterior</button>
                    <span class="text-sm text-text-secondary">{{ $paginaActual }} / {{ $ultimaPagina }}</span>
                    <button type="button" wire:click="cambiarPagina({{ $paginaActual + 1 }})" @disabled($paginaActual >= $ultimaPagina)
                        class="rounded-lg border border-border px-4 py-2 text-sm text-science-blue disabled:cursor-not-allowed disabled:opacity-40">Siguiente</button>
                </nav>
            @endif
        </div>

    @else
    @if($nivelActual !== '' && $nivelActual !== 'species' && $taxonActual !== '' && ($conteos[$nivelActual.':'.$taxonActual] ?? 0) > 0)
        <section class="collection-taxon-records" aria-label="Registros del taxón seleccionado">
            <p>{{ number_format($conteos[$nivelActual.':'.$taxonActual], 0, ',', '.') }} {{ $conteos[$nivelActual.':'.$taxonActual] === 1 ? 'registro público' : 'registros públicos' }} de <em>{{ $taxonActual }}</em>, incluidos los identificados hasta este rango.</p>
            <button type="button" wire:click="cambiarVista('registros')" wire:loading.attr="disabled">Ver registros de este taxón</button>
        </section>
    @endif
    @if($nivelExplorar === '')
    {{-- =====================================================================
         RAÍZ — presentación del catálogo + grid de filos
         ===================================================================== --}}
    @if($nivelActual === '')
        <div class="bg-blue-navy">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
                <h1 class="font-display text-2xl font-bold text-white">
                    Catálogo del laboratorio de invertebrados
                </h1>
            </div>
        </div>


        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            @if(count($hijos) === 0)
                <div role="status" class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-surface px-6 py-12 text-center">
                    <flux:icon name="magnifying-glass" class="size-6 text-text-secondary" />
                    <h2 class="font-display text-lg font-semibold text-text-primary">Sin resultados</h2>
                    <p class="max-w-md text-sm text-text-secondary">
                        No se encontraron taxones públicos que coincidan con los filtros aplicados.
                    </p>
                </div>
            @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($hijos as $hijo)
                    @php
                        $clave = $hijo['nivel'].':'.$hijo['taxon'];
                        $stats = $descendientes[$clave] ?? [];
                        $numEspecimenes = $hijo['total'] ?? $conteos[$clave] ?? 0;
                    @endphp
                    <article class="rounded-lg border border-border bg-surface p-4 shadow-sm transition-all hover:border-science-blue/40 hover:shadow-md">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="navegar('{{ $hijo['nivel'] }}', '{{ $hijo['taxon'] }}')" class="truncate font-serif text-lg italic text-text-primary transition-colors hover:text-science-blue">{{ $hijo['taxon'] }}</button>
                                    <x-catalogopublico::ayuda-taxon :nombre="$hijo['taxon']" :nivel="$hijo['nivel']" :registros="$numEspecimenes" :stats="$stats" :jerarquia="array_column($ruta, 'taxon', 'nivel')" :foto-publica="$portadas[$hijo['nivel'].':'.$hijo['taxon']] ?? null" />
                                </div>
                                <div class="mt-0.5 text-xs text-text-secondary">{{ $etiquetas[$hijo['nivel']] ?? $hijo['nivel'] }}</div>
                            </div>
                            <button type="button" wire:click="navegar('{{ $hijo['nivel'] }}', '{{ $hijo['taxon'] }}')" class="shrink-0 text-text-secondary hover:text-science-blue" aria-label="Explorar {{ $hijo['taxon'] }}"><flux:icon name="chevron-right" class="mt-1 size-4" /></button>
                        </div>

                            {{-- Resumen de descendientes --}}
                            @if(!empty($stats) || $numEspecimenes > 0)
                                <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 border-t border-border pt-3 text-xs text-text-secondary">
                                    @foreach($etiquetasDescendientes as $nivelStat => $etiquetaStat)
                                        @if(isset($stats[$nivelStat]) && $stats[$nivelStat] > 0)
                                            <span>
                                                <strong class="text-text-primary tabular-nums">{{ number_format($stats[$nivelStat], 0, ',', '.') }}</strong>
                                                {{ $etiquetaConteo($nivelStat, (int) $stats[$nivelStat], $etiquetaStat) }}
                                            </span>
                                        @endif
                                    @endforeach
                                    @if($numEspecimenes > 0)
                                        <span class="text-bio-green font-medium">
                                            <strong class="tabular-nums">{{ number_format($numEspecimenes, 0, ',', '.') }}</strong>
                                            {{ $numEspecimenes === 1 ? 'registro' : 'registros' }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                    </article>
                @endforeach
            </div>
            @endif
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
                                {{ in_array($nivelActual, ['class', 'family'], true) ? 'Otras' : 'Otros' }} {{ $nivelesPluralNavegacion[$nivelActual] ?? 'taxones' }}
                            </h4>
                            <div class="space-y-0.5">
                                @foreach($hermanos as $hermano)
                                    <button
                                        type="button" wire:click="navegar(@js($hermano['nivel']), @js($hermano['taxon']))" wire:loading.attr="disabled" wire:target="navegar,cambiarPaginaHermanos"
                                        class="w-full text-left flex items-center justify-between gap-2 rounded px-2 py-1.5 text-sm text-text-secondary hover:text-science-blue hover:bg-science-blue/5 transition-colors"
                                    >
                                        <span class="font-serif italic truncate">{{ $hermano['taxon'] }}</span>
                                        <span class="tabular-nums text-xs shrink-0">
                                            {{ number_format((int) ($hermano['total'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            <x-catalogopublico::paginacion-hermanos :total="$totalHermanos" :pagina="$paginaHermanosActual" :ultima="$ultimaPaginaHermanos" />
                        </div>
                    </aside>
                @endif

                {{-- Contenido principal --}}
                <div class="flex-1 min-w-0">
                    @php
                        // El linaje publicado puede omitir rangos entre un taxón y sus especies.
                        $taxonesPagina = array_merge($hijos, array_map(static fn (array $especie): array => [
                            'nivel' => 'species', 'taxon' => $especie['especie'], 'padre' => $especie['padre'],
                            'total' => $especie['total'] ?? $conteos['species:'.$especie['especie']] ?? 0,
                        ], $especiesActuales));
                    @endphp
                    <div class="mb-4 flex items-center justify-between">
                        <h1 class="font-display text-xl font-semibold text-blue-navy font-serif italic">
                            {{ $taxonActual }}
                        </h1>
                        <span class="text-xs text-text-secondary">
                            {{ number_format($totalTarjetas, 0, ',', '.') }} {{ $totalTarjetas === 1 ? 'taxón' : 'taxones' }}
                        </span>
                    </div>

                    @if($taxonesPagina === [])
                        <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-surface py-12 text-sm text-text-secondary">
                            Sin taxones visibles en este nivel.
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($taxonesPagina as $hijo)
                                @php
                                    $clave = $hijo['nivel'].':'.$hijo['taxon'];
                                    $stats = $descendientes[$clave] ?? [];
                                    $numEspecimenes = $hijo['total'] ?? $conteos[$clave] ?? 0;
                                @endphp
                                <article
                                    class="collection-taxon-card group text-left rounded-lg border border-border bg-surface shadow-sm hover:border-science-blue/40 hover:shadow-md transition-all"
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
                                                <button type="button" wire:click="navegar('{{ $hijo['nivel'] }}', '{{ $hijo['taxon'] }}')" class="collection-taxon-main font-serif italic text-base text-text-primary group-hover:text-science-blue transition-colors">
                                                    {{ $hijo['taxon'] }}
                                                </button>
                                                <div class="text-xs text-text-secondary mt-0.5">
                                                    {{ $etiquetas[$hijo['nivel']] ?? $hijo['nivel'] }}
                                                </div>
                                            </div>
                                            <x-catalogopublico::ayuda-taxon :nombre="$hijo['taxon']" :nivel="$hijo['nivel']" :registros="$numEspecimenes" :stats="$stats" :jerarquia="array_column($ruta, 'taxon', 'nivel')" :foto-publica="$portadas[$hijo['nivel'].':'.$hijo['taxon']] ?? null" />
                                        </div>

                                        @if(!empty($stats) || $numEspecimenes > 0)
                                            <div class="mt-2.5 pt-2.5 border-t border-border flex flex-wrap gap-x-2.5 gap-y-1 text-xs text-text-secondary">
                                                @foreach($etiquetasDescendientes as $nivelStat => $etiquetaStat)
                                                    @if(isset($stats[$nivelStat]) && $stats[$nivelStat] > 0)
                                                        <span>
                                                            <strong class="text-text-primary tabular-nums">{{ number_format($stats[$nivelStat], 0, ',', '.') }}</strong>
                                                            {{ $etiquetaConteo($nivelStat, (int) $stats[$nivelStat], $etiquetaStat) }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                                @if($numEspecimenes > 0)
                                                    <span class="text-bio-green font-medium">
                                                        <strong class="tabular-nums">{{ number_format($numEspecimenes, 0, ',', '.') }}</strong>
                                                        {{ $numEspecimenes === 1 ? 'registro' : 'registros' }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </article>
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
                                        type="button" wire:click="navegar(@js($hermano['nivel']), @js($hermano['taxon']))" wire:loading.attr="disabled" wire:target="navegar,cambiarPaginaHermanos"
                                        class="w-full text-left flex items-center justify-between gap-2 rounded px-2 py-1.5 text-sm text-text-secondary hover:text-science-blue hover:bg-science-blue/5 transition-colors"
                                    >
                                        <span class="font-serif italic truncate">{{ $hermano['taxon'] }}</span>
                                        <span class="tabular-nums text-xs shrink-0">
                                            {{ number_format((int) ($hermano['total'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            <x-catalogopublico::paginacion-hermanos :total="$totalHermanos" :pagina="$paginaHermanosActual" :ultima="$ultimaPaginaHermanos" />
                        </div>
                    </aside>
                @endif

                {{-- Lista de especies --}}
                <div class="flex-1 min-w-0">
                    <div class="mb-4 flex items-center justify-between">
                        <h1 class="font-display text-xl font-semibold text-blue-navy">
                            <span class="font-serif italic">{{ $taxonActual }}</span>
                            <span class="text-base font-normal text-text-secondary">· Género</span>
                        </h1>
                        <span class="text-xs text-text-secondary">
                            {{ number_format($totalTarjetas, 0, ',', '.') }} {{ $totalTarjetas === 1 ? 'especie' : 'especies' }}
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
                                    $numEspecimenes = $especie['total'] ?? $conteos['species:'.$especie['especie']] ?? 0;
                                    $portadaEspecie = $portadas['species:'.$especie['especie']] ?? null;
                                @endphp
                                <article
                                    class="collection-taxon-card group w-full text-left rounded-lg border border-border bg-surface shadow-sm px-4 py-3.5 hover:border-science-blue/40 hover:shadow transition-all flex flex-wrap items-center gap-4"
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
                                        <button type="button" wire:click="navegar('species', '{{ $especie['especie'] }}')" class="collection-taxon-main font-serif italic text-base text-text-primary group-hover:text-science-blue transition-colors">
                                            {{ $especie['especie'] }}
                                        </button>
                                        <div class="text-xs text-text-secondary mt-0.5">
                                            <span class="italic">{{ $especie['genus'] }}</span>
                                            {{ $especie['specificEpithet'] }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        @if($numEspecimenes > 0)
                                            <span class="text-xs text-bio-green font-medium tabular-nums">
                                                {{ number_format($numEspecimenes, 0, ',', '.') }} {{ $numEspecimenes === 1 ? 'registro' : 'registros' }}
                                            </span>
                                        @endif
                                        <x-catalogopublico::ayuda-taxon :nombre="$especie['especie']" nivel="species" :registros="$numEspecimenes" :jerarquia="array_column($ruta, 'taxon', 'nivel')" :foto-publica="$portadaEspecie" />
                                    </div>
                                </article>
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
        @endassets
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="collection-species-layout">

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
                                        type="button" wire:click="navegar(@js($hermano['nivel']), @js($hermano['taxon']))" wire:loading.attr="disabled" wire:target="navegar,cambiarPaginaHermanos"
                                        class="w-full text-left flex items-center justify-between gap-2 rounded px-2 py-1.5 text-xs text-text-secondary hover:text-science-blue hover:bg-science-blue/5 transition-colors"
                                    >
                                        <span class="font-serif italic truncate">{{ $hermano['taxon'] }}</span>
                                        <span class="tabular-nums shrink-0">
                                            {{ number_format((int) ($hermano['total'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            <x-catalogopublico::paginacion-hermanos :total="$totalHermanos" :pagina="$paginaHermanosActual" :ultima="$ultimaPaginaHermanos" />
                        </div>
                    </aside>
                @endif

                {{-- Registros de registros --}}
                <div class="collection-species-content">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h1 class="font-display text-xl font-semibold text-blue-navy">
                            <span class="font-serif italic">{{ $taxonActual }}</span>
                            <span class="text-base font-normal text-text-secondary">· Especie</span>
                        </h1>
                        <div class="collection-species-download flex flex-wrap items-center gap-3">
                            <span class="text-xs text-text-secondary tabular-nums">
                                {{ number_format($totalEspecimenes, 0, ',', '.') }} {{ $totalEspecimenes === 1 ? 'registro' : 'registros' }}
                            </span>
                            @if(count($especimenes) > 0)
                                <button
                                    wire:click="descargarDatos"
                                    wire:loading.attr="disabled"
                                    wire:target="descargarDatos"
                                    class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-2 text-xs font-medium text-text-secondary shadow-sm transition-colors hover:border-science-blue/50 hover:text-science-blue disabled:opacity-50 w-full sm:w-auto"
                                >
                                    <span wire:loading.remove wire:target="descargarDatos" class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        Descargar datos XLSX
                                    </span>
                                    <span wire:loading wire:target="descargarDatos" class="flex items-center gap-1.5">
                                        <span class="inline-block size-3 rounded-full border-2 border-current border-t-transparent animate-spin"></span>
                                        Generando…
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                    @if(count($especimenes) > 0)<p class="mb-4 text-xs text-text-secondary">El XLSX incluye todos los registros filtrados de esta especie, también otras páginas, con números, fechas y campos públicos de referencia geográfica. Conserva por separado condición de tipo, disposición y advertencias de coordenadas; no incluye fotografías. Perfil de intercambio: <code>{{ \Modules\CatalogoPublico\Domain\ValueObjects\PerfilExportacionPublica::IDENTIFICADOR }}</code>.</p>@endif

                    {{-- ══════════════════════════════════
                         IMAGEN DESTACADA DE LA ESPECIE (portada por defecto)
                         ══════════════════════════════════ --}}
                    @php
                        $portadaEspecie = collect($galeriaEspecie)->firstWhere('esPortada', true);
                        $jerarquiaEspecie = array_column($ruta, 'taxon', 'nivel');
                        $ilustracionEspecie = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon($jerarquiaEspecie);
                    @endphp
                    <x-catalogopublico::referencia-taxonomica :nombre="$taxonActual" :familia-visible="isset($jerarquiaEspecie['family'])" :familia-original="$jerarquiaEspecie['family'] ?? null" />
                    <section class="mb-6">
                        <div class="mb-3 flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-text-primary flex items-center gap-2">
                                <flux:icon name="photo" class="size-4 text-text-secondary" />
                                {{ $portadaEspecie ? 'Fotografía publicada de la especie' : 'Fotografía de referencia identificada' }}
                            </h3>
                        </div>

                        @if(! $portadaEspecie)
                            <x-catalogopublico::representacion-especie :nombre="$taxonActual" :jerarquia="$jerarquiaEspecie" :ilustracion="$ilustracionEspecie" />
                        @else
                            <button
                                type="button"
                                @click="$dispatch('lightbox-open', { url: @js($portadaEspecie['url']), alt: @js($taxonActual), nombreArchivo: @js($portadaEspecie['nombreArchivo']), invocador: $event.currentTarget })"
                                class="group/portada block max-w-xs overflow-hidden rounded-lg border border-bio-green ring-2 ring-bio-green/30 bg-surface shadow-sm cursor-zoom-in text-left"
                                title="Ver imagen completa"
                            >
                                <div class="relative aspect-square bg-bg-main">
                                    <img src="{{ $portadaEspecie['url'] }}" alt="{{ $taxonActual }}" class="h-full w-full object-cover transition-transform group-hover/portada:scale-105" loading="lazy" />
                                    <span class="absolute inset-0 flex items-end justify-end bg-blue-navy/0 p-2 transition-colors group-hover/portada:bg-blue-navy/20">
                                        <span class="opacity-0 transition-opacity group-hover/portada:opacity-100 rounded-full bg-blue-navy/80 p-1.5 text-white">
                                            <flux:icon name="magnifying-glass-plus" class="size-4" />
                                        </span>
                                    </span>
                                </div>
                            </button>
                        @endif
                    </section>

                    {{-- ══════════════════════════════════
                         MAPA DE DISTRIBUCIÓN
                         ══════════════════════════════════ --}}
                    @php
                        $puntosGeo = collect($puntosEspecie);
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
                                wire:ignore
                                wire:key="mapa-especie-{{ $claveMapaEspecie }}"
                                data-taxon-id="{{ $idTaxonActual }}"
                                x-data="portalMapaEspecie(@js($puntosEspecie), @js($taxonActual))"
                                class="collection-species-map rounded-lg border border-border bg-surface shadow-sm overflow-hidden"
                            >
                                <p x-show="errorMapa" x-cloak class="p-4 text-sm text-text-secondary" role="status">No se pudo cargar la cartografía. Los datos públicos de esta selección siguen disponibles.</p>
                                <div x-ref="mapaContainer" class="h-72 sm:h-96 w-full"></div>
                            </div>
                        @endif
                    </section>

                    @if(count($especimenes) === 0)
                        <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-surface py-12 text-sm text-text-secondary">
                            No hay registros públicos para esta especie en la selección actual.
                        </div>
                    @else
                        <div class="collection-specimen-list space-y-3">
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
                                        ? number_format(abs((float) $lat), 5, ',', '.').'°'.($lat >= 0 ? 'N' : 'S')
                                          .' · '.number_format(abs((float) $lon), 5, ',', '.').'°'.($lon >= 0 ? 'E' : 'O')
                                        : null;
                                    $elevMin = $especimen->elevation_min_m ?? null;
                                    $elevMax = $especimen->elevation_max_m ?? null;
                                    $elevStr = match (true) {
                                        $elevMin !== null && $elevMax !== null && (float) $elevMin !== (float) $elevMax
                                            => number_format((float) $elevMin, 0, ',', '.').'–'.number_format((float) $elevMax, 0, ',', '.').' m',
                                        $elevMin !== null => number_format((float) $elevMin, 0, ',', '.').' m',
                                        $elevMax !== null => number_format((float) $elevMax, 0, ',', '.').' m',
                                        default           => null,
                                    };
                                    $imagenesEspecimen = $imagenesPorEspecimen[$especimen->occurrence_id] ?? [];
                                    $numImagenes = count($imagenesEspecimen);
                                @endphp
                                <article
                                    x-data="{ abierto: false }" wire:key="ejemplar-{{ $especimen->especimen_id }}"
                                    class="collection-specimen-card rounded-lg border border-border bg-surface shadow-sm"
                                    tabindex="0" aria-label="Abrir ficha de {{ $especimen->occurrence_id ?: 'registro público' }}"
                                    x-on:click="if (!$event.target.closest('button,a,input,select')) $wire.abrirFichaRegistro(@js($especimen->especimen_id))"
                                    x-on:keydown.enter.self.prevent="$wire.abrirFichaRegistro(@js($especimen->especimen_id))"
                                    x-on:keydown.space.self.prevent="$wire.abrirFichaRegistro(@js($especimen->especimen_id))"
                                >
                                    <div class="flex">

                                        {{-- Miniatura: si hay imágenes, abre la galería del registro; si no, placeholder --}}
                                        @if($numImagenes > 0)
                                            <button
                                                type="button"
                                                x-on:click="$wire.abrirFichaRegistro(@js($especimen->especimen_id))"
                                                class="collection-specimen-thumbnail group/thumb relative w-28 shrink-0 bg-bg-main border-r border-border overflow-hidden"
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
                                            <div class="collection-specimen-thumbnail w-28 shrink-0 flex-col items-center justify-center gap-1.5 bg-bg-main border-r border-border py-4">
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
                                                    <flux:badge color="{{ $typeBadgeColor }}" size="sm" title="Condición de tipo">
                                                        {{ \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::tipo($especimen->type_status) }}
                                                    </flux:badge>
                                                @elseif($especimen->type_status_visible ?? false)
                                                    <span class="text-xs text-text-secondary">Condición de tipo: No informado</span>
                                                @endif
                                                @if(($especimen->disposition ?? '') !== '')
                                                    <span class="text-xs text-text-secondary">Disposición: {{ \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::disposicion($especimen->disposition) }}</span>
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
                                            @if($especimen->taxon_en_revision ?? false)<p class="atlas-data-warning">Dato original por revisar; excluido de riqueza e identificación a especie.</p>@endif

                                            {{-- Metadatos como lista de definición --}}
                                            <dl class="collection-specimen-metadata text-xs">
                                                @if($especimen->locality_visible)
                                                    <div class="min-w-0">
                                                        <dt class="mb-1 font-semibold text-text-secondary">Localidad del Excel</dt>
                                                        <dd class="break-words text-text-primary">{{ $especimen->locality_excel ?: $especimen->locality_name ?: 'Sin datos' }}</dd>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <dt class="mb-1 font-semibold text-text-secondary">Localidad INEC</dt>
                                                        <dd class="break-words text-text-primary">{{ $especimen->locality_inec ?: 'Sin coincidencia INEC' }}</dd>
                                                        @if($especimen->locality_inec_reference)
                                                            <dd class="mt-1 text-text-secondary">{{ ucfirst($especimen->locality_inec_reference) }}</dd>
                                                        @endif
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
                                                        <dd class="text-text-primary">
                                                            <span class="font-mono">{{ $coordStr }}</span>
                                                            @if(str_contains($especimen->coordinate_reference ?? '', 'aproximada'))
                                                                <span class="mt-1 block text-text-secondary">Referencia aproximada; ubicación de colecta pendiente de confirmar.</span>
                                                            @elseif(str_contains($especimen->coordinate_reference ?? '', 'recuperada'))
                                                                <span class="mt-1 block text-text-secondary">Coordenada recuperada del Excel; precisión de colecta pendiente de confirmar.</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                @endif
                                                @if($especimen->event_date)
                                                    <div class="flex gap-2">
                                                        <dt class="w-24 shrink-0 text-text-secondary">Recolección</dt>
                                                        <dd class="text-text-primary tabular-nums">{{ \Carbon\CarbonImmutable::parse($especimen->event_date)->format('d/m/Y') }}</dd>
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
                                                        <dd class="text-text-primary">{{ \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::etapa($especimen->life_stage) }}</dd>
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
                                                        @foreach($imagenesEspecimen as $indice => $img)
                                                            <button
                                                                type="button"
                                                                @click="$dispatch('lightbox-open', { url: @js($img['url']), alt: @js($especimen->occurrence_id), nombreArchivo: @js($img['nombre']), invocador: $event.currentTarget })"
                                                                class="group/thumb-galeria overflow-hidden rounded-lg border border-border bg-bg-main cursor-zoom-in"
                                                                title="Ver imagen completa"
                                                            >
                                                                <div class="relative aspect-square">
                                                                    <img src="{{ $img['url'] }}" alt="{{ $especimen->occurrence_id }}" class="h-full w-full object-cover transition-transform group-hover/thumb-galeria:scale-105" loading="lazy" />
                                                                    <span class="absolute inset-0 flex items-end justify-end bg-blue-navy/0 p-1.5 transition-colors group-hover/thumb-galeria:bg-blue-navy/20">
                                                                        <span class="opacity-0 transition-opacity group-hover/thumb-galeria:opacity-100 rounded-full bg-blue-navy/80 p-1 text-white">
                                                                            <flux:icon name="magnifying-glass-plus" class="size-3.5" />
                                                                        </span>
                                                                    </span>
                                                                </div>
                                                            </button>
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

        {{-- ══════════════════════════════════
             LIGHTBOX — visor de imagen completa
             ══════════════════════════════════ --}}
        @include('catalogopublico::components.visor-imagen')
    @endif

    @else
    {{-- =====================================================================
         MODO EXPLORAR — todos los taxones de un nivel dado
         ===================================================================== --}}
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Encabezado --}}
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-xl font-semibold text-blue-navy capitalize">
                    {{ ucfirst($nivelesPluralNavegacion[$nivelExplorar] ?? 'taxones') }} {{ in_array($nivelExplorar, ['class', 'family', 'species'], true) ? 'divulgadas' : 'divulgados' }}
                </h1>
                <p class="mt-0.5 text-sm text-text-secondary">
                    @if(count($taxonesExplorados) === 0)
                        Ningún taxón divulgado en este nivel.
                    @else
                        <strong class="text-text-primary tabular-nums">{{ number_format($totalTarjetas, 0, ',', '.') }}</strong>
                        {{ $etiquetaConteo($nivelExplorar, (int) $totalTarjetas, $nivelesPluralNavegacion[$nivelExplorar] ?? $nivelExplorar) }} en la colección
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
                        $numEspecimenes = $nodo['total'] ?? $conteos[$clave] ?? 0;
                    @endphp
                    <article
                        class="collection-taxon-card group text-left rounded-lg border border-border bg-surface shadow-sm hover:border-science-blue/40 hover:shadow-md transition-all"
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
                                    <button type="button" wire:click="navegar('{{ $nodo['nivel'] }}', '{{ $nodo['taxon'] }}')" class="collection-taxon-main font-serif italic text-base text-text-primary group-hover:text-science-blue transition-colors">
                                        {{ $nodo['taxon'] }}
                                    </button>
                                    <div class="text-xs text-text-secondary mt-0.5">
                                        {{ $etiquetas[$nodo['nivel']] ?? $nodo['nivel'] }}
                                        @if($nodo['nivel'] !== 'phylum')
                                            <span class="text-text-secondary/60">· {{ $nodo['padre'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <x-catalogopublico::ayuda-taxon :nombre="$nodo['taxon']" :nivel="$nodo['nivel']" :registros="$numEspecimenes" :stats="$stats" :jerarquia="$nodo['jerarquia'] ?? array_column($ruta, 'taxon', 'nivel')" :foto-publica="$portadas[$nodo['nivel'].':'.$nodo['taxon']] ?? null" />
                            </div>

                            @if(!empty($stats) || $numEspecimenes > 0)
                                <div class="mt-2.5 pt-2.5 border-t border-border flex flex-wrap gap-x-2.5 gap-y-1 text-xs text-text-secondary">
                                    @foreach($etiquetasDescendientes as $nivelStat => $etiquetaStat)
                                        @if(isset($stats[$nivelStat]) && $stats[$nivelStat] > 0)
                                            <span>
                                                <strong class="text-text-primary tabular-nums">{{ number_format($stats[$nivelStat], 0, ',', '.') }}</strong>
                                                {{ $etiquetaConteo($nivelStat, (int) $stats[$nivelStat], $etiquetaStat) }}
                                            </span>
                                        @endif
                                    @endforeach
                                    @if($numEspecimenes > 0)
                                        <span class="text-bio-green font-medium">
                                            <strong class="tabular-nums">{{ number_format($numEspecimenes, 0, ',', '.') }}</strong>
                                            {{ $numEspecimenes === 1 ? 'registro' : 'registros' }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    @endif {{-- fin modo árbol / explorar --}}
    @php $totalPaginado = $nivelActual === 'species' && $nivelExplorar === '' ? $totalEspecimenes : $totalTarjetas; @endphp
    @if($totalPaginado > 6)
        <nav class="collection-card-pagination" aria-label="Páginas de tarjetas">
            <button type="button" wire:click="cambiarPagina({{ $paginaActual - 1 }})" wire:loading.attr="disabled" @disabled($paginaActual <= 1)>Anterior</button>
            <span>Página {{ $paginaActual }} de {{ $ultimaPagina }} · 6 por página · {{ number_format($totalPaginado, 0, ',', '.') }} en total</span>
            <button type="button" wire:click="cambiarPagina({{ $paginaActual + 1 }})" wire:loading.attr="disabled" @disabled($paginaActual >= $ultimaPagina)>Siguiente</button>
        </nav>
    @endif
    @endif {{-- fin vista mapa / registros / tarjetas --}}
        </div>
    </div>

    {{-- Indicador de carga --}}
    <div
        wire:loading.delay
        class="fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 shadow-sm text-xs text-text-secondary"
    >
        <span class="inline-block size-3 rounded-full border-2 border-science-blue border-t-transparent animate-spin"></span>
        Cargando…
    </div>

    <dialog class="collection-taxon-dialog" x-ref="ayudaTaxon" wire:ignore.self aria-labelledby="titulo-ayuda-taxon"
        x-on:keydown.escape.stop.prevent="cerrarTaxon()" x-on:cancel.stop.prevent="cerrarTaxon()"
        x-on:close="restaurarTaxon()" x-on:click="if ($event.target === $el) cerrarTaxon()">
        <template x-if="taxonAyuda">
            <div class="collection-taxon-explanation">
                <header><h2 id="titulo-ayuda-taxon">Resumen de <em x-text="taxonAyuda.nombre"></em></h2><button type="button" autofocus x-on:click="cerrarTaxon()" aria-label="Cerrar explicación">×</button></header>
                <div class="collection-taxon-help-photograph" x-html="taxonAyuda.fotografia"></div>
                <p x-show="descripciones[taxonAyuda.nombre]" x-text="descripciones[taxonAyuda.nombre]"></p>
                <p x-show="!descripciones[taxonAyuda.nombre]">No hay una descripción de historia natural publicada para este taxón. Este resumen muestra su clasificación y los registros disponibles en la selección.</p>
                <p>Rango publicado: <strong x-text="taxonAyuda.nivel"></strong>. Linaje de la selección: <span x-text="Object.values(taxonAyuda.jerarquia || {}).join(' → ')"></span>.</p>
                <p><strong x-text="taxonAyuda.registros.toLocaleString('es-EC')"></strong> <span x-text="Number(taxonAyuda.registros) === 1 ? 'registro público' : 'registros públicos'"></span> de este taxón en la selección actual.</p>
                <ul class="collection-taxon-descendants" x-show="Object.keys(taxonAyuda.stats).length > 0">
                    <template x-for="([nivel, cantidad]) in Object.entries(taxonAyuda.stats)" :key="nivel"><li><strong x-text="Number(cantidad).toLocaleString('es-EC')"></strong> <span x-text="Number(cantidad) === 1 ? ({kingdom: 'reino', phylum: 'filo', class: 'clase', order: 'orden', family: 'familia', genus: 'género', species: 'especie'}[nivel] || nivel) : (etiquetasStats[nivel] || nivel)"></span></li></template>
                </ul>
            </div>
        </template>
    </dialog>
</div>
