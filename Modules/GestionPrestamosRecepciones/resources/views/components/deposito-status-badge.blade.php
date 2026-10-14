@props(['estado'])

@php
$config = [
    'En Borrador' => ['color' => 'blue', 'label' => 'En borrador'],
    'Rechazada' => ['color' => 'red', 'label' => 'Rechazada'],
    'Pausada para Asesoría' => ['color' => 'orange', 'label' => 'Pausada'],
    'Pendiente de Revisión por Curaduría' => ['color' => 'blue', 'label' => 'Pendiente revisión'],
    'Pendiente de Revisión Documental Previa' => ['color' => 'amber', 'label' => 'Revisión documental previa'],
    'Aprobada Documentalmente' => ['color' => 'green', 'label' => 'Aprobada documentalmente'],
    'Requiere Corrección' => ['color' => 'amber', 'label' => 'Requiere corrección'],
    'Rechazo Permanente' => ['color' => 'red', 'label' => 'Rechazo permanente'],
];

$value = $estado instanceof \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito
    ? $estado->value
    : (string) $estado;

$c = $config[$value] ?? ['color' => 'zinc', 'label' => $value];
@endphp

<flux:badge :color="$c['color']" size="sm" :class="$value === 'En Borrador' ? 'hub-draft-status' : ''">{{ $c['label'] }}</flux:badge>
