@props(['estado'])

@php
$config = [
    'borrador'  => ['color' => 'zinc',   'label' => 'Borrador'],
    'enviada'   => ['color' => 'blue',   'label' => 'Enviada'],
    'observada' => ['color' => 'orange', 'label' => 'Observada'],
    'aprobada'  => ['color' => 'green',  'label' => 'Aprobada'],
    'rechazada' => ['color' => 'red',    'label' => 'Rechazada'],
];

$value = $estado instanceof \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud
    ? $estado->value
    : (string) $estado;

$c = $config[$value] ?? ['color' => 'zinc', 'label' => $value];
@endphp

<flux:badge :color="$c['color']" size="sm">{{ $c['label'] }}</flux:badge>
