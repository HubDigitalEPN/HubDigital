@props(['tipo', 'contexto' => 'exportacion'])
@php($figura = config('figuras_portal.'.$tipo))
@if($contexto === 'indicador')
    <p class="atlas-indicator-explanation">{{ $figura['texto'] }}</p>
@else
    <template class="atlas-export-caption"><p class="atlas-figure-caption"><strong>Figura {{ $figura['numero'] }}.</strong> {{ $figura['texto'] }}</p></template>
@endif
