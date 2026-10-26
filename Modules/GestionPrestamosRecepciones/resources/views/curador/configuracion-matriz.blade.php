<div class="space-y-5 p-4 sm:p-6" data-matriz-config x-data
    x-effect="if ($wire.editando) $nextTick(() => requestAnimationFrame(() => document.getElementById($wire.campoAnterior === null ? 'matriz-campo-dwc' : 'matriz-cabecera-excel')?.focus({ preventScroll: true })))">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><flux:heading size="xl" level="1">Columnas de la matriz de depósito</flux:heading></div>
        <flux:button wire:click="editar" variant="primary" icon="plus">Nueva columna</flux:button>
    </div>
    <flux:input wire:model.live.debounce.300ms="busqueda" label="Buscar columna" placeholder="Nombre, cabecera, alias o requisito" />

    <x-grid-frame label="Columnas de la matriz de depósito">
        <div class="max-h-[65vh] overflow-auto" tabindex="0" aria-label="Configuración de columnas de la matriz">
            <table class="w-full text-sm">
                <thead><tr><th scope="col">Cabecera Excel</th><th scope="col">Campo</th><th scope="col">Alias admitidos</th><th scope="col">Requisito</th><th scope="col">Acción</th></tr></thead>
                <tbody>
                    @forelse($columnas as $col)
                        <tr wire:key="matriz-columna-{{ $col['campo'] }}"><th scope="row">{{ $col['cabecera'] }}</th><td>{{ $col['campo'] }}</td><td>{{ implode(', ', $col['alias']) ?: '—' }}</td><td>{{ ucfirst($col['requisito']) }}@if($col['excepcion_campo'] ?? '')<span class="block text-xs text-text-secondary">Salvo {{ $col['excepcion_campo'] }} = {{ $col['excepcion_valor'] }}</span>@endif</td><td><button type="button" class="hub-ig-button" wire:click="editar(@js($col['campo']))" aria-label="Editar columna {{ $col['cabecera'] }}">Editar</button></td></tr>
                    @empty<tr><td colspan="5">No hay columnas con esa búsqueda.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </x-grid-frame>
    <flux:modal name="matriz-columna" wire:model="editando" class="w-full max-w-2xl">
        <form wire:submit="guardar" class="space-y-4">
            <flux:heading size="lg">{{ $campoAnterior === null ? 'Nueva columna' : 'Editar columna' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2"><flux:input id="matriz-campo-dwc" wire:model="columna.campo" label="Campo Darwin Core" :readonly="$campoAnterior !== null" /><flux:input id="matriz-cabecera-excel" wire:model="columna.cabecera" label="Cabecera Excel" /></div>
            <flux:input wire:model="aliasTexto" label="Otros nombres admitidos" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="columna.requisito" label="Requisito"><option value="obligatorio">Obligatorio</option><option value="recomendado">Recomendado</option><option value="opcional">Opcional</option><option value="interno">Uso interno</option><option value="predeterminado">Predeterminado</option></flux:select>
                <flux:select wire:model="columna.formato" label="Formato"><option value="texto">Texto o número</option><option value="fecha">Fecha</option></flux:select>
            </div>
            <flux:textarea wire:model="columna.descripcion" label="Descripción" rows="3" />
            <fieldset class="grid gap-4 sm:grid-cols-2"><legend class="mb-2 text-sm font-semibold">Excepción al requisito obligatorio (opcional)</legend><flux:input wire:model="columna.excepcion_campo" label="Campo de la condición" /><flux:input wire:model="columna.excepcion_valor" label="No exigir cuando sea igual a" /></fieldset>
            <div class="flex justify-end gap-3"><flux:button type="button" wire:click="$set('editando', false)">Cancelar</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="guardar">Guardar columna</flux:button></div>
        </form>
    </flux:modal>
</div>
