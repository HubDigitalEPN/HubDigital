<div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Cargos</flux:heading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nueva">Nuevo cargo</flux:button>
    </div>

    @if(session('cargo-guardado'))
        <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 px-3 py-2 text-sm text-text-primary">{{ session('cargo-guardado') }}</div>
    @endif

    <x-interactive-grid :rows="$cargos" label="Cargos" :columns="['Nombre', 'Estado', 'Acciones']">
        <thead><tr><x-grid-heading field="nombre">Nombre</x-grid-heading><x-grid-heading field="estado">Estado</x-grid-heading><x-grid-heading>Acciones</x-grid-heading></tr></thead>
        <tbody>
            @forelse($cargos as $cargo)
                <tr wire:key="cargo-{{ $cargo->id }}"><td>{{ $cargo->nombre }}</td><td>{{ $cargo->activo ? 'Disponible' : 'Desactivado' }}</td><td>                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" aria-label="Editar {{ $cargo->nombre }}" wire:click="editar({{ $cargo->id }})" />
                        <flux:button size="xs" variant="ghost" :icon="$cargo->activo ? 'pause' : 'arrow-path'" :aria-label="$cargo->activo ? 'Desactivar '.$cargo->nombre : 'Activar '.$cargo->nombre" wire:click="cambiarEstado({{ $cargo->id }})" />
                    </div></td></tr>
            @empty
                <tr><td colspan="3">No se encontraron resultados.</td></tr>
            @endforelse
        </tbody>
    </x-interactive-grid>

    <flux:modal name="cargo-editor" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editandoId ? 'Editar cargo' : 'Nuevo cargo' }}</flux:heading>
                <p class="mt-1 text-xs text-text-secondary">El nombre aparecerá en la lista del depositante.</p>
            </div>
            <flux:input wire:model="nombre" label="Nombre" maxlength="120" autofocus />
            <flux:error name="nombre" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
