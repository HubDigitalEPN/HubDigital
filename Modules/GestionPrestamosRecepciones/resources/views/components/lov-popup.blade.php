@props(['campo', 'label', 'opciones' => [], 'valor' => '', 'placeholder' => 'Selecciona una opción', 'required' => true, 'disabled' => false, 'accion' => null, 'busquedaRemota' => null, 'contexto' => '', 'inputId' => null])
@php
    $valores = [];
    foreach ($opciones as $claveOpcion => $etiqueta) {
        $valores[] = ['valor' => array_is_list($opciones) ? (string) $etiqueta : (string) $claveOpcion, 'etiqueta' => (string) $etiqueta];
    }
    $idLov = 'lov-'.$campo;
    $etiquetaActual = collect($valores)->firstWhere('valor', $valor)['etiqueta'] ?? $placeholder;
@endphp
<div data-flux-field class="hub-lov-field" wire:key="{{ $idLov }}-{{ substr(hash('sha256', json_encode([$valores, $contexto])), 0, 12) }}-{{ $disabled ? 'bloqueado' : 'editable' }}"
    x-data="{{ $accion || $busquedaRemota ? 'hubDigitalOriginLov' : 'hubDigitalLov' }}({campo: @js($campo), opciones: @js($valores), placeholder: @js($placeholder), disabled: @js($disabled), accion: @js($accion), busquedaRemota: @js($busquedaRemota)})"
    x-bind:class="{'hub-lov-field--empty': !valor}"
    x-on:keydown.escape.window="if (abierto) cerrar()">
    <label for="{{ $idLov }}-boton" id="{{ $idLov }}-label" class="hub-lov-label" title="{{ $label }}">{{ $label }}</label>
    <input @if($inputId) id="{{ $inputId }}" @endif type="hidden" value="{{ $valor }}" x-bind:value="valor" @if($required) required @endif />
    <button id="{{ $idLov }}-boton" type="button" class="hub-lov-trigger" role="combobox" aria-haspopup="dialog"
        aria-labelledby="{{ $idLov }}-label {{ $idLov }}-valor" aria-controls="{{ $idLov }}-dialogo"
        @if($required) aria-required="true" @endif x-bind:aria-expanded="abierto.toString()"
        x-ref="invocador" x-on:click="abrir()" x-on:keydown.arrow-down.prevent="abrir()" @disabled($disabled) @if(!$disabled) wire:loading.attr="disabled" @endif>
        <span id="{{ $idLov }}-valor" class="truncate" x-text="etiqueta" x-bind:class="{'hub-lov-value--empty': !valor}" x-bind:title="etiqueta">{{ $etiquetaActual }}</span>
        <flux:icon name="magnifying-glass" class="size-4 shrink-0 text-text-secondary" />
    </button>
    <flux:error :name="$campo" />
    <template x-if="abierto">
    <dialog id="{{ $idLov }}-dialogo" class="hub-lov-overlay" role="dialog" aria-modal="true" aria-labelledby="{{ $idLov }}-titulo"
        x-init="$nextTick(() => { if ($el.isConnected && !$el.open) { $el.showModal(); $el.querySelector('.hub-lov-search')?.focus({preventScroll: true}) } })"
        x-on:cancel.prevent.stop="cerrar()" x-on:keydown.escape.stop.prevent="cerrar()"
        x-on:click.self="cerrar()">
        <section class="hub-lov-dialog {{ str_starts_with($label, 'Localidad') ? 'hub-lov-dialog--localidad' : '' }}">
            <div class="hub-lov-heading"><h3 id="{{ $idLov }}-titulo">Buscar</h3>
                <button type="button" x-on:click="cerrar()" aria-label="Cerrar lista de {{ mb_strtolower($label) }}">×</button>
            </div>
            <div class="hub-lov-searchbar">
            <label class="sr-only" for="{{ $idLov }}-buscar">Buscar {{ mb_strtolower($label) }}</label>
            <input id="{{ $idLov }}-buscar" class="hub-lov-search" type="search" autocomplete="off" placeholder="Buscar…"
                x-ref="busqueda" x-model="busqueda" x-on:input="buscar()" @if($busquedaRemota) maxlength="120" @endif
                aria-controls="{{ $idLov }}-lista" x-bind:aria-activedescendant="visibles.length ? '{{ $idLov }}-opcion-' + activa : null"
                x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)"
                x-on:keydown.enter.prevent="escoger()" />
            <button type="button" class="hub-lov-search-button" x-on:click="buscar(); $refs.busqueda.focus()" aria-label="Buscar opciones"><flux:icon name="magnifying-glass" class="size-4" /></button>
            </div>
            <p class="sr-only" role="status" x-text="{{ $busquedaRemota ? "cargando ? 'Buscando localidades…' : " : '' }}filtradas.length + (filtradas.length === 1 ? ' opción' : ' opciones')"></p>
            <div class="hub-lov-results">
            @if($busquedaRemota)<p x-show="cargando" class="hub-lov-empty">Buscando localidades…</p>@endif
            <div id="{{ $idLov }}-lista" class="hub-lov-list" role="listbox" aria-label="{{ $label }}" x-ref="lista" @if($busquedaRemota) x-bind:aria-busy="cargando.toString()" @endif>
                <template x-for="(opcion, indice) in visibles" :key="opcion.valor">
                    <button type="button" role="option" tabindex="-1" x-bind:id="'{{ $idLov }}-opcion-' + indice"
                        x-bind:data-indice="indice" x-bind:aria-selected="(opcion.valor === valor).toString()"
                        x-bind:class="{'is-active': indice === activa}" x-on:mouseenter="activa = indice"
                        x-on:click="escoger(opcion.valor)" x-text="opcion.etiqueta" @if($busquedaRemota) x-bind:disabled="cargando" @endif></button>
                </template>
            </div>
            <p x-show="{{ $busquedaRemota ? '!cargando && !errorBusqueda && ' : '' }}!filtradas.length" class="hub-lov-empty">No hay opciones que coincidan con la búsqueda.</p>
            </div>
            <div class="hub-lov-footer"><button type="button" x-on:click="mostrarMas()" x-bind:disabled="{{ $busquedaRemota ? 'cargando || !hayMas' : 'visibles.length >= filtradas.length' }}">Mostrar más</button></div>
        </section>
    </dialog>
    </template>
</div>
