@props(['campo', 'etiqueta', 'placeholder', 'maxlength' => 120, 'activo' => false])
<div @class(['research-search', 'research-filter-active' => $activo]) wire:key="busqueda-{{ $campo }}" x-data="portalBusquedaTexto(@js($campo), @js(route('portal.sugerencias')))" x-id="['busqueda', 'sugerencias']" x-on:click.outside="cancelar(); cerrar()" x-on:catalogo-busqueda-restaurada.window="restaurar()">
    <label :for="$id('busqueda')">{{ $etiqueta }}</label>
    <input type="search" x-ref="entrada" :id="$id('busqueda')" x-model="texto" x-on:input="buscar()"
        x-on:keydown.enter.prevent="aplicar($event)" x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)" x-on:keydown.escape.stop.prevent="cancelar(); cerrar()"
        x-on:blur="cancelar(); cerrar()" role="combobox" aria-autocomplete="list" :aria-expanded="abierto.toString()" :aria-controls="$id('sugerencias')"
        :aria-activedescendant="abierto && activa >= 0 ? $id('sugerencias') + '-' + activa : null" @if($errors->has($campo)) aria-describedby="error-{{ $campo }}" @endif
        aria-invalid="{{ $errors->has($campo) ? 'true' : 'false' }}" autocomplete="off" placeholder="{{ $placeholder }}" maxlength="{{ $maxlength }}">
    <ul class="research-search-options" :id="$id('sugerencias')" role="listbox" aria-label="Sugerencias de {{ $etiqueta }}" x-cloak x-show="abierto">
        <template x-for="(sugerencia, indice) in sugerencias" :key="sugerencia">
            <li role="option" :id="$id('sugerencias') + '-' + indice" :aria-selected="(activa === indice).toString()" :class="{'is-active': activa === indice}"
                x-on:pointerdown.prevent="elegir(indice)" x-text="sugerencia"></li>
        </template>
    </ul>
    <span class="sr-only" role="status" x-text="cargando ? 'Buscando sugerencias…' : aviso"></span>
</div>
