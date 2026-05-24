@props([
    'nombrePerfil',
    'nombreEnDocumento' => null,
    'resultado' => null,
])

@php
$colorClasses = match($resultado) {
    'Conforme' => 'border-success bg-success/5',
    'Discrepancia (Tipográfica)' => 'border-warning bg-warning/5',
    'Discrepancia (Tercero)' => 'border-error bg-error/5',
    default => 'border-border bg-bg-main',
};
@endphp

<div class="rounded-lg border-2 p-4 {{ $colorClasses }} flex flex-col items-center gap-3 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:items-center sm:gap-4">
    {{-- Left: Perfil del sistema --}}
    <div class="space-y-0.5 text-center sm:text-left">
        <p class="text-[10px] font-semibold tracking-wider text-text-secondary">Perfil del sistema</p>
        <p class="text-base font-semibold font-serif text-text-primary leading-snug">{{ $nombrePerfil }}</p>
        <p class="text-xs text-text-secondary">Nombre registrado</p>
    </div>

    {{-- Center: vs --}}
    <div class="size-9 rounded-full bg-surface border border-border flex items-center justify-center text-xs font-bold text-text-secondary shadow-sm">
        vs
    </div>

    {{-- Right: Nombre en documento --}}
    <div class="space-y-0.5 text-center sm:text-right">
        <p class="text-[10px] font-semibold tracking-wider text-text-secondary">Nombre en el formato</p>
        <p class="text-base font-semibold font-serif leading-snug
            {{ $nombreEnDocumento ? 'text-text-primary' : 'text-text-secondary italic' }}"
        >
            {{ $nombreEnDocumento ?? 'Por ingresar' }}
        </p>
        <p class="text-xs text-text-secondary">Del documento cargado</p>
    </div>
</div>

@if($resultado)
    <div class="flex items-center gap-2 mt-2">
        @if($resultado === 'Conforme')
            <flux:icon name="check-circle" class="size-4 text-success shrink-0" />
            <span class="text-sm font-medium text-success">Identidad conforme — puede continuar el trámite</span>
        @elseif($resultado === 'Discrepancia (Tipográfica)')
            <flux:icon name="exclamation-triangle" class="size-4 text-warning shrink-0" />
            <span class="text-sm font-medium text-warning">Discrepancia tipográfica — corrige el nombre en tu perfil</span>
        @elseif($resultado === 'Discrepancia (Tercero)')
            <flux:icon name="x-circle" class="size-4 text-error shrink-0" />
            <span class="text-sm font-medium text-error">Discrepancia por tercero — adjunta carta de delegación</span>
        @endif
    </div>
@endif
