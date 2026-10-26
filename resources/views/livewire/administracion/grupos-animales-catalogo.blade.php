<div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Grupos animales</flux:heading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nuevo">Nuevo grupo</flux:button>
    </div>
    <x-interactive-grid :rows="$grupos" label="Grupos animales" :columns="['Nombre', 'Código', 'Rango', 'Estado', 'Acciones']">
        <thead><tr><x-grid-heading field="nombre">Nombre</x-grid-heading><x-grid-heading field="codigo">Código</x-grid-heading><x-grid-heading field="rango">Rango</x-grid-heading><x-grid-heading field="estado">Estado</x-grid-heading><x-grid-heading>Acciones</x-grid-heading></tr></thead>
        <tbody>
            @forelse($grupos as $grupo)
                <tr wire:key="grupo-{{ $grupo->codigo }}"><td>{{ $grupo->nombre }}</td><td>{{ $grupo->codigo }}</td><td>{{ $grupo->rango_referencia }}</td><td>{{ $grupo->activo ? 'Disponible' : 'Desactivado' }}</td><td>                    <div class="flex shrink-0 gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" :aria-label="'Editar '.$grupo->nombre" wire:click="editar('{{ $grupo->codigo }}')" />
                        <flux:button size="xs" variant="ghost" :icon="$grupo->activo ? 'trash' : 'arrow-path'" :aria-label="($grupo->activo ? 'Desactivar ' : 'Restaurar ').$grupo->nombre" wire:click="cambiarEstado('{{ $grupo->codigo }}')" />
                    </div></td></tr>
            @empty
                <tr><td colspan="5">No se encontraron resultados.</td></tr>
            @endforelse
        </tbody>
    </x-interactive-grid>

    <flux:modal name="grupo-animal-editor" class="w-full max-w-md">
        <div class="space-y-3">
            <flux:heading size="lg">{{ $editandoCodigo ? 'Editar grupo' : 'Nuevo grupo' }}</flux:heading>

            <flux:input wire:model="codigo" label="Código científico" maxlength="50" :disabled="$editandoCodigo !== null" />
            <flux:error name="codigo" />
            <flux:input wire:model="nombre" label="Nombre para el depositante" maxlength="160" />
            <flux:error name="nombre" />
            <div><label class="mb-1 block text-sm">Rango taxonómico</label><select wire:model="rango" aria-label="Rango taxonómico" class="min-h-9 w-full rounded-lg border border-border bg-white px-3 text-sm"><option value="phylum">Filo</option><option value="subphylum">Subfilo</option><option value="class">Clase</option><option value="order">Orden</option><option value="family">Familia</option></select></div>
            <flux:error name="rango" />
            <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close><flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Guardar</flux:button></div>
        </div>
    </flux:modal>
</div>
