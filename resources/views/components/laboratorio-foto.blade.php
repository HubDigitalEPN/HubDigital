@props(['foto', 'clave', 'marcar' => true])
@php($attributes = $marcar ? $attributes->merge(['data-lab-element' => 'foto-'.$clave, 'data-lab-kind' => 'foto', 'data-lab-label' => $foto['alt']]) : $attributes)
@if($foto['ruta'])
    <img {{ $attributes->class(['lab-photo']) }} src="{{ route('laboratorio.foto', ['foto' => $clave, 'hash' => $foto['sha256']]) }}" alt="{{ $foto['alt'] }}" loading="{{ $clave === 'cabecera' ? 'eager' : 'lazy' }}" decoding="async">
@else
    <svg {{ $attributes->class(['lab-photo']) }} viewBox="{{ $foto['encuadre'] }}" preserveAspectRatio="xMidYMid slice" role="img" aria-label="{{ $foto['alt'] }}" focusable="false">
        <image href="{{ asset('images/laboratorio/personal-referencia.png') }}" width="1586" height="992" />
    </svg>
@endif
