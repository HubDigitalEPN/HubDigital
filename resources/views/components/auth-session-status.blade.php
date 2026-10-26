@props([
    'status',
])

@if ($status)
    <div data-hub-feedback="success" {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
        {{ $status }}
    </div>
@endif
