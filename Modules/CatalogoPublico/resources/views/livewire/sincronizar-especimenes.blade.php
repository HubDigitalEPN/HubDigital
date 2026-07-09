<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-blue-navy">Sincronizar especímenes</h1>
            <p class="text-sm text-text-secondary">Seleccione especímenes y configure su visibilidad en el catálogo público</p>
        </div>
        <flux:button
            :href="route('divulgacion.index')"
            icon="arrow-left"
            variant="ghost"
            wire:navigate
        >
            Volver a la tabla
        </flux:button>
    </div>

    {{-- Stepper --}}
    <x-catalogopublico::sync-stepper :paso="$paso" class="py-2" />

    {{-- ============================================================ --}}
    {{-- PASO 1: Selección de especímenes --}}
    {{-- ============================================================ --}}
    @if($paso === 1)
        <div class="flex flex-col gap-4">
            {{-- Barra de filtros --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <flux:input
                        wire:model.live.debounce.300ms="busquedaCatalogo"
                        label="N.º de catálogo"
                        placeholder="Ej. EPN-000123"
                        icon="magnifying-glass"
                        size="sm"
                        clearable
                    />
                    <flux:input
                        wire:model.live.debounce.300ms="busquedaTaxonomia"
                        label="Taxonomía"
                        placeholder="Nombre científico"
                        icon="magnifying-glass"
                        size="sm"
                        clearable
                    />
                    <flux:input
                        type="date"
                        wire:model.live="fechaDesde"
                        label="Recolección desde"
                        size="sm"
                    />
                    <flux:input
                        type="date"
                        wire:model.live="fechaHasta"
                        label="Recolección hasta"
                        size="sm"
                    />
                    <flux:select
                        wire:model.live="colector"
                        label="Colector"
                        size="sm"
                    >
                        <flux:select.option value="">Todos</flux:select.option>
                        @foreach($this->colectoresDisponibles as $col)
                            <flux:select.option value="{{ $col }}">{{ $col }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                @if($this->filtros->tieneFiltros())
                    <div class="mt-3 flex justify-end">
                        <flux:button wire:click="limpiarFiltros" variant="ghost" size="sm" icon="x-mark">
                            Limpiar filtros
                        </flux:button>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between">
                <p class="text-sm text-text-secondary">
                    {{ count($seleccionados) }} espécimen(es) seleccionado(s)
                </p>
                <div class="flex gap-2">
                    <flux:button
                        wire:click="seleccionarTodos"
                        variant="ghost"
                        size="sm"
                    >
                        Seleccionar página
                    </flux:button>
                    @if(count($seleccionados) > 0)
                        <flux:button wire:click="deseleccionarTodos" variant="ghost" size="sm">
                            Limpiar selección
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-blue-navy border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-white" style="width: 2.5rem;"></th>
                            <th class="px-4 py-3 text-left font-medium text-white w-3/12">Occurrence ID</th>
                            <th class="px-4 py-3 text-left font-medium text-white w-3/12">Nombre científico</th>
                            <th class="px-4 py-3 text-left font-medium text-white hidden md:table-cell w-2/12">Type Status</th>
                            <th class="px-4 py-3 text-left font-medium text-white hidden lg:table-cell w-2/12">Familia</th>
                            <th class="px-4 py-3 text-left font-medium text-white hidden xl:table-cell w-2/12">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($especimenes as $esp)
                            @php $seleccionado = in_array($esp->occurrence_id, $seleccionados, true); @endphp
                            <tr
                                wire:key="esp-paso1-{{ $esp->occurrence_id }}"
                                wire:click="toggleSeleccion('{{ $esp->occurrence_id }}')"
                                @class([
                                    'cursor-pointer transition-colors hover:bg-bg-main',
                                    'bg-science-blue/5' => $seleccionado,
                                ])
                            >
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        @checked($seleccionado)
                                        wire:click.stop="toggleSeleccion('{{ $esp->occurrence_id }}')"
                                        class="size-4 rounded border-border cursor-pointer accent-science-blue"
                                    />
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-text-secondary">
                                    {{ $esp->occurrence_id }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-serif italic text-sm text-text-primary">{{ $esp->scientific_name ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell text-xs text-text-secondary">
                                    {{ $esp->type_status ?? '—' }}
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-sm text-text-secondary">
                                    {{ $esp->family ?? '—' }}
                                </td>
                                <td class="px-4 py-3 hidden xl:table-cell">
                                    @if($esp->occurrence_status)
                                        <x-catalogopublico::occurrence-status-badge :status="$esp->occurrence_status" />
                                    @else
                                        <span class="text-text-secondary text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2 text-text-secondary">
                                        <flux:icon name="check-circle" class="size-8 text-success opacity-60" />
                                        <span class="text-sm">Todos los especímenes ya están sincronizados.</span>
                                        <flux:button
                                            :href="route('divulgacion.index')"
                                            variant="primary"
                                            size="sm"
                                            wire:navigate
                                        >
                                            Ver tabla de divulgación
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            @if($totalPaginas > 1)
                <div class="flex items-center justify-between">
                    <span class="text-xs text-text-secondary">
                        Página <strong class="text-text-primary">{{ $pagina }}</strong> de <strong class="text-text-primary">{{ $totalPaginas }}</strong>
                        · <strong class="text-text-primary tabular-nums">{{ $totalPendientes }}</strong> especímenes pendientes
                    </span>
                    <div class="flex items-center gap-1">
                        <flux:button
                            wire:click="irAPagina({{ $pagina - 1 }})"
                            :disabled="$pagina === 1"
                            variant="ghost"
                            size="sm"
                            icon="chevron-left"
                        >
                            Anterior
                        </flux:button>
                        <flux:button
                            wire:click="irAPagina({{ $pagina + 1 }})"
                            :disabled="$pagina === $totalPaginas"
                            variant="ghost"
                            size="sm"
                            icon-trailing="chevron-right"
                        >
                            Siguiente
                        </flux:button>
                    </div>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button
                    wire:click="avanzarPaso"
                    :disabled="count($seleccionados) === 0"
                    variant="primary"
                    icon-trailing="arrow-right"
                >
                    Configurar visibilidad
                </flux:button>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- PASO 2: Configuración de visibilidad --}}
    {{-- ============================================================ --}}
    @if($paso === 2)
        <div class="flex flex-col gap-4">
            {{-- Layout: lista de especímenes + panel de configuración --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

                {{-- Lista de especímenes seleccionados --}}
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-border">
                        <p class="text-sm font-medium text-text-primary">Especímenes ({{ count($seleccionados) }})</p>
                    </div>
                    <div class="divide-y divide-border max-h-[480px] overflow-y-auto">
                        @foreach($seleccionados as $id)
                            @php
                                $esp = $especimenes->firstWhere('occurrence_id', $id);
                                $activo = $especimenActivoId === $id;
                                $config = $configuracionPorEspecimen[$id] ?? [];
                                $visibles = count(array_filter($config));
                                $total = count($config);
                            @endphp
                            <button
                                wire:key="paso2-esp-{{ $id }}"
                                wire:click="setEspecimenActivo('{{ $id }}')"
                                type="button"
                                @class([
                                    'w-full text-left px-4 py-3 hover:bg-bg-main transition-colors',
                                    'bg-science-blue/5 border-l-2 border-l-science-blue' => $activo,
                                ])
                            >
                                <p class="text-xs font-mono text-text-secondary truncate">{{ $id }}</p>
                                @if($esp)
                                    <p class="text-sm font-serif italic truncate">{{ $esp->scientific_name ?? '—' }}</p>
                                @endif
                                <div class="mt-1.5">
                                    <x-catalogopublico::visibility-progress
                                        :visibles="$visibles"
                                        :total="$total"
                                    />
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Panel de configuración del espécimen activo --}}
                <div class="lg:col-span-2 rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    @if($especimenActivoId && isset($configuracionPorEspecimen[$especimenActivoId]))
                        <div class="px-4 py-3 border-b border-border flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <p class="text-sm font-medium text-text-primary">Configuración de campos</p>
                                <p class="text-xs font-mono text-text-secondary">{{ $especimenActivoId }}</p>
                            </div>
                            <div class="flex gap-2">
                                <flux:button
                                    wire:click="habilitarTodo('{{ $especimenActivoId }}')"
                                    variant="ghost"
                                    size="sm"
                                    icon="eye"
                                >
                                    Habilitar todo
                                </flux:button>
                                @if(count($seleccionados) > 1)
                                    <flux:tooltip content="Copiar esta configuración a todos los especímenes seleccionados">
                                        <flux:button
                                            wire:click="copiarConfigATodos"
                                            variant="ghost"
                                            size="sm"
                                            icon="document-duplicate"
                                        >
                                            Copiar a todos
                                        </flux:button>
                                    </flux:tooltip>
                                @endif
                            </div>
                        </div>

                        <div class="divide-y divide-border max-h-[440px] overflow-y-auto">
                            @foreach($grupos as $tituloGrupo => $campos)
                                <x-catalogopublico::field-group :titulo="$tituloGrupo" class="py-1">
                                    @foreach($campos as $campo)
                                        <x-catalogopublico::visibility-toggle
                                            wire:key="campo-{{ $especimenActivoId }}-{{ $campo['key'] }}"
                                            :field="'configuracionPorEspecimen.' . $especimenActivoId . '.' . $campo['key']"
                                            :label="$campo['label']"
                                            :checked="$configuracionPorEspecimen[$especimenActivoId][$campo['key']] ?? true"
                                            :sensible="$campo['sensible']"
                                            wire:click="toggleCampo('{{ $especimenActivoId }}', '{{ $campo['key'] }}')"
                                        />
                                    @endforeach
                                </x-catalogopublico::field-group>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center gap-2 py-12 text-text-secondary">
                            <flux:icon name="cursor-arrow-rays" class="size-8 opacity-40" />
                            <span class="text-sm">Seleccione un espécimen de la lista</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <flux:button wire:click="retrocederPaso" variant="ghost" icon="arrow-left">
                    Atrás
                </flux:button>
                <flux:button
                    wire:click="sincronizar"
                    wire:loading.attr="disabled"
                    variant="primary"
                    icon="cloud-arrow-up"
                >
                    <span wire:loading.remove wire:target="sincronizar">
                        Sincronizar {{ count($seleccionados) }} espécimen(es)
                    </span>
                    <span wire:loading wire:target="sincronizar">Sincronizando…</span>
                </flux:button>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- PASO 3: Resultado --}}
    {{-- ============================================================ --}}
    @if($paso === 3)
        <div class="flex flex-col items-center gap-6 py-8">
            <div class="flex flex-col items-center gap-3 text-center">
                <div class="flex items-center justify-center size-16 rounded-full bg-success/10">
                    <flux:icon name="check-circle" class="size-8 text-success" />
                </div>
                <h2 class="font-display text-xl font-semibold text-text-primary">
                    Sincronización completada
                </h2>
                <p class="text-sm text-text-secondary">
                    {{ count($occurrenceIDsActualizados) }} espécimen(es) actualizados en el catálogo público.
                </p>
            </div>

            @if(count($occurrenceIDsActualizados) > 0)
                <div class="w-full max-w-md rounded-lg border border-border bg-surface overflow-hidden">
                    <div class="px-4 py-3 border-b border-border">
                        <p class="text-sm font-medium text-text-primary">Registros actualizados</p>
                    </div>
                    <ul class="divide-y divide-border max-h-64 overflow-y-auto">
                        @foreach($occurrenceIDsActualizados as $id)
                            <li wire:key="resultado-{{ $id }}" class="flex items-center gap-2 px-4 py-2.5">
                                <flux:icon name="check-circle" class="size-4 text-success shrink-0" />
                                <span class="text-sm font-mono text-text-secondary">{{ $id }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex gap-3">
                <flux:button
                    :href="route('divulgacion.index')"
                    variant="primary"
                    icon="table-cells"
                    wire:navigate
                >
                    Ver tabla de divulgación
                </flux:button>
                <flux:button
                    wire:click="$set('paso', 1); $set('seleccionados', []); $set('configuracionPorEspecimen', []); $set('occurrenceIDsActualizados', [])"
                    variant="ghost"
                    icon="arrow-path"
                >
                    Nueva sincronización
                </flux:button>
            </div>
        </div>
    @endif
</div>
