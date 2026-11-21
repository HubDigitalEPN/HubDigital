<div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-5 sm:px-6" data-directorio-instituciones @if($soloEditorCatalogo) wire:init="iniciarEditorCatalogo" @endif>
    @unless($soloEditorCatalogo)
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="2">Instituciones y depositantes</flux:heading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nueva">Nueva institución o depositante</flux:button>
    </div>

    @if(session('institucion-guardada'))
        <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 px-3 py-2 text-sm text-text-primary">{{ session('institucion-guardada') }}</div>
    @endif

    <x-interactive-grid :rows="$instituciones" label="Instituciones y depositantes" :columns="['Nombre', 'Tipo', 'Contacto', 'Solicitudes', 'Colección', 'Acciones']">
        <thead><tr><x-grid-heading field="nombre">Nombre</x-grid-heading><x-grid-heading field="tipo">Tipo</x-grid-heading><x-grid-heading field="contacto">Contacto</x-grid-heading><x-grid-heading field="estado">Solicitudes</x-grid-heading><x-grid-heading field="coleccion">Colección</x-grid-heading><x-grid-heading>Acciones</x-grid-heading></tr></thead>
        <tbody>
            @forelse($instituciones as $institucion)
                <tr wire:key="directorio-{{ $institucion->id }}" data-institucion-id="{{ $institucion->id }}"><td>{{ $institucion->nombre }}</td><td>{{ $institucion->tipo_label }}</td><td>{{ $institucion->contacto ?: '—' }}</td><td>{{ $institucion->estado }}</td><td>{{ $institucion->coleccion }}</td><td><div class="flex shrink-0 items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" :aria-label="'Editar '.$institucion->nombre" :wire:click="'editarDirectorio('.\Illuminate\Support\Js::from($institucion->id).')'" />
                        @if($institucion->institucion_id)
                        <flux:button size="xs" variant="ghost" :icon="$institucion->activo ? 'pause' : 'arrow-path'" :aria-label="$institucion->activo ? 'Desactivar '.$institucion->nombre : 'Activar '.$institucion->nombre" wire:click="cambiarEstado({{ $institucion->institucion_id }})" />
                        @endif
                        @if($institucion->entidad_id)
                        <flux:button size="xs" variant="ghost" icon="document-text" :aria-label="'Generar acta de entrega para '.$institucion->nombre" :wire:click="'generarActa('.\Illuminate\Support\Js::from($institucion->entidad_id).')'" />
                        @endif
                    </div></td></tr>
            @empty
                <tr><td colspan="6">No se encontraron resultados.</td></tr>
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

    @endunless
    @if($errorMessage)<flux:callout data-hub-feedback="danger" variant="danger">{{ $errorMessage }}</flux:callout>@endif
    <flux:modal name="institucion-editor" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $filaId ? 'Editar institución o depositante' : 'Nueva institución o depositante' }}</flux:heading>

            </div>
            <flux:input wire:model="nombre" label="Nombre" :maxlength="$usarEnSolicitudes || $editandoId ? 160 : 255" autofocus />
            <flux:error name="nombre" />
            <flux:select wire:model="tipo" label="Tipo"><option value="">Sin clasificar</option><option value="institucion">Institución</option><option value="persona">Persona</option></flux:select>
            <flux:input wire:model="contacto" label="Contacto (opcional)" maxlength="255" />
            <flux:checkbox wire:model="usarEnSolicitudes" label="Disponible en solicitudes de depósito" />
            <flux:checkbox wire:model="usarEnColeccion" label="Depositante en los registros de colección" :disabled="$entidadId !== null" />
            @if($entidadId)<p class="text-sm text-text-secondary">Se conserva la referencia de este depositante en la colección.</p>@endif
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showActaModal" name="acta-entrega-institucion" class="w-full max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">Acta de entrega generada</flux:heading>
            <p>El acta para <strong>{{ $actaEntidadNombre }}</strong> se generó correctamente.</p>
            @if($actaPdfRuta)<p class="break-all text-sm">Archivo guardado: {{ $actaPdfRuta }}</p>@endif
            <flux:button wire:click="$set('showActaModal', false)">Cerrar</flux:button>
        </div>
    </flux:modal>
</div>
