<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Revisión de localidades</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Localidades verbatim del Excel sin enlazar a una localidad canónica. Confirma el candidato sugerido o registra una nueva.
            </p>
        </div>
        <flux:button icon="map-pin" variant="ghost" :href="route('inventario.taxonomia.localidades')" wire:navigate>
            Gestionar localidades
        </flux:button>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <x-inventariogestioncoleccion::bandeja-ayuda titulo="¿Cómo uso esta pantalla?" storage-key="ayuda-localidades-revision">
        <p>
            Cada tarjeta agrupa los especímenes que tienen el mismo <code>localidad_verbatim</code>
            (texto crudo del Excel) y que <strong>aún no</strong> tienen una localidad canónica asociada.
        </p>
        <ol class="list-decimal pl-6 space-y-1 text-xs text-text-secondary">
            <li>Si no hay candidatos sugeridos, registra primero la localidad canónica en
                <a href="{{ route('inventario.taxonomia.localidades') }}" wire:navigate class="text-info hover:underline">/localidades</a>.</li>
            <li>El sistema propone candidatos por similitud de nombre. El de mayor % aparece pre-seleccionado.</li>
            <li>Click en el candidato correcto → "Confirmar enlace" → todos los especímenes con ese verbatim quedan asociados.</li>
        </ol>
        <p class="text-xs text-text-secondary">
            <strong>Estado final esperado:</strong> esta bandeja vacía. Cada espécimen sabe en qué localidad canónica fue colectado.
        </p>
    </x-inventariogestioncoleccion::bandeja-ayuda>

    {{-- Resumen --}}
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

    {{-- Lista de tarjetas por verbatim --}}
    @forelse($items as $idx => $item)
        <div class="rounded-lg border border-border bg-surface shadow-sm border-l-4 border-l-warning overflow-hidden">
            {{-- Cabecera del verbatim --}}
            <div class="px-5 py-4 bg-bg-main border-b border-border flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs uppercase font-semibold text-text-secondary tracking-wide">localidad_verbatim</span>
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

            {{-- Candidatos --}}
            <div class="p-4 space-y-2">
                <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Candidatos canónicos (por similitud)</div>
                @if(empty($item['candidatos']))
                    <p class="text-sm text-text-secondary italic py-2">
                        No hay localidades canónicas para sugerir. Crea una nueva en la pantalla de gestión y vuelve aquí.
                    </p>
                @else
                    <div class="grid gap-2">
                        @foreach($item['candidatos'] as $cand)
                            @php
                                $seleccionado = ($item['localidadSeleccionada'] ?? '') === $cand['localidadId'];
                                $puntaje = (float) $cand['puntajeSimilitud'];
                                $clasePuntaje = $puntaje >= 70 ? 'text-success' : ($puntaje >= 40 ? 'text-warning' : 'text-error');
                            @endphp
                            <button type="button"
                                    wire:click="seleccionarCandidato({{ $idx }}, '{{ $cand['localidadId'] }}')"
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
                                    <div class="font-serif italic text-text-primary truncate">{{ $cand['nombreCanonico'] }}</div>
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
            <p class="text-text-primary font-medium">No quedan verbatims pendientes</p>
            <p class="text-sm text-text-secondary mt-1">Todos los <code class="text-xs">localidad_verbatim</code> están enlazados a localidades canónicas.</p>
        </div>
    @endforelse

    {{-- Paginación --}}
    @if($total > 0)
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-text-secondary">Página {{ $pagina }} de {{ $totalPaginas }}</span>
            <div class="flex gap-2">
                <flux:button size="sm" variant="ghost" icon="chevron-left"
                             :disabled="$pagina <= 1"
                             wire:click="paginaAnterior">Anterior</flux:button>
                <flux:button size="sm" variant="ghost" icon="chevron-right"
                             :disabled="$pagina >= $totalPaginas"
                             wire:click="siguientePagina">Siguiente</flux:button>
            </div>
        </div>
    @endif
</div>
