@props([
    'campo',
    'valor' => null,
    'fuente' => null,
    'faltante' => false,
    'manual' => false,
    'ayuda' => null,
])

<div class="rounded-lg border p-3 relative
    {{ $faltante ? 'border-error bg-white' : ($manual ? 'border-warning/50 bg-white' : 'border-border bg-surface') }}"
>
    {{-- Header --}}
    <div class="mb-1.5 flex min-h-4 items-center justify-between gap-1">
        <div class="flex items-start gap-1">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-text-secondary">{{ $campo }}{{ $campo === 'Localidad' ? ' (opcional)' : '' }}</span>
        </div>
        @if($campo === 'Localidad' && !filled($valor))
            <span class="text-[10px] text-text-secondary">Opcional</span>
        @elseif($faltante)
            <span class="absolute right-2 top-2 inline-flex items-center gap-1 rounded bg-error/15 px-1.5 py-0.5 text-[10px] font-semibold text-error">
                <span class="size-1.5 rounded-full bg-error" aria-hidden="true"></span>
                Faltante
            </span>
        @elseif($manual && $valor !== null)
            <span class="absolute right-2 top-2 inline-flex items-center gap-1 rounded bg-success/15 px-1.5 py-0.5 text-[10px] font-semibold text-success">
                <flux:icon name="check" class="size-2.5" />
                Verificado
            </span>
        @elseif(!$faltante && $valor !== null)
            <span class="absolute right-2 top-2 inline-flex items-center gap-1 rounded bg-success/15 px-1.5 py-0.5 text-[10px] font-semibold text-success">
                <flux:icon name="check" class="size-2.5" />
                Verificado
            </span>
        @endif
    </div>

    {{-- Valor siempre visible --}}
    <p class="text-sm font-medium
        {{ $faltante ? 'text-error italic' : 'text-text-primary' }}">
        {{ $campo === 'Localidad' && !filled($valor) ? 'No indicada' : ($faltante ? 'Falta ingresar' : ($valor ?? '—')) }}
    </p>

    {{-- Slot para formulario de edición o botón de captura manual --}}
    {{ $slot }}
</div>
