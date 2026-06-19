@props([
    'estado',
    'verificacion' => null,
    'observacionLegacy' => null,
])

@php
    // Novedades por espécimen (datos nuevos) + nota general; con fallback al texto antiguo del evento.
    $observacionesEspecimen = $verificacion?->observaciones ?? [];
    $notaGeneral = $verificacion?->observacionGeneral ?? $observacionLegacy;
    $tieneContenido = count($observacionesEspecimen) > 0 || ! empty($notaGeneral);
@endphp

@if($estado === 'cerrado_con_observacion' && $tieneContenido)
    <div class="rounded-lg border border-warning/40 bg-warning/5 overflow-hidden">
        <div class="px-5 py-4 border-b border-warning/20 flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-warning/15 shrink-0">
                <flux:icon name="exclamation-triangle" class="size-3.5 text-warning" />
            </div>
            <flux:heading size="base" level="2" class="font-display text-warning">Préstamo cerrado con observación</flux:heading>
        </div>
        <div class="p-5 space-y-3">
            <p class="text-xs text-text-secondary">El curador detectó anomalías al verificar los especímenes devueltos:</p>

            @foreach($observacionesEspecimen as $obs)
                <div class="rounded-lg bg-warning/10 border border-warning/20 px-4 py-3">
                    <p class="text-xs text-text-secondary font-mono">{{ $obs->codigoEspecimen }}</p>
                    <p class="text-sm text-text-primary mt-0.5 leading-relaxed">{{ $obs->descripcion }}</p>
                </div>
            @endforeach

            @if(! empty($notaGeneral))
                <div class="rounded-lg bg-warning/10 border border-warning/20 px-4 py-3">
                    @if(count($observacionesEspecimen) > 0)
                        <p class="text-xs text-text-secondary uppercase tracking-wide">Nota general</p>
                    @endif
                    <p class="text-sm text-text-primary mt-0.5 leading-relaxed">{{ $notaGeneral }}</p>
                </div>
            @endif
        </div>
    </div>
@endif
