@props(['catalogoUrl' => null, 'iptUrl' => null, 'accion' => null, 'label' => 'Descargar Excel'])
<div x-data="{ abierto: false }" class="relative inline-block text-left" x-on:keydown.escape.stop="abierto = false; $refs.activador.focus()" x-on:click.outside="abierto = false">
    <button type="button" x-ref="activador" x-on:click="abierto = !abierto; if (abierto) $nextTick(() => $refs.primera.focus())" x-on:keydown.arrow-down.prevent="abierto = true; $nextTick(() => $refs.primera.focus())" x-bind:aria-expanded="abierto" aria-haspopup="menu" class="hub-ig-button min-h-11">
        {{ $label }} <span aria-hidden="true">▾</span>
    </button>
    <div x-cloak x-show="abierto" role="menu" aria-label="Formato de descarga Excel" class="absolute right-0 z-50 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded-lg border border-border bg-surface p-1 shadow-lg" x-on:keydown.arrow-down.prevent="$event.target.nextElementSibling?.focus()" x-on:keydown.arrow-up.prevent="$event.target.previousElementSibling?.focus()" x-on:keydown.home.prevent="$refs.primera.focus()" x-on:keydown.end.prevent="$refs.ultima.focus()">
        <p class="px-3 py-2 text-xs text-text-secondary">IPT: los obligatorios sin dato quedan vacíos y se avisan en la hoja Revisión.</p>
        @if($accion)
            <button x-ref="primera" type="button" role="menuitem" wire:click="{{ $accion }}('catalogo')" wire:loading.attr="disabled" x-on:click="abierto = false" class="block min-h-11 w-full rounded px-3 py-2 text-left text-sm hover:bg-bg-main focus:bg-bg-main">Descargar todo el catálogo</button>
            <button x-ref="ultima" type="button" role="menuitem" wire:click="{{ $accion }}('ipt')" wire:loading.attr="disabled" x-on:click="abierto = false" class="block min-h-11 w-full rounded px-3 py-2 text-left text-sm hover:bg-bg-main focus:bg-bg-main">Descargar formato IPT (GBIF)</button>
        @else
            <a x-ref="primera" role="menuitem" href="{{ $catalogoUrl }}" data-catalogo-descarga x-on:click="abierto = false" class="block min-h-11 rounded px-3 py-2 text-sm hover:bg-bg-main focus:bg-bg-main">Descargar todo el catálogo</a>
            <a x-ref="ultima" role="menuitem" href="{{ $iptUrl }}" data-catalogo-descarga x-on:click="abierto = false" class="block min-h-11 rounded px-3 py-2 text-sm hover:bg-bg-main focus:bg-bg-main">Descargar formato IPT (GBIF)</a>
        @endif
    </div>
    <span class="sr-only">IPT conserva los registros incompletos: los campos obligatorios vacíos requieren revisión antes de publicar.</span>
</div>
