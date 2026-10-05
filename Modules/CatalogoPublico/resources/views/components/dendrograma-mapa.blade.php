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
<div class="atlas-taxonomic-tree" style="--tree-levels:{{ max([1, ...array_map(static fn ($nodo) => $nodo['profundidad'] + 1, $dendrograma['nodos'])]) }}" role="group" aria-label="Jerarquía taxonómica pública y registros de la ubicación">
    <ul class="atlas-tree-roots">
        @foreach($raicesArbol as $nodoDendrograma)
            @include('catalogopublico::components.rama-arbol-mapa', ['nodoDendrograma' => $nodoDendrograma])
        @endforeach
    </ul>
</div>
