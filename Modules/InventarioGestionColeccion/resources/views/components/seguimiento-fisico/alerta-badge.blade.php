@props(['tipo'])

@php
$config = match($tipo) {
    'movimiento_no_autorizado' => ['bg' => 'bg-error',   'text' => 'text-white',        'label' => 'Mov. no autorizado'],
    'extraccion_prolongada'    => ['bg' => 'bg-warning',  'text' => 'text-text-primary', 'label' => 'Extracción prolongada'],
    'orden_taxonomico_fuera_de_secuencia' => ['bg' => 'bg-info',    'text' => 'text-white',        'label' => 'Incongruencia taxonómica'],
    'familia_no_asignada'      => ['bg' => 'bg-info',    'text' => 'text-white',        'label' => 'Familia no asignada'],
    default                    => ['bg' => 'bg-border',   'text' => 'text-text-primary', 'label' => $tipo],
};
@endphp

<span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold leading-4 whitespace-nowrap {{ $config['bg'] }} {{ $config['text'] }}">
    {{ $config['label'] }}
</span>
