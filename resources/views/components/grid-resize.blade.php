@props(['field', 'label'])
<span data-grid-resize role="separator" aria-orientation="vertical" tabindex="0"
    aria-label="Cambiar ancho de {{ $label }}" aria-valuemin="104" aria-valuemax="720"
    x-data="controlAnchoColumna()" x-bind:aria-valuenow="anchoVisible"
    x-on:pointerdown.prevent.stop="iniciarAncho($event, @js($field))"
    x-on:pointermove="moverAncho($event)" x-on:lostpointercapture="terminarAncho($el)"
    x-on:keydown.arrow-left.prevent.stop="ajustarAncho(@js($field), anchoColumna(@js($field), $el) - 16)"
    x-on:keydown.arrow-right.prevent.stop="ajustarAncho(@js($field), anchoColumna(@js($field), $el) + 16)"></span>
