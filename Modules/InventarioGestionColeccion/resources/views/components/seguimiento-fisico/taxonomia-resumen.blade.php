@props([
    'subfamilia' => null,
    'genero' => null,
    'especie' => null,
])

@php
    $tieneClasificacion = $subfamilia || $genero || $especie;
    $binomial = trim(($genero ?? '').' '.($especie ?? ''));
@endphp

@if($tieneClasificacion)
    <span class="inline-flex flex-col leading-tight">
        @if($binomial !== '')
            <span class="font-serif italic text-text-primary">{{ $binomial }}</span>
        @endif
        @if($subfamilia)
            <span class="text-xs text-text-secondary">{{ $subfamilia }}</span>
        @endif
    </span>
@else
    <span class="text-xs italic text-text-secondary">Sin clasificación</span>
@endif
