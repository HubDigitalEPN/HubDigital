<flux:main>
<div class="flex flex-col gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Tabla de Divulgación</h1>
            <p class="text-sm text-text-secondary">Especímenes publicados en el catálogo público</p>
        </div>
        <flux:button
            :href="route('admin.divulgacion.sincronizar')"
            icon="cloud-arrow-up"
            variant="primary"
            wire:navigate
        >
            Sincronizar especímenes
        </flux:button>
    </div>

    @if($configGuardada)
        <flux:callout variant="success" icon="check-circle">
            Configuración de visibilidad guardada correctamente.
        </flux:callout>
    @endif

    {{-- Search --}}
    <div class="flex items-center gap-3">
        <flux:input
            wire:model.live.debounce.300ms="buscar"
            icon="magnifying-glass"
            placeholder="Buscar por nombre científico, occurrenceID o familia…"
            class="max-w-sm"
        />
        @if($buscar !== '')
            <flux:button wire:click="$set('buscar', '')" variant="ghost" size="sm" icon="x-mark">
                Limpiar
            </flux:button>
        @endif
    </div>

    {{-- Table --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column style="padding-inline-start:1rem;width:11rem">occurrenceID</flux:table.column>
                <flux:table.column>Nombre científico</flux:table.column>
                <flux:table.column class="hidden md:table-cell" style="width:8rem">Type Status</flux:table.column>
                <flux:table.column class="hidden lg:table-cell">Familia</flux:table.column>
                <flux:table.column class="hidden xl:table-cell">Estado</flux:table.column>
                <flux:table.column style="width:9rem">Campos visibles</flux:table.column>
                <flux:table.column align="end" style="width:5rem">Acción</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($especimenes as $esp)
                    <flux:table.row wire:key="{{ $esp->occurrence_id }}">
                        <flux:table.cell class="font-mono text-xs text-text-secondary" style="padding-inline-start:1rem">
                            {{ $esp->occurrence_id }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-serif italic text-sm text-text-primary">
                                {{ $esp->scientific_name ?? '—' }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell text-xs text-text-secondary">
                            {{ $esp->type_status ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell text-sm text-text-secondary">
                            {{ $esp->family ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="hidden xl:table-cell">
                            @if($esp->occurrence_status)
                                <x-catalogopublico::occurrence-status-badge :status="$esp->occurrence_status" />
                            @else
                                <span class="text-text-secondary text-xs">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="min-w-32">
                            <x-catalogopublico::visibility-progress
                                :visibles="(int)$esp->campos_visibles"
                                :total="$totalCampos"
                            />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button
                                wire:click="abrirConfiguracion('{{ $esp->occurrence_id }}')"
                                variant="ghost"
                                size="sm"
                                icon="adjustments-horizontal"
                            >
                                Config.
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-text-secondary">
                                <flux:icon name="table-cells" class="size-8 opacity-40" />
                                <span class="text-sm">No hay especímenes divulgados aún.</span>
                                <flux:button
                                    :href="route('admin.divulgacion.sincronizar')"
                                    variant="primary"
                                    size="sm"
                                    wire:navigate
                                >
                                    Sincronizar ahora
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Pagination --}}
    @if($especimenes->hasPages())
        <div class="flex justify-center">
            {{ $especimenes->links() }}
        </div>
    @endif

    {{-- Modal: configurar visibilidad --}}
    <flux:modal
        wire:model="modalConfigAbierto"
        name="config-visibilidad"
        class="max-w-lg w-full"
    >
        <div class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Configurar visibilidad</flux:heading>
                @if($occurrenceIDActivo)
                    <p class="text-xs text-text-secondary font-mono mt-1">{{ $occurrenceIDActivo }}</p>
                @endif
            </div>

            <p class="text-sm text-text-secondary">
                Seleccione los campos que serán visibles en el catálogo público.
            </p>

            @php
                $grupos = [
                    'Identificación' => [
                        ['key' => 'occurrenceIDVisible', 'label' => 'occurrenceID', 'sensible' => false],
                    ],
                    'Taxonomía' => [
                        ['key' => 'scientificNameVisible', 'label' => 'Nombre científico', 'sensible' => false],
                        ['key' => 'familyVisible', 'label' => 'Familia', 'sensible' => false],
                        ['key' => 'genusVisible', 'label' => 'Género', 'sensible' => false],
                    ],
                    'Registro' => [
                        ['key' => 'individualCountVisible', 'label' => 'Cantidad individuos', 'sensible' => false],
                        ['key' => 'typeStatusVisible', 'label' => 'Tipo de estatus', 'sensible' => false],
                        ['key' => 'typeNotesVisible', 'label' => 'Notas de tipo', 'sensible' => true],
                        ['key' => 'specimenNotesVisible', 'label' => 'Notas del espécimen', 'sensible' => true],
                        ['key' => 'occurrenceStatusVisible', 'label' => 'Estado de ocurrencia', 'sensible' => false],
                    ],
                    'Recolección' => [
                        ['key' => 'samplingProtocolVisible', 'label' => 'Protocolo de muestreo', 'sensible' => false],
                        ['key' => 'recordedByVisible', 'label' => 'Registrado por', 'sensible' => true],
                    ],
                    'Localización' => [
                        ['key' => 'countryVisible', 'label' => 'País', 'sensible' => false],
                        ['key' => 'localityNameVisible', 'label' => 'Localidad', 'sensible' => true],
                        ['key' => 'decimalLatitudeVisible', 'label' => 'Latitud decimal', 'sensible' => true],
                        ['key' => 'decimalLongitudeVisible', 'label' => 'Longitud decimal', 'sensible' => true],
                    ],
                ];
            @endphp

            <div class="divide-y divide-border rounded-lg border border-border overflow-hidden">
                @foreach($grupos as $titulo => $campos)
                    <x-catalogopublico::field-group :titulo="$titulo">
                        @foreach($campos as $campo)
                            <x-catalogopublico::visibility-toggle
                                :field="'configuracionEditando.' . $campo['key']"
                                :label="$campo['label']"
                                :checked="$configuracionEditando[$campo['key']] ?? true"
                                :sensible="$campo['sensible']"
                                wire:click="toggleFlag('{{ $campo['key'] }}')"
                            />
                        @endforeach
                    </x-catalogopublico::field-group>
                @endforeach
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button wire:click="cerrarConfiguracion" variant="ghost">
                    Cancelar
                </flux:button>
                <flux:button
                    wire:click="guardarConfiguracion"
                    wire:loading.attr="disabled"
                    variant="primary"
                    icon="check"
                >
                    <span wire:loading.remove wire:target="guardarConfiguracion">Guardar cambios</span>
                    <span wire:loading wire:target="guardarConfiguracion">Guardando…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
</flux:main>
