<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Gabinetes</flux:heading>
        <flux:button icon="plus" variant="primary" class="w-full sm:w-auto" wire:click="abrirModal">
            Nuevo Gabinete
        </flux:button>
    </div>

    @if($successMessage)
        <flux:callout variant="success" dismissible>
            {{ $successMessage }}
        </flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>
            {{ $errorMessage }}
        </flux:callout>
    @endif

    {{-- Tabla (desktop) --}}
    <div class="hidden md:block rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">Código</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Nombre</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Ranuras</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($gabinetes as $gabinete)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-medium text-text-primary">{{ $gabinete['codigo'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $gabinete['nombre'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $gabinete['totalRanuras'] }}</td>
                            <td class="px-4 py-3">
                                @if($gabinete['activo'])
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-success text-white">Activo</span>
                                @else
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-border text-text-primary">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="eye"
                                        :href="route('inventario.gabinetes.show', $gabinete['id'])"
                                        wire:navigate
                                    >
                                        Ver
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="pencil"
                                        wire:click="abrirEditModal('{{ $gabinete['id'] }}')"
                                    >
                                        Editar
                                    </flux:button>
                                    @if($gabinete['activo'])
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="x-circle"
                                            wire:click="desactivarGabinete('{{ $gabinete['id'] }}')"
                                            wire:confirm="¿Desactivar el gabinete {{ $gabinete['codigo'] }}? Seguirá visible en el historial."
                                            class="text-error hover:text-error"
                                        >
                                            Desactivar
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-text-primary">
                                No hay gabinetes registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tarjetas (móvil) --}}
    <div class="md:hidden space-y-3">
        @forelse($gabinetes as $gabinete)
            <div class="rounded-lg border border-border bg-surface p-4 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <span class="font-medium text-text-primary">{{ $gabinete['codigo'] }}</span>
                    @if($gabinete['activo'])
                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-success text-white">Activo</span>
                    @else
                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-border text-text-primary">Inactivo</span>
                    @endif
                </div>
                <dl class="space-y-1.5 text-sm">
                    <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Nombre">
                        {{ $gabinete['nombre'] }}
                    </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Ranuras">
                        {{ $gabinete['totalRanuras'] }}
                    </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                </dl>
                <div class="flex flex-wrap gap-2 pt-1">
                    <flux:button
                        variant="ghost"
                        icon="eye"
                        :href="route('inventario.gabinetes.show', $gabinete['id'])"
                        wire:navigate
                    >
                        Ver
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        icon="pencil"
                        wire:click="abrirEditModal('{{ $gabinete['id'] }}')"
                    >
                        Editar
                    </flux:button>
                    @if($gabinete['activo'])
                        <flux:button
                            variant="ghost"
                            icon="x-circle"
                            wire:click="desactivarGabinete('{{ $gabinete['id'] }}')"
                            wire:confirm="¿Desactivar el gabinete {{ $gabinete['codigo'] }}? Seguirá visible en el historial."
                            class="text-error hover:text-error"
                        >
                            Desactivar
                        </flux:button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-border p-8 text-center text-text-primary">
                No hay gabinetes registrados.
            </div>
        @endforelse
    </div>

    {{-- Modal: Crear gabinete --}}
    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nuevo gabinete</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Código</flux:label>
                <flux:input wire:model="codigo" placeholder="GAB-01" />
                <flux:error name="codigo" />
            </flux:field>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="nombre" placeholder="Gabinete Principal" />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>Total de ranuras</flux:label>
                <flux:input type="number" wire:model="totalRanuras" min="1" max="25" />
                <flux:error name="totalRanuras" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="crearGabinete" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="crearGabinete">Crear Gabinete</span>
                    <span wire:loading wire:target="crearGabinete">Creando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Editar gabinete --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-lg">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar gabinete</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="editNombre" />
                <flux:error name="editNombre" />
            </flux:field>

            <flux:field>
                <flux:label>Total de ranuras</flux:label>
                <flux:input type="number" wire:model="editTotalRanuras" min="1" max="25" />
                <flux:description>No puede ser menor al número de ranuras ya configuradas.</flux:description>
                <flux:error name="editTotalRanuras" />
            </flux:field>

            @if(count($editRanuras) > 0)
                <div class="border border-border rounded-lg overflow-hidden">
                    <div class="px-3 py-2 bg-bg-main border-b border-border">
                        <span class="text-xs font-medium text-text-secondary uppercase tracking-wide">Actividad de ranuras</span>
                    </div>
                    <div class="divide-y divide-border">
                        @foreach($editRanuras as $ranura)
                            <div class="flex items-center justify-between px-3 py-2">
                                <span class="text-sm text-text-primary">Ranura {{ $ranura['numeroRanura'] }}</span>
                                <flux:button
                                    size="sm"
                                    variant="{{ $ranura['activa'] ? 'ghost' : 'filled' }}"
                                    wire:click="toggleRanuraActiva('{{ $ranura['id'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleRanuraActiva('{{ $ranura['id'] }}')"
                                >
                                    @if($ranura['activa'])
                                        <flux:icon name="check-circle" class="size-3.5 text-success" />
                                        <span class="text-success">Activa</span>
                                    @else
                                        <flux:icon name="x-circle" class="size-3.5 text-text-secondary" />
                                        <span class="text-text-secondary">Inactiva</span>
                                    @endif
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="actualizarGabinete" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="actualizarGabinete">Guardar</span>
                    <span wire:loading wire:target="actualizarGabinete">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
