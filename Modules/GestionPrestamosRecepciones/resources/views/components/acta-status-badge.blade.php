@props(['estado'])

@php
$config = [
    'pendiente_envio'      => ['classes' => 'bg-[#F5F5F5] text-[#616161] border border-[#BDBDBD]', 'label' => 'Pendiente de Envío'],
    'pendiente_firma'      => ['classes' => 'bg-[#FFF3E0] text-[#E65100] border border-[#FFCC80]', 'label' => 'Pendiente de Firma'],
    'pendiente_validacion' => ['classes' => 'bg-[#E3F2FD] text-[#1565C0] border border-[#90CAF9]', 'label' => 'Pendiente de Validación'],
    'validada'             => ['classes' => 'bg-[#E8F5E9] text-[#2E7D32] border border-[#A5D6A7]', 'label' => 'Validada'],
    'rechazada'            => ['classes' => 'bg-[#FFEBEE] text-[#C62828] border border-[#EF9A9A]', 'label' => 'Rechazada'],
];

$c = $config[(string) $estado] ?? ['classes' => 'bg-[#F5F5F5] text-[#616161] border border-[#BDBDBD]', 'label' => (string) $estado];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[0.68rem] font-semibold {{ $c['classes'] }}">
    {{ $c['label'] }}
</span>
