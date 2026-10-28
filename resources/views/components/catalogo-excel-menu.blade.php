@props(['catalogoUrl' => null, 'iptUrl' => null, 'accion' => null, 'label' => 'Descargar Excel'])
@php
    $opcionesExcel = [];
    foreach (['catalogo' => ['catalogo.completo', 'Descargar todo el catálogo', $catalogoUrl], 'ipt' => ['catalogo.ipt', 'Descargar formato IPT (GBIF)', $iptUrl]] as $perfilExcel => [$permisoExcel, $textoExcel, $urlExcel]) {
        $visibleExcel = $accion ? \App\Support\BotonesSistema::visible('inventario.exportar') : \App\Support\DescargasCatalogo::visibles($permisoExcel);
        $habilitadoExcel = $accion ? \App\Support\BotonesSistema::permitido('inventario.exportar') : \App\Support\DescargasCatalogo::permitidas(boton: $permisoExcel);
        if ($visibleExcel) $opcionesExcel[] = ['perfil' => $perfilExcel, 'texto' => $textoExcel, 'url' => $urlExcel, 'habilitado' => $habilitadoExcel];
    }
@endphp
@if($opcionesExcel !== [])
<div x-data="{ abierto: false }" class="relative inline-block text-left" x-on:keydown.escape.stop="abierto = false; $refs.activador.focus()" x-on:click.outside="abierto = false">
    <button type="button" x-ref="activador" x-on:click="abierto = !abierto; if (abierto) $nextTick(() => $refs.menu.querySelector('[role=menuitem]')?.focus())" x-on:keydown.arrow-down.prevent="abierto = true; $nextTick(() => $refs.menu.querySelector('[role=menuitem]')?.focus())" x-bind:aria-expanded="abierto" aria-haspopup="menu" class="hub-ig-button min-h-11">
        {{ $label }} <span aria-hidden="true">▾</span>
    </button>
    <div x-ref="menu" x-cloak x-show="abierto" role="menu" aria-label="Formato de descarga Excel" class="absolute right-0 z-50 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded-lg border border-border bg-surface p-1 shadow-lg" x-on:keydown.arrow-down.prevent="$event.target.nextElementSibling?.focus()" x-on:keydown.arrow-up.prevent="$event.target.previousElementSibling?.focus()" x-on:keydown.home.prevent="$refs.menu.firstElementChild?.focus()" x-on:keydown.end.prevent="$refs.menu.lastElementChild?.focus()">
        @foreach($opcionesExcel as $opcionExcel)
            @if(!$opcionExcel['habilitado'])
                <button type="button" role="menuitem" aria-disabled="true" class="block min-h-11 w-full rounded px-3 py-2 text-left text-sm opacity-50">{{ $opcionExcel['texto'] }} · Sin permiso de uso</button>
            @elseif($accion)
                <button type="button" role="menuitem" wire:click="{{ $accion }}('{{ $opcionExcel['perfil'] }}')" wire:loading.attr="disabled" x-on:click="abierto = false" class="block min-h-11 w-full rounded px-3 py-2 text-left text-sm hover:bg-bg-main focus:bg-bg-main">{{ $opcionExcel['texto'] }}</button>
            @else
                <a role="menuitem" href="{{ $opcionExcel['url'] }}" data-catalogo-descarga x-on:click="abierto = false" class="block min-h-11 rounded px-3 py-2 text-sm hover:bg-bg-main focus:bg-bg-main">{{ $opcionExcel['texto'] }}</a>
            @endif
        @endforeach
    </div>
</div>
@endif
