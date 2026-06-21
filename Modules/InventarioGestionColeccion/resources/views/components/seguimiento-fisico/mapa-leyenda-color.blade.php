@props([
    // Cada item: ['clase' => string] (color del swatch) + uno de:
    //   'tokens' => [['texto','estilo'], ...]  → texto tipado por rango (taxon-tipografico), o
    //   'label'  => string                     → respaldo plano (se trata como rango 'familia').
    'items' => [],
    // Muestra la entrada "Ranura vacía / sin clasificar" al final.
    'mostrarVacia' => true,
])

{{-- Leyenda que mapea cada color a su taxón presente en la vista actual. El color de cada
     swatch coincide con el de los cajones/celdas porque comparten paleta y orden. La tipografía
     de cada token respeta la leyenda taxonómica (subfamilia serif · género cursiva "sp."). --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-lg border border-border bg-bg-main px-3 py-2 text-xs text-text-secondary']) }}>
    <span class="inline-flex items-center gap-1.5 font-medium text-text-primary">
        <flux:icon name="swatch" class="size-4 text-science-blue" />
        Color por taxonomía
    </span>

    @foreach($items as $item)
        @php
            $tokens = $item['tokens'] ?? [['texto' => $item['label'] ?? 'Sin clasificar', 'estilo' => 'familia']];
        @endphp
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block size-3 shrink-0 rounded {{ $item['clase'] }}"></span>
            <span class="text-text-primary">
                @foreach($tokens as $i => $token)
                    @if($i > 0)<span class="text-text-secondary"> · </span>@endif<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$token['texto']" :estilo="$token['estilo']" />
                @endforeach
            </span>
        </span>
    @endforeach

    @if($mostrarVacia)
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block size-3 shrink-0 rounded border border-dashed border-border bg-bg-main"></span>
            Ranura vacía / sin clasificar
        </span>
    @endif
</div>
