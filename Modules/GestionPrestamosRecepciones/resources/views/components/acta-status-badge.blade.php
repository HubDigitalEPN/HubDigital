@props(['estado'])

@php
$config = [
    'pendiente_envio'       => ['color' => 'zinc',   'label' => 'Pendiente de Envío'],
    'pendiente_firma'       => ['color' => 'blue',   'label' => 'Pendiente de Firma'],
    'pendiente_validacion'  => ['color' => 'orange', 'label' => 'Pendiente de Validación'],
    'validada'              => ['color' => 'green',  'label' => 'Validada'],
    'rechazada'             => ['color' => 'red',    'label' => 'Rechazada'],
];

$c = $config[(string) $estado] ?? ['color' => 'zinc', 'label' => (string) $estado];
@endphp

<flux:badge :color="$c['color']" size="sm">{{ $c['label'] }}</flux:badge>
