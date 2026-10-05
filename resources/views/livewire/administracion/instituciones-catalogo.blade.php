<div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">Instituciones</flux:heading>
            <p class="text-sm text-text-secondary">{{ $activas }} disponibles para el depositante en el paso 4.</p>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nueva">Nueva institución</flux:button>
    </div>

    @if(session('institucion-guardada'))
        <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 px-3 py-2 text-sm text-text-primary">{{ session('institucion-guardada') }}</div>
    @endif

    <div class="rounded-xl border border-border bg-white p-3 shadow-sm">
        <div class="mb-3 max-w-sm">
            <flux:input wire:model.live.debounce.300ms="busqueda" icon="magnifying-glass" placeholder="Buscar institución" aria-label="Buscar institución" />
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
            @forelse($instituciones as $institucion)
                <div wire:key="institucion-{{ $institucion->id }}" class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-border px-3 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-text-primary" title="{{ $institucion->nombre }}">{{ $institucion->nombre }}</p>
                        <p class="text-xs {{ $institucion->activo ? 'text-science-blue' : 'text-text-secondary' }}">{{ $institucion->activo ? 'Disponible' : 'Desactivada' }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" aria-label="Editar {{ $institucion->nombre }}" wire:click="editar({{ $institucion->id }})" />
                        <flux:button size="xs" variant="ghost" :icon="$institucion->activo ? 'pause' : 'arrow-path'" :aria-label="$institucion->activo ? 'Desactivar '.$institucion->nombre : 'Activar '.$institucion->nombre" wire:click="cambiarEstado({{ $institucion->id }})" />
                    </div>
                </div>
            @empty
                <p class="py-5 text-sm text-text-secondary">No se encontraron instituciones.</p>
            @endforelse
        </div>
        <div class="mt-3">{{ $instituciones->links() }}</div>
    </div>

    <section class="rounded-xl border border-border bg-white p-4" aria-labelledby="instituciones-pendientes">
        <flux:heading id="instituciones-pendientes" size="lg">Nombres por identificar</flux:heading>
        <p class="mt-1 text-sm text-text-secondary">Estos textos se conservan como referencia y requieren confirmar la entidad antes de añadirla al catálogo.</p>
        <flux:input class="mt-3" wire:model.live.debounce.300ms="busquedaPendientes" label="Filtrar nombres por identificar" />
        <div class="mt-3 space-y-3">
            @forelse($pendientes as $pendiente)
                <div class="rounded-lg border border-border p-3">
                    <p class="text-sm font-medium text-text-primary">{{ $pendiente->valor_original }}</p>
                    <p class="text-sm text-text-secondary">{{ $pendiente->motivo }}</p>
                    <p class="text-xs text-text-secondary">Filas del Excel: {{ $pendiente->filas_excel ?: 'Sin referencia de fila' }}</p>
                </div>
            @empty
                <p class="text-sm text-text-secondary">No hay nombres pendientes con este filtro.</p>
            @endforelse
        </div>
    </section>

    <flux:modal name="institucion-editor" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editandoId ? 'Editar institución' : 'Nueva institución' }}</flux:heading>
                <p class="mt-1 text-xs text-text-secondary">El nombre aparecerá en la lista del depositante.</p>
            </div>
            <flux:input wire:model="nombre" label="Nombre" maxlength="160" autofocus />
            <flux:error name="nombre" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
