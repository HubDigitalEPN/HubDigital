<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Revisión de taxa</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Verbatims taxonómicos del Excel sin enlazar a un taxón canónico. Útil para resolver typos (<code>"Acragas_sp.1"</code>) y morfoespecies.
            </p>
        </div>
        <flux:button icon="tag" variant="ghost" :href="route('inventario.taxonomia.taxones')" wire:navigate>
            Gestionar taxones
        </flux:button>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <x-inventariogestioncoleccion::bandeja-ayuda titulo="¿Cómo uso esta pantalla?" storage-key="ayuda-taxones-revision">
        <p>
            Cada tarjeta agrupa especímenes con el mismo <code>taxon_verbatim</code> (e.g. <code>"Acragas_sp.1"</code>)
            que el importador no pudo asociar a un taxón del catálogo.
        </p>
        <ol class="list-decimal pl-6 space-y-1 text-xs text-text-secondary">
            <li>El sistema sugiere candidatos por similitud + boost si el primer token coincide con un género conocido.</li>
            <li>Si el verbatim es claro → click en el candidato top → "Confirmar enlace".</li>
            <li>Si el verbatim es ambiguo (<code>"_"</code>, <code>"indet"</code>) registra una morfoespecie en
                <a href="{{ route('inventario.taxonomia.taxones') }}" wire:navigate class="text-info hover:underline">/taxones</a> y vuelve a esta pantalla.</li>
            <li>Si es irrecuperable, deja la tarjeta sin acción — esos especímenes no se exportan a GBIF.</li>
        </ol>
    </x-inventariogestioncoleccion::bandeja-ayuda>

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
        <div class="rounded-lg border border-border bg-surface shadow-sm border-l-4 border-l-warning overflow-hidden">
            <div class="px-5 py-4 bg-bg-main border-b border-border flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs uppercase font-semibold text-text-secondary tracking-wide">taxon_verbatim</span>
                        <span class="inline-flex items-center rounded-full bg-blue-navy/10 text-blue-navy border border-blue-navy/30 px-2 py-0.5 text-xs font-semibold">
                            {{ $item['totalEspecimenes'] }} espécimen(es)
                        </span>
                    </div>
                    <div class="font-serif italic text-text-primary text-base break-words">"{{ $item['verbatim'] }}"</div>
                </div>
                <flux:button variant="primary" icon="check"
                             wire:click="confirmar({{ $idx }})"
                             wire:loading.attr="disabled"
                             wire:target="confirmar">
                    Confirmar enlace
                </flux:button>
            </div>

            <div class="p-4 space-y-2">
                <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Candidatos canónicos (por similitud + boost por género)</div>
                @if(empty($item['candidatos']))
                    <p class="text-sm text-text-secondary italic py-2">
                        No hay taxones para sugerir. Registra el taxón en la pantalla de gestión y vuelve aquí.
                    </p>
                @else
                    <div class="grid gap-2">
                        @foreach($item['candidatos'] as $cand)
                            @php
                                $seleccionado = ($item['taxonSeleccionado'] ?? '') === $cand['taxonId'];
                                $puntaje = (float) $cand['puntajeSimilitud'];
                                $clasePuntaje = $puntaje >= 70 ? 'text-success' : ($puntaje >= 40 ? 'text-warning' : 'text-error');
                            @endphp
                            <button type="button"
                                    wire:click="seleccionarCandidato({{ $idx }}, '{{ $cand['taxonId'] }}')"
                                    @class([
                                        'w-full text-left rounded-lg border p-3 transition-colors flex items-center gap-3',
                                        'border-success bg-success/5' => $seleccionado,
                                        'border-border bg-surface hover:bg-bg-main' => !$seleccionado,
                                    ])>
                                <span @class([
                                    'shrink-0 inline-flex size-5 rounded-full items-center justify-center border-2',
                                    'border-success bg-success text-white' => $seleccionado,
                                    'border-border bg-surface' => !$seleccionado,
                                ])>
                                    @if($seleccionado)
                                        <flux:icon name="check" class="size-3" />
                                    @endif
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="font-serif italic text-text-primary truncate">{{ $cand['nombreCientifico'] }}</div>
                                    <div class="text-xs text-text-secondary">{{ ucfirst($cand['rango']) }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-xs text-text-secondary">Similitud</div>
                                    <div class="font-mono text-sm font-semibold {{ $clasePuntaje }}">{{ number_format($puntaje, 0) }}%</div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-border bg-surface p-8 text-center">
            <flux:icon name="check-circle" class="mx-auto size-12 text-success mb-2" />
            <p class="text-text-primary font-medium">No quedan verbatims taxonómicos pendientes</p>
            <p class="text-sm text-text-secondary mt-1">Todos los <code class="text-xs">taxon_verbatim</code> están enlazados.</p>
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
