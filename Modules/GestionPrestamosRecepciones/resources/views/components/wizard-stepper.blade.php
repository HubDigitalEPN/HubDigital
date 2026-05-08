@props([
    'pasos' => [],
    'pasoActual' => 1,
    'pasosCompletados' => [],
])

<div class="flex items-center gap-1 p-1 rounded-lg bg-surface border border-border shadow-sm overflow-x-auto">
    @foreach($pasos as $index => $paso)
        @php
            $numero = $index + 1;
            $esCompletado = in_array($numero, $pasosCompletados);
            $esActual = $numero === $pasoActual;
            $esDeshabilitado = !$esCompletado && !$esActual;
        @endphp

        <div class="flex-1 flex items-center gap-2 px-3 py-2.5 rounded-md min-w-0
            {{ $esActual ? 'bg-blue-navy/5' : '' }}
            {{ $esCompletado ? 'cursor-default' : '' }}
        ">
            <div class="shrink-0 size-7 rounded-full flex items-center justify-center text-sm font-semibold
                {{ $esCompletado ? 'bg-bio-green text-white' : '' }}
                {{ $esActual && !$esCompletado ? 'bg-blue-navy text-white' : '' }}
                {{ $esDeshabilitado ? 'bg-bg-main text-text-secondary border border-border' : '' }}
            ">
                @if($esCompletado)
                    <flux:icon name="check" class="size-4" />
                @else
                    {{ $numero }}
                @endif
            </div>

            <div class="min-w-0 hidden sm:block">
                <p class="text-xs font-semibold truncate
                    {{ $esActual ? 'text-blue-navy' : ($esCompletado ? 'text-text-primary' : 'text-text-secondary') }}
                ">
                    {{ $paso['label'] }}
                </p>
                <p class="text-[10px] text-text-secondary truncate">{{ $paso['sub'] }}</p>
            </div>
        </div>

        @if($index < count($pasos) - 1)
            <div class="shrink-0 w-4 h-px bg-border"></div>
        @endif
    @endforeach
</div>
