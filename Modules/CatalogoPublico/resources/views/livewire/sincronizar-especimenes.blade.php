<flux:main>
<div class="flex flex-col gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Sincronizar especímenes</h1>
            <p class="text-sm text-text-secondary">Seleccione especímenes y configure su visibilidad en el catálogo público</p>
        </div>
        <flux:button
            :href="route('admin.divulgacion.index')"
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
            <div class="flex items-center justify-between">
                <p class="text-sm text-text-secondary">
                    {{ count($seleccionados) }} espécimen(es) seleccionado(s)
                </p>
                <div class="flex gap-2">
                    <flux:button
                        wire:click="seleccionarTodos({{ json_encode($especimenes->pluck('occurrence_id')->all()) }})"
                        variant="ghost"
                        size="sm"
                    >
                        Seleccionar todos
                    </flux:button>
                    @if(count($seleccionados) > 0)
                        <flux:button wire:click="deseleccionarTodos" variant="ghost" size="sm">
                            Limpiar selección
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column style="padding-inline-start:1rem;width:2.5rem"></flux:table.column>
                        <flux:table.column style="padding-inline-start:0.75rem;width:11rem">occurrenceID</flux:table.column>
                        <flux:table.column>Nombre científico</flux:table.column>
                        <flux:table.column class="hidden md:table-cell" style="width:10rem">Localidad</flux:table.column>
                        <flux:table.column class="hidden lg:table-cell" style="width:9rem">Type Status</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($especimenes as $esp)
                            @php $seleccionado = in_array($esp->occurrence_id, $seleccionados, true); @endphp
                            <flux:table.row
                                wire:key="{{ $esp->occurrence_id }}"
                                wire:click="toggleSeleccion('{{ $esp->occurrence_id }}')"
                                @class([
                                    'cursor-pointer transition-colors',
                                    'bg-science-blue/5' => $seleccionado,
                                    'hover:bg-bg-main' => !$seleccionado,
                                ])
                            >
                                <flux:table.cell style="padding-inline-start:1rem">
                                    <input
                                        type="checkbox"
                                        @checked($seleccionado)
                                        wire:click.stop="toggleSeleccion('{{ $esp->occurrence_id }}')"
                                        class="size-4 rounded border-border cursor-pointer accent-science-blue"
                                    />
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-xs text-text-secondary" style="padding-inline-start:0.75rem">
                                    {{ $esp->occurrence_id }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="font-serif italic text-sm">{{ $esp->scientific_name ?? '—' }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="hidden md:table-cell text-sm text-text-secondary">
                                    {{ $esp->locality_name ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="hidden lg:table-cell text-xs text-text-secondary">
                                    {{ $esp->type_status ?? '—' }}
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="py-12 text-center">
                                    <div class="flex flex-col items-center gap-2 text-text-secondary">
                                        <flux:icon name="check-circle" class="size-8 text-success opacity-60" />
                                        <span class="text-sm">Todos los especímenes ya están sincronizados.</span>
                                        <flux:button
                                            :href="route('admin.divulgacion.index')"
                                            variant="primary"
                                            size="sm"
                                            wire:navigate
                                        >
                                            Ver tabla de divulgación
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

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
                            <li class="flex items-center gap-2 px-4 py-2.5">
                                <flux:icon name="check-circle" class="size-4 text-success shrink-0" />
                                <span class="text-sm font-mono text-text-secondary">{{ $id }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex gap-3">
                <flux:button
                    :href="route('admin.divulgacion.index')"
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
</flux:main>
