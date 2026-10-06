@props(['titulo'])
<div class="atlas-chart-canvas" x-show="modo === 'grafico'" x-cloak wire:ignore><canvas x-ref="lienzo" role="img" aria-label="{{ $titulo }}. Los mismos valores y filtros están disponibles en la lista del panel."></canvas></div>
<button type="button" class="atlas-chart-data-toggle" x-show="modo === 'grafico'" x-on:click="tablaAbierta = !tablaAbierta" :aria-expanded="tablaAbierta.toString()">Ver valores y filtrar</button>
