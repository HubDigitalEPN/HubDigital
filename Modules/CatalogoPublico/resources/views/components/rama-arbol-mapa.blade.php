@php
    $descendientes = $hijosArbol[(string) $nodoDendrograma['id']] ?? [];
    $troncoVertical = in_array($nodoDendrograma['etiqueta'], ['Reino', 'Subreino', 'Filo', 'Subfilo', 'Clase', 'Subclase', 'Superclase', 'Infraclase'], true);
    $plegable = count($descendientes) > 6;
    $soloRegistros = $descendientes !== [] && collect($descendientes)->every(fn ($hijo) => $hijo['rango'] === 'registro');
@endphp
<li class="atlas-tree-branch {{ $troncoVertical ? 'atlas-tree-branch--vertical' : 'atlas-tree-branch--horizontal' }}" data-padre-id="{{ $nodoDendrograma['padre_id'] }}" data-hijo-id="{{ $nodoDendrograma['id'] }}">
    @include('catalogopublico::components.nodo-arbol-mapa', ['nodoDendrograma' => $nodoDendrograma, 'disposicionFluida' => true])
    @if($descendientes !== [])
        @if($plegable)
            <details class="atlas-tree-fold" @if($nodoDendrograma['en_linaje']) open @endif>
                <summary>{{ count($descendientes) }} {{ $soloRegistros ? 'registros' : 'ramas' }} · mostrar</summary>
        @endif
        <ul class="atlas-tree-children {{ $troncoVertical ? 'atlas-tree-children--vertical' : 'atlas-tree-children--horizontal' }}">
            @foreach($descendientes as $hijoDendrograma)
                @include('catalogopublico::components.rama-arbol-mapa', ['nodoDendrograma' => $hijoDendrograma])
            @endforeach
        </ul>
        @if($plegable)</details>@endif
    @endif
</li>
