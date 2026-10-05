<div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">Cargos</flux:heading>
            <p class="text-sm text-text-secondary">{{ $activos }} disponibles para el depositante en el paso 4.</p>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nueva">Nuevo cargo</flux:button>
    </div>

    @if(session('cargo-guardado'))
        <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 px-3 py-2 text-sm text-text-primary">{{ session('cargo-guardado') }}</div>
    @endif

    <div class="rounded-xl border border-border bg-white p-3 shadow-sm">
        <div class="mb-3 max-w-sm">
            <flux:input wire:model.live.debounce.300ms="busqueda" icon="magnifying-glass" placeholder="Buscar cargo" aria-label="Buscar cargo" />
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
            @forelse($cargos as $cargo)
                <div wire:key="cargo-{{ $cargo->id }}" class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-border px-3 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-text-primary" title="{{ $cargo->nombre }}">{{ $cargo->nombre }}</p>
                        <p class="text-xs {{ $cargo->activo ? 'text-science-blue' : 'text-text-secondary' }}">{{ $cargo->activo ? 'Disponible' : 'Desactivado' }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" aria-label="Editar {{ $cargo->nombre }}" wire:click="editar({{ $cargo->id }})" />
                        <flux:button size="xs" variant="ghost" :icon="$cargo->activo ? 'pause' : 'arrow-path'" :aria-label="$cargo->activo ? 'Desactivar '.$cargo->nombre : 'Activar '.$cargo->nombre" wire:click="cambiarEstado({{ $cargo->id }})" />
                    </div>
                </div>
            @empty
                <p class="py-5 text-sm text-text-secondary">No se encontraron cargos.</p>
            @endforelse
        </div>
        <div class="mt-3">{{ $cargos->links() }}</div>
    </div>

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
