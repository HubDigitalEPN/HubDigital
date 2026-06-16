@props([
    // Ranura mapeada: ['numeroRanura', 'ocupada', 'codigoCaja', 'cajaId', 'clasificacion', ...].
    'ranura',
    // Clase de color de fondo del cajón ocupado (incluye color de texto).
    'clase' => '',
    // Etiqueta de taxón ya resuelta por el padre (con fallback por rango). Respaldo en texto plano.
    'etiqueta' => 'Sin clasificar',
    // Tokens dominantes tipados [['texto','estilo'], ...] para renderizar con la tipografía de la
    // leyenda (subfamilia·género). Vacío = usa $etiqueta plano.
    'tokens' => [],
    // Subfamilias y géneros albergados (caja de transición) para el detalle agrupado por rango.
    'subfamilias' => [],
    'generos' => [],
    // Hint legible para cajas de transición (p. ej. "4 taxones"); null = caja de un solo taxón.
    'extra' => null,
    // Detalle completo (lista de taxones) para el tooltip de una caja de transición; null = sin detalle.
    'detalle' => null,
    // Variante reducida y NO interactiva para la vista general de gabinetes.
    'compacto' => false,
])

@php
    $ocupada = $ranura['ocupada'] ?? false;
    $numero = $ranura['numeroRanura'] ?? '';
    $codigo = $ranura['codigoCaja'] ?? '';
    // Caja de transición: alberga varias subfamilias o géneros y tiene un detalle agrupado.
    $tieneDetalle = $subfamilias !== [] || $generos !== [];
    // El tooltip mantiene el formato «código · taxón»: en transición usa el detalle (lista de
    // taxones) tras el código; si no, el taxón dominante.
    $tooltip = $ocupada ? $codigo.' · '.($detalle ?? $etiqueta) : 'Ranura '.$numero.' — vacía';
@endphp

@if($compacto)
    {{-- Mini-cajón de la vista general: visual y con tooltip; navegar es tarea del botón «Abrir». --}}
    <flux:tooltip :content="$tooltip">
        <div @class([
            'flex h-7 items-center gap-1.5 rounded px-1.5 text-[10px] leading-none',
            $clase => $ocupada,
            'border border-dashed border-border bg-bg-main text-text-secondary' => ! $ocupada,
        ])>
            <span class="shrink-0 font-bold opacity-80">{{ $numero }}</span>
            @if($ocupada)
                <span class="truncate font-medium">{{ $codigo }}</span>
            @endif
        </div>
    </flux:tooltip>
@elseif($ocupada)
    {{-- Cajón ocupado: barra horizontal clicable (el padre aporta el wire:click). El tooltip
         lista los taxones de una caja de transición; si no, el código · taxón dominante. --}}
    <flux:tooltip :content="$tooltip">
        <button
            type="button"
            {{ $attributes->merge(['class' => 'flex min-h-[44px] w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left shadow-sm transition-colors hover:ring-2 hover:ring-science-blue '.$clase]) }}
        >
            <span class="grid size-7 shrink-0 place-items-center rounded bg-black/15 text-xs font-bold">{{ $numero }}</span>
            <span class="flex min-w-0 flex-col leading-tight">
                <span class="truncate text-sm font-bold">{{ $codigo }}</span>

                {{-- Etiqueta dominante (subfamilia·género). En una caja de transición solo aparece en
                     pantallas angostas; en ancho cede su lugar al detalle agrupado por rango. --}}
                <span @class(['truncate text-xs opacity-90', 'xl:hidden' => $tieneDetalle])>
                    @forelse($tokens as $i => $token)
                        @if($i > 0)<span class="opacity-70"> · </span>@endif<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$token['texto']" :estilo="$token['estilo']" />
                    @empty
                        <span class="font-serif italic">{{ $etiqueta }}</span>
                    @endforelse
                </span>

                @if($tieneDetalle)
                    {{-- Angosto: solo el conteo de taxones (el detalle completo va al tooltip). --}}
                    <span class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium not-italic opacity-90 xl:hidden">
                        <flux:icon name="rectangle-stack" class="size-3.5 shrink-0" />
                        {{ $extra }}
                    </span>

                    {{-- Ancho: detalle agrupado por rango con sub-rótulos, cada taxón con su tipografía. --}}
                    <span class="mt-1 hidden flex-col gap-0.5 text-xs xl:flex">
                        @if($subfamilias !== [])
                            <span class="flex flex-wrap items-baseline gap-x-1.5">
                                <span class="font-medium not-italic opacity-70">Subfamilias</span>
                                @foreach($subfamilias as $i => $sub)
                                    @if($i > 0)<span class="opacity-70">·</span>@endif<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$sub" estilo="subfamilia" />
                                @endforeach
                            </span>
                        @endif
                        @if($generos !== [])
                            <span class="flex flex-wrap items-baseline gap-x-1.5">
                                <span class="font-medium not-italic opacity-70">Géneros</span>
                                @foreach($generos as $i => $gen)
                                    @if($i > 0)<span class="opacity-70">·</span>@endif<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$gen" estilo="genero" />
                                @endforeach
                            </span>
                        @endif
                    </span>
                @endif
            </span>
        </button>
    </flux:tooltip>
@else
    {{-- Cajón vacío: barra discontinua, mismo alto que un cajón ocupado. --}}
    <div class="flex min-h-[44px] w-full items-center gap-2.5 rounded-md border border-dashed border-border bg-bg-main px-2.5 py-1.5 text-text-secondary">
        <span class="grid size-7 shrink-0 place-items-center rounded border border-dashed border-border text-xs font-bold">{{ $numero }}</span>
        <span class="text-xs">Vacía</span>
    </div>
@endif
