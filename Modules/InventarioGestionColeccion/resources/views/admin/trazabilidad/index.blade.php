<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">
                Trazabilidad de movimientos
            </flux:heading>
            <p class="text-xs text-text-secondary">Busca un espécimen y consulta su historial cronológico de movimientos, incluidos los de su unit tray y caja.</p>
        </div>
        @if($consultado)
            <flux:button variant="ghost" icon="arrow-path" wire:click="limpiar" class="w-full min-h-[44px] sm:w-auto">
                Nueva búsqueda
            </flux:button>
        @endif
    </div>

    {{-- Buscador acotado: el catálogo (48k+) nunca se carga de golpe. --}}
    @unless($consultado)
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
            <flux:field>
                <flux:label>Espécimen</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="busqueda"
                    icon="magnifying-glass"
                    placeholder="Código de catálogo o nombre científico"
                />
            </flux:field>

            @if($candidatos !== [])
                <div class="divide-y divide-border rounded-lg border border-border">
                    @foreach($candidatos as $c)
                        <button
                            type="button"
                            wire:key="cand-{{ $c['id'] }}"
                            wire:click="seleccionarEspecimen('{{ $c['id'] }}')"
                            class="flex w-full min-h-[44px] flex-col items-start gap-0.5 px-4 py-3 text-left hover:bg-bg-main"
                        >
                            <span class="font-medium text-text-primary">{{ $c['codigoCatalogo'] }}</span>
                            <span class="font-serif italic text-sm text-text-secondary">{{ $c['taxonNombre'] }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif(trim($busqueda) !== '')
                <p class="text-sm text-text-secondary">No se encontraron especímenes para «{{ $busqueda }}».</p>
            @endif
        </div>
    @endunless

    {{-- Línea de tiempo del espécimen seleccionado. --}}
    @if($consultado)
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4 sm:p-6 space-y-4">
            <flux:heading size="lg" class="text-blue-navy">{{ $especimenSeleccionadoLabel }}</flux:heading>

            @if($movimientos === [])
                <p class="text-sm text-text-secondary">Este espécimen no registra movimientos.</p>
            @else
                <ol class="relative space-y-6 border-l border-border pl-6">
                    @foreach($movimientos as $m)
                        <li wire:key="mov-{{ $loop->index }}" class="relative">
                            <span class="absolute -left-[31px] top-1 flex size-3 items-center justify-center rounded-full bg-science-blue ring-4 ring-surface"></span>
                            <div class="flex flex-col gap-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                    <span class="font-medium text-text-primary">{{ $m['tipo'] }}</span>
                                    <time class="text-xs text-text-secondary">{{ $m['fecha'] }}</time>
                                </div>
                                @if($m['origen'] || $m['destino'])
                                    <p class="text-sm text-text-secondary">
                                        @if($m['origen'])<span>Desde {{ $m['origen'] }}</span>@endif
                                        @if($m['origen'] && $m['destino']) <flux:icon name="arrow-right" class="inline size-3.5 align-middle" /> @endif
                                        @if($m['destino'])<span>Hacia {{ $m['destino'] }}</span>@endif
                                    </p>
                                @endif
                                <p class="text-xs text-text-secondary">Responsable: {{ $m['responsable'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endif
</div>
