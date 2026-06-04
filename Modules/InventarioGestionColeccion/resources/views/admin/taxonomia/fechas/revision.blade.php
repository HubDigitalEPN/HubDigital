<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Parseo de fechas</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                <code class="text-xs">fecha_verbatim</code> que el importador no pudo parsear. Asigna la fecha canónica
                (<code class="text-xs">YYYY-MM-DD</code>) o un rango de inicio/fin si aplica.
            </p>
        </div>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-medium text-text-primary">
            {{ $total }} verbatim(s) distinto(s) pendiente(s)
        </span>
        @if($total > 0)
            <span class="text-xs text-text-secondary">
                Mostrando {{ $inicio }}–{{ $fin }} (página {{ $pagina }} de {{ $totalPaginas }})
            </span>
        @endif
    </div>

    @forelse($items as $idx => $item)
        <div class="rounded-lg border border-border bg-surface shadow-sm border-l-4 {{ $item['sugerenciaInicio'] ? 'border-l-info' : 'border-l-warning' }} overflow-hidden">
            <div class="px-5 py-4 bg-bg-main border-b border-border flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs uppercase font-semibold text-text-secondary tracking-wide">fecha_verbatim</span>
                        <span class="inline-flex items-center rounded-full bg-blue-navy/10 text-blue-navy border border-blue-navy/30 px-2 py-0.5 text-xs font-semibold">
                            {{ $item['totalEspecimenes'] }} espécimen(es)
                        </span>
                        @if($item['sugerenciaInicio'])
                            <span class="inline-flex items-center rounded-full bg-info/10 text-info border border-info px-2 py-0.5 text-xs font-semibold">
                                Sugerencia del parser disponible
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-warning/10 text-warning border border-warning px-2 py-0.5 text-xs font-semibold">
                                Parser no logró interpretarla
                            </span>
                        @endif
                    </div>
                    <div class="font-mono text-text-primary text-base break-words">"{{ $item['verbatim'] }}"</div>
                </div>
            </div>

            <div class="p-4 space-y-3">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <flux:field>
                        <flux:label>Fecha de inicio (YYYY-MM-DD)</flux:label>
                        <flux:input type="date"
                                    wire:model="items.{{ $idx }}.fechaInicio"
                                    placeholder="YYYY-MM-DD" />
                        @if($item['sugerenciaInicio'])
                            <flux:description>
                                Sugerencia del parser: <code class="text-info">{{ $item['sugerenciaInicio'] }}</code>
                            </flux:description>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label>Fecha de fin (opcional, si es rango)</flux:label>
                        <flux:input type="date"
                                    wire:model="items.{{ $idx }}.fechaFin"
                                    placeholder="YYYY-MM-DD" />
                        @if($item['sugerenciaFin'])
                            <flux:description>
                                Sugerencia del parser: <code class="text-info">{{ $item['sugerenciaFin'] }}</code>
                            </flux:description>
                        @endif
                    </flux:field>
                </div>

                <div class="border-t border-border pt-3 flex flex-wrap justify-end gap-2">
                    <flux:button variant="primary" icon="check"
                                 wire:click="confirmar({{ $idx }})"
                                 wire:loading.attr="disabled"
                                 wire:target="confirmar">
                        Aplicar fecha a {{ $item['totalEspecimenes'] }} espécimen(es)
                    </flux:button>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-border bg-surface p-8 text-center">
            <flux:icon name="check-circle" class="mx-auto size-12 text-success mb-2" />
            <p class="text-text-primary font-medium">No quedan fechas verbatim sin asignar</p>
            <p class="text-sm text-text-secondary mt-1">Todos los especímenes con <code class="text-xs">fecha_verbatim</code> tienen una <code class="text-xs">fecha_colecta</code> parseada.</p>
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
