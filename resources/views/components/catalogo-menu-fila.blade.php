@props(['id', 'etiqueta'])
<div class="flex items-center justify-center" x-data>
    <button type="button" id="{{ $id }}-invocador" popovertarget="{{ $id }}"
        class="hub-ig-button size-7 min-w-0 shrink-0 justify-center p-0!"
        data-catalogo-menu-fila aria-label="Acciones de {{ $etiqueta }}" aria-haspopup="menu">
        <flux:icon name="bars-3" class="size-4" />
    </button>
    <div id="{{ $id }}" popover="auto" class="hub-ig-menu" role="menu" aria-label="Acciones de {{ $etiqueta }}"
        x-on:toggle="if ($event.newState === 'open') {
            const ancla = $el.previousElementSibling.getBoundingClientRect();
            const menu = $el.getBoundingClientRect();
            $el.style.left = Math.max(8, Math.min(ancla.left, innerWidth - menu.width - 8)) + 'px';
            $el.style.top = (ancla.bottom + menu.height + 8 < innerHeight ? ancla.bottom + 4 : Math.max(8, ancla.top - menu.height - 4)) + 'px';
            $el.querySelector('[role=menuitem]:not(:disabled)')?.focus();
        }"
        x-on:keydown.arrow-down.prevent="const items = [...$el.querySelectorAll('[role=menuitem]:not(:disabled)')]; items[(items.indexOf(document.activeElement) + 1) % items.length]?.focus()"
        x-on:keydown.arrow-up.prevent="const items = [...$el.querySelectorAll('[role=menuitem]:not(:disabled)')]; items[(items.indexOf(document.activeElement) + items.length - 1) % items.length]?.focus()"
        x-on:click="if ($event.target.closest('[role=menuitem]')) $el.hidePopover()">
        {{ $slot }}
    </div>
</div>
