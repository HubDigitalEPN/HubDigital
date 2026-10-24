@props(['server' => false, 'client' => false, 'pageSize' => null, 'pageSizeAction' => null, 'allowDownload' => true])
<div class="hub-ig-actions" x-on:click.outside="closeMenu(false)">
    <button type="button" class="hub-ig-button" x-ref="actionsButton" aria-haspopup="menu" :aria-expanded="menuOpen.toString()"
        x-on:click="openMenu()" x-on:keydown.arrow-down.prevent="if (!menuOpen) openMenu()">Acciones <flux:icon name="chevron-down" /></button>
    <div class="hub-ig-menu" x-ref="actionMenu" x-show="menuOpen" x-cloak role="menu" aria-label="Acciones de la grilla" x-on:keydown="menuKey($event)">
        <div data-menu-view="main" x-show="menuView === 'main'">
            <button type="button" role="menuitem" tabindex="-1" x-on:click="openColumns()"><flux:icon name="table-cells" /><span>Columnas</span><span class="hub-ig-menu-trailing">…</span></button>
            @if($server || $client)
                <button type="button" role="menuitem" tabindex="-1" x-on:click="openFilters()"><flux:icon name="funnel" /><span>Filtrar</span><span class="hub-ig-menu-trailing">…</span></button>
                <button type="button" role="menuitem" tabindex="-1" x-on:click="openSort()"><flux:icon name="arrows-up-down" /><span>Ordenar</span><span class="hub-ig-menu-trailing">…</span></button>
            @endif
            @if($server || $client || $pageSizeAction)
                <div class="hub-ig-menu-separator" role="separator"></div>
                <button type="button" role="menuitem" tabindex="-1" aria-haspopup="menu" x-on:click="changeMenu('rows')"><flux:icon name="bars-3" /><span>Filas por página</span><span class="hub-ig-menu-trailing">@if($server){{ $this->gridPerPage }}@elseif($client)<span x-text="clientPerPage"></span>@else{{ $pageSize }}@endif <flux:icon name="chevron-right" /></span></button>
            @endif
            <button type="button" role="menuitem" tabindex="-1" aria-haspopup="menu" x-on:click="changeMenu('format')"><flux:icon name="adjustments-horizontal" /><span>Formato</span><flux:icon name="chevron-right" class="hub-ig-menu-trailing" /></button>
            {{ $slot }}
            <div class="hub-ig-menu-separator" role="separator"></div>
            @if($allowDownload)<button type="button" role="menuitem" tabindex="-1" x-on:click="downloadPage()"><flux:icon name="arrow-down-tray" /><span>Descargar página</span><span class="hub-ig-menu-hint">CSV</span></button>@endif
            @if($server)
                <button type="button" role="menuitem" tabindex="-1" wire:click="restablecerGrilla" x-on:click="reset()"><flux:icon name="arrow-uturn-left" /><span>Restablecer</span></button>
            @else
                <button type="button" role="menuitem" tabindex="-1" x-on:click="reset()"><flux:icon name="arrow-uturn-left" /><span>Restablecer vista</span></button>
            @endif
        </div>
        @if($server || $client || $pageSizeAction)
            <div data-menu-view="rows" x-show="menuView === 'rows'" x-cloak>
                <button type="button" role="menuitem" tabindex="-1" class="hub-ig-menu-back" x-on:click="changeMenu('main')"><flux:icon name="chevron-left" /> Filas por página</button>
                @foreach([15, 25, 50, 100] as $amount)
                    @if($client)
                        <button type="button" role="menuitemradio" tabindex="-1" :aria-checked="(clientPerPage === {{ $amount }}).toString()" x-on:click="clientPerPage = {{ $amount }}; closeMenu()"><span class="hub-ig-menu-check"><flux:icon name="check" x-show="clientPerPage === {{ $amount }}" /></span>{{ $amount }} filas</button>
                    @else
                        @php($currentSize = $server ? $this->gridPerPage : $pageSize)
                        <button type="button" role="menuitemradio" tabindex="-1" aria-checked="{{ $currentSize === $amount ? 'true' : 'false' }}" wire:click="{{ $server ? '$set(\'gridPerPage\', '.$amount.')' : $pageSizeAction.'('.$amount.')' }}" x-on:click="closeMenu()"><span class="hub-ig-menu-check">@if($currentSize === $amount)<flux:icon name="check" />@endif</span>{{ $amount }} filas</button>
                    @endif
                @endforeach
            </div>
        @endif
        <div data-menu-view="format" x-show="menuView === 'format'" x-cloak>
            <button type="button" role="menuitem" tabindex="-1" class="hub-ig-menu-back" x-on:click="changeMenu('main')"><flux:icon name="chevron-left" /> Densidad de las filas</button>
            <button type="button" role="menuitemradio" tabindex="-1" :aria-checked="(!compact).toString()" x-on:click="compact = false; closeMenu()"><span class="hub-ig-menu-check"><flux:icon name="check" x-show="!compact" /></span>Normal</button>
            <button type="button" role="menuitemradio" tabindex="-1" :aria-checked="compact.toString()" x-on:click="compact = true; closeMenu()"><span class="hub-ig-menu-check"><flux:icon name="check" x-show="compact" /></span>Compacta</button>
        </div>
    </div>
