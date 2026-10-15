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
            @if($ayuda)
                <div
                    x-data="{
                        infoAbierta: false,
                        x: 0,
                        y: 0,
                        abrir() {
                            const r = this.$el.getBoundingClientRect();
                            this.x = Math.min(r.left, window.innerWidth - 272);
                            this.y = r.bottom + 6;
                            this.infoAbierta = true;
                        }
                    }"
                    x-on:mouseenter="abrir()"
                    x-on:mouseleave="infoAbierta = false"
                    class="relative mt-0.5 shrink-0"
                >
                    <span
                        x-on:click.stop="infoAbierta ? infoAbierta = false : abrir()"
                        :class="infoAbierta ? 'text-science-blue' : 'text-text-secondary'"
                        class="-m-1.5 flex cursor-help p-1.5 transition-colors duration-200"
                        aria-label="Más información sobre este campo"
                    >
                        <flux:icon name="information-circle" class="size-3" />
                    </span>

                    <template x-teleport="body">
                        <div
                            x-show="infoAbierta"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.97]"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.97]"
                            :style="'position:fixed;left:'+x+'px;top:'+y+'px;z-index:9999'"
                            class="w-64 max-w-[calc(100dvi-2rem)] origin-top-left overflow-hidden rounded-lg bg-surface shadow-lg ring-1 ring-science-blue/30 sm:w-72"
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
                    </template>
                </div>
            @endif
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
