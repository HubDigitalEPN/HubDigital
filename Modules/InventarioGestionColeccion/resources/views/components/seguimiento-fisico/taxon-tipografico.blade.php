@props([
    // Texto del taxón a mostrar.
    'texto' => '',
    // Rango que fija el estilo de letra: 'especie' | 'genero' | 'subfamilia' | 'familia' | 'superior'.
    // Espejo de la leyenda tipográfica (taxonomia-leyenda): convención única en todo el mapa.
    'estilo' => 'subfamilia',
])

@if($estilo === 'especie')
    <span {{ $attributes->merge(['class' => 'font-serif italic']) }}>{{ $texto }}</span>
@elseif($estilo === 'genero')
    <span {{ $attributes->merge(['class' => 'font-serif']) }}><span class="italic">{{ $texto }}</span> sp.</span>
@elseif($estilo === 'subfamilia')
    <span {{ $attributes->merge(['class' => 'font-serif']) }}>{{ $texto }}</span>
@elseif($estilo === 'familia')
    <span {{ $attributes->merge(['class' => 'font-sans font-semibold']) }}>{{ $texto }}</span>
@else
    <span {{ $attributes->merge(['class' => 'font-sans uppercase tracking-wide']) }}>{{ $texto }}</span>
@endif