</div>

<dialog wire:ignore.self class="hub-ig-dialog" x-ref="columnsDialog" :aria-labelledby="$id('grid-columns-title')" x-on:close="$refs.actionsButton.focus()">
    <div class="hub-ig-dialog-heading"><h2 :id="$id('grid-columns-title')">Columnas</h2><button type="button" class="hub-ig-icon-button" x-on:click="$refs.columnsDialog.close()" aria-label="Cerrar columnas"><flux:icon name="x-mark" /></button></div>
    <div class="hub-ig-dialog-body">
        <label class="hub-ig-dialog-search">Buscar columna<input type="search" x-model="columnSearch" placeholder="Nombre de columna" autofocus></label>
        <div class="hub-ig-column-list">
            <template x-for="(column, index) in columns" :key="index"><label x-show="column.toLocaleLowerCase().includes(columnSearch.toLocaleLowerCase())">
                <input type="checkbox" :checked="!draftHidden.includes(index)" x-on:change="toggleDraft(index)"><span x-text="column"></span>
            </label></template>
        </div>
        <button type="button" class="hub-ig-text-button" x-on:click="draftHidden = []">Mostrar todas las columnas</button>
        <button type="button" class="hub-ig-text-button" x-on:click="draftHidden = columns.map((_, index) => index)">Ocultar todas las columnas</button>
    </div>
    <div class="hub-ig-dialog-footer"><button type="button" class="hub-ig-button" x-on:click="$refs.columnsDialog.close()">Cancelar</button><button type="button" class="hub-ig-button hub-ig-button-primary" x-on:click="applyColumnSelection()">Aplicar</button></div>
</dialog>
@if($server || $client)
    <dialog wire:ignore.self class="hub-ig-dialog" x-ref="filterDialog" :aria-labelledby="$id('grid-filter-title')" x-on:close="$refs.actionsButton.focus()">
        <form x-on:submit.prevent="await applyFilters()">
            <div class="hub-ig-dialog-heading"><h2 :id="$id('grid-filter-title')">Filtrar</h2><button type="button" class="hub-ig-icon-button" x-on:click="$refs.filterDialog.close()" aria-label="Cerrar filtros"><flux:icon name="x-mark" /></button></div>
            <div class="hub-ig-dialog-body hub-ig-filter-fields">
                <label class="hub-ig-dialog-search">Texto en todas las filas<input type="search" x-model="filterText" placeholder="Texto que contiene" autofocus></label>
                {{ $filters ?? '' }}
            </div>
            <div class="hub-ig-dialog-footer"><button type="button" class="hub-ig-button" x-on:click="$refs.filterDialog.close()">Cancelar</button><button type="submit" class="hub-ig-button hub-ig-button-primary">Aplicar</button></div>
        </form>
    </dialog>
    <dialog wire:ignore.self class="hub-ig-dialog" x-ref="sortDialog" :aria-labelledby="$id('grid-sort-title')" x-on:close="$refs.actionsButton.focus()">
        <form x-on:submit.prevent="await applySort()">
            <div class="hub-ig-dialog-heading"><h2 :id="$id('grid-sort-title')">Ordenar</h2><button type="button" class="hub-ig-icon-button" x-on:click="$refs.sortDialog.close()" aria-label="Cerrar ordenación"><flux:icon name="x-mark" /></button></div>
            <div class="hub-ig-dialog-body hub-ig-sort-fields">
                <label>Columna<select x-model="sortField"><template x-for="column in sortColumns" :key="column.field"><option :value="column.field" x-text="column.label"></option></template></select></label>
                <label>Dirección<select x-model="sortDirection"><option value="asc">Ascendente</option><option value="desc">Descendente</option></select></label>
            </div>
            <div class="hub-ig-dialog-footer"><button type="button" class="hub-ig-button" x-on:click="$refs.sortDialog.close()">Cancelar</button><button type="submit" class="hub-ig-button hub-ig-button-primary">Aplicar</button></div>
        </form>
    </dialog>
@endif
