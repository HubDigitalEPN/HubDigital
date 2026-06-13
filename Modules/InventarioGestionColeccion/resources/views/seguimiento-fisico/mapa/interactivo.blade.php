@php
    // Paleta taxonómica determinista. Clases LITERALES para que el JIT de Tailwind las incluya.
    // Se evita `error` (rojo) porque connota alerta, no un taxón.
    $paletaTaxon = [
        'bg-blue-navy/80 text-white',
        'bg-bio-green/80 text-white',
        'bg-science-blue/80 text-white',
        'bg-warning/80 text-text-primary',
        'bg-info/80 text-white',
        'bg-success/80 text-white',
        'bg-blue-navy/55 text-text-primary',
        'bg-bio-green/55 text-text-primary',
        'bg-science-blue/55 text-text-primary',
        'bg-info/55 text-text-primary',
        'bg-success/55 text-text-primary',
        'bg-warning/55 text-text-primary',
    ];
    $claseNeutra = 'bg-bg-main text-text-secondary border border-dashed border-border';

    $norm = fn (?string $v): string => mb_strtolower(trim((string) $v));

    // Índice global de familias → posición: fija un color estable por familia en todo el mapa.
    $indiceFamilia = array_flip(array_map($norm, $ordenFamilias));

    // Color por familia (estable y global). null/desconocida → clase neutra.
    $familiaClase = function (?string $familia) use ($paletaTaxon, $claseNeutra, $indiceFamilia, $norm): string {
        if ($familia === null || trim($familia) === '') {
            return $claseNeutra;
        }
        $i = $indiceFamilia[$norm($familia)] ?? null;

        return $i === null ? $claseNeutra : $paletaTaxon[$i % count($paletaTaxon)];
    };

    // Clave de coloreado por subfamilia·género dentro de una caja (nivel unit trays).
    $claveTray = function (?array $c) use ($norm): ?string {
        if ($c === null) {
            return null;
        }
        $clave = trim($norm($c['subfamilia'] ?? '').'|'.$norm($c['genero'] ?? ''), '|');

        return $clave !== '' ? $clave : ($norm($c['familia'] ?? '') ?: null);
    };

    // Etiqueta legible con fallback por TODOS los rangos (de fino a general).
    $etiquetaTaxon = function (?array $c): string {
        if ($c === null) {
            return 'Sin clasificar';
        }

        return $c['especie']
            ?? $c['genero']
            ?? $c['subfamilia']
            ?? $c['familia']
            ?? $c['superfamilia']
            ?? $c['suborden']
            ?? $c['orden']
            ?? 'Sin clasificar';
    };

    // Etiqueta corta subfamilia·género para la leyenda de color del nivel caja.
    $etiquetaTray = function (?array $c): string {
        if ($c === null) {
            return 'Sin clasificar';
        }
        $partes = array_filter([$c['subfamilia'] ?? null, $c['genero'] ?? null]);

        return $partes !== [] ? implode(' · ', $partes) : ($c['familia'] ?? 'Sin clasificar');
    };

    // Recolecta valores distintos (escalar o lista) de un campo a lo largo de varias
    // clasificaciones, ordenados natural-case. Trabaja solo a nivel caja/tray (sin especímenes).
    $recolectar = function (array $clasificaciones, string $clave) use ($norm): array {
        $vistos = [];
        $resultado = [];
        foreach ($clasificaciones as $c) {
            if ($c === null) {
                continue;
            }
            foreach ((array) ($c[$clave] ?? []) as $valor) {
                $valor = trim((string) $valor);
                if ($valor === '' || isset($vistos[$norm($valor)])) {
                    continue;
                }
                $vistos[$norm($valor)] = true;
                $resultado[] = $valor;
            }
        }
        sort($resultado, SORT_NATURAL | SORT_FLAG_CASE);

        return $resultado;
    };

    // Rangos superiores combinados (para el fallback más profundo de las firmas).
    $superiores = fn (array $clasificaciones): array => array_values(array_unique(array_merge(
        $recolectar($clasificaciones, 'superfamilia'),
        $recolectar($clasificaciones, 'suborden'),
        $recolectar($clasificaciones, 'orden'),
    )));
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
        @php
            $clasifTrays = array_map(fn ($t) => $t['clasificacionDominante'] ?? null, $unitTrays);

            // Leyenda y color por subfamilia·género, por orden de aparición en la caja.
            $leyendaTray = [];
            foreach ($unitTrays as $t) {
                $c = $t['clasificacionDominante'] ?? null;
                $k = $claveTray($c);
                if ($k === null || isset($leyendaTray[$k])) {
                    continue;
                }
                $leyendaTray[$k] = [
                    'label' => $etiquetaTray($c),
                    'clase' => $paletaTaxon[count($leyendaTray) % count($paletaTaxon)],
                ];
            }
            $claseTray = fn (?array $c) => $leyendaTray[$claveTray($c)]['clase'] ?? $claseNeutra;
        @endphp

        <div wire:key="nivel-caja" wire:transition class="space-y-3">
            <div class="space-y-2">
                <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                    Caja {{ $cajaSeleccionada['codigo'] }}
                </flux:heading>
                {{-- Firma de especie/s de la caja (rango), con fallback por rango. --}}
                <x-inventariogestioncoleccion::seguimiento-fisico.firma-taxonomica
                    enfasis="especie"
                    :especies="$recolectar($clasifTrays, 'especie')"
                    :generos="$recolectar($clasifTrays, 'generos')"
                    :subfamilias="$recolectar($clasifTrays, 'subfamilias')"
                    :familias="$recolectar($clasifTrays, 'familia')"
                    :superiores="$superiores($clasifTrays)"
                />
            </div>

            @if(count($leyendaTray) > 1)
                <x-inventariogestioncoleccion::seguimiento-fisico.mapa-leyenda-color :items="array_values($leyendaTray)" />
            @endif

            @if(count($unitTrays) > 0)
                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr))">
                    @foreach($unitTrays as $tray)
                        <button
                            type="button"
                            wire:click="abrirUnitTray('{{ $tray['unitTrayId'] }}', {{ $tray['numero'] }})"
                            class="flex min-h-[44px] flex-col gap-1 rounded-lg border border-border p-3 text-left shadow-sm transition-colors hover:ring-2 hover:ring-science-blue {{ $claseTray($tray['clasificacionDominante'] ?? null) }}"
                        >
                            <span class="text-sm font-bold">Tray {{ $tray['numero'] }}</span>
                            <span class="font-serif text-xs italic">{{ $etiquetaTaxon($tray['clasificacionDominante'] ?? null) }}</span>
                        </button>
                    @endforeach
                </div>
            @else
                <p class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-text-secondary">
                    Esta caja no tiene unit trays asignados.
                </p>
            @endif
        </div>

    {{-- ============ NIVEL 2: ranuras de un gabinete (vitrina vertical) ============ --}}
    @elseif($gabineteSeleccionado)
        @php
            $clasifRanuras = array_map(fn ($r) => $r['clasificacion'] ?? null, $ranuras);
            $familiasGab = $recolectar($clasifRanuras, 'familia');
            usort($familiasGab, fn ($a, $b) => ($indiceFamilia[$norm($a)] ?? PHP_INT_MAX) <=> ($indiceFamilia[$norm($b)] ?? PHP_INT_MAX));
            $leyendaFamGab = array_map(fn ($f) => ['label' => $f, 'clase' => $familiaClase($f)], $familiasGab);
        @endphp

        <div wire:key="nivel-gabinete" wire:transition class="space-y-4">
            <div class="space-y-2">
                <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                    {{ $gabineteSeleccionado['codigo'] }} — {{ $gabineteSeleccionado['nombre'] }}
                </flux:heading>
                {{-- Firma de subfamilia/s y especie/s del gabinete, con fallback por rango. --}}
                <x-inventariogestioncoleccion::seguimiento-fisico.firma-taxonomica
                    enfasis="subfamiliaEspecie"
                    :subfamilias="$recolectar($clasifRanuras, 'subfamilias')"
                    :especies="$recolectar($clasifRanuras, 'especie')"
                    :generos="$recolectar($clasifRanuras, 'generos')"
                    :familias="$familiasGab"
                    :superiores="$superiores($clasifRanuras)"
                />
            </div>

            @if(count($leyendaFamGab) > 1)
                <x-inventariogestioncoleccion::seguimiento-fisico.mapa-leyenda-color :items="$leyendaFamGab" />
            @endif

            {{-- Vitrina: cajones (ranuras) apilados verticalmente. --}}
            <div class="max-w-2xl space-y-1.5 rounded-lg border-2 border-border bg-surface p-3 shadow-sm">
                @foreach($ranuras as $ranura)
                    {{-- wire:click solo surte efecto en cajones ocupados; el cajón vacío no emite atributos. --}}
                    <x-inventariogestioncoleccion::seguimiento-fisico.ranura-cajon
                        :ranura="$ranura"
                        :clase="$familiaClase(data_get($ranura, 'clasificacion.familia'))"
                        :etiqueta="$etiquetaTaxon($ranura['clasificacion'] ?? null)"
                        wire:click="abrirCaja('{{ $ranura['cajaId'] }}', '{{ $ranura['codigoCaja'] }}')"
                    />
                @endforeach
            </div>
        </div>

    {{-- ============ NIVEL 1: vista general de gabinetes (vitrinas lado a lado) ============ --}}
    @else
        @php
            $leyendaFamiliasGlobal = array_map(fn ($f) => ['label' => $f, 'clase' => $familiaClase($f)], $ordenFamilias);
        @endphp

        <div wire:key="nivel-general" wire:transition class="space-y-4">
            @if(count($leyendaFamiliasGlobal) > 1)
                <x-inventariogestioncoleccion::seguimiento-fisico.mapa-leyenda-color :items="$leyendaFamiliasGlobal" />
            @endif

            @if(count($gabinetes) > 0)
                {{-- Gabinetes como vitrinas verticales: apiladas en móvil, lado a lado en escritorio. --}}
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-nowrap sm:overflow-x-auto sm:pb-2">
                    @foreach($gabinetes as $gabinete)
                        @php
                            $clasifGab = array_map(fn ($r) => $r['clasificacion'] ?? null, $gabinete['ranuras']);
                        @endphp
                        <div class="flex shrink-0 flex-col gap-3 rounded-lg border-2 border-border bg-surface p-4 shadow-sm sm:w-72">
                            <div class="space-y-2">
                                <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                                    {{ $gabinete['codigo'] }} — {{ $gabinete['nombre'] }}
                                </flux:heading>
                                <p class="text-xs text-text-secondary">{{ count($gabinete['ranuras']) }} ranuras de {{ $gabinete['totalRanuras'] }}</p>
                                <x-inventariogestioncoleccion::seguimiento-fisico.firma-taxonomica
                                    enfasis="familia"
                                    :familias="$recolectar($clasifGab, 'familia')"
                                    :superiores="$superiores($clasifGab)"
                                />
                                <flux:button
                                    variant="primary"
                                    icon="magnifying-glass-plus"
                                    style="color: white;"
                                    class="w-full"
                                    wire:click="abrirGabinete('{{ $gabinete['id'] }}')"
                                >
                                    Abrir
                                </flux:button>
                            </div>

                            @if(count($gabinete['ranuras']) > 0)
                                <div class="flex flex-col gap-1">
                                    @foreach($gabinete['ranuras'] as $ranura)
                                        <x-inventariogestioncoleccion::seguimiento-fisico.ranura-cajon
                                            compacto
                                            :ranura="$ranura"
                                            :clase="$familiaClase(data_get($ranura, 'clasificacion.familia'))"
                                            :etiqueta="$etiquetaTaxon($ranura['clasificacion'] ?? null)"
                                        />
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-text-secondary">Sin ranuras configuradas.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-lg border border-dashed border-border p-12 text-center">
                    <flux:icon name="archive-box" class="mx-auto mb-3 size-12 text-text-secondary" />
                    <p class="text-text-primary">No hay gabinetes registrados.</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Leyenda tipográfica de las firmas (estilo de letra por rango). --}}
    <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-leyenda class="mt-2" />
</div>
