@props([
    'orden' => null,
    'suborden' => null,
    'superfamilia' => null,
    'familia' => null,
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

    $tieneNivelFino = $listaSubfamilias || $listaGeneros || $especie;

    $variosGeneros = count($listaGeneros) > 1;
    $variasSubfamilias = count($listaSubfamilias) > 1;

    // Respaldo: cuando no se llega a subfamilia/género/especie, se muestra el rango
    // más específico que sí exista, subiendo por la cadena taxonómica. El estilo de
    // letra (definido en la leyenda) indica de qué rango se trata.
    $respaldoValor = null;
    $respaldoEsFamilia = false;
    if (! $tieneNivelFino) {
        foreach ([$familia, $superfamilia, $suborden, $orden] as $i => $valor) {
            if (! empty($valor)) {
                $respaldoValor = $valor;
                $respaldoEsFamilia = $i === 0;
                break;
            }
        }
    }

    $tieneClasificacion = $tieneNivelFino || $respaldoValor !== null;
@endphp

@if($tieneClasificacion)
    <span class="inline-flex flex-col leading-tight">
        @if($tieneNivelFino)
            {{-- Especie: binomio completo en cursiva serif (ya incluye el género). --}}
            {{-- Género solo: cursiva + "sp." en redonda (notación de género sin especie). --}}
            @if($variosGeneros)
                <span class="font-serif italic text-text-primary">{{ implode(', ', $listaGeneros) }}</span>
            @elseif(! empty($especie))
                <span class="font-serif italic text-text-primary">{{ $especie }}</span>
            @elseif(! empty($listaGeneros))
                <span class="font-serif text-text-primary"><span class="italic">{{ $listaGeneros[0] }}</span> sp.</span>
            @endif

            {{-- Subfamilia: serif redonda (rango sobre el género) --}}
            @if(! empty($listaSubfamilias))
                <span class="font-serif text-xs text-text-secondary">{{ implode(', ', $listaSubfamilias) }}</span>
            @endif

            @if($variasSubfamilias || $variosGeneros)
                <span class="text-xs italic text-text-secondary">Varios taxones</span>
            @endif
        @elseif($respaldoEsFamilia)
            {{-- Familia: sans seminegrita --}}
            <span class="font-sans font-semibold text-text-primary">{{ $respaldoValor }}</span>
        @else
            {{-- Orden / suborden / superfamilia: sans en mayúsculas --}}
            <span class="font-sans uppercase tracking-wide text-xs text-text-primary">{{ $respaldoValor }}</span>
        @endif
    </span>
@else
    <span class="text-xs italic text-text-secondary">Sin clasificación</span>
@endif
