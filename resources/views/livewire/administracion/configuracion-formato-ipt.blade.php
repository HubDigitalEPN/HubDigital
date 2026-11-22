<div class="hub-workspace space-y-5 p-4 sm:p-6">
    <header><h1 class="hub-page-title">Formato IPT (GBIF)</h1></header>

    <p class="text-sm"><a class="text-science-blue underline" href="{{ route('inventario.taxonomia.dataset.config') }}" wire:navigate>Configurar institución, licencia y tipo de registro del dataset</a></p>
    @error('formato')<p data-hub-feedback="error" role="alert" class="text-error">{{ $message }}</p>@enderror
    @if($mensaje)<p role="status" class="text-success">{{ $mensaje }}</p>@endif
    <div class="flex flex-wrap items-end gap-3">
        <flux:field><flux:label>Nueva columna Darwin Core</flux:label><select wire:model="nuevoCampo" class="rounded border border-border bg-surface p-2"><option value="">Seleccionar término…</option>@foreach($terminos as $t)@if(!in_array($t, array_column($columnas, 'campo'), true))<option value="{{ $t }}">{{ $t }}</option>@endif @endforeach</select></flux:field>
        <flux:button wire:click="agregar">Agregar columna</flux:button>
        <flux:button wire:click="restaurar" wire:confirm="Se preparará el perfil original de 69 columnas. Los cambios sólo se aplicarán al guardar. ¿Continuar?">Restaurar ejemplo EPN</flux:button>
        <flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled">Guardar formato</flux:button>
        <strong>{{ count($columnas) }} columnas</strong>
    </div>

    <datalist id="ipt-origenes">@foreach($origenes as $o)@if($o !== '')<option value="{{ $o }}"></option>@endif @endforeach</datalist>
    <div class="overflow-auto rounded border border-border"><x-grid-frame label="Formato de exportación IPT"><table class="w-full text-sm"><thead><tr class="bg-bg-main text-left"><th scope="col" class="w-14" data-grid-fixed="56" data-grid-label="Acciones"><span class="sr-only">Acciones</span></th><th scope="col" class="p-3">Orden</th><th scope="col" class="p-3">Cabecera IPT</th><th scope="col" class="p-3">Campo de origen</th><th scope="col" class="p-3">Formato</th><th scope="col" class="p-3">Ancho</th></tr></thead><tbody>
    @foreach($columnas as $i => $c)
        <tr wire:key="ipt-{{ $c['campo'] }}" class="border-t border-border">
            <td>
                <x-catalogo-menu-fila :id="'ipt-fila-'.hash('sha256', $c['campo'])" :etiqueta="$c['campo']">
                    <button type="button" role="menuitem" wire:click="mover({{ $i }}, -1)" aria-label="Subir {{ $c['campo'] }}" @disabled($i === 0)><flux:icon name="arrow-up" />Subir</button>
                    <button type="button" role="menuitem" wire:click="mover({{ $i }}, 1)" aria-label="Bajar {{ $c['campo'] }}" @disabled($loop->last)><flux:icon name="arrow-down" />Bajar</button>
                    <button type="button" role="menuitem" wire:click="quitar({{ $i }})" aria-label="Quitar {{ $c['campo'] }}" @disabled(in_array($c['campo'], ['occurrenceID','basisOfRecord','scientificName','eventDate'], true))><flux:icon name="trash" />Quitar</button>
                </x-catalogo-menu-fila>
            </td>
            <td class="p-2">{{ $i + 1 }}</td><th scope="row" class="p-2 font-mono text-left" data-copy-preserve>{{ $c['campo'] }}</th>
            <td class="p-2"><input list="ipt-origenes" wire:model="columnas.{{ $i }}.origen" aria-label="Origen de {{ $c['campo'] }}" placeholder="Sin origen: celda vacía" class="max-w-64 rounded border border-border bg-surface p-2" /></td>
            <td class="p-2"><select wire:model="columnas.{{ $i }}.formato" aria-label="Formato de {{ $c['campo'] }}" class="rounded border border-border bg-surface p-2">@foreach($formatos as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></td>
            <td class="p-2"><input type="number" min="5" max="80" wire:model="columnas.{{ $i }}.ancho" aria-label="Ancho de {{ $c['campo'] }}" class="w-20 rounded border border-border bg-surface p-2" /></td>
        </tr>
    @endforeach
    </tbody></table></x-grid-frame></div>
    <flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled">Guardar formato</flux:button>
</div>
