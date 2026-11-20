@props(['label' => 'Resultados', 'pageSizeLabel' => 'Filas por página', 'client' => false, 'pageSize' => null, 'pageSizeAction' => null, 'allowDownload' => true, 'pageSizes' => [15, 25, 50, 100], 'actionMenu' => true, 'columnAction' => null, 'continuousWindow' => null])
<section {{ $attributes->class(['hub-ig']) }} aria-label="{{ $label }}" data-hub-grid="specialized" @if($continuousWindow) data-grid-scroll-only @endif
    x-id="['grid-columns-title', 'grid-sort-title', 'grid-filter-title']" x-data="hubInteractiveGrid([], false, @js($label), @js($client))" :class="{'hub-ig-compact': compact}">
    <div class="hub-ig-toolbar">
        {{ $search ?? '' }}
        @if($client)
            <div class="hub-ig-search"><flux:icon name="magnifying-glass" /><input type="search" x-model="searchText" x-on:keydown.enter.prevent="searchPage()" aria-label="Buscar en {{ $label }}" placeholder="Buscar en la tabla"><button type="button" class="hub-ig-button" x-on:click="searchPage()">Ir</button></div>
        @endif
        @if($actionMenu)
            <x-grid-tools :page-size-label="$pageSizeLabel" :client="$client" :page-size="$pageSize" :page-size-action="$pageSizeAction" :allow-download="$allowDownload" :page-sizes="$pageSizes" :column-action="$columnAction">{{ $actions ?? '' }}</x-grid-tools>
        @endif
        {{ $reset ?? '' }}
        @unless(isset($reset))<button type="button" class="hub-ig-button hub-ig-reset" x-on:click="reset()"><flux:icon name="arrow-uturn-left" /> Restablecer vista</button>@endunless
    </div>
    @if($continuousWindow)
        <span hidden data-grid-window='@json($continuousWindow)'></span>
    @endif
    <div class="hub-ig-scroll" tabindex="0" role="region" aria-label="Filas de {{ $label }}">{{ $slot }}</div>
    <p x-show="cargandoContinuidad" x-cloak role="status" class="p-3 text-sm">Cargando más filas…</p><div x-show="errorContinuidad" x-cloak class="p-3 text-sm" role="status"><span x-text="errorContinuidad"></span><button type="button" class="hub-ig-button" x-on:click="cargarContinuidad(ultimaDireccion,true)">Reintentar carga</button></div>
    @if($client)
        <p x-show="clientTotal === 0" class="p-4 text-sm" role="status">No hay filas que coincidan.</p>
        <nav class="hub-ig-footer" aria-label="Paginación de {{ $label }}">
            <div class="hub-ig-page-controls">
                <button type="button" class="hub-ig-page-button" x-on:click="clientGo(1)" :disabled="clientPage <= 1" aria-label="Primera página">«</button>
                <button type="button" class="hub-ig-page-button" x-on:click="clientGo(clientPage-1)" :disabled="clientPage <= 1" aria-label="Página anterior">‹</button>
                <template x-for="page in clientPageNumbers" :key="page"><button type="button" class="hub-ig-page-button hub-ig-page-number" :class="{'hub-ig-page-distant': Math.abs(page-clientPage)>1}" x-on:click="clientGo(page)" :aria-current="page === clientPage ? 'page' : null" :aria-label="'Página '+page+' de '+clientLastPage" x-text="page"></button></template>
                <button type="button" class="hub-ig-page-button" x-on:click="clientGo(clientPage+1)" :disabled="clientPage >= clientLastPage" aria-label="Página siguiente">›</button>
                <button type="button" class="hub-ig-page-button" x-on:click="clientGo(clientLastPage)" :disabled="clientPage >= clientLastPage" aria-label="Última página">»</button>
            </div>
            <span class="hub-ig-range" role="status" x-text="(clientTotal ? (clientFirstPage-1)*clientPerPage+1 : 0)+'–'+Math.min(clientLastLoaded*clientPerPage,clientTotal)+' de '+clientTotal"></span>
        </nav>
    @endif
    {{ $pagination ?? '' }}
</section>
