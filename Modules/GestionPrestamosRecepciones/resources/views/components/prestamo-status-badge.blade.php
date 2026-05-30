@props(['estado'])

@php
$config = [
    'en_transito'                        => ['classes' => 'bg-[#FFF8E1] text-[#F57F17] border border-[#FFE082]', 'label' => 'Despachado'],
    'pendiente_aprobacion_verificacion'  => ['classes' => 'bg-[#E3F2FD] text-[#1565C0] border border-[#90CAF9]', 'label' => 'Pte. verificación'],
    'activo'                  => ['classes' => 'bg-[#E8F5E9] text-[#2E7D32] border border-[#A5D6A7]', 'label' => 'Activo'],
    'prorroga_solicitada'     => ['classes' => 'bg-[#FFF3E0] text-[#E65100] border border-[#FFCC80]', 'label' => 'Prórroga solicitada'],
    'vencido'                 => ['classes' => 'bg-[#FFEBEE] text-[#C62828] border border-[#EF9A9A]', 'label' => 'Vencido'],
    'en_revision'             => ['classes' => 'bg-[#E3F2FD] text-[#1565C0] border border-[#90CAF9]', 'label' => 'En revisión'],
    'cerrado'                 => ['classes' => 'bg-[#F5F5F5] text-[#616161] border border-[#BDBDBD]', 'label' => 'Cerrado'],
    'cerrado_con_observacion' => ['classes' => 'bg-[#FFF8E1] text-[#F57F17] border border-[#FFE082]', 'label' => 'Cerrado con observación'],
];

$value = $estado instanceof \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo
    ? $estado->value
    : (string) $estado;

$c = $config[$value] ?? ['classes' => 'bg-[#F5F5F5] text-[#616161] border border-[#BDBDBD]', 'label' => $value];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[0.68rem] font-semibold {{ $c['classes'] }}">
    {{ $c['label'] }}
</span>
