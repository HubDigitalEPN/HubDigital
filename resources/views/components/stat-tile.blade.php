{{-- Tarjeta de métrica del dashboard: un ícono con acento, la cifra protagonista,
     su etiqueta legible y una pista opcional. Si recibe href, toda la tarjeta es un
     enlace interno (wire:navigate). El color se elige por "tone", no por hex. --}}
@props([
    'label',
    'value',
    'icon' => 'chart-bar',
    'tone' => 'blue',
    'href' => null,
    'hint' => null,
])

@php
    // tone → [fondo del chip, color del ícono]. Solo tokens del sistema.
    [$chipBg, $chipInk] = match ($tone) {
        'green'   => ['bg-bio-green/10', 'text-bio-green'],
        'warning' => ['bg-warning/10', 'text-warning'],
        'error'   => ['bg-error/10', 'text-error'],
        'navy'    => ['bg-blue-navy/10', 'text-blue-navy'],
        default   => ['bg-science-blue/10', 'text-science-blue'],
    };
    $tag = $href ? 'a' : 'div';
    $mostrado = is_numeric($value) ? number_format((int) $value, 0, ',', '.') : $value;
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class([
        'flex items-start gap-3 rounded-lg border border-border bg-surface p-4 shadow-sm',
        'transition hover:border-science-blue/40 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-science-blue/40' => (bool) $href,
    ]) }}>
    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $chipBg }}">
        <flux:icon :name="$icon" variant="outline" class="size-5 {{ $chipInk }}" />
    </span>
    <div class="flex min-w-0 flex-col">
        <span class="text-2xl font-semibold leading-tight text-blue-navy tabular-nums">{{ $mostrado }}</span>
        <span class="text-sm text-text-secondary">{{ $label }}</span>
        @if($hint)
            <span class="mt-0.5 text-xs text-text-secondary">{{ $hint }}</span>
        @endif
    </div>
</{{ $tag }}>
