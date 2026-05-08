@props([
    'campo',
    'valor' => null,
    'fuente' => null,
    'faltante' => false,
])

<div class="rounded-lg border p-3 relative
    {{ $faltante ? 'border-error bg-error/5' : 'border-border bg-surface' }}"
>
    {{-- Pulse dot for missing --}}
    @if($faltante)
        <span class="absolute top-2.5 right-2.5 size-2 rounded-full bg-error animate-pulse"></span>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-1.5 pr-4">
        <span class="text-[10px] font-semibold uppercase tracking-wider text-text-secondary">{{ $campo }}</span>
        @if(!$faltante && $valor !== null)
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-success/15 text-success">
                <flux:icon name="check" class="size-2.5" />
                Extraído
            </span>
        @elseif($faltante)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-error/15 text-error">
                Faltante
            </span>
        @endif
    </div>

    {{-- Value or edit slot --}}
    @if(isset($slot) && !$slot->isEmpty())
        {{ $slot }}
    @else
        <p class="text-sm font-medium font-serif
            {{ $faltante ? 'text-error italic' : 'text-text-primary' }}"
        >
            {{ $faltante ? 'Por completar manualmente' : ($valor ?? '—') }}
        </p>
        @if($fuente && !$faltante)
            <p class="text-[10px] text-text-secondary mt-1 flex items-center gap-1">
                <flux:icon name="document-text" class="size-3 shrink-0" />
                {{ $fuente }}
            </p>
        @endif
    @endif
</div>
