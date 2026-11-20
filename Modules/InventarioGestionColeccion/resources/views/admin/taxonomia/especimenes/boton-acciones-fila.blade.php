<button type="button" class="hub-ig-button size-7 shrink-0 min-w-0 justify-center p-0!"
        data-especimen-menu-fila data-codigo="{{ $especimen['codigoCatalogo'] }}"
        aria-label="Acciones de {{ $especimen['codigoCatalogo'] }}" aria-haspopup="menu"
        x-bind:aria-expanded="(menuFila.invocador === $el).toString()"
        x-on:click="abrirMenuFila($el, @js($especimen['id']))"
        x-on:keydown.arrow-down.prevent="if (menuFila.invocador !== $el) abrirMenuFila($el, @js($especimen['id']))">
    <flux:icon name="bars-3" class="size-4" />
</button>
