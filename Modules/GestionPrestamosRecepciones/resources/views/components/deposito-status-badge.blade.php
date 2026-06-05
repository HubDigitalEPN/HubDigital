@props(['estado'])

@php
$config = [
    'En Borrador' => ['color' => 'zinc', 'label' => 'En borrador'],
    'Rechazada' => ['color' => 'red', 'label' => 'Rechazada'],
    'Pausada para Asesoría' => ['color' => 'orange', 'label' => 'Pausada'],
    'Pendiente de Revisión por Curaduría' => ['color' => 'blue', 'label' => 'Pendiente revisión'],
];

$value = $estado instanceof \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito
    ? $estado->value
    : (string) $estado;

$c = $config[$value] ?? ['color' => 'zinc', 'label' => $value];
@endphp

<flux:badge :color="$c['color']" size="sm">{{ $c['label'] }}</flux:badge>
