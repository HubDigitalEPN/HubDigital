<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Localidades por confirmar</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Localidades tal como venían en el Excel, sin enlazar a una localidad del catálogo.
                Elige la correcta (te sugerimos candidatos, o búscala tú) y aplícala a todo el grupo o
                solo a los especímenes que elijas.
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
            Cada tarjeta agrupa los especímenes que traían el mismo texto de localidad sin resolver.
        </p>
        <ol class="list-decimal pl-6 space-y-1 text-xs text-text-secondary">
            <li>Te mostramos <strong>candidatos sugeridos</strong> por parecido. Elige el correcto, o usa
                <strong>"Buscar otra localidad"</strong> si no está en la lista.</li>
            <li>Botón <strong>"Ver especímenes"</strong>: abre el grupo para revisar cada ejemplar y
                <strong>marcar solo algunos</strong>.</li>
            <li>Con el grupo cerrado se aplica a <strong>todos</strong>; con el grupo abierto, solo a los <strong>marcados</strong>.</li>
        </ol>
    </x-inventariogestioncoleccion::bandeja-ayuda>

    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-medium text-text-primary">
            {{ $total }} grupo(s) de localidad pendiente(s)
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
            $seleccionados = count($seleccion[$idx] ?? []);
            $mensajeConfirmar = $abierto
                ? "Vas a enlazar {$seleccionados} espécimen(es) seleccionado(s) a «{$item['localidadSeleccionadaNombre']}». ¿Continuar?"
                : "Vas a enlazar los {$item['totalEspecimenes']} espécimen(es) del grupo a «{$item['localidadSeleccionadaNombre']}». ¿Continuar?";
            $mensajeCrear = $abierto
                ? "Vas a crear una localidad nueva y enlazar {$seleccionados} espécimen(es) seleccionado(s). ¿Continuar?"
                : "Vas a crear una localidad nueva y enlazar los {$item['totalEspecimenes']} espécimen(es) del grupo. ¿Continuar?";
        @endphp
        <div wire:key="localidad-{{ md5($item['verbatim']) }}" class="rounded-lg border border-border bg-surface shadow-sm border-l-4 border-l-warning overflow-hidden">
            <div class="px-5 py-4 bg-bg-main border-b border-border flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs uppercase font-semibold text-text-secondary tracking-wide">Localidad original (del Excel)</span>
                        <span class="inline-flex items-center rounded-full bg-blue-navy/10 text-blue-navy border border-blue-navy/30 px-2 py-0.5 text-xs font-semibold">
                            {{ $item['totalEspecimenes'] }} espécimen(es)
                        </span>
                    </div>
                    <div class="text-text-primary text-base font-medium break-words">"{{ $item['verbatim'] }}"</div>
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
                            <div class="flex items-start gap-2 px-3 py-2 hover:bg-bg-main">
                                <label class="flex min-w-0 flex-1 items-start gap-3 cursor-pointer">
                                    <input type="checkbox"
                                           wire:model.live="seleccion.{{ $idx }}"
                                           value="{{ $m['id'] }}"
                                           class="mt-1 size-4 rounded border-border text-science-blue" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-sm text-text-primary">{{ $m['codigoCatalogo'] }}</span>
                                            @if(!empty($m['taxonNombre']))
                                                <span class="font-serif text-sm italic text-text-primary">{{ $m['taxonNombre'] }}</span>
                                            @else
                                                <span class="text-xs italic text-text-secondary">sin determinar</span>
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-xs text-text-secondary">
                                            {{ $m['fechaColecta'] ?: ($m['fechaVerbatim'] ?: '—') }}
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
                <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Elige la localidad correcta</div>
                @if(empty($item['candidatos']))
                    <p class="text-sm text-text-secondary italic py-1">
                        No hay sugerencias. Usa el buscador de abajo o crea la localidad en "Gestionar localidades".
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
                                    <div class="text-text-primary font-medium truncate">{{ $cand['nombreCanonico'] }}</div>
                                    <div class="text-xs text-text-secondary">Nivel: {{ ucfirst(str_replace('_', ' ', $cand['rango'])) }} · parecido {{ number_format($puntaje, 0) }}%</div>
                                </div>
                                <div class="font-mono text-sm font-semibold {{ $clasePuntaje }} shrink-0">{{ number_format($puntaje, 0) }}%</div>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Reasignar a otra localidad --}}
                <div class="rounded-lg border border-dashed border-border p-3 space-y-2">
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <flux:input wire:model="busquedaTexto.{{ $idx }}"
                                    wire:keydown.enter="buscarOtros({{ $idx }})"
                                    placeholder="Buscar otra localidad…"
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
                                @php($sel = ($item['localidadSeleccionada'] ?? '') === $res['localidadId'])
                                <button type="button"
                                        wire:click="seleccionarCandidato({{ $idx }}, '{{ $res['localidadId'] }}')"
                                        @class([
                                            'w-full text-left rounded-lg border px-3 py-2 text-sm flex items-center gap-2',
                                            'border-success bg-success/5' => $sel,
                                            'border-border hover:bg-bg-main' => !$sel,
                                        ])>
                                    @if($sel)
                                        <flux:icon name="check" class="size-3.5 text-success shrink-0" />
                                    @endif
                                    <span class="text-text-primary font-medium truncate">{{ $res['nombreCanonico'] }}</span>
                                    <span class="text-xs text-text-secondary ml-auto shrink-0">{{ ucfirst(str_replace('_', ' ', $res['rango'])) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif(isset($busquedaResultados[$idx]))
                        <p class="text-xs text-text-secondary italic">Sin coincidencias para esa búsqueda.</p>
                    @endif
                </div>

                {{-- ¿No existe? Crear la localidad canónica nueva y enlazar en el acto --}}
                <div class="rounded-lg border border-dashed border-science-blue/40 bg-science-blue/5 p-3 space-y-2">
                    <p class="text-xs font-semibold text-science-blue uppercase tracking-wide">¿No está en la lista? Créala y enlaza</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <flux:input wire:model="nuevaLocalidadNombre.{{ $idx }}"
                                    placeholder="Nombre de la nueva localidad…"
                                    class="flex-1" />
                        <flux:select wire:model="nuevaLocalidadRango.{{ $idx }}" class="sm:w-44">
                            @foreach($rangosLocalidad as $r)
                                <option value="{{ $r }}" @selected(($nuevaLocalidadRango[$idx] ?? 'sitio') === $r)>
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
                        Crea una localidad canónica con ese nombre y {{ $abierto ? 'la aplica a los especímenes seleccionados' : 'la aplica a todo el grupo' }}.
                    </p>
                </div>

                <div class="border-t border-border pt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-xs text-text-secondary">
                        Seleccionada:
                        <span class="text-text-primary font-medium">{{ $item['localidadSeleccionadaNombre'] ?: '— ninguna —' }}</span>
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
            <p class="text-text-primary font-medium">No quedan localidades por confirmar</p>
            <p class="text-sm text-text-secondary mt-1">Todos los especímenes están enlazados a una localidad del catálogo.</p>
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

    @livewire('inventario-ficha-especimen', key: 'ficha-modal-localidades')
</div>
