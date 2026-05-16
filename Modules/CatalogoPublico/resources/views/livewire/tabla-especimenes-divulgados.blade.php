<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-blue-navy">Tabla de divulgación</h1>
            <p class="text-sm text-text-secondary">Especímenes publicados en el catálogo público</p>
        </div>
        <flux:button
            :href="route('divulgacion.sincronizar')"
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
        <table class="w-full text-sm">
            <thead class="bg-blue-navy border-b border-border">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-white">Occurrence ID</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Nombre científico</th>
                    <th class="px-4 py-3 text-left font-medium text-white hidden md:table-cell">Type Status</th>
                    <th class="px-4 py-3 text-left font-medium text-white hidden lg:table-cell">Familia</th>
                    <th class="px-4 py-3 text-left font-medium text-white hidden xl:table-cell">Estado</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Campos visibles</th>
                    <th class="px-4 py-3 text-right font-medium text-white">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($especimenes as $especimen)
                    <tr wire:key="{{ $especimen->occurrence_id }}" class="hover:bg-bg-main transition-colors">
                        <td class="px-4 py-3 font-mono text-xs text-text-secondary">
                            {{ $especimen->occurrence_id }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-serif italic text-sm text-text-primary">
                                {{ $especimen->scientific_name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell text-xs text-text-secondary">
                            {{ @$especimen->type_status ?? '—' }}
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell text-sm text-text-secondary">
                            {{ $especimen->family ?? '—' }}
                        </td>
                        <td class="px-4 py-3 hidden xl:table-cell">
                            @if($especimen->occurrence_status)
                                <x-catalogopublico::occurrence-status-badge :status="$especimen->occurrence_status" />
                            @else
                                <span class="text-text-secondary text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 min-w-32">
                            <x-catalogopublico::visibility-progress
                                :visibles="(int)$especimen->campos_visibles"
                                :total="$totalCampos"
                            />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <flux:button
                                wire:click="abrirConfiguracion('{{ $especimen->occurrence_id }}')"
                                variant="ghost"
                                size="sm"
                                icon="adjustments-horizontal"
                                square
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-text-secondary">
                                <flux:icon name="table-cells" class="size-8 opacity-40" />
                                <span class="text-sm">No hay especímenes divulgados aún.</span>
                                <flux:button
                                    :href="route('divulgacion.sincronizar')"
                                    variant="primary"
                                    size="sm"
                                    wire:navigate
                                >
                                    Sincronizar ahora
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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
