<div class="space-y-6 p-6">
    <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">
        Asignación de Unit Trays
    </flux:heading>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    {{-- Paso 1: Unit trays en una caja disponible --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
        <flux:heading size="lg" class="text-blue-navy">1. Unit trays en una caja</flux:heading>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
            <flux:field class="flex-1">
                <flux:label>Caja disponible</flux:label>
                <flux:select wire:model.live="cajaSeleccionada" placeholder="Selecciona una caja...">
                    @foreach($cajas as $caja)
                        <flux:select.option value="{{ $caja['id'] }}">{{ $caja['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field class="w-full sm:w-40">
                <flux:label>Número de tray</flux:label>
                <flux:input type="number" min="1" wire:model="numeroNuevoTray" placeholder="Ej. 1" />
            </flux:field>

            <flux:button
                variant="primary"
                icon="plus"
                wire:click="crearUnitTray"
                class="min-h-[44px]"
                :disabled="$cajaSeleccionada === ''"
            >
                Crear unit tray
            </flux:button>
        </div>

        @if($cajaSeleccionada !== '')
            <div class="rounded-lg border border-border overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-blue-navy">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-white">N.°</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Subfamilia</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Género</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Especímenes</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($unitTrays as $tray)
                            <tr class="hover:bg-bg-main transition-colors {{ $unitTraySeleccionado === $tray['unitTrayId'] ? 'bg-bg-main' : '' }}">
                                <td class="px-4 py-3 font-medium text-text-primary">{{ $tray['numero'] }}</td>
                                <td class="px-4 py-3 text-text-primary">{{ $tray['subfamilia'] ?? '—' }}</td>
                                <td class="px-4 py-3 font-serif italic text-text-primary">{{ $tray['genero'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $tray['totalEspecimenes'] }}</td>
                                <td class="px-4 py-3">
                                    <flux:button
                                        size="sm"
                                        variant="filled"
                                        wire:click="seleccionarUnitTray('{{ $tray['unitTrayId'] }}')"
                                        class="min-h-[44px]"
                                    >
                                        Asignar especímenes
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-text-secondary">
                                    Esta caja no tiene unit trays todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Paso 2: Asignar especímenes al unit tray seleccionado --}}
    @if($unitTraySeleccionado !== '')
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
            <flux:heading size="lg" class="text-blue-navy">2. Especímenes a asignar</flux:heading>
            <p class="text-sm text-text-secondary">
                Marca los especímenes que pertenecen al unit tray seleccionado. La clasificación de la caja se recalcula automáticamente.
            </p>

            <div class="max-h-96 overflow-y-auto rounded-lg border border-border divide-y divide-border">
                @forelse($especimenes as $especimen)
                    <label class="flex items-center gap-3 px-4 py-3 hover:bg-bg-main cursor-pointer min-h-[44px]">
                        <flux:checkbox
                            wire:model="especimenesSeleccionados"
                            value="{{ $especimen['id'] }}"
                        />
                        <span class="font-medium text-text-primary">{{ $especimen['codigoCatalogo'] }}</span>
                        <span class="font-serif italic text-text-secondary">{{ $especimen['taxonNombre'] }}</span>
                        @if($especimen['unitTrayId'] && $especimen['unitTrayId'] !== $unitTraySeleccionado)
                            <span class="ml-auto text-xs text-warning">en otro unit tray</span>
                        @endif
                    </label>
                @empty
                    <div class="px-4 py-6 text-center text-text-secondary">
                        No hay especímenes registrados.
                    </div>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" icon="check" wire:click="asignarEspecimenes" class="min-h-[44px]">
                    Guardar asignación
                </flux:button>
            </div>
        </div>
    @endif
</div>
