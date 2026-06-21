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
                <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="especie" :texto="implode(', ', $listaGeneros)" class="text-text-primary" />
            @elseif(! empty($especie))
                <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="especie" :texto="$especie" class="text-text-primary" />
            @elseif(! empty($listaGeneros))
                <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="genero" :texto="$listaGeneros[0]" class="text-text-primary" />
            @endif

            {{-- Subfamilia: serif redonda (rango sobre el género) --}}
            @if(! empty($listaSubfamilias))
                <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="subfamilia" :texto="implode(', ', $listaSubfamilias)" class="text-xs text-text-secondary" />
            @endif

            @if($variasSubfamilias || $variosGeneros)
                <span class="text-xs italic text-text-secondary">Varios taxones</span>
            @endif
        @elseif($respaldoEsFamilia)
            {{-- Familia: sans seminegrita --}}
            <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="familia" :texto="$respaldoValor" class="text-text-primary" />
        @else
            {{-- Orden / suborden / superfamilia: sans en mayúsculas --}}
            <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="superior" :texto="$respaldoValor" class="text-xs text-text-primary" />
        @endif
    </span>
@else
    <span class="text-xs italic text-text-secondary">Sin clasificación</span>
@endif
