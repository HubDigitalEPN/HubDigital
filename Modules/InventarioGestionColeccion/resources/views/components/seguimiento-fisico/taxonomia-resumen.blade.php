@props([
    'subfamilia' => null,
    'genero' => null,
    'especie' => null,
    'subfamilias' => [],
    'generos' => [],
])

@php
    // El conjunto puede traer varias subfamilias/géneros (caja con varios taxones);
    // si no llega, se reconstruye a partir del valor dominante para no perder nada.
    $listaSubfamilias = ! empty($subfamilias) ? $subfamilias : array_filter([$subfamilia]);
    $listaGeneros = ! empty($generos) ? $generos : array_filter([$genero]);

    $tieneClasificacion = $listaSubfamilias || $listaGeneros || $especie;
    $binomial = trim(($genero ?? '').' '.($especie ?? ''));

    $variosGeneros = count($listaGeneros) > 1;
    $variasSubfamilias = count($listaSubfamilias) > 1;
@endphp

@if($tieneClasificacion)
    <span class="inline-flex flex-col leading-tight">
        @if($variosGeneros)
            <span class="font-serif italic text-text-primary">{{ implode(', ', $listaGeneros) }}</span>
        @elseif($binomial !== '')
            <span class="font-serif italic text-text-primary">{{ $binomial }}</span>
        @elseif(! empty($listaGeneros))
            <span class="font-serif italic text-text-primary">{{ $listaGeneros[0] }}</span>
        @endif

        @if(! empty($listaSubfamilias))
            <span class="text-xs text-text-secondary">{{ implode(', ', $listaSubfamilias) }}</span>
        @endif

        @if($variasSubfamilias || $variosGeneros)
            <span class="text-xs italic text-text-secondary">Varios taxones</span>
        @endif
    </span>
@else
    <span class="text-xs italic text-text-secondary">Sin clasificación</span>
@endif
