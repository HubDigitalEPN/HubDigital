@props([
    'campo',
    'presente' => true,
    'requerido' => false,
])

@php
    if ($presente && $requerido) {
        $clases = 'border-success/40 bg-success/5 text-success';
        $icono = 'check';
        $etiqueta = 'Presente';
    } elseif (!$presente && $requerido) {
        $clases = 'border-error/40 bg-error/5 text-error';
        $icono = 'exclamation-triangle';
        $etiqueta = 'Faltante · requerido';
    } else {
        $clases = 'border-border bg-bg-main text-text-secondary';
        $icono = 'check';
        $etiqueta = null;
    }
@endphp

<div {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border text-xs {$clases}"]) }}>
    <flux:icon :name="$icono" class="size-3.5" />
    <span class="font-mono text-xs">{{ $campo }}</span>
    @if($etiqueta)
        <span class="text-[10px] font-semibold uppercase tracking-wide ml-0.5 px-1.5 py-0.5 rounded {{ $presente ? 'bg-success/15' : 'bg-error/15' }}">
            {{ $etiqueta }}
        </span>
    @endif
</div>
