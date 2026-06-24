@props([
    'diasRestantes',
    'vencido' => false,
    'fechaFin' => null,
])

@php
    // Escala de color según urgencia — pura presentación, deriva solo de los días.
    // Cuanto más cerca el vencimiento, más alarmante el color.
    $clases = match (true) {
        $vencido            => 'bg-error/10 border-error/30 text-error',
        $diasRestantes <= 7 => 'bg-error/10 border-error/30 text-error',
        $diasRestantes <= 30 => 'bg-warning/10 border-warning/40 text-warning',
        $diasRestantes <= 90 => 'bg-science-blue/10 border-science-blue/30 text-science-blue',
        default             => 'bg-bio-green/10 border-bio-green/30 text-bio-green',
    };
@endphp

<div class="mb-5 flex items-center gap-4 rounded-lg border p-4 {{ $clases }}">
    <flux:icon name="{{ $vencido ? 'exclamation-triangle' : 'clock' }}" class="size-8 shrink-0" />
    <div class="min-w-0">
        <p class="text-xs font-semibold uppercase tracking-wide opacity-80">
            {{ $vencido ? 'Préstamo vencido' : 'Plazo de devolución' }}
        </p>
        <p class="text-2xl font-bold leading-tight">
            @if($vencido)
                Vencido hace {{ $diasRestantes }} {{ $diasRestantes === 1 ? 'día' : 'días' }}
            @else
                {{ $diasRestantes }} {{ $diasRestantes === 1 ? 'día restante' : 'días restantes' }}
            @endif
        </p>
        @if($fechaFin)
            <p class="text-xs opacity-80 mt-0.5">
                {{ $vencido ? 'Venció el' : 'Vence el' }} {{ $fechaFin->format('d/m/Y') }}
            </p>
        @endif
    </div>
</div>
