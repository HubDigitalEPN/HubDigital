@props(['paso' => 1])

@php
    $pasos = [
        1 => ['label' => 'Selección', 'icon' => 'check-circle'],
        2 => ['label' => 'Configuración', 'icon' => 'adjustments-horizontal'],
        3 => ['label' => 'Resultado', 'icon' => 'cloud-arrow-up'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-0']) }}>
    @foreach($pasos as $num => $info)
        @php
            $estado = $num < $paso ? 'done' : ($num === $paso ? 'active' : 'pending');
        @endphp

        <div class="flex items-center gap-2">
            <div @class([
                'flex items-center justify-center size-8 rounded-full text-sm font-semibold transition-colors',
                'bg-bio-green text-white' => $estado === 'done',
                'bg-science-blue text-white' => $estado === 'active',
                'bg-border text-text-secondary' => $estado === 'pending',
            ])>
                @if($estado === 'done')
                    <flux:icon name="check" class="size-4" />
                @else
                    {{ $num }}
                @endif
            </div>
            <span @class([
                'text-sm',
                'text-bio-green font-medium' => $estado === 'done',
                'text-science-blue font-semibold' => $estado === 'active',
                'text-text-secondary' => $estado === 'pending',
            ])>{{ $info['label'] }}</span>
        </div>

        @if(!$loop->last)
            <div @class([
                'flex-1 h-px mx-3 min-w-8',
                'bg-bio-green' => $paso > $num,
                'bg-border' => $paso <= $num,
            ])></div>
        @endif
    @endforeach
</div>
