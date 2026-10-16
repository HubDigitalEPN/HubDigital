<div class="hub-workspace space-y-6 p-4 sm:p-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="hub-page-title">Localidades de Ecuador</h1></div>
        <flux:button wire:click="nueva" variant="primary" icon="plus">Nueva localidad</flux:button>
    </header>
    @if(session('localidad-guardada'))<p role="status" class="rounded-lg bg-success/10 p-3 text-sm text-success">{{ session('localidad-guardada') }}</p>@endif
    <x-interactive-grid :rows="$localidades" label="Localidades de Ecuador" :columns="['Localidad', 'Provincia', 'Parroquia / cantón', 'Estado', 'Acciones']">
        <x-slot:filters>        <flux:select x-model="filterValues.provinciaFiltro" data-grid-filter="provinciaFiltro" label="Provincia">
            <option value="">Todas las provincias</option>
            @foreach($provincias as $item)<option value="{{ $item['codigo'] }}">{{ $item['nombre'] }}</option>@endforeach
        </flux:select></x-slot:filters>
        <thead><tr><x-grid-heading field="nombre">Localidad</x-grid-heading><x-grid-heading field="provincia">Provincia</x-grid-heading><x-grid-heading field="parroquia">Parroquia / cantón</x-grid-heading><x-grid-heading field="estado">Estado</x-grid-heading><x-grid-heading>Acciones</x-grid-heading></tr></thead>
            <tbody>
                @forelse($localidades as $fila)
                    <tr wire:key="localidad-{{ $fila->codigo }}" class="border-t border-border">
                        <td class="p-3 font-medium">{{ $fila->nombre }}</td>
                        <td class="p-3">{{ $nombresProvincias[$fila->provincia_codigo] ?? '' }}</td>
                        <td class="p-3">{{ $fila->parroquia }} / {{ $fila->canton }}</td>
                        <td class="p-3">{{ $fila->activo ? 'Activa' : 'Inactiva' }}</td>
                        <td class="p-3"><div class="flex gap-2"><flux:button size="sm" wire:click="editar('{{ $fila->codigo }}')">Editar</flux:button><flux:button size="sm" wire:click="cambiarEstado('{{ $fila->codigo }}')">{{ $fila->activo ? 'Desactivar' : 'Activar' }}</flux:button></div></td>
                    </tr>
                @empty<tr><td colspan="5" class="p-5 text-text-secondary">No hay localidades para esta búsqueda.</td></tr>@endforelse
            </tbody>
    </x-interactive-grid>
    <flux:modal name="localidad-ecuador-editor" class="md:w-[32rem]">
        <form wire:submit="guardar" class="space-y-4">
            <flux:heading size="lg">{{ $editandoCodigo ? 'Editar localidad' : 'Nueva localidad' }}</flux:heading>
            <flux:select wire:model="provincia" label="Provincia" required>
                <option value="">Selecciona una provincia</option>
                @foreach($provincias as $item)<option value="{{ $item['codigo'] }}">{{ $item['nombre'] }}</option>@endforeach
            </flux:select>
            <flux:input wire:model="nombre" label="Nombre de la localidad" maxlength="254" required />
            <flux:input wire:model="parroquia" label="Parroquia" maxlength="254" />
            <flux:input wire:model="canton" label="Cantón de referencia" maxlength="160" />
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar localidad</flux:button>
        </form>
    </flux:modal>
</div>
