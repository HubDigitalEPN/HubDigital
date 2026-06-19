@php
    // Sin color por taxón (confundía: el mismo color cambiaba de significado entre niveles de zoom).
    // Las cajas ocupadas usan un tono neutro de énfasis y el taxón lo porta el TEXTO; la caja vacía
    // (trazo discontinuo) la resuelve el propio componente ranura-cajon.
    $claseOcupada = 'bg-surface text-text-primary border border-border';

    $norm = fn (?string $v): string => mb_strtolower(trim((string) $v));

    // Índice global de familias en su orden canónico: ordena las familias de la ruta taxonómica.
    $indiceFamilia = array_flip(array_map($norm, $ordenFamilias));

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

    // Todas las subfamilias y géneros que alberga una clasificación, en una lista plana: primero
    // subfamilias (nivel más alto), luego géneros, y cada grupo en orden alfabético natural para
    // que la leyenda y el detalle de transición se lean ordenados. Base de la leyenda de color.
    $albergados = function (?array $c): array {
        if ($c === null) {
            return [];
        }
        $subs = array_values(array_filter($c['subfamilias'] ?? []));
        $gens = array_values(array_filter($c['generos'] ?? []));
        sort($subs, SORT_NATURAL | SORT_FLAG_CASE);
        sort($gens, SORT_NATURAL | SORT_FLAG_CASE);

        return array_merge($subs, $gens);
    };

    // Etiqueta dominante por subfamilia·género: alinea el texto del cajón con lo que codifica su
    // color. Si no hay subfamilia/género, sube por la cadena (familia → rangos superiores).
    $etiquetaDominante = function (?array $c): string {
        if ($c === null) {
            return 'Sin clasificar';
        }
        $partes = array_filter([$c['subfamilia'] ?? null, $c['genero'] ?? null]);
        if ($partes !== []) {
            return implode(' · ', $partes);
        }

        return $c['familia'] ?? $c['superfamilia'] ?? $c['suborden'] ?? $c['orden'] ?? 'Sin clasificar';
    };

    // ¿La clasificación alberga varios taxones (caja/tray de transición)? Se mide a granularidad
    // subfamilia o género, igual que la clave de color.
    $variosTaxones = function (?array $c): bool {
        if ($c === null) {
            return false;
        }

        return count($c['subfamilias'] ?? []) > 1 || count($c['generos'] ?? []) > 1;
    };

    // ===== Tokens tipados (texto + estilo) para renderizar cada taxón con la tipografía de la
    // leyenda (taxon-tipografico): subfamilia serif · género cursiva "sp." · familia sans, etc. =====

    // Token dominante único, con fallback por rango (fino → general). Espejo de etiquetaTaxon.
    $tokenDominante = function (?array $c): array {
        if ($c === null) {
            return ['texto' => 'Sin clasificar', 'estilo' => 'superior'];
        }
        foreach ([['especie', 'especie'], ['genero', 'genero'], ['subfamilia', 'subfamilia'], ['familia', 'familia'], ['superfamilia', 'superior'], ['suborden', 'superior'], ['orden', 'superior']] as [$campo, $estilo]) {
            if (($c[$campo] ?? null) !== null && trim((string) $c[$campo]) !== '') {
                return ['texto' => $c[$campo], 'estilo' => $estilo];
            }
        }

        return ['texto' => 'Sin clasificar', 'estilo' => 'superior'];
    };

    // Tokens dominantes subfamilia·género (lo que codifica el color del cajón); si faltan ambos,
    // cae al token dominante por rango. Espejo de etiquetaDominante.
    $tokensDominantes = function (?array $c) use ($tokenDominante): array {
        if ($c === null) {
            return [$tokenDominante(null)];
        }
        $tokens = [];
        if (($c['subfamilia'] ?? null) !== null && trim((string) $c['subfamilia']) !== '') {
            $tokens[] = ['texto' => $c['subfamilia'], 'estilo' => 'subfamilia'];
        }
        if (($c['genero'] ?? null) !== null && trim((string) $c['genero']) !== '') {
            $tokens[] = ['texto' => $c['genero'], 'estilo' => 'genero'];
        }

        return $tokens !== [] ? $tokens : [$tokenDominante($c)];
    };

    // Listas separadas y ordenadas de subfamilias y géneros albergados (para el detalle agrupado
    // de una caja de transición). Cada grupo en orden natural-case.
    $taxonesAgrupados = function (?array $c): array {
        if ($c === null) {
            return ['subfamilias' => [], 'generos' => []];
        }
        $subs = array_values(array_filter($c['subfamilias'] ?? []));
        $gens = array_values(array_filter($c['generos'] ?? []));
        sort($subs, SORT_NATURAL | SORT_FLAG_CASE);
        sort($gens, SORT_NATURAL | SORT_FLAG_CASE);

        return ['subfamilias' => $subs, 'generos' => $gens];
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

        <div class="relative z-30 w-full sm:w-80">
            {{-- Un @if dentro de la etiqueta del componente rompe el compilador de componentes
                 de Blade y deja <flux:input> como texto literal (invisible). Por eso el
                 condicional de modo se resuelve partiendo en dos invocaciones completas. --}}
            @if($modo === 'visitante')
                <flux:input
                    wire:model.live.debounce.350ms="busquedaEspecimen"
                    wire:keydown.enter="localizarPorNombre(busquedaEspecimen)"
                    icon="magnifying-glass"
                    placeholder="Buscar por nombre científico"
                    class="w-full"
                />
            @else
                <flux:input
                    wire:model.live.debounce.350ms="busquedaEspecimen"
                    icon="magnifying-glass"
                    placeholder="Buscar espécimen por nombre o código"
                    class="w-full"
                />
            @endif

            <div wire:loading.flex wire:target="busquedaEspecimen" class="absolute right-3 top-1/2 -translate-y-1/2 items-center">
                <flux:icon name="arrow-path" class="size-4 animate-spin text-text-secondary" />
            </div>

            @if(count($sugerencias) > 0)
                <ul class="absolute z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-border bg-surface shadow-sm">
                    {{-- Al clicar una sugerencia, feedback inmediato mientras se resuelve la ubicación. --}}
                    <li wire:loading.flex wire:target="localizar,localizarPorNombre" class="items-center gap-2 border-b border-border px-3 py-2 text-xs text-text-secondary">
                        <flux:icon name="arrow-path" class="size-4 animate-spin" />
                        Localizando…
                    </li>
                    @foreach($sugerencias as $sugerencia)
                        <li>
                            <button
                                type="button"
                                @if($modo === 'visitante')
                                    wire:click="localizarPorNombre(@js($sugerencia['taxonNombre']))"
                                @else
                                    wire:click="localizar('{{ $sugerencia['id'] }}')"
                                @endif
                                class="flex w-full min-h-[44px] flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-bg-main"
                            >
                                <span class="font-serif italic text-text-primary">{{ $sugerencia['taxonNombre'] }}</span>
                                <span class="text-xs text-text-secondary">{{ $sugerencia['codigoCatalogo'] }}</span>
                                @php
                                    $us = $sugerencia['ubicacion'] ?? null;
                                    $partesUbic = $us === null ? [] : array_filter([
                                        $us['gabineteCodigo'] ? 'Gab. '.$us['gabineteCodigo'] : null,
                                        $us['ranuraNumero'] ? 'Ranura '.$us['ranuraNumero'] : null,
                                        $us['cajaCodigo'] ?: null,
                                        $us['trayNumero'] ? 'Tray '.$us['trayNumero'] : null,
                                    ]);
                                @endphp
                                @if($partesUbic !== [])
                                    <span class="flex items-center gap-1 text-xs text-text-secondary">
                                        <flux:icon name="map-pin" class="size-3.5 shrink-0 text-blue-navy" />
                                        {{ implode(' · ', $partesUbic) }}
                                    </span>
                                @endif
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

    {{-- Breadcrumb físico (gabinete · ranura/caja · unit tray). Cada migaja muestra un spinner al
         clicarla para que el cambio de nivel tenga feedback inmediato. --}}
    <nav class="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm" aria-label="Ruta del mapa">
        <button type="button" wire:click="volverAGeneral" class="inline-flex items-center gap-1 text-science-blue hover:underline @if(! $gabineteSeleccionado) font-semibold text-text-primary @endif">
            <flux:icon wire:loading wire:target="volverAGeneral" name="arrow-path" class="size-3.5 animate-spin" />
            Colección
        </button>
        @if($gabineteSeleccionado)
            <flux:icon name="chevron-right" class="size-4 text-text-secondary" />
            <button type="button" wire:click="cerrarCaja" class="inline-flex items-center gap-1 text-science-blue hover:underline @if(! $cajaSeleccionada) font-semibold text-text-primary @endif">
                <flux:icon wire:loading wire:target="cerrarCaja" name="arrow-path" class="size-3.5 animate-spin" />
                {{ $gabineteSeleccionado['codigo'] }}
            </button>
        @endif
        @if($cajaSeleccionada)
            <flux:icon name="chevron-right" class="size-4 text-text-secondary" />
            <button type="button" wire:click="cerrarUnitTray" class="inline-flex items-center gap-1 text-science-blue hover:underline @if(! $unitTraySeleccionado) font-semibold text-text-primary @endif">
                <flux:icon wire:loading wire:target="cerrarUnitTray" name="arrow-path" class="size-3.5 animate-spin" />
                @if(($cajaSeleccionada['numeroRanura'] ?? 0) > 0)Ranura {{ $cajaSeleccionada['numeroRanura'] }} · @endif Caja {{ $cajaSeleccionada['codigo'] }}
            </button>
        @endif
        @if($unitTraySeleccionado)
            <flux:icon name="chevron-right" class="size-4 text-text-secondary" />
            <span class="font-semibold text-text-primary">Unit tray {{ $unitTraySeleccionado['numero'] }}</span>
        @endif
    </nav>

    {{-- Indicador de carga al cambiar de nivel (cubre toda navegación: zoom, migajas y búsqueda) --}}
    <div wire:loading.flex wire:target="abrirGabinete,abrirCaja,abrirCajaDirecto,abrirUnitTray,volverAGeneral,cerrarCaja,cerrarUnitTray,localizar,localizarPorNombre" class="items-center gap-2 text-sm text-text-secondary">
        <flux:icon name="arrow-path" class="size-4 animate-spin" />
        Cargando…
    </div>

    {{-- Leyenda tipográfica de las firmas (estilo de letra por rango): arriba, visible en todos los niveles. --}}
    <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-leyenda />

    {{-- ============ NIVEL 4: especímenes de un unit tray ============ --}}
    @if($unitTraySeleccionado)
        @php
            // Clasificación del tray abierto (de los trays ya cargados de la caja) para su ruta taxonómica.
            $cTrayAbierto = null;
            foreach ($unitTrays as $t) {
                if (($t['unitTrayId'] ?? null) === $trayAbierto) {
                    $cTrayAbierto = $t['clasificacionDominante'] ?? null;
                    break;
                }
            }
        @endphp
        <div wire:key="nivel-unittray" wire:transition class="rounded-lg border border-border bg-surface shadow-sm">
            <div class="border-b border-border p-4">
                <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                    Unit tray {{ $unitTraySeleccionado['numero'] }}
                </flux:heading>
                <p class="text-xs text-text-secondary">{{ count($especimenes) }} especímenes</p>
                {{-- Ruta taxonómica completa del unit tray: contexto de dónde estás hasta especie. --}}
                <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-ruta
                    class="mt-2"
                    :orden="data_get($cTrayAbierto, 'orden')"
                    :subordenes="data_get($cTrayAbierto, 'suborden')"
                    :superfamilias="data_get($cTrayAbierto, 'superfamilia')"
                    :familias="data_get($cTrayAbierto, 'familia')"
                    :subfamilias="data_get($cTrayAbierto, 'subfamilias', [])"
                    :generos="data_get($cTrayAbierto, 'generos', [])"
                    :especies="data_get($cTrayAbierto, 'especie')"
                />
            </div>

            @if(count($especimenes) > 0)
                {{-- Escritorio --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-blue-navy text-left text-white">
                                <th class="px-4 py-2 font-semibold">Código de catálogo</th>
                                <th class="px-4 py-2 font-semibold">Género</th>
                                <th class="px-4 py-2 font-semibold">Especie</th>
                                <th class="px-4 py-2 font-semibold">N.º individuos</th>
                                <th class="px-4 py-2 font-semibold">Provincia</th>
                                <th class="px-4 py-2 font-semibold">Localidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($especimenes as $especimen)
                                <tr @class([
                                    'border-t border-border',
                                    'bg-info/10' => $especimen['especimenId'] === $especimenResaltadoId,
                                    'hover:bg-bg-main' => $especimen['especimenId'] !== $especimenResaltadoId,
                                ])>
                                    <td class="px-4 py-2 text-text-primary">
                                        {{ $especimen['codigoCatalogo'] }}
                                        @if($especimen['especimenId'] === $especimenResaltadoId)
                                            <flux:badge size="sm" color="sky" class="ml-2">Buscado</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-text-primary">
                                        @if($especimen['genero'])<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$especimen['genero']" estilo="genero" />@else—@endif
                                    </td>
                                    <td class="px-4 py-2 text-text-primary">
                                        @if($especimen['especie'])<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$especimen['especie']" estilo="especie" />@else—@endif
                                    </td>
                                    <td class="px-4 py-2 text-text-primary">{{ $especimen['individualCount'] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-text-primary">{{ $especimen['provincia'] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-text-primary">{{ $especimen['localidad'] ?? '—' }}</td>
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
                                @if($especimen['especimenId'] === $especimenResaltadoId)
                                    <flux:badge size="sm" color="sky" class="ml-2">Buscado</flux:badge>
                                @endif
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Género">
                                @if($especimen['genero'])<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$especimen['genero']" estilo="genero" />@else—@endif
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Especie">
                                @if($especimen['especie'])<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$especimen['especie']" estilo="especie" />@else—@endif
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="N.º individuos">
                                {{ $especimen['individualCount'] ?? '—' }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Provincia">
                                {{ $especimen['provincia'] ?? '—' }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Localidad">
                                {{ $especimen['localidad'] ?? '—' }}
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
        @endphp

        <div wire:key="nivel-caja" wire:transition class="space-y-3">
            <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                Caja {{ $cajaSeleccionada['codigo'] }}
            </flux:heading>

            {{-- Ruta taxonómica completa de la caja: para saber siempre en qué familia/subfamilia/género estás. --}}
            <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-ruta
                :orden="$recolectar($clasifTrays, 'orden')"
                :subordenes="$recolectar($clasifTrays, 'suborden')"
                :superfamilias="$recolectar($clasifTrays, 'superfamilia')"
                :familias="$recolectar($clasifTrays, 'familia')"
                :subfamilias="$recolectar($clasifTrays, 'subfamilias')"
                :generos="$recolectar($clasifTrays, 'generos')"
                :especies="$recolectar($clasifTrays, 'especie')"
            />

            {{-- Firma de especie/s de la caja (rango), con fallback por rango. --}}
            <x-inventariogestioncoleccion::seguimiento-fisico.firma-taxonomica
                enfasis="especie"
                :especies="$recolectar($clasifTrays, 'especie')"
                :generos="$recolectar($clasifTrays, 'generos')"
                :subfamilias="$recolectar($clasifTrays, 'subfamilias')"
                :familias="$recolectar($clasifTrays, 'familia')"
                :superiores="$superiores($clasifTrays)"
            />

            @if(count($unitTrays) > 0)
                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr))">
                    @foreach($unitTrays as $tray)
                        @php
                            $cTray = $tray['clasificacionDominante'] ?? null;
                            $variosTray = $variosTaxones($cTray);
                            $taxonesTray = $albergados($cTray);
                            // El tooltip mantiene el formato «Tray N · taxón»: en transición lista todos
                            // sus taxones tras el número; si no, el taxón dominante.
                            $detalleTray = $variosTray && $taxonesTray !== []
                                ? implode(' · ', $taxonesTray)
                                : $etiquetaTaxon($cTray);
                            $tooltipTray = 'Tray '.$tray['numero'].' · '.$detalleTray;
                        @endphp
                        <flux:tooltip :content="$tooltipTray">
                            <button
                                type="button"
                                wire:click="abrirUnitTray('{{ $tray['unitTrayId'] }}', {{ $tray['numero'] }})"
                                class="flex min-h-[44px] w-full cursor-pointer flex-col gap-1 rounded-lg p-3 text-left shadow-sm transition-colors hover:ring-2 hover:ring-science-blue {{ $claseOcupada }}"
                            >
                                <span class="text-sm font-bold">Tray {{ $tray['numero'] }}</span>
                                @php $tokTray = $tokenDominante($cTray); @endphp
                                <span class="text-xs text-text-secondary">
                                    <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$tokTray['texto']" :estilo="$tokTray['estilo']" />
                                </span>
                                @if($variosTray)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium not-italic text-text-secondary">
                                        <flux:icon name="rectangle-stack" class="size-3.5 shrink-0" />
                                        {{ count($taxonesTray) }} taxones
                                    </span>
                                @endif
                            </button>
                        </flux:tooltip>
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
        @endphp

        <div wire:key="nivel-gabinete" wire:transition class="space-y-4">
            <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                {{ $gabineteSeleccionado['codigo'] }} — {{ $gabineteSeleccionado['nombre'] }}
            </flux:heading>

            {{-- La ruta taxonómica no se repite aquí: cada label de caja ya muestra su familia y
                 subfamilia/género, así que en el nivel de gabinete sería redundante. --}}

            {{-- Firma de subfamilia/s y especie/s del gabinete, con fallback por rango. --}}
            <x-inventariogestioncoleccion::seguimiento-fisico.firma-taxonomica
                enfasis="subfamiliaEspecie"
                :subfamilias="$recolectar($clasifRanuras, 'subfamilias')"
                :especies="$recolectar($clasifRanuras, 'especie')"
                :generos="$recolectar($clasifRanuras, 'generos')"
                :familias="$familiasGab"
                :superiores="$superiores($clasifRanuras)"
            />

            {{-- Vitrina: cajones (ranuras) apilados verticalmente, con aire entre colores. Se usa
                 flex+gap (no space-y) porque el tooltip envuelve cada cajón ocupado en un
                 <ui-tooltip display:contents>, sobre el que el margen de space-y no se renderiza. --}}
            <div class="flex w-full flex-col gap-3 rounded-lg border-2 border-border bg-surface p-3 shadow-sm">
                @foreach($ranuras as $ranura)
                    @php
                        $cRanura = $ranura['clasificacion'] ?? null;
                        $variosRanura = $variosTaxones($cRanura);
                        $taxonesRanura = $albergados($cRanura);
                        $gruposRanura = $taxonesAgrupados($cRanura);
                    @endphp
                    {{-- wire:click solo surte efecto en cajones ocupados; el cajón vacío no emite atributos.
                         La etiqueta dominante usa subfamilia·género; la familia se muestra aparte como
                         contexto. Una caja de transición lista el detalle agrupado por rango en ancho y el
                         conteo de taxones en angosto, y el detalle plano en el tooltip. --}}
                    <x-inventariogestioncoleccion::seguimiento-fisico.ranura-cajon
                        :ranura="$ranura"
                        :clase="$claseOcupada"
                        :familia="data_get($cRanura, 'familia')"
                        :etiqueta="$etiquetaDominante($cRanura)"
                        :tokens="$tokensDominantes($cRanura)"
                        :subfamilias="$variosRanura ? $gruposRanura['subfamilias'] : []"
                        :generos="$variosRanura ? $gruposRanura['generos'] : []"
                        :extra="$variosRanura ? count($taxonesRanura).' taxones' : null"
                        :detalle="$variosRanura && $taxonesRanura !== [] ? implode(' · ', $taxonesRanura) : null"
                        wire:click="abrirCaja('{{ $ranura['cajaId'] }}', '{{ $ranura['codigoCaja'] }}')"
                    />
                @endforeach
            </div>

            {{-- Vista de tabla de las cajas (aprovecha el ancho): ranura, caja y taxonomía por columnas.
                 Cada fila abre la caja. Solo cajas ocupadas. --}}
            @php $ranurasOcupadas = array_values(array_filter($ranuras, fn ($r) => $r['ocupada'] ?? false)); @endphp
            @if(count($ranurasOcupadas) > 0)
                <details class="rounded-lg border border-border bg-surface text-sm shadow-sm">
                    <summary class="cursor-pointer px-4 py-2 font-medium text-science-blue hover:underline">Ver como tabla</summary>
                    <div class="overflow-x-auto border-t border-border">
                        <table class="w-full text-left">
                            <thead class="bg-blue-navy text-white">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Ranura</th>
                                    <th class="px-3 py-2 font-medium">Caja</th>
                                    <th class="px-3 py-2 font-medium">Familia</th>
                                    <th class="px-3 py-2 font-medium">Subfamilia</th>
                                    <th class="px-3 py-2 font-medium">Género</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($ranurasOcupadas as $r)
                                    @php
                                        $cr = $r['clasificacion'] ?? null;
                                        $subsR = array_values(array_filter($cr['subfamilias'] ?? []));
                                        $gensR = array_values(array_filter($cr['generos'] ?? []));
                                    @endphp
                                    <tr class="cursor-pointer hover:bg-bg-main" wire:click="abrirCaja('{{ $r['cajaId'] }}', '{{ $r['codigoCaja'] }}')">
                                        <td class="px-3 py-2 text-text-secondary">{{ $r['numeroRanura'] }}</td>
                                        <td class="px-3 py-2 font-medium text-text-primary">{{ $r['codigoCaja'] }}</td>
                                        <td class="px-3 py-2 align-top">
                                            @if(trim((string) data_get($cr, 'familia')) !== '')<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="data_get($cr, 'familia')" estilo="familia" />@else<span class="text-text-secondary">—</span>@endif
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            @forelse($subsR as $i => $s)@if($i > 0)<span class="text-text-secondary"> · </span>@endif<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$s" estilo="subfamilia" />@empty<span class="text-text-secondary">—</span>@endforelse
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            @forelse($gensR as $i => $g)@if($i > 0)<span class="text-text-secondary"> · </span>@endif<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$g" estilo="genero" />@empty<span class="text-text-secondary">—</span>@endforelse
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </div>

    {{-- ============ NIVEL 1: vista general de gabinetes (vitrinas lado a lado) ============ --}}
    @else
        <div wire:key="nivel-general" wire:transition class="space-y-4">
            @if(count($gabinetes) > 0)
                {{-- Alternar vista compacta: minimiza las cajas para abarcar muchos gabinetes a la vez. --}}
                <div class="flex justify-end">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        :icon="$vistaCompacta ? 'arrows-pointing-out' : 'arrows-pointing-in'"
                        wire:click="alternarVistaCompacta"
                    >
                        {{ $vistaCompacta ? 'Vista normal' : 'Minimizar vista' }}
                    </flux:button>
                </div>
            @endif

            @if(count($gabinetes) > 0)
                @if($vistaCompacta)
                    {{-- Vista compacta: grilla densa de gabinetes. Solo familias visibles; el desplegable
                         muestra una tabla Familia · Subfamilia · Género con las combinaciones presentes. --}}
                    <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr))">
                        @foreach($gabinetes as $gabinete)
                            @php
                                $clasifGab = array_map(fn ($r) => $r['clasificacion'] ?? null, $gabinete['ranuras']);

                                // Combinaciones (familia, subfamilia, género) presentes en el gabinete, sin
                                // duplicados y ordenadas: una fila por cada terna distinta de sus cajas.
                                $filasTaxon = [];
                                foreach ($gabinete['ranuras'] as $r) {
                                    $c = $r['clasificacion'] ?? null;
                                    if ($c === null) {
                                        continue;
                                    }
                                    $fam = $c['familia'] ?? null;
                                    $sub = $c['subfamilia'] ?? null;
                                    $gen = $c['genero'] ?? null;
                                    if (trim((string) $fam) === '' && trim((string) $sub) === '' && trim((string) $gen) === '') {
                                        continue;
                                    }
                                    $clave = mb_strtolower(trim((string) $fam).'|'.trim((string) $sub).'|'.trim((string) $gen));
                                    $filasTaxon[$clave] = ['familia' => $fam, 'subfamilia' => $sub, 'genero' => $gen];
                                }
                                $filasTaxon = array_values($filasTaxon);
                                usort($filasTaxon, fn ($a, $b) => [mb_strtolower((string) $a['familia']), mb_strtolower((string) $a['subfamilia']), mb_strtolower((string) $a['genero'])]
                                    <=> [mb_strtolower((string) $b['familia']), mb_strtolower((string) $b['subfamilia']), mb_strtolower((string) $b['genero'])]);
                            @endphp
                            <div class="flex flex-col gap-2 rounded-lg border border-border bg-surface p-3 shadow-sm">
                                <button
                                    type="button"
                                    wire:click="abrirGabinete('{{ $gabinete['id'] }}')"
                                    class="group cursor-pointer text-left"
                                >
                                    <span class="font-display font-semibold text-blue-navy group-hover:underline">{{ $gabinete['codigo'] }}</span>
                                    <span class="block truncate text-xs text-text-secondary">{{ $gabinete['nombre'] }}</span>
                                </button>
                                <x-inventariogestioncoleccion::seguimiento-fisico.firma-taxonomica
                                    enfasis="familia"
                                    :familias="$recolectar($clasifGab, 'familia')"
                                    :superiores="$superiores($clasifGab)"
                                />
                                @if($filasTaxon !== [])
                                    <details class="text-xs">
                                        <summary class="cursor-pointer text-science-blue hover:underline">Ver subfamilias y géneros</summary>
                                        <div class="mt-1.5 overflow-x-auto">
                                            <table class="w-full text-left">
                                                <thead>
                                                    <tr class="text-text-secondary">
                                                        <th class="py-1 pr-3 font-medium">Familia</th>
                                                        <th class="py-1 pr-3 font-medium">Subfamilia</th>
                                                        <th class="py-1 font-medium">Género</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-border">
                                                    @foreach($filasTaxon as $fila)
                                                        <tr>
                                                            <td class="py-1 pr-3 align-top">
                                                                @if(trim((string) $fila['familia']) !== '')<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$fila['familia']" estilo="familia" />@else<span class="text-text-secondary">—</span>@endif
                                                            </td>
                                                            <td class="py-1 pr-3 align-top">
                                                                @if(trim((string) $fila['subfamilia']) !== '')<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$fila['subfamilia']" estilo="subfamilia" />@else<span class="text-text-secondary">—</span>@endif
                                                            </td>
                                                            <td class="py-1 align-top">
                                                                @if(trim((string) $fila['genero']) !== '')<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$fila['genero']" estilo="genero" />@else<span class="text-text-secondary">—</span>@endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- Gabinetes como vitrinas: grilla responsiva que llena el ancho y envuelve (sin
                         tira con scroll, que dejaba espacio horizontal sin usar). Cada mini-caja ocupada
                         es clicable para saltar directo a la caja. --}}
                    <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr))">
                        @foreach($gabinetes as $gabinete)
                            @php
                                $clasifGab = array_map(fn ($r) => $r['clasificacion'] ?? null, $gabinete['ranuras']);
                            @endphp
                            <div class="flex flex-col gap-3 rounded-lg border-2 border-border bg-surface p-4 shadow-sm">
                                <div class="space-y-2">
                                    {{-- Familias junto al título (no debajo): se reparten en línea y envuelven
                                         dinámicamente cuando hay más de una. --}}
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">
                                            {{ $gabinete['codigo'] }} — {{ $gabinete['nombre'] }}
                                        </flux:heading>
                                        <x-inventariogestioncoleccion::seguimiento-fisico.firma-taxonomica
                                            enfasis="familia"
                                            :familias="$recolectar($clasifGab, 'familia')"
                                            :superiores="$superiores($clasifGab)"
                                        />
                                    </div>
                                    <p class="text-xs text-text-secondary">{{ $gabinete['totalRanuras'] }} ranuras</p>
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
                                            @php
                                                // Sneak peek al nivel de cajas: si la caja alberga varios taxones,
                                                // el tooltip los lista todos (igual que en el nivel de gabinete);
                                                // si no, el taxón dominante.
                                                $cMini = $ranura['clasificacion'] ?? null;
                                                $taxonesMini = $albergados($cMini);
                                                $detalleMini = count($taxonesMini) > 1 ? implode(' · ', $taxonesMini) : null;
                                            @endphp
                                            {{-- wire:click va siempre: la rama del mini-cajón vacío no consume
                                                 $attributes, así que solo surte efecto en cajones ocupados. --}}
                                            <x-inventariogestioncoleccion::seguimiento-fisico.ranura-cajon
                                                compacto
                                                :ranura="$ranura"
                                                :clase="$claseOcupada"
                                                :familia="data_get($ranura, 'clasificacion.familia')"
                                                :etiqueta="$etiquetaTaxon($cMini)"
                                                :detalle="$detalleMini"
                                                wire:click="abrirCajaDirecto('{{ $gabinete['id'] }}', '{{ $ranura['cajaId'] }}')"
                                            />
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-text-secondary">Sin ranuras configuradas.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-border p-12 text-center">
                    <flux:icon name="archive-box" class="mx-auto mb-3 size-12 text-text-secondary" />
                    <p class="text-text-primary">No hay gabinetes registrados.</p>
                </div>
            @endif
        </div>
    @endif
</div>
