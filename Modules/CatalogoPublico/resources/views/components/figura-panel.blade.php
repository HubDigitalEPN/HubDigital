@props(['tipo'])
@php($figura = config('figuras_portal.'.$tipo))
<p class="atlas-figure-caption"><strong>Figura {{ $figura['numero'] }}.</strong> {{ $figura['texto'] }}</p>
