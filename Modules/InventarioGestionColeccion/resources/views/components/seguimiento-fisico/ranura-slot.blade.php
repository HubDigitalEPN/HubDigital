@props(['ranura', 'caja' => null])

@php
$estado = $caja['estado'] ?? null;

$tooltip = $caja
    ? $caja['codigo'] . ' · ' . str_replace('_', ' ', ucfirst($estado))
    : 'Ranura ' . $ranura['numeroRanura'] . ' — vacía';

[$bg, $border, $text] = match($estado) {
    'en_gabinete'             => ['bg-success',  'border-success',  'text-white'],
    'en_transito'             => ['bg-warning',  'border-warning',  'text-text-primary'],
    'extraccion_prolongada'   => ['bg-warning',  'border-warning',  'text-text-primary'],
    'ubicacion_incorrecta'    => ['bg-error',    'border-error',    'text-white'],
    'pendiente_clasificacion' => ['bg-info',     'border-info',     'text-white'],
    'extraviada'              => ['bg-error',    'border-error',    'text-white'],
    default                   => ['bg-bg-main',  'border-border',   'text-text-secondary'],
};
@endphp

<flux:tooltip :content="$tooltip">
    <div @class([
        'flex items-center justify-center rounded border aspect-square min-w-0 cursor-default transition-colors overflow-hidden p-0.5',
        $bg, $border, $text,
        'border-dashed' => $estado === null,
        'animate-pulse' => $estado === 'extraviada',
    ])>
        @if($caja !== null)
            <span class="text-[9px] font-bold leading-none text-center w-full truncate px-0.5">
                {{ $caja['codigo'] }}
            </span>
        @else
            <span class="text-[10px] font-medium">{{ $ranura['numeroRanura'] }}</span>
        @endif
    </div>
</flux:tooltip>
