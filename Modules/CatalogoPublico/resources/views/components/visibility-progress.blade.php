@props(['visibles', 'total'])

@php
    $porcentaje = $total > 0 ? round(($visibles / $total) * 100) : 0;
    $colorBar = $porcentaje >= 80 ? 'bg-success' : ($porcentaje >= 40 ? 'bg-warning' : 'bg-error');
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="flex-1 h-1.5 bg-border rounded-full overflow-hidden">
        <div
            class="{{ $colorBar }} h-full rounded-full transition-all duration-200"
            style="width: {{ $porcentaje }}%"
        ></div>
    </div>
    <span class="text-xs text-text-secondary tabular-nums shrink-0">{{ $visibles }}/{{ $total }}</span>
</div>
