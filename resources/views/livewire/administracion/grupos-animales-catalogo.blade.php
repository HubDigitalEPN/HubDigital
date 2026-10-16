<div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Grupos animales</flux:heading>
            <p class="text-sm text-text-secondary">Opciones del paso 4. El código científico y su rango siguen la <a class="text-science-blue underline" href="https://www.gbif.org/dataset/d7dddbf4-2cf0-4f39-9b2a-bb099caae36c" target="_blank" rel="noopener">taxonomía de GBIF</a>.</p>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nuevo">Nuevo grupo</flux:button>
    </div>
    <div class="rounded-xl border border-border bg-white p-3 shadow-sm">
        <div class="mb-3 max-w-xs"><flux:input wire:model.live.debounce.300ms="busqueda" icon="magnifying-glass" placeholder="Buscar grupo" aria-label="Buscar grupo" /></div>
        <div class="grid gap-2 sm:grid-cols-2">
            @forelse($grupos as $grupo)
                <div wire:key="grupo-{{ $grupo->codigo }}" class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-border px-3 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-text-primary">{{ $grupo->nombre }}</p>
                        <p class="text-xs text-text-secondary">{{ $grupo->codigo }} · {{ $grupo->rango_referencia }} · {{ $grupo->activo ? 'Disponible' : 'Desactivado' }}</p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" :aria-label="'Editar '.$grupo->nombre" wire:click="editar('{{ $grupo->codigo }}')" />
                        <flux:button size="xs" variant="ghost" :icon="$grupo->activo ? 'trash' : 'arrow-path'" :aria-label="($grupo->activo ? 'Desactivar ' : 'Restaurar ').$grupo->nombre" wire:click="cambiarEstado('{{ $grupo->codigo }}')" />
                    </div>
                </div>
            @empty
                <p class="py-5 text-sm text-text-secondary">No se encontraron grupos.</p>
            @endforelse
        </div>
    </div>
    <flux:modal name="grupo-animal-editor" class="w-full max-w-md">
        <div class="space-y-3">
            <flux:heading size="lg">{{ $editandoCodigo ? 'Editar grupo' : 'Nuevo grupo' }}</flux:heading>
            <p class="text-xs text-text-secondary">Confirma el nombre científico y el rango en GBIF antes de agregar un grupo.</p>
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
