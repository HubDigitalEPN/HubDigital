@props(['status'])

@php
    $label = \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::estado($status);
    $color = match(strtolower(trim($status ?? ''))) {
        'present', 'detected' => 'success',
        'absent', 'notdetected' => 'warning',
        default => 'zinc',
    };
@endphp

<flux:badge color="{{ $color }}" size="sm" title="Estado original de la fuente; no equivale necesariamente a detección durante la colecta" {{ $attributes }}>Estado original: {{ $label }}</flux:badge>
