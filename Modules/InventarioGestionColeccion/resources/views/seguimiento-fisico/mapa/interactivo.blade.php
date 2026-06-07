@php
    // Paleta taxonómica determinista. Las clases se escriben literales para que el
    // JIT de Tailwind las incluya; el color se elige por hash de la clave taxonómica.
    $paletaTaxon = [
        'bg-blue-navy/80 text-white',
        'bg-bio-green/80 text-white',
        'bg-science-blue/80 text-white',
        'bg-info/80 text-white',
        'bg-success/80 text-white',
        'bg-warning/80 text-text-primary',
        'bg-blue-navy/45 text-text-primary',
        'bg-bio-green/45 text-text-primary',
    ];

    $claveTaxon = function (?array $clasificacion): ?string {
        if ($clasificacion === null) {
            return null;
        }
        $clave = trim(($clasificacion['subfamilia'] ?? '').'|'.($clasificacion['genero'] ?? ''), '|');

        return $clave !== '' ? $clave : ($clasificacion['familia'] ?? null);
    };

    $colorTaxon = function (?array $clasificacion) use ($paletaTaxon, $claveTaxon): string {
        $clave = $claveTaxon($clasificacion);
        if ($clave === null) {
            return 'bg-bg-main text-text-secondary border border-dashed border-border';
        }

        return $paletaTaxon[crc32($clave) % count($paletaTaxon)];
    };

    $etiquetaTaxon = function (?array $clasificacion): string {
        if ($clasificacion === null) {
            return 'Sin clasificar';
        }

        return $clasificacion['genero']
            ?? $clasificacion['subfamilia']
            ?? $clasificacion['familia']
            ?? 'Sin clasificar';
    };
@endphp

