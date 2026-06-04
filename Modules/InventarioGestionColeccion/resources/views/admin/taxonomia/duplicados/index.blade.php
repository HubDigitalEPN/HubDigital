<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Duplicados de <code class="text-base">catalog_number</code></flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Grupos de especímenes que comparten el mismo <code class="text-xs">catalog_number</code>. Decide si son eventos legítimos distintos o un error de catalogación.
            </p>
        </div>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-medium text-text-primary">
            {{ $total }} grupo(s) con duplicados (≥ {{ $minimoDuplicados }} especímenes)
        </span>
        @if($total > 0)
            <span class="text-xs text-text-secondary">
                Mostrando {{ $inicio }}–{{ $fin }} (página {{ $pagina }} de {{ $totalPaginas }})
            </span>
        @endif
    </div>

    @forelse($items as $idx => $item)
        <div class="rounded-lg border border-border bg-surface shadow-sm border-l-4 {{ $item['fechasDistintas'] ? 'border-l-info' : 'border-l-warning' }} overflow-hidden">
            {{-- Cabecera del grupo --}}
            <div class="px-5 py-4 bg-bg-main border-b border-border flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs uppercase font-semibold text-text-secondary tracking-wide">catalog_number</span>
                        <span class="inline-flex items-center rounded-full bg-blue-navy/10 text-blue-navy border border-blue-navy/30 px-2 py-0.5 text-xs font-semibold">
                            {{ $item['total'] }} espécimen(es)
                        </span>
                        @if($item['fechasDistintas'])
                            <span class="inline-flex items-center rounded-full bg-info/10 text-info border border-info px-2 py-0.5 text-xs font-semibold">
                                Fechas distintas — probable evento legítimo
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-warning/10 text-warning border border-warning px-2 py-0.5 text-xs font-semibold">
                                Misma fecha — probable error
                            </span>
                        @endif
                    </div>
                    <div class="font-mono text-text-primary text-base break-words">{{ $item['catalogNumber'] }}</div>
                </div>
            </div>

            {{-- Listado de especímenes del grupo --}}
            <div class="p-4 space-y-3">
                <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Especímenes en el grupo</div>
                <div class="grid gap-2">
                    @foreach($item['especimenes'] as $e)
                        @php
                            $clases = match ($e['estadoRevision'] ?? 'pendiente') {
                                'pendiente' => 'bg-warning/10 text-warning border-warning',
                                'confirmada' => 'bg-success/10 text-success border-success',
                                'descartada' => 'bg-error/10 text-error border-error',
                                default => 'bg-border/30 text-text-secondary border-border',
                            };
                        @endphp
                        <div class="rounded-lg border border-border bg-surface p-3 flex flex-wrap items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="font-mono text-text-primary text-sm break-all">{{ $e['codigoCatalogo'] }}</div>
                                <div class="text-xs text-text-secondary mt-1">
                                    Fecha: <span class="text-text-primary">{{ $e['fechaColecta'] ?: '—' }}</span>
                                    &nbsp;·&nbsp;
                                    Colector: <span class="text-text-primary">{{ $e['colector'] ?: '—' }}</span>
                                </div>
                            </div>
                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $clases }}">
                                {{ ucfirst($e['estadoRevision']) }}
                            </span>
                        </div>
                    @endforeach
                </div>

                {{-- Acciones --}}
                <div class="border-t border-border pt-3 space-y-3">
                    <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Decisión del curador</div>
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-stretch">
                        <flux:button
                            variant="primary"
                            icon="check-circle"
                            wire:click="marcarEventosDistintos({{ $idx }})"
                            wire:loading.attr="disabled"
                            wire:target="marcarEventosDistintos"
                            class="lg:w-1/3">
                            Confirmar: eventos distintos
                        </flux:button>

                        <div class="flex-1 flex flex-col gap-2 lg:flex-row">
                            <flux:input
                                wire:model="items.{{ $idx }}.motivoInput"
                                placeholder="Motivo del error (requerido)"
                                class="flex-1" />
                            <flux:button
                                variant="ghost"
                                icon="exclamation-triangle"
                                wire:click="marcarErrorCatalogacion({{ $idx }})"
                                wire:loading.attr="disabled"
                                wire:target="marcarErrorCatalogacion">
                                Marcar error de catalogación
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-border bg-surface p-8 text-center">
            <flux:icon name="check-circle" class="mx-auto size-12 text-success mb-2" />
            <p class="text-text-primary font-medium">No hay duplicados de catalog_number sin resolver</p>
            <p class="text-sm text-text-secondary mt-1">Todos los grupos fueron evaluados o no superan el mínimo de {{ $minimoDuplicados }}.</p>
        </div>
    @endforelse

    @if($total > 0)
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-text-secondary">Página {{ $pagina }} de {{ $totalPaginas }}</span>
            <div class="flex gap-2">
                <flux:button size="sm" variant="ghost" icon="chevron-left"
                             :disabled="$pagina <= 1" wire:click="paginaAnterior">Anterior</flux:button>
                <flux:button size="sm" variant="ghost" icon="chevron-right"
                             :disabled="$pagina >= $totalPaginas" wire:click="siguientePagina">Siguiente</flux:button>
            </div>
        </div>
    @endif
</div>
