@props(['arbol', 'seleccionado' => null])
@php
    $dendrograma = \Modules\CatalogoPublico\Application\Services\DendrogramaTaxonomico::calcular($arbol, $seleccionado === null ? null : (string) $seleccionado);
@endphp
<div class="atlas-dendrogram" role="group" aria-label="Dendrograma de la jerarquía taxonómica pública" style="height:{{ $dendrograma['alto'] }}px">
    <svg class="atlas-dendrogram-branches" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {{ $dendrograma['ancho'] }} {{ $dendrograma['alto'] }}" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        @foreach($dendrograma['ramas'] as $ramaDendrograma)
            <path d="{{ $ramaDendrograma['trazo'] }}" class="atlas-dendrogram-branch {{ $ramaDendrograma['activa'] ? 'is-active' : ($ramaDendrograma['hoja'] ? 'is-leaf' : '') }}" data-padre-id="{{ $ramaDendrograma['padre_id'] }}" data-hijo-id="{{ $ramaDendrograma['hijo_id'] }}" />
        @endforeach
    </svg>
    @foreach($dendrograma['nodos'] as $nodoDendrograma)
        @include('catalogopublico::components.nodo-arbol-mapa', ['nodoDendrograma' => $nodoDendrograma])
    @endforeach
</div>
