@props(['tipo'])

@php
    $config = match($tipo) {
        'movimiento_no_autorizado' => ['color' => 'red',    'label' => 'Mov. No Autorizado'],
        'extraccion_prolongada'    => ['color' => 'yellow', 'label' => 'Extracción Prolongada'],
        'incongruencia_taxonomica' => ['color' => 'blue',   'label' => 'Incongruencia Taxonómica'],
        'familia_no_asignada'      => ['color' => 'blue',   'label' => 'Familia No Asignada'],
        default                    => ['color' => 'zinc',   'label' => $tipo],
    };
@endphp

<flux:badge :color="$config['color']">{{ $config['label'] }}</flux:badge>
