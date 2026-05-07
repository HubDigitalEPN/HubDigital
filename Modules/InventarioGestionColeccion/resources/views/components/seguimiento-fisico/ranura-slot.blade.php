@props(['ranura', 'caja' => null])

<div
    @class([
        'relative flex flex-col items-center justify-center rounded-lg border p-1.5 h-14 transition-colors',
        'border-dashed border-border bg-bg-main text-text-secondary' => $caja === null,
        'border-border bg-surface'                                   => $caja !== null,
    ])
>
    @if($caja !== null)
        <flux:tooltip :content="$caja['codigo']">
            <x-inventariogestioncoleccion::seguimiento-fisico.caja-estado-badge :estado="$caja['estado']" />
        </flux:tooltip>
    @else
        <span class="text-xs">{{ $ranura['numeroRanura'] }}</span>
    @endif
</div>
