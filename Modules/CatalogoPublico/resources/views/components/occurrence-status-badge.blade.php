@props(['status'])

@php
    $status = strtolower($status ?? '');
    [$color, $label] = match($status) {
        'present'  => ['success', 'Presente'],
        'absent'   => ['warning', 'Ausente'],
        default    => ['zinc',    ucfirst($status ?: 'Desconocido')],
    };
@endphp

<flux:badge color="{{ $color }}" size="sm" {{ $attributes }}>{{ $label }}</flux:badge>
