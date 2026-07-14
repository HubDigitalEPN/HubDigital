@props(['estado'])

@php
$config = [
    'En Verificación' => ['color' => 'blue', 'label' => 'En verificación'],
    'Verificado Físicamente' => ['color' => 'green', 'label' => 'Verificado físicamente'],
    'Recepción Suspendida' => ['color' => 'amber', 'label' => 'Recepción suspendida'],
    'Verificado con Observaciones' => ['color' => 'orange', 'label' => 'Verificado con observaciones'],
];

$value = $estado instanceof \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecepcionLote
    ? $estado->value
    : (string) $estado;

$c = $config[$value] ?? ['color' => 'zinc', 'label' => $value];
@endphp

<flux:badge :color="$c['color']" size="sm">{{ $c['label'] }}</flux:badge>
