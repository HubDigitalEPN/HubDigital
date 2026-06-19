@props([
    // Valores presentes por rango (cada uno una lista; el orden superior puede traer varios al
    // agregar varias cajas). Se pintan en cadena descendente, omitiendo los rangos vacíos.
    'orden' => [],
    'subordenes' => [],
    'superfamilias' => [],
    'familias' => [],
    'subfamilias' => [],
    'generos' => [],
    'especies' => [],
])

@php
    // Cada rango: lista de valores limpios + el estilo tipográfico de la leyenda (taxon-tipografico).
    $norm = fn (array $v): array => array_values(array_filter($v, fn ($x) => trim((string) $x) !== ''));
    $rangos = [
        ['valores' => $norm((array) $orden), 'estilo' => 'superior'],
        ['valores' => $norm((array) $subordenes), 'estilo' => 'superior'],
        ['valores' => $norm((array) $superfamilias), 'estilo' => 'superior'],
        ['valores' => $norm((array) $familias), 'estilo' => 'familia'],
        ['valores' => $norm((array) $subfamilias), 'estilo' => 'subfamilia'],
        ['valores' => $norm((array) $generos), 'estilo' => 'genero'],
        ['valores' => $norm((array) $especies), 'estilo' => 'especie'],
    ];
    $rangos = array_values(array_filter($rangos, fn ($r) => $r['valores'] !== []));
@endphp

{{-- Ruta taxonómica completa y concisa del punto actual (orden › familia › subfamilia › género ›
     especie): complementa el breadcrumb físico para saber siempre en qué taxonomía estás. Cada
     rango respeta la tipografía de la leyenda; varios valores de un mismo rango van con «·». --}}
@if($rangos !== [])
    <nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-text-secondary']) }} aria-label="Ruta taxonómica">
        @foreach($rangos as $i => $rango)
            @if($i > 0)<flux:icon name="chevron-right" class="size-3.5 shrink-0 text-text-secondary" />@endif
            <span class="inline-flex flex-wrap items-baseline gap-x-1 text-text-primary">
                @foreach($rango['valores'] as $j => $valor)
                    @if($j > 0)<span class="text-text-secondary">·</span>@endif<x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico :texto="$valor" :estilo="$rango['estilo']" />
                @endforeach
            </span>
        @endforeach
    </nav>
@endif