<div class="space-y-4 p-4 sm:p-6">
    {{-- Encabezado + búsqueda --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Mapa de la colección</flux:heading>
            <p class="text-xs text-text-secondary">Recorre la colección: gabinete, ranura, caja, unit tray y espécimen.</p>
        </div>

        <div class="relative w-full sm:w-80">
            <flux:input
                wire:model.live.debounce.350ms="busquedaEspecimen"
                icon="magnifying-glass"
                placeholder="Buscar espécimen por nombre o código"
                class="w-full"
            />

            <div wire:loading.flex wire:target="busquedaEspecimen" class="absolute right-3 top-1/2 -translate-y-1/2 items-center">
                <flux:icon name="arrow-path" class="size-4 animate-spin text-text-secondary" />
            </div>

            @if(count($sugerencias) > 0)
                <ul class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-border bg-surface shadow-sm">
                    @foreach($sugerencias as $sugerencia)
                        <li>
                            <button
                                type="button"
                                wire:click="localizar('{{ $sugerencia['id'] }}')"
                                class="flex w-full min-h-[44px] flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-bg-main"
                            >
                                <span class="font-serif italic text-text-primary">{{ $sugerencia['taxonNombre'] }}</span>
                                <span class="text-xs text-text-secondary">{{ $sugerencia['codigoCatalogo'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if($errorMessage)
        <flux:callout variant="danger" icon="exclamation-triangle">{{ $errorMessage }}</flux:callout>
    @endif

    @if($mensajeBusqueda)
        <flux:callout variant="warning" icon="information-circle">{{ $mensajeBusqueda }}</flux:callout>
    @endif

    {{-- Breadcrumb de navegación --}}
    <nav class="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm" aria-label="Ruta del mapa">
        <button type="button" wire:click="volverAGeneral" class="text-science-blue hover:underline @if(! $gabineteSeleccionado) font-semibold text-text-primary @endif">
            Colección
        </button>
        @if($gabineteSeleccionado)
            <flux:icon name="chevron-right" class="size-4 text-text-secondary" />
            <button type="button" wire:click="cerrarCaja" class="text-science-blue hover:underline @if(! $cajaSeleccionada) font-semibold text-text-primary @endif">
                {{ $gabineteSeleccionado['codigo'] }}
            </button>
        @endif
        @if($cajaSeleccionada)
            <flux:icon name="chevron-right" class="size-4 text-text-secondary" />
            <button type="button" wire:click="cerrarUnitTray" class="text-science-blue hover:underline @if(! $unitTraySeleccionado) font-semibold text-text-primary @endif">
                Caja {{ $cajaSeleccionada['codigo'] }}
            </button>
        @endif
        @if($unitTraySeleccionado)
            <flux:icon name="chevron-right" class="size-4 text-text-secondary" />
            <span class="font-semibold text-text-primary">Unit tray {{ $unitTraySeleccionado['numero'] }}</span>
        @endif
    </nav>

    {{-- Indicador de carga al cambiar de nivel --}}
    <div wire:loading.flex wire:target="abrirGabinete,abrirCaja,abrirUnitTray,localizar" class="items-center gap-2 text-sm text-text-secondary">
        <flux:icon name="arrow-path" class="size-4 animate-spin" />
        Cargando…
    </div>

    {{-- ============ NIVEL 4: especímenes de un unit tray ============ --}}
    @if($unitTraySeleccionado)
        <div wire:key="nivel-unittray" wire:transition class="rounded-lg border border-border bg-surface shadow-sm">
            <div class="border-b border-border p-4">
                <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                    Unit tray {{ $unitTraySeleccionado['numero'] }}
                </flux:heading>
                <p class="text-xs text-text-secondary">{{ count($especimenes) }} especímenes</p>
            </div>

            @if(count($especimenes) > 0)
                {{-- Escritorio --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-blue-navy text-left text-white">
                                <th class="px-4 py-2 font-semibold">Código de catálogo</th>
                                <th class="px-4 py-2 font-semibold">Nombre científico</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($especimenes as $especimen)
                                <tr @class([
                                    'border-t border-border',
                                    'bg-info/10' => $especimen['especimenId'] === $especimenResaltadoId,
                                    'hover:bg-bg-main' => $especimen['especimenId'] !== $especimenResaltadoId,
                                ])>
                                    <td class="px-4 py-2 text-text-primary">{{ $especimen['codigoCatalogo'] }}</td>
                                    <td class="px-4 py-2">
                                        <span class="font-serif italic text-text-primary">{{ $especimen['nombreCientifico'] ?? '—' }}</span>
                                        @if($especimen['especimenId'] === $especimenResaltadoId)
                                            <flux:badge size="sm" color="sky" class="ml-2">Buscado</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Móvil --}}
                <div class="divide-y divide-border md:hidden">
                    @foreach($especimenes as $especimen)
                        <div @class([
                            'flex flex-col gap-2 p-4',
                            'bg-info/10' => $especimen['especimenId'] === $especimenResaltadoId,
                        ])>
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Código">
                                {{ $especimen['codigoCatalogo'] }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Nombre científico">
                                <span class="font-serif italic">{{ $especimen['nombreCientifico'] ?? '—' }}</span>
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="p-6 text-center text-sm text-text-secondary">Este unit tray no tiene especímenes asignados.</p>
            @endif
        </div>

    {{-- ============ NIVEL 3: unit trays de una caja ============ --}}
    @elseif($cajaSeleccionada)
        <div wire:key="nivel-caja" wire:transition class="space-y-3">
            <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                Caja {{ $cajaSeleccionada['codigo'] }}
            </flux:heading>

            @if(count($unitTrays) > 0)
                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr))">
                    @foreach($unitTrays as $tray)
                        <button
                            type="button"
                            wire:click="abrirUnitTray('{{ $tray['unitTrayId'] }}', {{ $tray['numero'] }})"
                            class="flex min-h-[44px] flex-col gap-1 rounded-lg border border-border p-3 text-left shadow-sm transition-colors hover:ring-2 hover:ring-science-blue {{ $colorTaxon($tray['clasificacionDominante']) }}"
                        >
                            <span class="text-sm font-bold">Tray {{ $tray['numero'] }}</span>
                            <span class="font-serif text-xs italic">{{ $etiquetaTaxon($tray['clasificacionDominante']) }}</span>
                        </button>
                    @endforeach
                </div>
            @else
                <p class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-text-secondary">
                    Esta caja no tiene unit trays asignados.
                </p>
            @endif
        </div>

    {{-- ============ NIVEL 2: ranuras de un gabinete ============ --}}
    @elseif($gabineteSeleccionado)
        <div wire:key="nivel-gabinete" wire:transition class="space-y-4">
            <div>
                <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                    {{ $gabineteSeleccionado['codigo'] }} — {{ $gabineteSeleccionado['nombre'] }}
                </flux:heading>
                @if(count($composicion['subfamilias']) > 0 || count($composicion['generos']) > 0)
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($composicion['subfamilias'] as $subfamilia)
                            <flux:badge size="sm" color="zinc">{{ $subfamilia }}</flux:badge>
                        @endforeach
                        @foreach($composicion['generos'] as $genero)
                            <flux:badge size="sm" color="sky" class="font-serif italic">{{ $genero }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr))">
                @foreach($ranuras as $ranura)
                    @if($ranura['ocupada'])
                        <button
                            type="button"
                            wire:click="abrirCaja('{{ $ranura['cajaId'] }}', '{{ $ranura['codigoCaja'] }}')"
                            class="flex min-h-[64px] flex-col gap-0.5 rounded-lg p-2 text-left shadow-sm transition-transform hover:ring-2 hover:ring-science-blue {{ $colorTaxon($ranura['clasificacion']) }}"
                        >
                            <span class="text-[10px] opacity-80">Ranura {{ $ranura['numeroRanura'] }}</span>
                            <span class="truncate text-sm font-bold">{{ $ranura['codigoCaja'] }}</span>
                            <span class="truncate font-serif text-xs italic">{{ $etiquetaTaxon($ranura['clasificacion']) }}</span>
                        </button>
                    @else
                        <div class="flex min-h-[64px] flex-col items-center justify-center rounded-lg border border-dashed border-border bg-bg-main text-text-secondary">
                            <span class="text-[10px]">Ranura {{ $ranura['numeroRanura'] }}</span>
                            <span class="text-xs">Vacía</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

    {{-- ============ NIVEL 1: vista general de gabinetes ============ --}}
    @else
        <div wire:key="nivel-general" wire:transition>
            @forelse($gabinetes as $gabinete)
                <div class="mb-4 rounded-lg border border-border bg-surface p-4 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                                {{ $gabinete['codigo'] }} — {{ $gabinete['nombre'] }}
                            </flux:heading>
                            <p class="text-xs text-text-secondary">{{ count($gabinete['ranuras']) }} ranuras de {{ $gabinete['totalRanuras'] }}</p>
                        </div>
                        <flux:button
                            variant="primary"
                            icon="magnifying-glass-plus"
                            style="color: white;"
                            class="w-full sm:w-auto"
                            wire:click="abrirGabinete('{{ $gabinete['id'] }}')"
                        >
                            Abrir
                        </flux:button>
                    </div>

                    @if(count($gabinete['ranuras']) > 0)
                        <div class="mt-3 grid gap-1" style="grid-template-columns: repeat(auto-fill, minmax(3rem, 1fr))">
                            @foreach($gabinete['ranuras'] as $ranura)
                                <flux:tooltip :content="$ranura['ocupada'] ? $ranura['codigoCaja'].' · '.$etiquetaTaxon($ranura['clasificacion']) : 'Ranura '.$ranura['numeroRanura'].' — vacía'">
                                    <div @class([
                                        'flex aspect-square items-center justify-center rounded text-[10px] font-bold',
                                        $colorTaxon($ranura['clasificacion']) => $ranura['ocupada'],
                                        'border border-dashed border-border bg-bg-main text-text-secondary' => ! $ranura['ocupada'],
                                    ])>
                                        {{ $ranura['numeroRanura'] }}
                                    </div>
                                </flux:tooltip>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-3 text-sm text-text-secondary">Sin ranuras configuradas.</p>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-border p-12 text-center">
                    <flux:icon name="archive-box" class="mx-auto mb-3 size-12 text-text-secondary" />
                    <p class="text-text-primary">No hay gabinetes registrados.</p>
                </div>
            @endforelse
        </div>
    @endif

    {{-- Leyenda --}}
    <div class="flex flex-wrap items-center gap-3 border-t border-border pt-3 text-xs text-text-secondary">
        <span class="font-semibold">Leyenda:</span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block size-3 rounded bg-blue-navy/80"></span>
            Color por taxonomía (subfamilia · género)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block size-3 rounded border border-dashed border-border bg-bg-main"></span>
            Ranura vacía / sin clasificar
        </span>
    </div>
</div>
