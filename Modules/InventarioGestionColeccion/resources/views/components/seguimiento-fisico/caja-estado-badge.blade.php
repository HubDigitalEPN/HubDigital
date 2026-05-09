@props(['estado'])

@php
$config = match($estado) {
    'en_gabinete'             => ['bg' => 'bg-success',  'text' => 'text-white',        'label' => 'En Gabinete',          'pulse' => false],
    'en_transito'             => ['bg' => 'bg-warning',  'text' => 'text-text-primary', 'label' => 'En Tránsito',           'pulse' => false],
    'extraccion_prolongada'   => ['bg' => 'bg-warning',  'text' => 'text-text-primary', 'label' => 'Extracción Prolongada', 'pulse' => false],
    'pendiente_clasificacion' => ['bg' => 'bg-info',     'text' => 'text-white',        'label' => 'Pendiente',             'pulse' => false],
    'ubicacion_incorrecta'    => ['bg' => 'bg-error',    'text' => 'text-white',        'label' => 'Ubic. Incorrecta',      'pulse' => false],
    'extraviada'              => ['bg' => 'bg-error',    'text' => 'text-white',        'label' => 'Extraviada',            'pulse' => true],
    default                   => ['bg' => 'bg-border',   'text' => 'text-text-primary', 'label' => $estado,                 'pulse' => false],
};
@endphp

<span @class([
    'inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold leading-4 whitespace-nowrap',
    $config['bg'],
    $config['text'],
    'animate-pulse' => $config['pulse'],
])>
    {{ $config['label'] }}
</span>
