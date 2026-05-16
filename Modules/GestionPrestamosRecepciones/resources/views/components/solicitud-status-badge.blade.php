@props(['estado'])

@php
$config = [
    'borrador'  => ['classes' => 'bg-[#F5F5F5] text-[#616161] border border-[#BDBDBD]', 'label' => 'Borrador'],
    'enviada'   => ['classes' => 'bg-[#E3F2FD] text-[#1565C0] border border-[#90CAF9]', 'label' => 'Enviada'],
    'observada' => ['classes' => 'bg-[#FFF3E0] text-[#E65100] border border-[#FFCC80]', 'label' => 'Observada'],
    'aprobada'  => ['classes' => 'bg-[#E8F5E9] text-[#2E7D32] border border-[#A5D6A7]', 'label' => 'Aprobada'],
    'rechazada' => ['classes' => 'bg-[#FFEBEE] text-[#C62828] border border-[#EF9A9A]', 'label' => 'Rechazada'],
];

$value = $estado instanceof \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud
    ? $estado->value
    : (string) $estado;

$c = $config[$value] ?? ['classes' => 'bg-[#F5F5F5] text-[#616161] border border-[#BDBDBD]', 'label' => $value];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[0.68rem] font-semibold {{ $c['classes'] }}">
    {{ $c['label'] }}
</span>
