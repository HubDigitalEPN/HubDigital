<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Taxones por confirmar</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Nombres de taxón tal como venían en el Excel, sin enlazar a un taxón del catálogo.
                Elige el nombre correcto (te sugerimos candidatos, o búscalo tú), y aplícalo a todo el
                grupo o solo a los especímenes que elijas.
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
            Cada tarjeta agrupa los especímenes que traían el mismo nombre de taxón sin resolver
            (e.g. <code>"Acragas_sp.1"</code>).
        </p>
        <ol class="list-decimal pl-6 space-y-1 text-xs text-text-secondary">
            <li>Te mostramos <strong>candidatos sugeridos</strong> por parecido. Elige el correcto, o usa
                <strong>"Buscar otro nombre"</strong> si no está en la lista.</li>
            <li>Botón <strong>"Ver especímenes"</strong>: abre el grupo para revisar cada ejemplar y
                <strong>marcar solo algunos</strong> (por si el mismo texto esconde especies distintas).</li>
            <li>Con el grupo cerrado, se aplica a <strong>todos</strong>; con el grupo abierto, solo a los <strong>marcados</strong>.</li>
            <li>Si un ejemplar es irrecuperable, déjalo sin resolver — advertencia: esos especímenes
                <strong>no se exportan a GBIF</strong>.</li>
        </ol>
    </x-inventariogestioncoleccion::bandeja-ayuda>

    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-medium text-text-primary">
            {{ $total }} grupo(s) de taxón pendiente(s)
        </span>
        @if($total > 0)
            <span class="text-xs text-text-secondary">
                Mostrando {{ $inicio }}–{{ $fin }} (página {{ $pagina }} de {{ $totalPaginas }})
            </span>
        @endif
    </div>

    @forelse($items as $idx => $item)
        @php
            $abierto = ! empty($expandido[$idx]);
            $seleccionados = count(is_array($seleccion[$idx] ?? null) ? $seleccion[$idx] : []);
            $mensajeConfirmar = $abierto
                ? "Vas a enlazar {$seleccionados} espécimen(es) seleccionado(s) a «{$item['taxonSeleccionadoNombre']}». ¿Continuar?"
                : "Vas a enlazar los {$item['totalEspecimenes']} espécimen(es) del grupo a «{$item['taxonSeleccionadoNombre']}». ¿Continuar?";
            $mensajeCrear = $abierto
                ? "Vas a crear un taxón nuevo y enlazar {$seleccionados} espécimen(es) seleccionado(s). ¿Continuar?"
                : "Vas a crear un taxón nuevo y enlazar los {$item['totalEspecimenes']} espécimen(es) del grupo. ¿Continuar?";
        @endphp
        <div wire:key="taxon-{{ md5($item['verbatim']) }}" class="rounded-lg border border-border bg-surface shadow-sm border-l-4 border-l-warning overflow-hidden">
            <div class="px-5 py-4 bg-bg-main border-b border-border flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs uppercase font-semibold text-text-secondary tracking-wide">Nombre original (del Excel)</span>
                        <span class="inline-flex items-center rounded-full bg-blue-navy/10 text-blue-navy border border-blue-navy/30 px-2 py-0.5 text-xs font-semibold">
                            {{ $item['totalEspecimenes'] }} espécimen(es)
                        </span>
                    </div>
                    <div class="font-serif italic text-text-primary text-base break-words">"{{ $item['verbatim'] }}"</div>
                </div>
                <flux:button variant="ghost" size="sm"
                             :icon="$abierto ? 'chevron-up' : 'chevron-down'"
                             wire:click="verEspecimenes({{ $idx }})"
                             wire:loading.attr="disabled"
                             wire:target="verEspecimenes">
                    {{ $abierto ? 'Ocultar especímenes' : 'Ver especímenes' }}
                </flux:button>
            </div>

            @if($abierto)
                <div class="border-b border-border bg-surface px-5 py-3">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold text-text-secondary">
                            {{ $seleccionados }} de {{ count($miembros[$idx] ?? []) }} seleccionado(s)
                        </span>
                        <div class="flex gap-2">
                            <flux:button variant="ghost" size="sm" wire:click="seleccionarTodos({{ $idx }})">Todos</flux:button>
                            <flux:button variant="ghost" size="sm" wire:click="limpiarSeleccion({{ $idx }})">Ninguno</flux:button>
                        </div>
                    </div>
                    <div class="max-h-72 overflow-y-auto divide-y divide-border rounded-lg border border-border">
                        @foreach($miembros[$idx] ?? [] as $m)
                            <div class="flex items-start gap-2 px-3 py-2 hover:bg-bg-main"
                                 wire:key="taxon-m-{{ $idx }}-{{ $m['id'] }}">
                                <label class="flex min-w-0 flex-1 items-start gap-3 cursor-pointer">
                                    <input type="checkbox"
                                           wire:model.live="seleccion.{{ $idx }}"
                                           value="{{ $m['id'] }}"
                                           class="mt-1 size-4 rounded border-border text-science-blue" />
                                    <div class="min-w-0 flex-1">
                                        <span class="font-mono text-sm text-text-primary">{{ $m['codigoCatalogo'] }}</span>
                                        <div class="mt-0.5 text-xs text-text-secondary">
                                            <span class="text-text-primary">{{ ($m['taxonNombre'] ?? null) ?: 'sin determinar' }}</span>
                                            · {{ $m['localidad'] ?: '—' }}
                                            · {{ $m['fechaColecta'] ?: '—' }}
                                            · {{ $m['colector'] ?: '—' }}
                                        </div>
                                    </div>
                                </label>
                                <flux:button size="sm" variant="ghost" icon="eye"
                                             wire:click="$dispatch('ver-ficha-especimen', { id: '{{ $m['id'] }}' })">
                                    Ficha
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="p-4 space-y-3">
                <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Elige el nombre correcto</div>
                @if(empty($item['candidatos']))
                    <p class="text-sm text-text-secondary italic py-1">
                        No hay sugerencias. Usa el buscador de abajo o registra el taxón en "Gestionar taxones".
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
                                    <div class="text-xs text-text-secondary">{{ ucfirst($cand['rango']) }} · parecido {{ number_format($puntaje, 0) }}%</div>
                                </div>
                                <div class="font-mono text-sm font-semibold {{ $clasePuntaje }} shrink-0">{{ number_format($puntaje, 0) }}%</div>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Reasignar a otro nombre --}}
                <div class="rounded-lg border border-dashed border-border p-3 space-y-2">
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <flux:input wire:model="busquedaTexto.{{ $idx }}"
                                    wire:keydown.enter="buscarOtros({{ $idx }})"
                                    placeholder="Buscar otro nombre de taxón…"
                                    class="flex-1" />
                        <flux:button variant="ghost" icon="magnifying-glass"
                                     wire:click="buscarOtros({{ $idx }})"
                                     wire:loading.attr="disabled" wire:target="buscarOtros">
                            Buscar
                        </flux:button>
                    </div>
                    @if(!empty($busquedaResultados[$idx]))
                        <div class="grid gap-1">
                            @foreach($busquedaResultados[$idx] as $res)
                                @php($sel = ($item['taxonSeleccionado'] ?? '') === $res['taxonId'])
                                <button type="button"
                                        wire:click="seleccionarCandidato({{ $idx }}, '{{ $res['taxonId'] }}')"
                                        @class([
                                            'w-full text-left rounded-lg border px-3 py-2 text-sm flex items-center gap-2',
                                            'border-success bg-success/5' => $sel,
                                            'border-border hover:bg-bg-main' => !$sel,
                                        ])>
                                    @if($sel)
                                        <flux:icon name="check" class="size-3.5 text-success shrink-0" />
                                    @endif
                                    <span class="font-serif italic text-text-primary truncate">{{ $res['nombreCientifico'] }}</span>
                                    <span class="text-xs text-text-secondary ml-auto shrink-0">{{ ucfirst($res['rango']) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif(isset($busquedaResultados[$idx]))
                        <p class="text-xs text-text-secondary italic">Sin coincidencias para esa búsqueda.</p>
                    @endif
                </div>

                {{-- ¿No existe? Crear el taxón canónico nuevo y enlazar en el acto --}}
                <div class="rounded-lg border border-dashed border-science-blue/40 bg-science-blue/5 p-3 space-y-2">
                    <p class="text-xs font-semibold text-science-blue uppercase tracking-wide">¿No está en la lista? Créalo y enlaza</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <flux:input wire:model="nuevoTaxonNombre.{{ $idx }}"
                                    placeholder="Nombre científico del nuevo taxón…"
                                    class="flex-1 font-serif italic" />
                        <flux:select wire:model="nuevoTaxonRango.{{ $idx }}" class="sm:w-48">
                            @foreach($rangosTaxon as $r)
                                <option value="{{ $r }}" @selected(($nuevoTaxonRango[$idx] ?? 'morfoespecie') === $r)>
                                    {{ ucfirst(str_replace('_', ' ', $r)) }}
                                </option>
                            @endforeach
                        </flux:select>
                        <flux:button variant="primary" icon="plus"
                                     wire:click="crearYEnlazar({{ $idx }})"
                                     wire:confirm="{{ $mensajeCrear }}"
                                     wire:loading.attr="disabled"
                                     wire:target="crearYEnlazar">
                            Crear y enlazar
                        </flux:button>
                    </div>
                    <p class="text-xs text-text-secondary">
                        Crea un taxón con ese nombre (por defecto morfoespecie) y {{ $abierto ? 'lo aplica a los especímenes seleccionados' : 'lo aplica a todo el grupo' }}.
                    </p>
                </div>

                <div class="border-t border-border pt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-xs text-text-secondary">
                        Seleccionado:
                        <span class="font-serif italic text-text-primary">{{ $item['taxonSeleccionadoNombre'] ?: '— ninguno —' }}</span>
                    </span>
                    <flux:button variant="primary" icon="check"
                                 wire:click="confirmar({{ $idx }})"
                                 wire:confirm="{{ $mensajeConfirmar }}"
                                 wire:loading.attr="disabled"
                                 wire:target="confirmar">
                        @if($abierto)
                            Enlazar {{ $seleccionados }} seleccionado(s)
                        @else
                            Enlazar todo el grupo ({{ $item['totalEspecimenes'] }})
                        @endif
                    </flux:button>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-border bg-surface p-8 text-center">
            <flux:icon name="check-circle" class="mx-auto size-12 text-success mb-2" />
            <p class="text-text-primary font-medium">No quedan taxones por confirmar</p>
            <p class="text-sm text-text-secondary mt-1">Todos los nombres de taxón están enlazados a un taxón del catálogo.</p>
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

    @livewire('inventario-ficha-especimen', [], 'ficha-modal-taxa')
</div>
