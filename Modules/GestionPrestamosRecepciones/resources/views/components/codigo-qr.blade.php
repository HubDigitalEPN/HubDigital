@props([
    'codigo',
    'tamanio' => 180,
])

@php
    $svgQr = \Modules\GestionPrestamosRecepciones\Infrastructure\Services\GeneradorQrSvg::svg($codigo, (int) $tamanio);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3']) }}>
    <div class="rounded-lg border border-border bg-surface p-3 shadow-sm">
        <div class="h-[180px] w-[180px]" style="width: {{ $tamanio }}px; height: {{ $tamanio }}px;">
            {!! $svgQr !!}
        </div>
    </div>
    <p class="font-mono text-sm font-semibold tracking-wide text-text-primary">{{ $codigo }}</p>
</div>
