<div class="space-y-4">
    @if($error)<p role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-red-900">{{ $error }}</p>@endif
    @if($mensaje)<p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-green-900">{{ $mensaje }}</p>@endif
    <x-grid-frame label="Columnas y guía del catálogo">
        <table>
            <thead><tr><th scope="col" class="w-14" data-grid-fixed="56" data-grid-label="Acciones"><span class="sr-only">Acciones</span></th><th scope="col">Campo</th><th scope="col">Cabecera Excel</th><th scope="col">Descripción y ejemplo</th><th scope="col">Requisito</th><th scope="col">Formato</th></tr></thead>
            <tbody>
                @foreach($definicion['columnas'] as $columna)
                    <tr wire:key="plantilla-{{ $columna['campo'] }}">
                        <td>
                            <x-catalogo-menu-fila :id="'plantilla-fila-'.hash('sha256', $columna['campo'])" :etiqueta="$columna['cabecera']">
                                <button type="button" role="menuitem" wire:click="editarColumna(@js($columna['campo']))"><flux:icon name="pencil" />Editar</button>
                            </x-catalogo-menu-fila>
                        </td>
                        <td>{{ $columna['campo'] }}</td>
                        <td>{{ $columna['cabecera'] }}</td>
                        <td>{{ $columna['descripcion'] }}</td>
                        <td>{{ ucfirst($columna['requisito']) }}</td>
                        <td>{{ ucfirst($columna['formato']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-grid-frame>

    <section class="space-y-3 rounded border border-border bg-surface p-4">
        <flux:button :href="route('inventario.taxonomia.importar.plantilla')" icon="arrow-down-tray">Descargar plantilla</flux:button>
        <label for="plantilla-catalogo-modificada" class="block font-semibold">Plantilla modificada (.xlsx)</label>
        <input id="plantilla-catalogo-modificada" type="file" wire:model="archivo" accept=".xlsx" class="block w-full rounded border border-border p-3" />
        <flux:error name="archivo" />
        <flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled" wire:target="archivo,guardar"
            wire:confirm="Se actualizarán la plantilla y sus reglas para las próximas importaciones. ¿Continuar?">Subir plantilla modificada</flux:button>
        <p wire:loading wire:target="archivo,guardar" role="status">Procesando plantilla…</p>
    </section>

    <flux:modal name="columna-plantilla-importacion" class="w-full max-w-2xl">
        <form wire:submit="guardarColumna" class="space-y-4">
            <flux:heading size="lg">Editar columna {{ $campoEdicion }}</flux:heading>
            <flux:input wire:model="cabecera" label="Cabecera Excel" required />
            <flux:textarea wire:model="descripcion" label="Descripción y ejemplo" required />
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="requisito" label="Requisito">
                    <option value="obligatorio">Obligatorio</option><option value="recomendado">Recomendado</option><option value="opcional">Opcional</option>
                </flux:select>
                <flux:select wire:model="formato" label="Formato">
                    <option value="texto">Texto o número</option><option value="fecha">Fecha</option>
                </flux:select>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
