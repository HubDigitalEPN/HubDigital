@props(['titulo'])
<div class="atlas-chart-canvas" x-show="modo === 'grafico'" x-cloak wire:ignore><canvas x-ref="lienzo" role="img" aria-label="{{ $titulo }}. {{ \App\Support\PortalCopy::text('catalogo.grafico_ayuda') }}"></canvas></div>
