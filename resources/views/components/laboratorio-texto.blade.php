@props(['texto', 'palabras' => 300, 'clave' => null])
@php($attributes = $clave ? $attributes->merge(['data-lab-element' => 'texto-'.$clave]) : $attributes)
@if(preg_match_all('/\S+/u', $texto) <= $palabras)
    <p {{ $attributes }}>{{ $texto }}</p>
@else
    @php($resumen = \Illuminate\Support\Str::words($texto, $palabras, ''))
    <div {{ $attributes->class(['lab-readable-copy']) }}>
        <p>{{ $resumen }}</p>
        <details>
            <summary><span class="lab-copy-more">Leer más</span><span class="lab-copy-less">Leer menos</span></summary>
            <p>{{ \Illuminate\Support\Str::substr($texto, \Illuminate\Support\Str::length($resumen)) }}</p>
        </details>
    </div>
@endif
