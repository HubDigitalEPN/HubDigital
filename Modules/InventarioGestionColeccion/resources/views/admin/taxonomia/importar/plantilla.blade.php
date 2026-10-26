<div class="mx-auto max-w-6xl space-y-5 px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-semibold">Plantilla del catálogo</h1><a href="{{ route('inventario.taxonomia.importar') }}" class="hub-ig-button">Volver a importar</a></div>
    @if($error)<p role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-red-900">{{ $error }}</p>@endif
    @if($mensaje)<p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-green-900">{{ $mensaje }}</p>@endif
    <section class="space-y-4 rounded border border-border bg-white p-5">
        <a href="{{ route('inventario.taxonomia.importar.plantilla') }}" class="hub-ig-button">Modificación/visualización plantilla original</a>

        <label for="plantilla-catalogo-modificada" class="block font-semibold">Plantilla modificada (.xlsx)</label>
        <input id="plantilla-catalogo-modificada" type="file" wire:model="archivo" accept=".xlsx" class="block w-full rounded border border-border p-3"/><flux:error name="archivo"/>
        <flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled" wire:target="archivo,guardar" wire:confirm="Se actualizarán la plantilla y sus reglas para las próximas importaciones. ¿Continuar?">Subir plantilla modificada</flux:button>
        <p wire:loading wire:target="archivo,guardar" role="status">Procesando plantilla…</p>
    </section>
    <x-grid-frame label="Columnas y guía del catálogo"><table><thead><tr><th>Campo</th><th>Columna</th><th>Descripción y ejemplo</th><th>Requisito</th><th>Formato</th></tr></thead><tbody>@foreach($definicion['columnas'] as $c)<tr><td>{{ $c['campo'] }}</td><td>{{ $c['cabecera'] }}</td><td>{{ $c['descripcion'] }}</td><td>{{ $c['requisito'] }}</td><td>{{ $c['formato'] }}</td></tr>@endforeach</tbody></table></x-grid-frame>
</div>
