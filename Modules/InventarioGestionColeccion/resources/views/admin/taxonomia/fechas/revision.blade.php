<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Fechas por normalizar</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Textos de fecha que el importador no pudo interpretar. Asigna la fecha real
                (<code class="text-xs">AAAA-MM-DD</code>) o un rango. Puedes aplicarla a todo el grupo,
                o abrir el detalle y elegir a cuáles especímenes.
            </p>
        </div>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <x-inventariogestioncoleccion::bandeja-ayuda titulo="¿Cómo uso esta pantalla?" storage-key="ayuda-fechas-revision">
        <p>
            Cada tarjeta agrupa los especímenes que comparten el mismo texto de fecha original
            (formatos como <code>"25 a 27-May-09"</code>, <code>"10/18-22/2007"</code>, <code>"s/f"</code>).
        </p>
        <ol class="list-decimal pl-6 space-y-1 text-xs text-text-secondary">
            <li>Si la tarjeta dice <strong>"Sugerencia disponible"</strong> (chip azul), los campos ya vienen
                pre-llenados con lo que el sistema intuyó. Revisa y aplica.</li>
            <li>Si dice <strong>"No se pudo interpretar"</strong> (chip naranja), escribe tú la fecha de inicio
                (y de fin si es un rango).</li>
            <li>Botón <strong>"Ver especímenes"</strong>: abre el grupo, verás cada ejemplar y podrás
                <strong>marcar solo algunos</strong> (útil cuando el mismo texto esconde fechas distintas).</li>
            <li>Con el grupo cerrado, "Aplicar" afecta a <strong>todos</strong>; con el grupo abierto, solo a los
                <strong>marcados</strong>.</li>
        </ol>
    </x-inventariogestioncoleccion::bandeja-ayuda>

    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-medium text-text-primary">
            {{ $total }} grupo(s) de fecha pendiente(s)
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
                ? "Vas a asignar la fecha a {$seleccionados} espécimen(es) seleccionado(s). ¿Continuar?"
                : "Vas a asignar la fecha a los {$item['totalEspecimenes']} espécimen(es) del grupo. ¿Continuar?";
        @endphp
        <div wire:key="fecha-{{ md5($item['verbatim']) }}" class="rounded-lg border border-border bg-surface shadow-sm border-l-4 {{ $item['sugerenciaInicio'] ? 'border-l-info' : 'border-l-warning' }} overflow-hidden">
            <div class="px-5 py-4 bg-bg-main border-b border-border flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs uppercase font-semibold text-text-secondary tracking-wide">Texto original de la fecha</span>
                        <span class="inline-flex items-center rounded-full bg-blue-navy/10 text-blue-navy border border-blue-navy/30 px-2 py-0.5 text-xs font-semibold">
                            {{ $item['totalEspecimenes'] }} espécimen(es)
                        </span>
                        @if($item['sugerenciaInicio'])
                            <span class="inline-flex items-center rounded-full bg-info/10 text-info border border-info px-2 py-0.5 text-xs font-semibold">
                                Sugerencia disponible
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-warning/10 text-warning border border-warning px-2 py-0.5 text-xs font-semibold">
                                No se pudo interpretar
                            </span>
                        @endif
                    </div>
                    <div class="font-mono text-text-primary text-base break-words">"{{ $item['verbatim'] }}"</div>
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
                            <label class="flex items-start gap-3 px-3 py-2 hover:bg-bg-main cursor-pointer">
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
                                        <span class="text-text-primary">{{ $m['localidad'] ?: '—' }}</span>
                                        · {{ $m['fechaColecta'] ?: ($m['fechaVerbatim'] ?: '—') }}
                                        · {{ $m['colector'] ?: '—' }}
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="p-4 space-y-3">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <flux:field>
                        <flux:label>Fecha de inicio (AAAA-MM-DD)</flux:label>
                        <flux:input type="date"
                                    wire:model="items.{{ $idx }}.fechaInicio"
                                    placeholder="AAAA-MM-DD" />
                        @if($item['sugerenciaInicio'])
                            <flux:description>
                                Sugerencia: <code class="text-info">{{ $item['sugerenciaInicio'] }}</code>
                            </flux:description>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label>Fecha de fin (opcional, si es rango)</flux:label>
                        <flux:input type="date"
                                    wire:model="items.{{ $idx }}.fechaFin"
                                    placeholder="AAAA-MM-DD" />
                        @if($item['sugerenciaFin'])
                            <flux:description>
                                Sugerencia: <code class="text-info">{{ $item['sugerenciaFin'] }}</code>
                            </flux:description>
                        @endif
                    </flux:field>
                </div>

                <div class="border-t border-border pt-3 flex flex-wrap justify-end gap-2">
                    <flux:button variant="primary" icon="check"
                                 wire:click="confirmar({{ $idx }})"
                                 wire:confirm="{{ $mensajeConfirmar }}"
                                 wire:loading.attr="disabled"
                                 wire:target="confirmar">
                        @if($abierto)
                            Aplicar a {{ $seleccionados }} seleccionado(s)
                        @else
                            Aplicar a todo el grupo ({{ $item['totalEspecimenes'] }})
                        @endif
                    </flux:button>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-border bg-surface p-8 text-center">
            <flux:icon name="check-circle" class="mx-auto size-12 text-success mb-2" />
            <p class="text-text-primary font-medium">No quedan fechas por normalizar</p>
            <p class="text-sm text-text-secondary mt-1">Todos los especímenes con un texto de fecha ya tienen su fecha real asignada.</p>
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
