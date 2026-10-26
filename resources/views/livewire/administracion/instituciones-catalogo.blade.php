<div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Instituciones</flux:heading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nueva">Nueva institución</flux:button>
    </div>

    @if(session('institucion-guardada'))
        <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 px-3 py-2 text-sm text-text-primary">{{ session('institucion-guardada') }}</div>
    @endif

    <x-interactive-grid :rows="$instituciones" label="Instituciones" :columns="['Nombre', 'Estado', 'Acciones']">
        <thead><tr><x-grid-heading field="nombre">Nombre</x-grid-heading><x-grid-heading field="estado">Estado</x-grid-heading><x-grid-heading>Acciones</x-grid-heading></tr></thead>
        <tbody>
            @forelse($instituciones as $institucion)
                <tr wire:key="institucion-{{ $institucion->id }}"><td>{{ $institucion->nombre }}</td><td>{{ $institucion->activo ? 'Disponible' : 'Desactivado' }}</td><td>                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" aria-label="Editar {{ $institucion->nombre }}" wire:click="editar({{ $institucion->id }})" />
                        <flux:button size="xs" variant="ghost" :icon="$institucion->activo ? 'pause' : 'arrow-path'" :aria-label="$institucion->activo ? 'Desactivar '.$institucion->nombre : 'Activar '.$institucion->nombre" wire:click="cambiarEstado({{ $institucion->id }})" />
                    </div></td></tr>
            @empty
                <tr><td colspan="3">No se encontraron resultados.</td></tr>
            @endforelse
        </tbody>
    </x-interactive-grid>

    <section class="rounded-xl border border-border bg-white p-4" aria-labelledby="instituciones-pendientes">
        <flux:heading id="instituciones-pendientes" size="lg">Nombres por identificar</flux:heading>

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
