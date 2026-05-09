@props(['tipo'])

@php
$config = match($tipo) {
    'movimiento_no_autorizado' => ['bg' => 'bg-error',   'text' => 'text-white',        'label' => 'Mov. No Autorizado'],
    'extraccion_prolongada'    => ['bg' => 'bg-warning',  'text' => 'text-text-primary', 'label' => 'Extracción Prolongada'],
    'incongruencia_taxonomica' => ['bg' => 'bg-info',    'text' => 'text-white',        'label' => 'Incongruencia Taxonómica'],
    'familia_no_asignada'      => ['bg' => 'bg-info',    'text' => 'text-white',        'label' => 'Familia No Asignada'],
    default                    => ['bg' => 'bg-border',   'text' => 'text-text-primary', 'label' => $tipo],
};
@endphp

<span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold leading-4 whitespace-nowrap {{ $config['bg'] }} {{ $config['text'] }}">
    {{ $config['label'] }}
</span>
