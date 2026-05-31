<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-semibold">
                Orden de familias
            </flux:heading>
            <p class="text-sm text-text-secondary">
                Define qué familia se espera primero a lo largo de la colección. Dentro de cada familia, las
                cajas se ordenan alfabéticamente por subfamilia → género → especie. Esta secuencia es la
                referencia para las alertas de orden taxonómico.
            </p>
        </div>
        <flux:button
            variant="primary"
            icon="check"
            class="w-full sm:w-auto"
            wire:click="guardar"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="guardar">Guardar orden</span>
            <span wire:loading wire:target="guardar">Guardando…</span>
        </flux:button>
    </div>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" icon="exclamation-triangle" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    @if(count($familias) === 0)
        <div class="rounded-lg border border-dashed border-border bg-surface p-8 text-center text-text-secondary">
            <flux:icon name="rectangle-stack" class="mx-auto size-8 text-text-secondary" />
            <p class="mt-2 text-sm">Aún no hay familias clasificadas en la colección.</p>
        </div>
    @else
        <ol class="space-y-3">
            @foreach($familias as $index => $item)
                <li
                    wire:key="familia-{{ $item['familia'] }}"
                    class="rounded-lg border border-border bg-surface p-4 shadow-sm"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-navy text-sm font-semibold text-white">
                                {{ $index + 1 }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-serif text-lg font-semibold text-text-primary italic">
                                        {{ $item['familia'] }}
                                    </span>
                                    @unless($item['presente'])
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-warning text-text-primary">
                                            Sin cajas
                                        </span>
                                    @endunless
                                    @unless($item['enSecuencia'])
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-info text-white">
                                            Nueva
                                        </span>
                                    @endunless
                                </div>
                                @if(count($item['subfamilias']) > 0)
                                    <p class="mt-1 text-sm text-text-secondary">
                                        Subfamilias: {{ implode(' · ', $item['subfamilias']) }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 self-end sm:self-auto">
                            <flux:button
                                type="button"
                                variant="ghost"
                                icon="chevron-up"
                                wire:click="subir({{ $index }})"
                                :disabled="$loop->first"
                                aria-label="Subir {{ $item['familia'] }}"
                            />
                            <flux:button
                                type="button"
                                variant="ghost"
                                icon="chevron-down"
                                wire:click="bajar({{ $index }})"
                                :disabled="$loop->last"
                                aria-label="Bajar {{ $item['familia'] }}"
                            />
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
