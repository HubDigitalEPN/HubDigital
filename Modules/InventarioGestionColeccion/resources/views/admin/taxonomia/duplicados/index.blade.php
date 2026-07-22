<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Números de catálogo duplicados</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Grupos de especímenes que comparten el mismo número de catálogo. Decide si son eventos
                legítimos distintos o un error de catalogación.
            </p>
        </div>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <x-inventariogestioncoleccion::bandeja-ayuda titulo="¿Cómo uso esta pantalla?" storage-key="ayuda-duplicados-catalog">
        <p>
            Cada grupo muestra especímenes que comparten el mismo <code>catalog_number</code>. Hay dos casos válidos:
        </p>
        <ul class="list-disc pl-6 space-y-1 text-xs text-text-secondary">
            <li><strong>Eventos legítimos</strong> — dos colectas diferentes con el mismo número físico (raro pero ocurre).
                Si las fechas/colectores son distintos, el chip azul lo indica. Click "Eventos distintos" → todos pasan a "confirmada".</li>
            <li><strong>Error de catalogación</strong> — el numerador se repitió por error. Chip naranja si la fecha coincide.
                Escribe un motivo y click "Marcar error" → los especímenes quedan pendientes para investigar y corregir.</li>
        </ul>
    </x-inventariogestioncoleccion::bandeja-ayuda>

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
        @php
            // Señales que permiten decidir de un vistazo si el catalog_number es el
            // mismo evento (mismo taxón/localidad) o se reusó para otra especie (error).
            $taxones = collect($item['especimenes'])->pluck('taxonNombre')->filter()->unique()->values();
            $localidades = collect($item['especimenes'])->pluck('localidad')->filter()->unique()->values();
            $hayFechas = collect($item['especimenes'])->contains(fn ($e) => ($e['fechaColecta'] ?? '') !== '');
            $bordeIzq = $taxones->count() > 1 ? 'border-l-error' : ($item['fechasDistintas'] ? 'border-l-info' : 'border-l-warning');
            $sel = count($seleccion[$idx] ?? []);
        @endphp
        <div wire:key="dup-{{ md5($item['catalogNumber']) }}" class="rounded-lg border border-border bg-surface shadow-sm border-l-4 {{ $bordeIzq }} overflow-hidden">
            {{-- Cabecera del grupo --}}
            <div class="px-5 py-4 bg-bg-main border-b border-border flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs uppercase font-semibold text-text-secondary tracking-wide">Número de catálogo</span>
                        <span class="inline-flex items-center rounded-full bg-blue-navy/10 text-blue-navy border border-blue-navy/30 px-2 py-0.5 text-xs font-semibold">
                            {{ $item['total'] }} espécimen(es)
                        </span>
                    </div>
                    <div class="font-mono text-text-primary text-base break-words">{{ $item['catalogNumber'] }}</div>
                    {{-- Señales para decidir de un vistazo --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($taxones->count() > 1)
                            <span class="inline-flex items-center rounded-full bg-error/10 text-error border border-error px-2 py-0.5 text-xs font-semibold">
                                {{ $taxones->count() }} taxones distintos — probable número reusado
                            </span>
                        @elseif($taxones->count() === 1)
                            <span class="inline-flex items-center gap-1 rounded-full bg-success/10 text-success border border-success px-2 py-0.5 text-xs font-semibold">
                                Mismo taxón: <span class="font-serif italic">{{ $taxones->first() }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-border/40 text-text-secondary border border-border px-2 py-0.5 text-xs font-semibold">
                                Sin taxón determinado
                            </span>
                        @endif
                        <span class="inline-flex items-center rounded-full bg-bg-main text-text-secondary border border-border px-2 py-0.5 text-xs">
                            {{ $localidades->count() }} localidad(es)
                        </span>
                        @if($hayFechas)
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $item['fechasDistintas'] ? 'bg-info/10 text-info border border-info' : 'bg-warning/10 text-warning border border-warning' }}">
                                {{ $item['fechasDistintas'] ? 'Fechas distintas' : 'Misma fecha' }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-border/40 text-text-secondary border border-border px-2 py-0.5 text-xs">
                                Sin fecha registrada
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Listado de especímenes del grupo --}}
            <div class="p-4 space-y-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">
                        Especímenes en el grupo ({{ count($item['especimenes']) }})
                        @if($sel > 0)
                            <span class="ml-1 text-science-blue">· {{ $sel }} seleccionado(s)</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button variant="ghost" size="sm" wire:click="seleccionarTodos({{ $idx }})">Todos</flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="limpiarSeleccion({{ $idx }})">Ninguno</flux:button>
                    </div>
                </div>
                <p class="text-xs text-text-secondary">
                    Marca especímenes para actuar solo sobre ellos; sin selección, la decisión aplica a todo el grupo.
                </p>

                @php
                    $badge = fn ($estado) => match ($estado ?? 'pendiente') {
                        'pendiente' => 'bg-warning/10 text-warning border-warning',
                        'confirmada' => 'bg-success/10 text-success border-success',
                        'descartada' => 'bg-error/10 text-error border-error',
                        default => 'bg-border/30 text-text-secondary border-border',
                    };
                @endphp

                {{-- Escritorio: tabla densa con scroll interno para que 96 filas no
                     estiren la página; el encabezado queda fijo al hacer scroll. --}}
                <div class="hidden md:block max-h-80 overflow-auto rounded-lg border border-border">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-bg-main text-text-secondary">
                            <tr class="text-left text-xs uppercase tracking-wide">
                                <th class="px-3 py-2 font-medium"><span class="sr-only">Seleccionar</span></th>
                                <th class="px-3 py-2 font-medium">Código</th>
                                <th class="px-3 py-2 font-medium">Taxón</th>
                                <th class="px-3 py-2 font-medium">Localidad</th>
                                <th class="px-3 py-2 font-medium whitespace-nowrap">Fecha</th>
                                <th class="px-3 py-2 font-medium">Colector</th>
                                <th class="px-3 py-2 font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($item['especimenes'] as $e)
                                <tr class="border-t border-border" wire:key="dup-{{ md5($item['catalogNumber']) }}-{{ $e['id'] }}">
                                    <td class="px-3 py-2">
                                        <flux:checkbox wire:model.live="seleccion.{{ $idx }}" value="{{ $e['id'] }}" />
                                    </td>
                                    <td class="px-3 py-2 font-mono text-text-primary whitespace-nowrap">{{ $e['codigoCatalogo'] }}</td>
                                    <td class="px-3 py-2 font-serif italic text-text-primary">{{ $e['taxonNombre'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-text-primary">{{ $e['localidad'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-text-primary whitespace-nowrap">{{ $e['fechaColecta'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-text-secondary">{{ $e['colector'] ?: '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $badge($e['estadoRevision']) }}">
                                            {{ ucfirst($e['estadoRevision']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Móvil: filas compactas apiladas, también con scroll interno. --}}
                <div class="md:hidden max-h-80 space-y-2 overflow-y-auto rounded-lg border border-border p-2">
                    @foreach($item['especimenes'] as $e)
                        <div class="rounded-lg border border-border bg-surface p-3" wire:key="dupm-{{ md5($item['catalogNumber']) }}-{{ $e['id'] }}">
                            <div class="flex items-center justify-between gap-2">
                                <label class="flex min-w-0 items-center gap-2">
                                    <flux:checkbox wire:model.live="seleccion.{{ $idx }}" value="{{ $e['id'] }}" />
                                    <span class="font-mono text-sm text-text-primary break-all">{{ $e['codigoCatalogo'] }}</span>
                                </label>
                                <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $badge($e['estadoRevision']) }}">
                                    {{ ucfirst($e['estadoRevision']) }}
                                </span>
                            </div>
                            <p class="mt-1 font-serif text-sm italic text-text-primary">{{ $e['taxonNombre'] ?: '— sin taxón —' }}</p>
                            <p class="mt-0.5 text-xs text-text-secondary">
                                {{ $e['localidad'] ?: '—' }} · {{ $e['fechaColecta'] ?: '—' }} · {{ $e['colector'] ?: '—' }}
                            </p>
                        </div>
                    @endforeach
                </div>

                {{-- Acciones --}}
                @php
                    $objetivo = $sel > 0 ? $sel : $item['total'];
                    $alcance = $sel > 0
                        ? "los {$sel} espécimen(es) seleccionado(s)"
                        : "los {$item['total']} espécimen(es) del grupo";
                    $msgEventos = "Vas a confirmar {$alcance} como eventos distintos (revisión confirmada). ¿Continuar?";
                    $msgError = "Vas a marcar {$alcance} para revisión por error de catalogación. ¿Continuar?";
                @endphp
                <div class="border-t border-border pt-3 space-y-3">
                    <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">
                        Decisión del curador — aplica a {{ $sel > 0 ? "{$sel} seleccionado(s)" : 'todo el grupo' }}
                    </div>
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-stretch">
                        <flux:button
                            variant="primary"
                            icon="check-circle"
                            wire:click="marcarEventosDistintos({{ $idx }})"
                            wire:confirm="{{ $msgEventos }}"
                            wire:loading.attr="disabled"
                            wire:target="marcarEventosDistintos"
                            class="lg:w-1/3">
                            Confirmar eventos distintos ({{ $objetivo }})
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
                                wire:confirm="{{ $msgError }}"
                                wire:loading.attr="disabled"
                                wire:target="marcarErrorCatalogacion">
                                Marcar error de catalogación ({{ $objetivo }})
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-border bg-surface p-8 text-center">
            <flux:icon name="check-circle" class="mx-auto size-12 text-success mb-2" />
            <p class="text-text-primary font-medium">No hay duplicados de número de catálogo sin resolver</p>
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
