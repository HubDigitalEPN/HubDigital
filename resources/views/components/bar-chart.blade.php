{{-- Gráfico de barras horizontales para el dashboard. Recibe filas con la forma
     ['etiqueta' => string, 'valor' => int] y dibuja cada barra proporcional al
     valor máximo. Sin dependencias externas: solo tokens y anchos en porcentaje. --}}
@props([
    'titulo',
    'subtitulo' => null,
    'filas' => [],
])

@php
    $filas = collect($filas);
    $max = (int) ($filas->max('valor') ?: 0);
@endphp

<div class="flex flex-col rounded-lg border border-border bg-surface p-5 shadow-sm">
    <h3 class="font-display text-base font-semibold text-blue-navy">{{ $titulo }}</h3>
    @if($subtitulo)
        <p class="mt-0.5 text-xs text-text-secondary">{{ $subtitulo }}</p>
    @endif

    <div class="mt-4 flex flex-col gap-2.5">
        @forelse($filas as $fila)
            @php
                $valor = (int) ($fila['valor'] ?? 0);
                // Ancho proporcional. Se da un mínimo visible a los valores > 0 para
                // que una barra pequeña no desaparezca; el 0 queda en blanco.
                $pct = $max > 0 && $valor > 0 ? max(2, (int) round($valor / $max * 100)) : 0;
            @endphp
            <div class="flex items-center gap-3">
                <span class="w-28 shrink-0 truncate text-xs text-text-primary" title="{{ $fila['etiqueta'] ?? '' }}">
                    {{ $fila['etiqueta'] ?? '' }}
                </span>
                <div class="relative h-5 flex-1 overflow-hidden rounded bg-bg-main">
                    <div class="absolute inset-y-0 left-0 rounded bg-chart-serie" style="width: {{ $pct }}%"></div>
                </div>
                <span class="w-12 shrink-0 text-right text-xs font-medium tabular-nums text-text-secondary">
                    {{ number_format($valor, 0, ',', '.') }}
                </span>
            </div>
        @empty
            <p class="text-sm text-text-secondary">Sin datos para mostrar.</p>
        @endforelse
    </div>
</div>
