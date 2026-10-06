@props(['arbol', 'seleccionado' => null])
@php
    $dendrograma = \Modules\CatalogoPublico\Application\Services\DendrogramaTaxonomico::calcular($arbol, $seleccionado === null ? null : (string) $seleccionado);
    $hijosArbol = [];
    $raicesArbol = [];
    foreach ($dendrograma['nodos'] as $nodo) {
        if ($nodo['padre_id'] === null) {
            $raicesArbol[] = $nodo;
        } else {
            $hijosArbol[(string) $nodo['padre_id']][] = $nodo;
        }
    }
@endphp
<div class="atlas-taxonomic-tree atlas-tree-adaptive" wire:key="arbol-adaptable-{{ sha1(json_encode($arbol)) }}" x-data="portalArbolAdaptable(@js($dendrograma['nodos']), @js($seleccionado))" :style="{width: distribucion.ancho + 'px', height: distribucion.alto + 'px', '--tree-node-height': distribucion.altoNodo + 'px'}" :class="{'atlas-tree-compact': distribucion.compacto}" x-on:keydown="navegarTeclado($event)" role="group" aria-label="Jerarquía taxonómica pública y registros de la ubicación">
    <svg class="atlas-tree-connections" x-ref="enlaces" wire:ignore :width="distribucion.ancho" :height="distribucion.alto" aria-hidden="true"></svg>
    <ul class="atlas-tree-roots">
        @foreach($raicesArbol as $nodoDendrograma)
            @include('catalogopublico::components.rama-arbol-mapa', ['nodoDendrograma' => $nodoDendrograma])
        @endforeach
    </ul>
</div>
