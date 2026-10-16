@props(['label' => 'Resultados'])
<section class="hub-ig" aria-label="{{ $label }}" data-hub-grid="specialized"
    x-id="['grid-columns-title', 'grid-sort-title']" x-data="hubInteractiveGrid([], false, @js($label))" :class="{'hub-ig-compact': compact}">
    <div class="hub-ig-toolbar">
        <x-grid-tools />
        <button type="button" class="hub-ig-button hub-ig-reset" x-on:click="reset()"><flux:icon name="arrow-uturn-left" /> Restablecer vista</button>
    </div>
    <div class="hub-ig-scroll" tabindex="0" role="region" aria-label="Filas de {{ $label }}">{{ $slot }}</div>
</section>
