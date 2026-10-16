@props(['rows', 'label', 'columns' => []])
<section {{ $attributes->class(['hub-ig']) }} aria-label="{{ $label }}" data-hub-grid="server"
    x-id="['grid-columns-title', 'grid-sort-title', 'grid-filter-title']" x-data="hubInteractiveGrid(@js($columns), true, @js($label))" :class="{'hub-ig-compact': compact}" :style="{'--ig-visible-columns': columns.length - hidden.length}">
    <div class="hub-ig-toolbar" role="group" aria-label="Herramientas de {{ $label }}">
        <form class="hub-ig-search" wire:submit="buscarGrilla">
            <flux:icon name="magnifying-glass" />
            <input type="search" wire:model="busqueda" placeholder="Buscar en todas las filas" aria-label="Buscar en {{ $label }}" data-testid="admin-user-search" />
            <button type="submit" class="hub-ig-button hub-ig-search-go">Ir</button>
        </form>
        <x-grid-tools :server="true"><x-slot:filters>{{ $filters ?? '' }}</x-slot:filters></x-grid-tools>
        <button type="button" class="hub-ig-button hub-ig-reset" wire:click="restablecerGrilla" x-on:click="reset()"><flux:icon name="arrow-uturn-left" /> Restablecer</button>
    </div>
    <div class="hub-ig-scroll" tabindex="0" role="region" aria-label="Filas de {{ $label }}">
        <table>{{ $slot }}</table>
    </div>
    <x-grid-pagination :paginator="$rows" :label="$label" />
</section>
