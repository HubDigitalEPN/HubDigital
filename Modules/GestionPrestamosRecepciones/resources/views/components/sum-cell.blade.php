@props([
    'campo',
    'valor' => null,
    'fuente' => null,
    'faltante' => false,
    'manual' => false,
    'ayuda' => null,
])

<div class="rounded-lg border p-3 relative
    {{ $faltante ? 'border-error bg-error/5' : ($manual ? 'border-warning/50 bg-warning/5' : 'border-border bg-surface') }}"
>
    {{-- Pulse dot for missing --}}
    @if($faltante)
        <span class="absolute top-2.5 right-2.5 size-2 rounded-full bg-error animate-pulse"></span>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-1.5 pr-4">
        <div class="flex items-center gap-1">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-text-secondary">{{ $campo }}</span>
            @if($ayuda)
                <div
                    x-data="{ infoAbierta: false }"
                    x-on:mouseenter="infoAbierta = true"
                    x-on:mouseleave="infoAbierta = false"
                    x-on:click.outside="infoAbierta = false"
                    class="relative shrink-0"
                >
                    <span
                        x-on:click.stop="infoAbierta = !infoAbierta"
                        :class="infoAbierta ? 'text-science-blue' : 'text-text-secondary'"
                        class="-m-1.5 flex cursor-help p-1.5 transition-colors duration-200"
                        aria-label="Más información sobre este campo"
                    >
                        <flux:icon name="information-circle" class="size-3" />
                    </span>

                    <div
                        x-show="infoAbierta"
                        x-cloak
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.97]"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.97]"
                        class="absolute left-0 top-full z-30 mt-1.5 w-64 max-w-[calc(100vw-3rem)] origin-top-left overflow-hidden rounded-lg bg-surface bg-gradient-to-br from-science-blue/10 via-surface to-bio-green/5 shadow-lg ring-1 ring-science-blue/20 sm:w-72"
                    >
                        <div class="flex gap-2.5 p-3">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-science-blue/15 ring-1 ring-science-blue/20">
                                <flux:icon name="light-bulb" class="size-4 text-science-blue" />
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-science-blue">¿Qué es este campo?</p>
                                <p class="text-xs text-text-secondary leading-relaxed">{{ $ayuda }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @if($faltante)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-error/15 text-error">
                Faltante
            </span>
        @elseif($manual && $valor !== null)
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-warning/20 text-warning">
                <flux:icon name="pencil-square" class="size-2.5" />
                Ingresado manualmente
            </span>
        @elseif(!$faltante && $valor !== null)
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-success/15 text-success">
                <flux:icon name="check" class="size-2.5" />
                Extraído
            </span>
        @endif
    </div>

    {{-- Valor siempre visible --}}
    <p class="text-sm font-medium
        {{ $faltante ? 'text-error italic' : 'text-text-primary' }}">
        {{ $faltante ? 'Por completar manualmente' : ($valor ?? '—') }}
    </p>

    @if($fuente && !$faltante && $valor !== null && !$manual)
        <p class="text-[10px] text-text-secondary mt-1 flex items-center gap-1">
            <flux:icon name="document-text" class="size-3 shrink-0" />
            {{ $fuente }}
        </p>
    @endif

    {{-- Slot para formulario de edición o botón de captura manual --}}
    {{ $slot }}
</div>
