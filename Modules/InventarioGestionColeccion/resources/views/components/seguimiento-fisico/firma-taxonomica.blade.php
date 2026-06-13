@props([
    // Conjuntos de taxones presentes en el nivel (cada uno orden alfabético, dominante primero).
    'familias' => [],
    'subfamilias' => [],
    'generos' => [],
    'especies' => [],
    // Rangos superiores (orden / suborden / superfamilia) para el fallback más profundo.
    'superiores' => [],
    // Rango que el nivel enfatiza: 'familia' | 'subfamiliaEspecie' | 'especie'.
    'enfasis' => 'familia',
])

@php
    $familias = array_values(array_filter($familias));
    $subfamilias = array_values(array_filter($subfamilias));
    $generos = array_values(array_filter($generos));
    $especies = array_values(array_filter($especies));
    $superiores = array_values(array_filter($superiores));

    // Construye la lista de chips a mostrar respetando el fallback por rango: se baja al
    // rango que el nivel enfatiza y, si está vacío, se sube por la cadena hasta encontrar algo.
    $chips = [];
    $agregar = function (array $valores, string $estilo) use (&$chips): void {
        foreach ($valores as $valor) {
            $chips[] = ['texto' => $valor, 'estilo' => $estilo];
        }
    };
    $respaldoAlto = function () use ($agregar, $familias, $superiores): void {
        if ($familias !== []) {
            $agregar($familias, 'familia');
        } elseif ($superiores !== []) {
            $agregar($superiores, 'superior');
        }
    };

    if ($enfasis === 'familia') {
        $respaldoAlto();
    } elseif ($enfasis === 'especie') {
        if ($especies !== []) {
            $agregar($especies, 'especie');
        } elseif ($generos !== []) {
            $agregar($generos, 'genero');
        } elseif ($subfamilias !== []) {
            $agregar($subfamilias, 'subfamilia');
        } else {
            $respaldoAlto();
        }
    } else { // subfamiliaEspecie
        if ($subfamilias !== []) {
            $agregar($subfamilias, 'subfamilia');
        }
        if ($especies !== []) {
            $agregar($especies, 'especie');
        } elseif ($generos !== []) {
            $agregar($generos, 'genero');
        }
        if ($chips === []) {
            $respaldoAlto();
        }
    }
@endphp

@if($chips !== [])
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5']) }}>
        @foreach($chips as $chip)
            <span class="inline-flex items-center rounded-full border border-border bg-surface px-2 py-0.5 text-xs text-text-primary">
                @if($chip['estilo'] === 'especie')
                    <span class="font-serif italic">{{ $chip['texto'] }}</span>
                @elseif($chip['estilo'] === 'genero')
                    <span class="font-serif"><span class="italic">{{ $chip['texto'] }}</span> sp.</span>
                @elseif($chip['estilo'] === 'subfamilia')
                    <span class="font-serif">{{ $chip['texto'] }}</span>
                @elseif($chip['estilo'] === 'familia')
                    <span class="font-sans font-semibold">{{ $chip['texto'] }}</span>
                @else
                    <span class="font-sans uppercase tracking-wide">{{ $chip['texto'] }}</span>
                @endif
            </span>
        @endforeach
    </div>
@else
    <span {{ $attributes->merge(['class' => 'text-xs italic text-text-secondary']) }}>Sin clasificación</span>
@endif
