<flux:main class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Gabinetes</flux:heading>
        <flux:button icon="plus" variant="primary" wire:click="abrirModal">
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

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
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
                        <td class="px-4 py-3 font-medium text-text-primary dark:text-text-primary">{{ $gabinete['codigo'] }}</td>
                        <td class="px-4 py-3 text-text-primary dark:text-text-primary">{{ $gabinete['nombre'] }}</td>
                        <td class="px-4 py-3 text-text-primary dark:text-text-primary">{{ $gabinete['totalRanuras'] }}</td>
                        <td class="px-4 py-3">
                            @if($gabinete['activo'])
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-success text-white">Activo</span>
                            @else
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-border text-text-primary">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                :href="route('admin.inventario.gabinetes.show', $gabinete['id'])"
                                wire:navigate
                            >
                                Ver
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-text-primary dark:text-text-primary">
                            No hay gabinetes registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary dark:text-text-primary">Nuevo Gabinete</flux:heading>

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
</flux:main>
