@props(['estado'])

@php
    $config = match($estado) {
        'en_gabinete'             => ['color' => 'green',  'label' => 'En Gabinete',          'pulse' => false],
        'en_transito'             => ['color' => 'yellow', 'label' => 'En Tránsito',           'pulse' => false],
        'extraccion_prolongada'   => ['color' => 'yellow', 'label' => 'Extracción Prolongada', 'pulse' => false],
        'pendiente_clasificacion' => ['color' => 'blue',   'label' => 'Pendiente',             'pulse' => false],
        'ubicacion_incorrecta'    => ['color' => 'red',    'label' => 'Ubic. Incorrecta',      'pulse' => false],
        'extraviada'              => ['color' => 'red',    'label' => 'Extraviada',            'pulse' => true],
        default                   => ['color' => 'zinc',   'label' => $estado,                 'pulse' => false],
    };
@endphp

<flux:badge
    :color="$config['color']"
    @class(['animate-pulse' => $config['pulse']])
>
    {{ $config['label'] }}
</flux:badge>
