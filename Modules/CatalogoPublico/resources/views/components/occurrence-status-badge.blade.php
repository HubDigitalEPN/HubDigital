@props(['status'])

@php
    $label = \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::estado($status);
    $color = match(strtolower(trim($status ?? ''))) {
        'present', 'in_collection', 'in collection' => 'success',
        'absent' => 'warning',
        default => 'zinc',
    };
@endphp

<flux:badge color="{{ $color }}" size="sm" {{ $attributes }}>{{ $label }}</flux:badge>
